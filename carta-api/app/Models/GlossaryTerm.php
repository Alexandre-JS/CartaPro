<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Termo do glossário, referenciável a partir das explicações das perguntas. */
#[Fillable(['term', 'slug', 'definition', 'article_ref', 'sort_order', 'is_active', 'is_locked'])]
class GlossaryTerm extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_locked' => 'boolean'];
    }

    public function toPackageArray(): array
    {
        return [
            'bloqueado' => (bool) $this->is_locked,
            'slug' => $this->slug,
            'termo' => $this->term,
            'definicao' => $this->definition,
            'artigoRef' => $this->article_ref,
        ];
    }
}
