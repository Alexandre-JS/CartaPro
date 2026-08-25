<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\EntitlementService;
use App\Services\PlanCatalog;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentService;
use App\Support\Carteira;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagamento dentro do app.
 *
 * Antes disto, o ecrã de desbloqueio pedia ao aluno que pagasse "para o número
 * indicado pelo ProntoVia" — sem mostrar número nem preço, e sem forma de
 * comunicar que tinha pago. Todo o pagamento vivia fora do software.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly GatewayManager $gateways,
        private readonly EntitlementService $entitlements,
        private readonly PlanCatalog $plans,
    ) {}

    /**
     * Catálogo: o que se vende, por quanto, por que métodos e com que número.
     *
     * Os prefixos vão para o app de propósito — é o que lhe permite escolher o
     * método certo assim que o aluno escreve o número, em vez de o deixar
     * tentar pagar M-Pesa com um número Movitel.
     */
    public function plans(Request $request): JsonResponse
    {
        $user = $request->user();
        $numeroDaConta = $user->phone_normalized ?: Phone::normalize($user->phone);
        $disponiveis = $this->gateways->disponiveis();

        return response()->json([
            'moeda' => config('payments.currency'),
            'telefone' => Phone::format($numeroDaConta),
            'telefoneSugerido' => $numeroDaConta,
            'metodoSugerido' => Carteira::paraNumero($numeroDaConta),
            'metodos' => collect(config('payments.methods'))
                ->filter(fn ($_, string $chave) => in_array($chave, $disponiveis, true))
                ->map(fn (array $metodo, string $chave) => [
                    'chave' => $chave,
                    'nome' => $metodo['nome'],
                    'operadora' => $metodo['operadora'],
                    'prefixos' => array_values($metodo['prefixos']),
                ])
                ->values(),
            'promessa' => config('payments.promessa'),
            // Vazia enquanto o negócio não decidir que compromisso assume.
            'garantia' => config('payments.garantia') ?: null,
            'planos' => $this->plans->all()
                ->map(fn ($plano) => [
                    'chave' => $plano->code,
                    'nome' => $plano->name,
                    'descricao' => $plano->description,
                    'preco' => (float) $plano->price,
                    'dias' => $plano->duration_days,
                    'periodo' => $plano->duration_days ? $plano->duration_days.' dias' : null,
                    'recursos' => $plano->features,
                    'compravel' => $plano->is_purchasable,
                ])
                ->values(),
            'acesso' => $this->entitlements->describe($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'plan' => ['required', 'string', 'max:50'],
            'method' => ['required', 'string', 'max:30'],
            // Opcional: quem tem a carteira noutro número escreve-o aqui.
            'wallet_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $payment = $this->payments->iniciar(
            $request->user(),
            $dados['plan'],
            $dados['method'],
            $dados['wallet_phone'] ?? null,
        );

        return response()->json($this->apresentar($payment), $payment->isPaid() ? 201 : 202);
    }

    /**
     * Estado da transação.
     *
     * Limitado às transações da conta: sem o filtro, um id sequencial deixaria
     * qualquer aluno ler os pagamentos dos outros.
     */
    public function show(Request $request, int $payment): JsonResponse
    {
        $registo = Payment::where('mobile_user_id', $request->user()->id)->findOrFail($payment);

        return response()->json($this->apresentar($this->payments->atualizar($registo)));
    }

    private function apresentar(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'estado' => $payment->status,
            'metodo' => $payment->method,
            'referencia' => $payment->reference,
            'telefone' => Phone::format($payment->phone_normalized),
            'valor' => (float) $payment->amount,
            'produto' => \App\Models\Plan::canonical($payment->plan),
            'moeda' => $payment->currency,
            'mensagem' => $payment->provider_message,
            'transacao' => $payment->provider_transaction_id,
            // Preenchido quando o método cobra numa página em vez de no
            // telemóvel — cartões, e a e-Mola via agregador. O app abre-a.
            'checkoutUrl' => $payment->checkout_url,
            // O app precisa do acesso actualizado na mesma resposta: sem isto
            // teria de fazer outra chamada só para saber se os cadeados abrem.
            'acesso' => $this->entitlements->describe($payment->mobileUser->fresh()),
        ];
    }
}
