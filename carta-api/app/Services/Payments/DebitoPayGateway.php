<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * DebitoPay — orquestrador server-to-server (M-Pesa, e-Mola, mKesh, cartões).
 *
 * Toda a API vive num único endpoint, `POST /payment-orchestrator`, e o campo
 * `action` diz o que se quer: 'process' cobra, 'check-status' consulta. Não há
 * rota GET de estado — a consulta é outro POST ao mesmo sítio.
 *
 * A chave secreta (sk_live_/sk_sandbox_) nunca sai do backend: o app fala
 * connosco, nós falamos com a DebitoPay.
 *
 * O modelo de confirmação muda com o método, e é isso que o app vê:
 * o M-Pesa confirma de forma síncrona (a resposta já traz `success`), a e-Mola
 * e o mKesh ficam pendentes até ao callback, e os cartões devolvem um
 * `checkout_url` para o cliente abrir.
 */
class DebitoPayGateway implements PaymentGateway
{
    private const PAGOS = ['success', 'successful', 'paid', 'completed', 'approved'];

    private const PENDENTES = ['pending', 'processing', 'initiated', 'created', 'awaiting', 'in_progress'];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'debitopay';
    }

    public function charge(Payment $payment): PaymentResult
    {
        return $this->result($this->request($this->corpoDaCobranca($payment), [
            // Tocar duas vezes no botão não pode gerar duas cobranças. A
            // referência é estável por pagamento, e serve de chave.
            'X-Idempotency-Key' => $payment->reference,
        ]));
    }

    /**
     * Consulta o estado — reconciliação e rede de segurança quando o webhook
     * se perde. Sem `payment_id` não há nada a perguntar: o pedido nem chegou
     * a ser aceite, e o app continua a mostrar pendente.
     */
    public function query(Payment $payment): PaymentResult
    {
        if (! $payment->provider_transaction_id) {
            return PaymentResult::pendente($payment->provider_code, $payment->provider_message);
        }

        return $this->result($this->request([
            'action' => 'check-status',
            'payment_id' => $payment->provider_transaction_id,
        ]));
    }

    /**
     * O corpo muda conforme o método recolhe o dinheiro no telemóvel ou numa
     * página de checkout: mobile money leva `phone` e recebe o pedido de PIN,
     * cartão leva `return_url` e devolve o cliente ao fim do caminho.
     */
    private function corpoDaCobranca(Payment $payment): array
    {
        $user = $payment->mobileUser;

        $corpo = [
            'action' => 'process',
            'payment_method' => $this->metodoApi($payment->method),
            'merchant_id' => $this->obrigatorio('merchant_id'),
            'wallet_code' => $this->wallet($payment->method),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            // Como a transação aparece na reconciliação do portal.
            'source' => 'gateway',
            'source_id' => $payment->reference,
            'customer_name' => $user?->name,
            'customer_email' => $user?->email,
            'customer_phone' => $payment->phone_normalized,
        ];

        if ($this->carteiraMovel($payment->method)) {
            // Aceite em 258XXXXXXXXX, que é como o guardamos.
            $corpo['phone'] = $payment->phone_normalized;
        } else {
            $corpo['return_url'] = $this->returnUrl($payment);
        }

        return array_filter($corpo, fn ($valor) => $valor !== null && $valor !== '');
    }

    private function request(array $corpo, array $headers = []): ?Response
    {
        try {
            return Http::withToken($this->obrigatorio('api_key'))
                ->acceptJson()
                ->asJson()
                ->withHeaders($headers)
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->post($this->url('payment-orchestrator'), $corpo);
        } catch (\Throwable $erro) {
            Log::error('DebitoPay: falha de comunicação', ['erro' => $erro->getMessage()]);

            return null;
        }
    }

    /**
     * Traduz a resposta para o vocabulário da aplicação.
     *
     * A dúvida é sempre resolvida a favor de "pendente": um timeout ou um 500
     * não significam que o cliente não pagou — significam que ainda não
     * sabemos. Dar por falhado aqui arriscava recusar acesso a quem já viu o
     * dinheiro sair, e o webhook (ou a próxima consulta) desempata.
     */
    private function result(?Response $resposta): PaymentResult
    {
        if (! $resposta) {
            return PaymentResult::pendente(null, 'Sem resposta da DebitoPay. A confirmar…');
        }

        $payload = is_array($resposta->json()) ? $resposta->json() : [];
        // O check-status aninha tudo em `payment`; o process devolve à cabeça.
        $dados = is_array($payload['payment'] ?? null) ? $payload['payment'] : $payload;

        $estado = strtolower((string) ($dados['status'] ?? ''));
        $id = $dados['payment_id'] ?? $dados['id'] ?? null;
        $referencia = $dados['provider_reference'] ?? $dados['transactionId'] ?? $dados['reference'] ?? null;
        $checkout = $dados['checkout_url'] ?? $payload['checkout_url'] ?? null;
        $codigo = $dados['error'] ?? $payload['error'] ?? ($estado ?: (string) $resposta->status());
        $mensagem = $payload['message'] ?? $dados['message'] ?? null;
        $aceite = $resposta->successful() && ($payload['success'] ?? true) !== false;

        if ($aceite && in_array($estado, self::PAGOS, true)) {
            // Guarda-se a referência do provedor quando existe: é o que o
            // apoio compara com o extrato da operadora.
            return new PaymentResult(Payment::PAGO, $id ?: $referencia, (string) $codigo, $mensagem, $payload, $checkout);
        }

        if ($resposta->serverError() || $resposta->status() === 429 || ($aceite && in_array($estado, self::PENDENTES, true))) {
            return new PaymentResult(Payment::PENDENTE, $id, (string) $codigo, $mensagem, $payload, $checkout);
        }

        return new PaymentResult(
            Payment::FALHADO,
            $id,
            (string) $codigo,
            $mensagem ?: $this->explicar((string) $codigo),
            $payload,
            $checkout,
        );
    }

    /**
     * Códigos de erro traduzidos para quem está do outro lado do ecrã.
     *
     * Os que resultam de má configuração nossa não são explicados ao aluno —
     * ele não tem nada a corrigir, e "carteira inexistente" só assustaria.
     */
    private function explicar(string $codigo): string
    {
        return match ($codigo) {
            'expired' => 'O pedido expirou sem confirmação. Tenta de novo.',
            'INSUFFICIENT_FUNDS' => 'Saldo insuficiente na carteira.',
            default => 'A DebitoPay não conseguiu concluir o pagamento.',
        };
    }

    /** O nome que a DebitoPay dá ao método — 'cartao' é 'visa_mastercard' lá. */
    private function metodoApi(string $metodo): string
    {
        return (string) ($this->config['metodos'][$metodo] ?? $metodo);
    }

    private function carteiraMovel(string $metodo): bool
    {
        return (bool) config("payments.methods.{$metodo}.movel", true);
    }

    /**
     * A carteira do método, ou a comum. São contas distintas no portal: cada
     * método liquida na sua, e cobrar M-Pesa contra uma carteira e-Mola é
     * recusado pela DebitoPay (WALLET_INACTIVE).
     */
    private function wallet(string $metodo): string
    {
        $wallet = (string) ($this->config['wallets'][$metodo] ?? '') ?: (string) ($this->config['wallet_code'] ?? '');

        if ($wallet === '') {
            throw new RuntimeException("DebitoPay: wallet_code em falta para {$metodo}.");
        }

        return $wallet;
    }

    /** Onde o cartão devolve o cliente depois do Hosted Checkout. */
    private function returnUrl(Payment $payment): string
    {
        $base = (string) ($this->config['return_url'] ?? '') ?: rtrim((string) config('app.url'), '/').'/pagamento/resultado';

        return $base.(str_contains($base, '?') ? '&' : '?').'ref='.urlencode($payment->reference);
    }

    private function obrigatorio(string $chave): string
    {
        $valor = (string) ($this->config[$chave] ?? '');

        if ($valor === '') {
            throw new RuntimeException("DebitoPay: configuração {$chave} em falta.");
        }

        return $valor;
    }

    private function url(string $caminho): string
    {
        return rtrim((string) $this->config['base_url'], '/').'/'.ltrim($caminho, '/');
    }
}
