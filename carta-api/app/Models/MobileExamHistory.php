<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileExamHistory extends Model
{
    protected $table = 'mobile_exam_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
