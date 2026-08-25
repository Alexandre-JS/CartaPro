<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Models\Unlock;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnlockController extends Controller
{
    public function index(Request $request): View
    {
        $termo = $request->string('q')->value();

        return view('admin.unlocks.index', [
            'unlocks' => Unlock::with(['creator', 'mobileUser'])
                ->when($request->filled('q'), fn ($query) => $query->where(function ($nested) use ($termo) {
                    $nested->where('phone', 'like', '%'.$termo.'%')
                        ->orWhere('payment_reference', 'like', '%'.$termo.'%')
                        // Pesquisa também pelo número normalizado, para o apoio
                        // ao cliente encontrar o registo escreva-o como escrever.
                        ->orWhere('phone_normalized', 'like', '%'.Phone::normalize($termo).'%');
                }))
                ->latest('unlocked_at')->paginate(10)->withQueryString(),
            'semConta' => Unlock::whereNull('mobile_user_id')->where('is_active', true)->count(),
            'total' => Unlock::count(),
            'ativos' => Unlock::where('is_active', true)->count(),
            'associados' => Unlock::whereNotNull('mobile_user_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $unlock = Unlock::create($this->validated($request) + ['created_by' => $request->user()->id]);

        // Se já existir conta com este número, associa de imediato — evita
        // pedir OTP a quem o apoio ao cliente acabou de registar.
        $associado = $this->associarConta($unlock);

        return back()->with('status', $associado
            ? 'Número desbloqueado e associado à conta '.$unlock->fresh()->mobileUser->email.'.'
            : 'Número desbloqueado. Será ativado quando o aluno confirmar o código no app.');
    }

    public function update(Request $request, Unlock $unlock): RedirectResponse
    {
        $unlock->update($this->validated($request, $unlock));

        return back()->with('status', 'Desbloqueio atualizado.');
    }

    /**
     * Liga manualmente o desbloqueio à conta com o mesmo número.
     *
     * O acesso passou a exigir vínculo explícito à conta (é o que impede
     * partilhar um número pago por várias contas). Esta ação existe para o
     * apoio ao cliente resolver os pagamentos registados antes da mudança.
     */
    public function bind(Unlock $unlock): RedirectResponse
    {
        if ($unlock->mobile_user_id) {
            return back()->with('status', 'Este desbloqueio já está associado a uma conta.');
        }

        return back()->with('status', $this->associarConta($unlock)
            ? 'Desbloqueio associado à conta '.$unlock->fresh()->mobileUser->email.'.'
            : 'Não existe nenhuma conta ProntoVia com este número. O aluno tem de se registar primeiro.');
    }

    public function destroy(Unlock $unlock): RedirectResponse
    {
        $unlock->delete();

        return back()->with('status', 'Desbloqueio removido.');
    }

    private function associarConta(Unlock $unlock): bool
    {
        $conta = MobileUser::where('phone_normalized', Phone::normalize($unlock->phone))->first();

        if (! $conta) {
            return false;
        }

        $unlock->update(['mobile_user_id' => $conta->id, 'last_verified_at' => now()]);

        return true;
    }

    private function validated(Request $request, ?Unlock $unlock = null): array
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', Rule::unique('unlocks')->ignore($unlock)],
            'plan' => ['required', 'string', 'max:50'],
            'payment_method' => ['nullable', Rule::in(['mpesa', 'emola', 'manual', 'outro'])],
            'payment_reference' => ['nullable', 'string', 'max:100', Rule::unique('unlocks')->ignore($unlock)],
            'unlocked_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:unlocked_at'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
