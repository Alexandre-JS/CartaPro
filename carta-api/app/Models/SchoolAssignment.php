<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'classroom_id', 'mobile_user_id', 'created_by', 'type', 'title', 'instructions', 'resource_type', 'resource_id', 'status', 'due_at', 'published_at'])]
class SchoolAssignment extends Model
{
    public const TYPES = ['training', 'reading', 'simulation', 'test', 'revision'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(SchoolAssignmentProgress::class);
    }
}
