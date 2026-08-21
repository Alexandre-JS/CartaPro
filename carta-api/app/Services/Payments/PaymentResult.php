<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Resposta de um fornecedor, já traduzida para o vocabulário da aplicação.
 *
 * Existe para que o resto do código nunca veja códigos como 'INS-0': quando um
 * segundo fornecedor entrar (e-Mola), só o driver muda.
 */
class PaymentResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
        /** Só a PaySuite o usa: o C2B do M-Pesa não abre página nenhuma. */
        public readonly ?string $checkoutUrl = null,
    ) {}

    public static function pago(?string $transactionId, ?string $code = null, array $payload = []): self
    {
        return new self(Payment::PAGO, $transactionId, $code, null, $payload);
    }

    public static function pendente(?string $code = null, ?string $message = null, array $payload = []): self
    {
        return new self(Payment::PENDENTE, null, $code, $message, $payload);
    }

    public static function falhado(?string $code, ?string $message, array $payload = []): self
    {
        return new self(Payment::FALHADO, null, $code, $message, $payload);
    }

    public function isPaid(): bool
    {
        return $this->status === Payment::PAGO;
    }

    public function isPending(): bool
    {
        return $this->status === Payment::PENDENTE;
    }
}
