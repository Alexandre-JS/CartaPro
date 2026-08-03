<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unlock;
use App\Models\UnlockChallenge;
use App\Services\EntitlementService;
use App\Services\SmsSender;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Desbloqueio ligado à conta autenticada.
 *
 * O que mudou face à versão anterior:
 *  - já não existe rota pública `/desbloqueios/{telefone}` (permitia enumerar
 *    quem pagou e ativar o plano com o número de outra pessoa);
 *  - o número usado é sempre o da conta, nunca um valor digitado;
 *  - é exigido OTP para provar a posse do número;
 *  - o desbloqueio fica preso (`mobile_user_id`) à primeira conta que o
 *    reclama, pelo que partilhar o número já não multiplica acessos.
 */
class UnlockController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly SmsSender $sms,
    ) {}

    /** Estado atual do plano — o app revalida por aqui, não por cache local. */
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->entitlements->describe($request->user()));
    }

    public function requestCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $normalized = Phone::normalize($user->phone);

        abort_if($normalized === '', 422, 'A conta não tem um número de telefone válido.');

        if ($this->entitlements->isPaid($user)) {
            return response()->json(['estado' => 'ja_ativo'] + $this->entitlements->describe($user));
        }

        $unlock = $this->claimableUnlock($normalized);

        if (! $unlock) {
            // Distingue "não pagou" de "já foi usado noutra conta", para o
            // apoio ao cliente não andar a adivinhar.
            $claimedElsewhere = Unlock::where('phone_normalized', $normalized)
                ->where('is_active', true)->whereNotNull('mobile_user_id')->exists();

            abort(
                $claimedElsewhere ? 409 : 404,
                $claimedElsewhere
                    ? 'Este número já desbloqueou outra conta CartaPro. Usa essa conta ou fala connosco.'
                    : 'Ainda não encontrámos uma activação para este número. Podes desbloquear já no app, em segundos.',
            );
        }

        $code = (string) random_int(100000, 999999);
        UnlockChallenge::where('mobile_user_id', $user->id)->whereNull('consumed_at')->delete();
        UnlockChallenge::create([
            'mobile_user_id' => $user->id,
            'phone_normalized' => $normalized,
            'code_hash' => UnlockChallenge::hash($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sms->send(Phone::format($normalized), "CartaPro: o seu codigo de activacao e {$code}. Valido por 10 minutos.");

        return response()->json([
            'estado' => 'codigo_enviado',
            'telefone' => Phone::format($normalized),
            'expiraEmMinutos' => 10,
        ]);
    }

    public function confirmCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        $challenge = UnlockChallenge::where('mobile_user_id', $user->id)
            ->whereNull('consumed_at')->latest('id')->first();

        abort_unless($challenge && $challenge->isUsable(), 422, 'Código expirado. Peça um novo código.');

        if (! hash_equals($challenge->code_hash, UnlockChallenge::hash($data['code']))) {
            $challenge->increment('attempts');
            abort(422, 'Código incorreto.');
        }

        $result = DB::transaction(function () use ($challenge, $user) {
            $unlock = $this->claimableUnlock($challenge->phone_normalized, lock: true);

            if (! $unlock) {
                return null;
            }

            $unlock->update([
                'mobile_user_id' => $user->id,
                'last_verified_at' => now(),
            ]);
            $challenge->update(['consumed_at' => now()]);
            $user->forceFill(['phone_verified_at' => now()])->save();

            return $unlock;
        });

        abort_unless($result, 409, 'Este desbloqueio já foi reclamado por outra conta.');

        return response()->json(['estado' => 'ativado'] + $this->entitlements->describe($user->fresh()));
    }

    /**
     * Desbloqueio pago para este número que ainda não foi reclamado
     * (ou que já pertence a esta conta).
     */
    private function claimableUnlock(string $normalized, bool $lock = false): ?Unlock
    {
        $query = Unlock::where('phone_normalized', $normalized)
            ->where('is_active', true)
            ->where(fn ($nested) => $nested->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereNull('mobile_user_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
