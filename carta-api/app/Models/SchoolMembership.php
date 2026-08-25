<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'mobile_user_id', 'student_id', 'classroom_id', 'status', 'joined_at', 'left_at'])]
class SchoolMembership extends Model
{
    public const STATUSES = ['invited', 'active', 'suspended', 'left', 'completed'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', ['invited', 'active', 'suspended']);
    }
}
