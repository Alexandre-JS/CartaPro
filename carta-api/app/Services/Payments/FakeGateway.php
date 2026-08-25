<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Fornecedor de desenvolvimento.
 *
 * Aprova de imediato, sem rede nem credenciais. Existe para que o fluxo
 * completo — ecrã de pagamento, activação, cadeados a abrir — seja construído
 * e testado enquanto a conta de comerciante da Vodacom não é emitida, em vez
 * de ficar tudo pendurado à espera dela.
 *
 * Nunca é o driver activo em produção: `PaymentServiceProvider` recusa-o
 * quando a aplicação não está em ambiente local ou de testes.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function charge(Payment $payment): PaymentResult
    {
        // Um número terminado em 0 falha de propósito: sem isto não haveria
        // forma de exercitar o caminho de erro no app.
        if (str_ends_with($payment->phone_normalized, '0')) {
            return PaymentResult::falhado('INS-2006', 'Saldo insuficiente na carteira. Carrega e volta — o teu acesso fica à espera.');
        }

        return PaymentResult::pago('FAKE'.Str::upper(Str::random(8)), 'INS-0', ['fake' => true]);
    }

    public function query(Payment $payment): PaymentResult
    {
        return $payment->isPaid()
            ? PaymentResult::pago($payment->provider_transaction_id, 'INS-0')
            : PaymentResult::pendente();
    }
}
