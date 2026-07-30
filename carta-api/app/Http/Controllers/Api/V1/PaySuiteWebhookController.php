<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Confirmação de pagamento vinda da PaySuite.
 *
 * Ao contrário do C2B do M-Pesa, aqui o resultado não vem na resposta ao pedido
 * — o aluno conclui numa página de checkout e o fornecedor avisa-nos depois.
 * Esta rota é pública por necessidade, o que faz da assinatura a única coisa
 * entre um webhook legítimo e alguém a oferecer-se o plano completo com um
 * `curl`.
 */
class PaySuiteWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        $segredo = (string) config('payments.paysuite.webhook_secret');

        // Sem segredo configurado não se aceita nada: um webhook por verificar
        // é pior do que webhook nenhum.
        if ($segredo === '') {
            Log::error('PaySuite: webhook recebido sem PAYSUITE_WEBHOOK_SECRET definido.');

            return response()->json(['erro' => 'Webhook não configurado.'], 503);
        }

        $corpo = $request->getContent();
        $assinatura = (string) $request->header('X-Webhook-Signature');
        $esperada = hash_hmac('sha256', $corpo, $segredo);

        if (! hash_equals($esperada, $assinatura)) {
            Log::warning('PaySuite: assinatura de webhook inválida.', ['ip' => $request->ip()]);

            return response()->json(['erro' => 'Assinatura inválida.'], 401);
        }

        $evento = $request->input('event');
        $dados = $request->input('data', []);
        $referencia = $dados['reference'] ?? null;

        $payment = $referencia ? Payment::where('reference', $referencia)->first() : null;

        if (! $payment) {
            // 200 de propósito: repetir não vai encontrar o que não existe, e
            // um erro só faria a PaySuite insistir para sempre.
            Log::warning('PaySuite: webhook para referência desconhecida.', ['referencia' => $referencia]);

            return response()->json(['estado' => 'ignorado']);
        }

        /*
         * O valor é reconferido contra o que gravámos: aceitar o do webhook
         * deixaria alguém pagar 1 MZN e receber o plano completo, caso a
         * assinatura alguma vez fosse comprometida.
         */
        if (isset($dados['amount']) && round((float) $dados['amount'], 2) !== round((float) $payment->amount, 2)) {
            Log::error('PaySuite: valor do webhook diferente do cobrado.', [
                'referencia' => $referencia,
                'esperado' => $payment->amount,
                'recebido' => $dados['amount'],
            ]);

            return response()->json(['estado' => 'ignorado']);
        }

        $resultado = $evento === 'payment.success'
            ? PaymentResult::pago($dados['transaction']['id'] ?? null, 'paid', $request->all())
            : PaymentResult::falhado('failed', $dados['error'] ?? 'O pagamento não foi concluído.', $request->all());

        $this->payments->confirmarPorWebhook($payment, $resultado);

        return response()->json(['estado' => 'recebido']);
    }
}
