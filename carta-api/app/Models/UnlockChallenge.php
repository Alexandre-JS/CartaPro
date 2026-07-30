<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desafio OTP que prova a posse do número antes de ativar um desbloqueio.
 * O código nunca é guardado em claro.
 */
#[Fillable(['mobile_user_id', 'phone_normalized', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class UnlockChallenge extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function isUsable(): bool
    {
        return ! $this->consumed_at && $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', trim($code));
    }
}
