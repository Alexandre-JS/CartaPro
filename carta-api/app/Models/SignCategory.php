<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Categoria de sinais — e também subcategoria.
 *
 * São a mesma coisa a dois níveis: uma subcategoria é uma categoria com pai.
 * Ter duas tabelas quase iguais duplicaria CRUD, validações e vistas sem
 * ganhar nada, porque os campos são os mesmos nos dois casos.
 *
 * A hierarquia pára no segundo nível de propósito. Sinalização rodoviária
 * organiza-se em categoria e, quando muito, um refinamento — profundidade
 * arbitrária traria árvores que ninguém consegue navegar num formulário.
 */
#[Fillable(['parent_id', 'slug', 'name', 'description', 'icon', 'sort_order', 'is_active'])]
class SignCategory extends Model
{
    protected static function booted(): void
    {
        // A coluna é única e NOT NULL: derivar do nome evita rebentar em quem
        // criar a categoria fora do painel (importação, seeder, tinker).
        static::saving(function (self $categoria): void {
            if (blank($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->name);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function signs(): HasMany
    {
        return $this->hasMany(Sign::class, 'sign_category_id');
    }

    /** Categorias de topo. */
    public function scopeRaiz(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isSubcategoria(): bool
    {
        return $this->parent_id !== null;
    }

    /** Forma canónica no pacote offline. */
    public function toPackageArray(): array
    {
        return [
            'slug' => $this->slug,
            'nome' => $this->name,
            'descricao' => $this->description,
            'icone' => $this->icon,
            'ordem' => $this->sort_order,
        ];
    }
}
