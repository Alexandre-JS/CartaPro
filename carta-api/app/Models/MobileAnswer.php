<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['correct' => 'boolean', 'answered_at' => 'datetime'];
    }
}
