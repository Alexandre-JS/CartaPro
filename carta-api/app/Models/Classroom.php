<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['school_id', 'name', 'code', 'year', 'is_active'])]
class Classroom extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMembership::class);
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(Instructor::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SchoolAssignment::class);
    }
}
