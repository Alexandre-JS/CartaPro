<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * M-Pesa Moçambique — Vodacom OpenAPI (C2B single stage).
 *
 * Autenticação em dois passos, que é a parte que costuma confundir:
 *  1. a API key é cifrada em RSA com a chave pública do portal e usada como
 *     Bearer para pedir uma sessão;
 *  2. o Session ID devolvido é cifrado da mesma forma e é *esse* o Bearer de
 *     todas as transações.
 *
 * Nada de host, mercado ou prefixo está fixo aqui: vêm de `config/payments.php`
 * porque só se confirmam no portal quando a conta de comerciante for emitida.
 * Passar de sandbox a produção é mudar uma variável de ambiente.
 */
class MpesaGateway implements PaymentGateway
{
    /** A OpenAPI devolve este código quando a operação correu bem. */
    private const SUCESSO = 'INS-0';

    /**
     * Códigos em que a transação ainda não é terminal.
     *
     * INS-1 é um erro interno que a Vodacom recomenda repetir; os restantes
     * indicam que o cliente ainda não interagiu com o pedido de PIN.
     */
    private const PENDENTES = ['INS-1', 'INS-9', 'INS-10'];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'mpesa';
    }

    public function charge(Payment $payment): PaymentResult
    {
        $resposta = $this->pedido('post', 'c2bPayment/singleStage/', [
            'input_Amount' => number_format((float) $payment->amount, 2, '.', ''),
            'input_Country' => $this->config['country'],
            'input_Currency' => $payment->currency,
            'input_CustomerMSISDN' => $this->msisdn($payment->phone_normalized),
            'input_ServiceProviderCode' => $this->config['service_provider_code'],
            'input_ThirdPartyConversationID' => $payment->conversation_id,
            'input_TransactionReference' => $payment->reference,
            'input_PurchasedItemsDesc' => 'CartaPro '.$payment->plan,
        ]);

        return $this->interpretar($resposta);
    }

    public function query(Payment $payment): PaymentResult
    {
        $resposta = $this->pedido('get', 'queryTransactionStatus/', [
            'input_QueryReference' => $payment->provider_transaction_id ?: $payment->reference,
            'input_ServiceProviderCode' => $this->config['service_provider_code'],
            'input_ThirdPartyConversationID' => $payment->conversation_id,
            'input_Country' => $this->config['country'],
        ]);

        return $this->interpretar($resposta);
    }

    /**
     * Traduz a resposta do fornecedor.
     *
     * Uma falha de rede não é uma transação falhada: se marcássemos 'falhado'
     * um timeout, perderíamos pagamentos que o cliente chegou a confirmar. Fica
     * pendente e o polling resolve.
     */
    private function interpretar(?array $resposta): PaymentResult
    {
        if ($resposta === null) {
            return PaymentResult::pendente(null, 'Sem resposta do M-Pesa. A confirmar…');
        }

        $codigo = $resposta['output_ResponseCode'] ?? null;
        $mensagem = $resposta['output_ResponseDesc'] ?? null;

        if ($codigo === self::SUCESSO) {
            return PaymentResult::pago(
                $resposta['output_TransactionID'] ?? null,
                $codigo,
                $resposta,
            );
        }

        if (in_array($codigo, self::PENDENTES, true)) {
            return PaymentResult::pendente($codigo, $mensagem, $resposta);
        }

        return PaymentResult::falhado($codigo, $this->mensagemLegivel($codigo, $mensagem), $resposta);
    }

    /**
     * O texto da OpenAPI é para programadores ("INS-2006: Insufficient
     * balance"). O aluno precisa de saber o que fazer a seguir.
     */
    private function mensagemLegivel(?string $codigo, ?string $original): string
    {
        return match ($codigo) {
            'INS-2006' => 'Saldo insuficiente na carteira. Carrega e volta — o teu acesso fica à espera.',
            'INS-2051' => 'Este número não tem M-Pesa activo. Tenta com outro número ou escolhe e-Mola.',
            'INS-995', 'INS-2001' => 'O PIN não foi aceite. Tenta de novo com calma.',
            'INS-996', 'INS-997' => 'Esta carteira não está activa. Confirma com a operadora e volta.',
            'INS-2057' => 'O pedido expirou antes da confirmação. Tenta de novo — é rápido.',
            default => $original ?: 'Não conseguimos concluir desta vez. Tenta de novo.',
        };
    }

    /** A OpenAPI espera o MSISDN em dígitos com indicativo, sem '+'. */
    private function msisdn(string $normalized): string
    {
        return Phone::normalize($normalized);
    }

    private function pedido(string $metodo, string $caminho, array $dados): ?array
    {
        try {
            $pedido = Http::withToken($this->bearerDeSessao())
                ->asJson()
                ->acceptJson()
                ->withHeaders(['Origin' => '*'])
                ->timeout($this->config['timeout']);

            $resposta = $metodo === 'get'
                ? $pedido->get($this->url($caminho), $dados)
                : $pedido->post($this->url($caminho), $dados);

            return $resposta->json();
        } catch (RuntimeException $erro) {
            // Falta de credenciais é erro de configuração, não do aluno.
            throw $erro;
        } catch (\Throwable $erro) {
            Log::error('M-Pesa: falha de comunicação', [
                'caminho' => $caminho,
                'erro' => $erro->getMessage(),
            ]);

            return null;
        }
    }

    private function url(string $caminho): string
    {
        return sprintf('https://%s/%s/ipg/v2/%s/%s',
            $this->config['host'],
            trim($this->config['path_prefix'], '/'),
            $this->config['market'],
            $caminho,
        );
    }

    /**
     * Session ID cifrado — o Bearer de todas as transações.
     *
     * Fica em cache porque cada sessão dura cerca de uma hora e pedir uma por
     * transação acrescenta uma ida ao servidor no meio do pagamento.
     */
    private function bearerDeSessao(): string
    {
        $chave = 'mpesa:sessao:'.md5((string) $this->config['api_key']);

        $sessao = Cache::remember($chave, $this->config['session_ttl'], function (): string {
            $resposta = Http::withToken($this->cifrar((string) $this->config['api_key']))
                ->acceptJson()
                ->withHeaders(['Origin' => '*'])
                ->timeout(20)
                ->get($this->url('getSession/'));

            $sessao = $resposta->json('output_SessionID');

            if (! $sessao || $resposta->json('output_ResponseCode') !== self::SUCESSO) {
                Log::error('M-Pesa: sessão recusada', ['resposta' => $resposta->json()]);

                throw new RuntimeException('Não foi possível abrir sessão no M-Pesa.');
            }

            return $sessao;
        });

        return $this->cifrar($sessao);
    }

    /**
     * RSA/ECB/PKCS1 com a chave pública do portal, resultado em base64.
     *
     * A chave chega em base64 DER (sem cabeçalho PEM), pelo que é envolvida
     * antes de o OpenSSL a conseguir ler.
     */
    private function cifrar(string $valor): string
    {
        $publica = (string) $this->config['public_key'];

        if ($publica === '' || ($this->config['api_key'] ?? '') === '') {
            throw new RuntimeException('Credenciais M-Pesa em falta: define MPESA_API_KEY e MPESA_PUBLIC_KEY.');
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(preg_replace('/\s+/', '', $publica), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $recurso = openssl_pkey_get_public($pem);

        if (! $recurso) {
            throw new RuntimeException('MPESA_PUBLIC_KEY inválida: esperava-se a chave do portal em base64.');
        }

        if (! openssl_public_encrypt($valor, $cifrado, $recurso, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Falha ao cifrar as credenciais M-Pesa.');
        }

        return base64_encode($cifrado);
    }
}
