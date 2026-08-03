<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Escolhe o driver de cada método.
 *
 * Por omissão todos os métodos passam pela DebitoPay, que orquestra M-Pesa,
 * e-Mola, mKesh e cartões atrás de um único endpoint — evita manter um acordo
 * comercial e uma integração por operadora (a Movitel, por exemplo, nem publica
 * API: só cede o WSDL por contrato).
 *
 * Os drivers directos — a OpenAPI da Vodacom para o M-Pesa, a PaySuite para a
 * e-Mola — continuam aqui e escolhem-se por `MPESA_DRIVER` / `EMOLA_DRIVER`.
 * São a saída se a DebitoPay falhar ou se as taxas deixarem de compensar:
 * trocar de fornecedor passa a ser uma variável de ambiente, não um deploy.
 *
 * Quem chama não precisa de saber nada disto — pede o método e recebe um
 * gateway.
 */
class GatewayManager
{
    public function __construct(private readonly Application $app) {}

    /** Métodos que a instalação consegue mesmo cobrar. */
    public function disponiveis(): array
    {
        return array_keys(array_filter(
            config('payments.methods', []),
            fn (array $metodo, string $chave) => $this->configurado($metodo['driver'], $chave),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    public function para(string $metodo): PaymentGateway
    {
        $definicao = config("payments.methods.{$metodo}");

        if (! $definicao) {
            throw new RuntimeException("Método de pagamento desconhecido: {$metodo}.");
        }

        return $this->driver($definicao['driver']);
    }

    private function driver(string $nome): PaymentGateway
    {
        /*
         * O driver falso aprova qualquer pagamento sem cobrar nada. Se um
         * deploy de produção ficasse com PAYMENTS_PROVIDER por definir, o plano
         * completo passava a ser gratuito sem ninguém dar por isso.
         */
        if (config('payments.provider') === 'fake') {
            if (! $this->app->environment('local', 'testing')) {
                throw new RuntimeException(
                    'PAYMENTS_PROVIDER=fake não é permitido em '.$this->app->environment().'. Define PAYMENTS_PROVIDER=real.'
                );
            }

            return new FakeGateway;
        }

        return match ($nome) {
            'mpesa' => new MpesaGateway(config('payments.mpesa')),
            'paysuite' => new PaySuiteGateway(config('payments.paysuite')),
            'debitopay' => new DebitoPayGateway(config('payments.debitopay')),
            'fake' => new FakeGateway,
            default => throw new RuntimeException("Driver de pagamento desconhecido: {$nome}."),
        };
    }

    /**
     * Sem credenciais o método não é oferecido — melhor do que falhar depois.
     *
     * Na DebitoPay a verificação é por método, não por driver: as carteiras são
     * contas separadas no portal, e ter a de M-Pesa configurada não dá para
     * cobrar cartões. Aceitar a carteira comum como alternativa mantém simples
     * a instalação de quem liquida tudo na mesma conta.
     */
    private function configurado(string $driver, string $metodo): bool
    {
        if (config('payments.provider') === 'fake') {
            return true;
        }

        return match ($driver) {
            'mpesa' => (bool) config('payments.mpesa.api_key') && (bool) config('payments.mpesa.public_key'),
            'paysuite' => (bool) config('payments.paysuite.token'),
            'debitopay' => (bool) config('payments.debitopay.api_key')
                && (bool) config('payments.debitopay.merchant_id')
                && ((bool) config('payments.debitopay.wallet_code')
                    || (bool) config("payments.debitopay.wallets.{$metodo}")),
            default => false,
        };
    }
}
