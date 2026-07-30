<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['topic_id', 'author_id', 'school_id', 'external_id', 'type', 'categories', 'statement', 'image', 'sign_id', 'article_id', 'options', 'correct_index', 'explanation', 'article_ref', 'is_locked', 'is_active', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'sort_order'])]
class Question extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'options' => 'array',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sign(): BelongsTo
    {
        return $this->belongsTo(Sign::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class)->withPivot('sort_order');
    }

    /**
     * Forma canónica da pergunta no pacote/API.
     * Único mapeamento — antes estava duplicado em ContentController,
     * MobileController e PublicationController, com risco de divergirem.
     */
    public function toPackageArray(): array
    {
        return [
            'id' => $this->external_id,
            'tipo' => $this->type,
            'tema' => $this->topic->slug,
            'categoriaCarta' => $this->categories,
            'enunciado' => $this->statement,
            'imagem' => $this->image ? url($this->image) : null,
            'opcoes' => $this->options,
            'correta' => $this->correct_index,
            'explicacao' => $this->explanation,
            'artigoRef' => $this->article_ref,
            'bloqueado' => $this->is_locked,
        ];
    }
}
