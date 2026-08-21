<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'phone', 'password', 'license_category', 'is_active'])]
#[Hidden(['password'])]
class MobileUser extends Authenticatable
{
    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    /** Mantém `phone_normalized` sempre em sincronia — nunca se compara `phone` em bruto. */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->phone_normalized = Phone::normalize($user->phone);
        });
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(Unlock::class);
    }

    public function unlockChallenges(): HasMany
    {
        return $this->hasMany(UnlockChallenge::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(MobileApiToken::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MobileAnswer::class);
    }

    public function examHistory(): HasMany
    {
        return $this->hasMany(MobileExamHistory::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MobileRevision::class);
    }

    public function readContents(): HasMany
    {
        return $this->hasMany(MobileReadContent::class);
    }
}
