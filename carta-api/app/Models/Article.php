<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['number', 'chapter_number', 'chapter_title', 'title', 'text', 'sort_order', 'is_active', 'is_locked'])]
class Article extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_locked' => 'boolean'];
    }

    /** Forma canónica no pacote offline. */
    public function toPackageArray(): array
    {
        return [
            'bloqueado' => (bool) $this->is_locked,
            'numero' => $this->number,
            'capitulo' => $this->chapter_number,
            'capituloTitulo' => $this->chapter_title,
            'titulo' => $this->title,
            'texto' => $this->text,
        ];
    }
}
