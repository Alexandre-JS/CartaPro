<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'price', 'currency', 'duration_days', 'features', 'is_purchasable', 'is_active', 'sort_order'])]
class Plan extends Model
{
    public const FREE = 'free';
    public const PLUS = 'plus';
    public const SCHOOL = 'school';
    public const LEGACY_COMPLETE = 'completo';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'features' => 'array',
            'is_purchasable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function canonical(string $code): string
    {
        return $code === self::LEGACY_COMPLETE ? self::PLUS : $code;
    }
}
