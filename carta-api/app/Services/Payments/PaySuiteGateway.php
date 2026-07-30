<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PaySuite — agregador (e-Mola, M-Pesa, mKesh, cartões).
 *
 * É por aqui que a e-Mola entra: a Movitel não publica API, só cede o WSDL por
 * acordo comercial directo, pelo que integrá-la sozinha não é possível.
 *
 * O modelo de interacção é diferente do C2B do M-Pesa e isso transparece no
 * app: em vez de empurrar um pedido de PIN para um número que indicamos, a
 * PaySuite devolve um `checkout_url` e recolhe lá o número. Continuamos a pedir
 * o número no app à mesma — serve para validar o prefixo contra a carteira e
 * para a reconciliação — mas quem o cobra é a página de checkout.
 *
 * A confirmação não vem na resposta: chega por webhook assinado.
 */
class PaySuiteGateway implements PaymentGateway
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'paysuite';
    }

    public function charge(Payment $payment): PaymentResult
    {
        $resposta = $this->pedido('post', 'payments', [
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'description' => 'CartaPro '.$payment->plan,
            'callback_url' => route('webhooks.paysuite'),
        ]);

        if ($resposta === null) {
            return PaymentResult::pendente(null, 'Sem resposta do fornecedor. A confirmar…');
        }

        if (($resposta['status'] ?? null) !== 'success') {
            return PaymentResult::falhado(
                $resposta['status'] ?? null,
                $resposta['message'] ?? 'Não foi possível iniciar o pagamento.',
                $resposta,
            );
        }

        $dados = $resposta['data'] ?? [];

        // 'pending' aqui não é o cliente a hesitar: é o checkout que ainda nem
        // foi aberto. O estado real chega por webhook.
        return new PaymentResult(
            Payment::PENDENTE,
            transactionId: $dados['id'] ?? null,
            code: $dados['status'] ?? 'pending',
            message: null,
            payload: $resposta,
            checkoutUrl: $dados['checkout_url'] ?? null,
        );
    }

    /**
     * A PaySuite não expõe consulta de estado por transação: a confirmação é
     * empurrada por webhook. O polling do app limita-se a reler o que já
     * gravámos, e é o webhook que muda o estado.
     */
    public function query(Payment $payment): PaymentResult
    {
        return $payment->isPaid()
            ? PaymentResult::pago($payment->provider_transaction_id)
            : PaymentResult::pendente($payment->provider_code);
    }

    private function pedido(string $metodo, string $caminho, array $dados): ?array
    {
        $token = (string) ($this->config['token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Credenciais PaySuite em falta: define PAYSUITE_TOKEN.');
        }

        try {
            return Http::withToken($token)
                ->asJson()
                ->acceptJson()
                ->timeout($this->config['timeout'])
                ->{$metodo}(rtrim($this->config['base_url'], '/').'/'.$caminho, $dados)
                ->json();
        } catch (\Throwable $erro) {
            Log::error('PaySuite: falha de comunicação', ['caminho' => $caminho, 'erro' => $erro->getMessage()]);

            return null;
        }
    }
}
