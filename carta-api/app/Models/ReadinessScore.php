<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mobile_user_id', 'score', 'breakdown', 'level', 'calculated_at'])]
class ReadinessScore extends Model
{
    protected function casts(): array
    {
        return ['breakdown' => 'array', 'calculated_at' => 'datetime'];
    }
}
