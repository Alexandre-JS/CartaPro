<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /** Nome gravado em `payments.provider`. */
    public function name(): string;

    /**
     * Pede o pagamento ao cliente.
     *
     * Em C2B isto faz aparecer o pedido de PIN no telemóvel do aluno. Pode
     * devolver 'pendente': o cliente ainda não confirmou.
     */
    public function charge(Payment $payment): PaymentResult;

    /** Estado atual de uma transação — usado enquanto o app faz polling. */
    public function query(Payment $payment): PaymentResult;
}
