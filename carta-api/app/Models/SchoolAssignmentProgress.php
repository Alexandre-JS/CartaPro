<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_assignment_id', 'mobile_user_id', 'status', 'started_at', 'completed_at', 'metadata'])]
class SchoolAssignmentProgress extends Model
{
    protected $table = 'school_assignment_progress';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchoolAssignment::class, 'school_assignment_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }
}
