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
     * Caminho público da imagem, com o banco de sinais a mandar.
     *
     * A coluna `image` guardava uma cópia do caminho do sinal no momento em que
     * a pergunta foi gravada, e ficava a apodrecer: trocar o ficheiro do sinal
     * no painel — ou carregá-lo pela primeira vez, como acontece com os 144
     * sinais importados sem imagem — não chegava às perguntas que o usam, que
     * continuavam a apontar para o caminho antigo ou para nada.
     *
     * Com o sinal escolhido, é ele a fonte; a coluna só serve as perguntas com
     * imagem própria, que não existe em lado nenhum senão ali.
     */
    public function imagemPublica(): ?string
    {
        $caminho = $this->sign_id ? ($this->sign?->file_path ?: null) : $this->image;

        return $caminho ?: null;
    }

    /**
     * Forma canónica da pergunta no pacote/API.
     * Único mapeamento — antes estava duplicado em ContentController,
     * MobileController e PublicationController, com risco de divergirem.
     */
    public function toPackageArray(): array
    {
        $imagem = $this->imagemPublica();

        return [
            'id' => $this->external_id,
            'tipo' => $this->type,
            'tema' => $this->topic->slug,
            'categoriaCarta' => $this->categories,
            'enunciado' => $this->statement,
            'imagem' => $imagem ? url($imagem) : null,
            // Slug do sinal ilustrado, quando vem da biblioteca: deixa o app
            // ligar a pergunta à ficha do sinal em vez de mostrar só a imagem.
            'sinal' => $this->sign_id ? $this->sign?->slug : null,
            'opcoes' => $this->options,
            'correta' => $this->correct_index,
            'explicacao' => $this->explanation,
            'artigoRef' => $this->article_ref,
            'bloqueado' => $this->is_locked,
        ];
    }
}
