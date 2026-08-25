<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'sign_category_id', 'sign_subcategory_id', 'topic_id', 'meaning', 'description', 'article_ref', 'file_path', 'sort_order', 'is_locked', 'is_active'])]
class Sign extends Model
{
    /**
     * O slug é a chave com que as fichas e o app referem o sinal, e a coluna é
     * NOT NULL: deriva-se do nome sempre que não vier preenchido, em vez de
     * rebentar em quem criar o registo fora do painel.
     */
    protected static function booted(): void
    {
        static::saving(function (self $sign): void {
            if (blank($sign->slug)) {
                $sign->slug = Str::slug($sign->name);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_locked' => 'boolean'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SignCategory::class, 'sign_category_id');
    }

    /** Nem todo o sinal precisa de refinamento — a subcategoria é opcional. */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(SignCategory::class, 'sign_subcategory_id');
    }

    public function categoriaNome(): string
    {
        return (string) ($this->category?->name ?? '—');
    }

    /** Forma canónica no pacote offline. */
    public function toPackageArray(): array
    {
        return [
            'slug' => $this->slug,
            'nome' => $this->name,
            'categoria' => $this->category?->slug,
            // Acrescentado, nunca em substituição: o app lê `categoria` e
            // continuaria a funcionar se ignorasse este campo.
            'subcategoria' => $this->subcategory?->slug,
            'tema' => $this->topic?->slug,
            'significado' => $this->meaning,
            'descricao' => $this->description,
            'artigoRef' => $this->article_ref,
            'imagem' => $this->file_path ? url($this->file_path) : null,
            'bloqueado' => $this->is_locked,
        ];
    }
}
