<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mobile_user_id', 'last_activity_at', 'calculated_at'])]
class LearningProfile extends Model
{
    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime', 'calculated_at' => 'datetime'];
    }
}
