<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'created_by', 'name', 'license_category', 'license_categories', 'type', 'selection_mode', 'blueprint', 'topic_ids', 'question_count', 'pass_score', 'duration_minutes', 'is_active', 'is_public', 'publication_status', 'published_at'])]
class Exam extends Model
{
    protected function casts(): array
    {
        return ['topic_ids' => 'array', 'license_categories' => 'array', 'blueprint' => 'array', 'is_active' => 'boolean', 'is_public' => 'boolean', 'published_at' => 'datetime'];
    }

    /** Categoria que governa a regra de classificação desta prova. */
    public function gradingCategory(): ?string
    {
        return $this->license_categories[0] ?? $this->license_category;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }
}
