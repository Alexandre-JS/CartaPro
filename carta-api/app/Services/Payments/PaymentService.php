<?php

namespace App\Services\Payments;

use App\Models\MobileUser;
use App\Models\Payment;
use App\Models\Unlock;
use App\Support\Carteira;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquestra o pagamento e a concessão do acesso.
 *
 * Duas decisões de desenho que importam:
 *
 * 1. Um pagamento C2B **dispensa o OTP**. O código por SMS existia para provar
 *    que o aluno possui o número associado ao pagamento; numa transação C2B ele
 *    confirmou-a com o PIN nesse número, o que é prova mais forte. O OTP fica
 *    para os desbloqueios que o apoio regista à mão.
 *
 * 2. A carteira pode não ser o número da conta. Muita gente tem a conta num
 *    número e o dinheiro noutro — e a versão anterior, que cobrava sempre o
 *    número da conta, deixava essas pessoas sem forma de pagar. O acesso
 *    continua a ser concedido à **conta autenticada**, nunca ao número: quem
 *    paga com a carteira de outra pessoa está a oferecer-lhe o plano, não a
 *    roubá-lo.
 */
class PaymentService
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function plano(string $chave): array
    {
        $plano = config("payments.plans.{$chave}");

        if (! $plano) {
            throw ValidationException::withMessages(['plan' => 'Plano desconhecido.']);
        }

        return $plano + ['chave' => $chave];
    }

    /**
     * Inicia a cobrança.
     *
     * Um pendente do mesmo aluno, método e carteira é reaproveitado: tocar duas
     * vezes no botão não pode gerar duas cobranças. Mudar de método ou de
     * número abre um pagamento novo — é outra intenção.
     */
    public function iniciar(MobileUser $user, string $chavePlano, string $metodo, ?string $carteira = null): Payment
    {
        $plano = $this->plano($chavePlano);

        if (! in_array($metodo, $this->gateways->disponiveis(), true)) {
            throw ValidationException::withMessages([
                'method' => 'Esta carteira não está disponível de momento. Experimenta a outra.',
            ]);
        }

        $minimo = (float) config("payments.methods.{$metodo}.minimo", 0);

        if ($minimo > 0 && (float) $plano['preco'] < $minimo) {
            throw ValidationException::withMessages([
                'method' => "{$this->nomeMetodo($metodo)} só aceita pagamentos a partir de {$minimo} ".config('payments.currency').'.',
            ]);
        }

        $telefone = Phone::normalize($carteira ?: ($user->phone_normalized ?: $user->phone));

        /*
         * O cartão não se cobra a um número: os dados vão para o Hosted
         * Checkout da DebitoPay. Guardamos na mesma o número da conta — a
         * reconciliação e o apoio precisam de saber a quem pertence o
         * pagamento — mas não o validamos contra operadora nenhuma, porque
         * aqui ele não é a carteira, é só a identificação do aluno.
         */
        if (config("payments.methods.{$metodo}.movel", true)) {
            if (strlen($telefone) !== 12) {
                throw ValidationException::withMessages([
                    'wallet_phone' => 'Escreve os 9 dígitos da tua carteira móvel.',
                ]);
            }

            if (! Carteira::serve($telefone, $metodo)) {
                $nome = config("payments.methods.{$metodo}.nome");
                $sugerido = Carteira::paraNumero($telefone);

                throw ValidationException::withMessages([
                    'wallet_phone' => $sugerido
                        ? "Este número é {$this->nomeOperadora($sugerido)}. Escolhe {$this->nomeMetodo($sugerido)} ou usa um número {$this->nomeOperadora($metodo)}."
                        : "Este número não parece servir {$nome}. Confirma antes de continuar.",
                ]);
            }
        }

        $gateway = $this->gateways->para($metodo);

        $pendente = Payment::where('mobile_user_id', $user->id)
            ->where('status', Payment::PENDENTE)
            ->where('plan', $chavePlano)
            ->where('method', $metodo)
            ->where('phone_normalized', $telefone)
            ->latest('id')
            ->first();

        $payment = $pendente ?: Payment::create([
            'mobile_user_id' => $user->id,
            'plan' => $chavePlano,
            'amount' => $plano['preco'],
            'currency' => config('payments.currency'),
            'provider' => $gateway->name(),
            'method' => $metodo,
            'phone_normalized' => $telefone,
            'status' => Payment::PENDENTE,
            'reference' => Payment::novaReferencia(),
            'conversation_id' => Payment::novaConversa(),
        ]);

        return $this->aplicar($payment, $gateway->charge($payment));
    }

    /** Reconsulta o fornecedor enquanto o app faz polling. */
    public function atualizar(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        return $this->aplicar($payment, $this->gateways->para($payment->method)->query($payment));
    }

    /** Confirmação empurrada por webhook (PaySuite). */
    public function confirmarPorWebhook(Payment $payment, PaymentResult $resultado): Payment
    {
        return $this->aplicar($payment, $resultado);
    }

    /**
     * Devolve o pagamento e retira o acesso que ele concedeu.
     *
     * As duas metades são inseparáveis. Prometemos devolução em 7 dias, e sem
     * a segunda metade reembolsar significaria mover o dinheiro de volta e o
     * aluno ficar com o plano completo à mesma — a garantia pagava-se a si
     * própria em fraude.
     *
     * Descontam-se os dias que este pagamento acrescentou, não se apaga o
     * desbloqueio: quem já tinha tempo comprado antes não o pode perder por
     * causa de uma renovação devolvida.
     */
    public function reembolsar(Payment $payment, ?string $motivo = null, ?int $operador = null): Payment
    {
        return DB::transaction(function () use ($payment, $motivo, $operador) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            if (! $payment->isPaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'Só pagamentos concluídos podem ser devolvidos.',
                ]);
            }

            $dias = (int) $this->plano($payment->plan)['dias'];
            $unlock = $payment->unlock_id ? Unlock::lockForUpdate()->find($payment->unlock_id) : null;

            if ($unlock) {
                $restante = $unlock->expires_at?->copy()->subDays($dias);

                $unlock->update([
                    'expires_at' => $restante,
                    // Sem tempo comprado antes, o acesso fecha-se já.
                    'is_active' => $restante ? $restante->isFuture() : false,
                    'notes' => trim(($unlock->notes ?? '').' Devolvido em '.now()->toDateString().'.'),
                ]);
            }

            $payment->update([
                'status' => Payment::REEMBOLSADO,
                'refunded_at' => now(),
                'refund_reason' => $motivo,
                'refunded_by' => $operador,
            ]);

            return $payment;
        });
    }

    /**
     * Grava o resultado e, se pago, concede o acesso.
     *
     * Tudo numa transação com bloqueio de linha: o app faz polling e o webhook
     * chega quando quer, e as duas coisas juntas não podem criar dois
     * desbloqueios nem cobrar duas vezes.
     */
    private function aplicar(Payment $payment, PaymentResult $resultado): Payment
    {
        return DB::transaction(function () use ($payment, $resultado) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            if (! $payment->isPending()) {
                return $payment;
            }

            $payment->fill([
                'provider_code' => $resultado->code,
                'provider_message' => $resultado->message,
                'provider_payload' => $resultado->payload ?: null,
            ]);

            if ($resultado->checkoutUrl) {
                $payment->checkout_url = $resultado->checkoutUrl;
            }

            if ($resultado->transactionId) {
                $payment->provider_transaction_id = $resultado->transactionId;
            }

            if ($resultado->isPending()) {
                $payment->save();

                return $payment;
            }

            if (! $resultado->isPaid()) {
                $payment->status = Payment::FALHADO;
                $payment->save();

                return $payment;
            }

            $payment->status = Payment::PAGO;
            $payment->paid_at = now();
            $payment->unlock_id = $this->conceder($payment)->id;
            $payment->save();

            return $payment;
        });
    }

    /**
     * Cria — ou estende — o desbloqueio desta conta.
     *
     * Renovar antes de expirar soma ao que resta em vez de encurtar: quem paga
     * cedo não pode sair prejudicado.
     *
     * O desbloqueio é gravado com o número **da conta**, não o da carteira: é a
     * conta que fica com o acesso. E procura-se também por `phone_normalized`
     * porque `unlocks.phone` é único — sem isso, pagar depois de o apoio já ter
     * registado o mesmo número rebentava com violação de unicidade.
     */
    private function conceder(Payment $payment): Unlock
    {
        $plano = $this->plano($payment->plan);
        $user = $payment->mobileUser;
        $numeroDaConta = Phone::normalize($user->phone);

        $existente = Unlock::query()
            ->where(fn ($query) => $query
                ->where('mobile_user_id', $user->id)
                ->orWhere('phone_normalized', $numeroDaConta))
            ->orderByDesc('expires_at')
            ->lockForUpdate()
            ->first();

        $base = $existente?->expires_at?->isFuture() ? $existente->expires_at : now();
        $expira = $plano['dias'] > 0 ? $base->copy()->addDays($plano['dias']) : null;

        if ($existente) {
            $existente->update([
                'expires_at' => $expira,
                'is_active' => true,
                'mobile_user_id' => $user->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->method,
                'last_verified_at' => now(),
            ]);

            return $existente;
        }

        return Unlock::create([
            'phone' => $user->phone,
            'plan' => $payment->plan,
            'payment_method' => $payment->method,
            'payment_reference' => $payment->reference,
            'amount' => $payment->amount,
            'unlocked_at' => now(),
            'expires_at' => $expira,
            'is_active' => true,
            'mobile_user_id' => $user->id,
            'last_verified_at' => now(),
            'notes' => 'Pagamento automático via '.config("payments.methods.{$payment->method}.nome", $payment->method).'.',
        ]);
    }

    private function nomeMetodo(string $metodo): string
    {
        return config("payments.methods.{$metodo}.nome", $metodo);
    }

    private function nomeOperadora(string $metodo): string
    {
        return config("payments.methods.{$metodo}.operadora", $metodo);
    }
}
