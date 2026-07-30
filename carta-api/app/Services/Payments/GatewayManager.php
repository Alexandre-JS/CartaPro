<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Escolhe o driver de cada método.
 *
 * O M-Pesa fala directamente com a OpenAPI da Vodacom; a e-Mola passa por um
 * agregador, porque a Movitel não publica API. Quem chama não precisa de saber
 * disso — pede o método e recebe um gateway.
 */
class GatewayManager
{
    public function __construct(private readonly Application $app) {}

    /** Métodos que a instalação consegue mesmo cobrar. */
    public function disponiveis(): array
    {
        return array_keys(array_filter(
            config('payments.methods', []),
            fn (array $metodo) => $this->configurado($metodo['driver']),
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
            'fake' => new FakeGateway,
            default => throw new RuntimeException("Driver de pagamento desconhecido: {$nome}."),
        };
    }

    /** Sem credenciais o método não é oferecido — melhor do que falhar depois. */
    private function configurado(string $driver): bool
    {
        if (config('payments.provider') === 'fake') {
            return true;
        }

        return match ($driver) {
            'mpesa' => (bool) config('payments.mpesa.api_key') && (bool) config('payments.mpesa.public_key'),
            'paysuite' => (bool) config('payments.paysuite.token'),
            default => false,
        };
    }
}
