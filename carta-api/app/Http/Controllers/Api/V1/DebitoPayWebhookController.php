<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Recebe eventos DebitoPay e confirma o acesso sem confiar no aplicativo. */
class DebitoPayWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('payments.debitopay.webhook_secret');

        if ($secret === '') {
            Log::error('DebitoPay: webhook recebido sem segredo configurado.');

            return response()->json(['erro' => 'Webhook não configurado.'], 503);
        }

        $signature = (string) ($request->header('X-Webhook-Signature')
            ?: $request->header('X-DebitoPay-Signature')
            ?: $request->header('X-Signature'));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, strtolower(trim($signature)))) {
            Log::warning('DebitoPay: assinatura de webhook inválida.', ['ip' => $request->ip()]);

            return response()->json(['erro' => 'Assinatura inválida.'], 401);
        }

        $event = strtolower((string) $request->input('event'));
        $data = $request->input('data', []);
        $data = is_array($data) ? $data : [];
        $reference = $data['reference'] ?? $request->input('reference');
        $providerId = $data['payment_id'] ?? $data['transaction_id'] ?? $data['id'] ?? $request->input('payment_id');

        if (! $reference && ! $providerId) {
            Log::warning('DebitoPay: webhook sem referência nem payment_id.');

            return response()->json(['estado' => 'ignorado']);
        }

        $payment = Payment::query()
            ->where('provider', 'debitopay')
            ->where(fn ($query) => $query
                ->when($reference, fn ($q) => $q->orWhere('reference', $reference))
                ->when($providerId, fn ($q) => $q->orWhere('provider_transaction_id', $providerId)))
            ->first();

        if (! $payment) {
            Log::warning('DebitoPay: webhook sem pagamento correspondente.', compact('reference', 'providerId'));

            return response()->json(['estado' => 'ignorado']);
        }

        if (isset($data['amount']) && round((float) $data['amount'], 2) !== round((float) $payment->amount, 2)) {
            Log::error('DebitoPay: valor do webhook divergente.', ['reference' => $reference]);

            return response()->json(['estado' => 'ignorado']);
        }

        if (isset($data['currency']) && strtoupper((string) $data['currency']) !== strtoupper($payment->currency)) {
            Log::error('DebitoPay: moeda do webhook divergente.', ['reference' => $reference]);

            return response()->json(['estado' => 'ignorado']);
        }

        match ($event) {
            'payment.completed' => $this->payments->confirmarPorWebhook(
                $payment,
                PaymentResult::pago($providerId, 'completed', $request->all()),
            ),
            'payment.failed' => $this->payments->confirmarPorWebhook(
                $payment,
                PaymentResult::falhado('failed', $data['message'] ?? 'Pagamento recusado.', $request->all()),
            ),
            /*
             * O dinheiro voltou para trás. Sem isto o acesso ficava concedido
             * depois de estornado — que é exactamente o que um chargeback
             * fraudulento procura: fica-se com o plano e recupera-se o valor.
             * Reembolsar desconta os dias que este pagamento acrescentou.
             */
            'payment.refunded', 'payment.chargeback' => $this->reverter($payment, $event),
            default => Log::info('DebitoPay: evento ignorado.', ['event' => $event]),
        };

        return response()->json(['estado' => 'recebido']);
    }

    /**
     * Só pagamentos concluídos se devolvem. Uma repetição do evento — que a
     * DebitoPay garante ser possível — encontra o pagamento já reembolsado e
     * não desconta os dias uma segunda vez.
     */
    private function reverter(Payment $payment, string $event): void
    {
        if (! $payment->isPaid()) {
            return;
        }

        $this->payments->reembolsar($payment, "Devolvido pela DebitoPay ({$event}).");

        Log::warning('DebitoPay: pagamento revertido e acesso retirado.', [
            'reference' => $payment->reference,
            'event' => $event,
        ]);
    }
}
