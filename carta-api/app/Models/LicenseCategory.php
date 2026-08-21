<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_active'])]
class LicenseCategory extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
