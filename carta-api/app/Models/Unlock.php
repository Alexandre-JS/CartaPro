<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['phone', 'plan', 'payment_method', 'payment_reference', 'unlocked_at', 'expires_at', 'notes', 'is_active', 'created_by', 'mobile_user_id', 'last_verified_at'])]
class Unlock extends Model
{
    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime', 'expires_at' => 'datetime', 'last_verified_at' => 'datetime', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $unlock): void {
            $unlock->phone_normalized = Phone::normalize($unlock->phone);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    /** Ativo e dentro da validade. */
    public function isCurrentlyValid(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
