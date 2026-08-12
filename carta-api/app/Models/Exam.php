<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['school_id', 'created_by', 'name', 'license_category', 'license_categories', 'type', 'selection_mode', 'blueprint', 'topic_ids', 'question_count', 'pass_score', 'duration_minutes', 'is_active', 'is_public', 'is_locked', 'publication_status', 'published_at'])]
class Exam extends Model
{
    /** Preenchido pelas listagens; a prova em si não guarda isto. */
    public bool $has_locked_questions = false;

    protected function casts(): array
    {
        return ['topic_ids' => 'array', 'license_categories' => 'array', 'blueprint' => 'array', 'is_active' => 'boolean', 'is_public' => 'boolean', 'is_locked' => 'boolean', 'published_at' => 'datetime'];
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

    /**
     * Tentativas já submetidas nesta prova, através das sessões.
     *
     * Serve para decidir se as perguntas ainda podem ser trocadas: as respostas
     * são guardadas com o `external_id` da pergunta por chave e o diagnóstico
     * por tema é recalculado sobre as perguntas *actuais* da prova. Mexer no
     * conjunto depois de alguém responder não dá erro nenhum — passa a mostrar
     * a tentativa antiga corrigida contra uma prova que o aluno nunca fez.
     */
    public function attempts(): HasManyThrough
    {
        return $this->hasManyThrough(ExamAttempt::class, ExamSession::class);
    }
}
