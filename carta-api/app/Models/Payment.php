<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'mobile_user_id', 'plan', 'amount', 'currency', 'provider', 'method', 'phone_normalized',
    'status', 'reference', 'conversation_id', 'provider_transaction_id',
    'provider_code', 'provider_message', 'provider_payload', 'checkout_url', 'unlock_id', 'paid_at', 'refunded_at', 'refund_reason', 'refunded_by',
])]
class Payment extends Model
{
    public const PENDENTE = 'pendente';
    public const PAGO = 'pago';
    public const FALHADO = 'falhado';
    public const EXPIRADO = 'expirado';
    public const REEMBOLSADO = 'reembolsado';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function unlock(): BelongsTo
    {
        return $this->belongsTo(Unlock::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDENTE;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAGO;
    }

    /** Dentro da janela de devolução prometida ao aluno. */
    public function reembolsavel(): bool
    {
        return $this->isPaid()
            && $this->paid_at
            && $this->paid_at->addDays((int) config('payments.garantia_dias', 7))->isFuture();
    }

    /**
     * Identificadores da transação.
     *
     * A OpenAPI limita ambos os campos e rejeita caracteres fora de
     * [A-Za-z0-9]; daí o prefixo curto e o sufixo aleatório em maiúsculas.
     */
    public static function novaReferencia(): string
    {
        return 'CP'.Str::upper(Str::random(10));
    }

    public static function novaConversa(): string
    {
        return 'CP'.Str::upper(Str::random(30));
    }
}
