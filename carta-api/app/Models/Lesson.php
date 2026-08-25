<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha de estudo: o material que ensina, ao contrário do artigo legal em bruto.
 */
#[Fillable(['topic_id', 'slug', 'title', 'summary', 'body', 'group', 'license_categories', 'sign_slugs', 'article_numbers', 'reading_minutes', 'sort_order', 'is_locked', 'is_active', 'created_by'])]
class Lesson extends Model
{
    protected function casts(): array
    {
        return [
            'license_categories' => 'array',
            'sign_slugs' => 'array',
            'article_numbers' => 'array',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grupoNome(): string
    {
        return config('estudo.grupos_licoes.'.$this->group.'.nome', $this->group);
    }

    /** Forma canónica no pacote offline. */
    public function toPackageArray(): array
    {
        return [
            'slug' => $this->slug,
            'titulo' => $this->title,
            'resumo' => $this->summary,
            'corpo' => $this->body,
            'grupo' => $this->group,
            'tema' => $this->topic?->slug,
            'categoriasCarta' => $this->license_categories ?: [],
            'sinais' => $this->sign_slugs ?: [],
            'artigos' => array_map('intval', $this->article_numbers ?: []),
            'minutosLeitura' => $this->reading_minutes,
            'bloqueado' => $this->is_locked,
        ];
    }
}
