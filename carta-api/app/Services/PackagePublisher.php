<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Topic;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Publicação do pacote offline — um único caminho.
 *
 * Existiam duas implementações (PublicationController e
 * ManagementController::publish) e ambas escreviam o ficheiro em
 * `public/packages/`, servido pelo webserver sem autenticação, com a resposta
 * correta e a explicação de todas as perguntas. A segunda ainda produzia um
 * payload diferente (sem provas, sem regras), pelo que o app recebia contratos
 * distintos conforme quem publicava.
 */
class PackagePublisher
{
    public const DISK = 'local';

    public const DIRECTORY = 'packages';

    public function publish(User $publisher, ?string $notes = null): ContentPackage
    {
        $payload = $this->buildPayload();

        $relativePath = self::DIRECTORY.'/cartapro-'.$payload['versao'].'.json';
        Storage::disk(self::DISK)->put($relativePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        ContentPackage::where('status', 'published')->update(['status' => 'archived']);

        return ContentPackage::create([
            'version' => $payload['versao'],
            'status' => 'published',
            'question_count' => count($payload['perguntas']),
            'payload' => $payload,
            'file_path' => $relativePath,
            'notes' => $notes,
            'published_by' => $publisher->id,
            'published_at' => now(),
        ]);
    }

    public function buildPayload(): array
    {
        $questions = Question::with(['topic', 'sign'])
            ->where('status', 'approved')->where('is_active', true)
            ->whereHas('topic', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->get();

        abort_if($questions->isEmpty(), 422, 'Não existem perguntas aprovadas para publicar.');

        $exams = Exam::with('questions.topic')
            ->where(['is_public' => true, 'is_active' => true, 'publication_status' => 'published'])
            ->orderBy('id')->get();

        return [
            'versao' => now()->format('Y.m.d-His'),
            'temas' => $questions->pluck('topic.slug')->unique()->values()->all(),
            // Nome e descrição de cada tema viajam no pacote, para o app deixar
            // de ter mapas de temas hardcoded e suportar temas novos.
            'temasDetalhe' => Topic::where('is_active', true)->orderBy('sort_order')->get(['slug', 'name', 'description'])
                ->map(fn (Topic $topic) => ['slug' => $topic->slug, 'nome' => $topic->name, 'descricao' => $topic->description])->all(),
            'regras' => Grading::packageRules(),
            'perguntas' => $questions->map(fn (Question $question) => $question->toPackageArray())->all(),
            'provas' => $exams->map(fn (Exam $exam) => $this->mapExam($exam))->all(),
            // Material de estudo offline. Antes o app descarregava os artigos
            // em runtime, página a página, e guardava-os em Preferences.
            'estudo' => $this->buildStudyPayload(),
        ];
    }

    /**
     * Sinalização, fichas de estudo, artigos por capítulo e glossário.
     */
    public function buildStudyPayload(): array
    {
        /*
         * Os sinais saem agrupados por categoria, pela ordem que o painel lhes
         * deu: na grelha "Todos" o aluno vê-os como no manual, e não
         * intercalados pelo sort_order de cada sinal.
         *
         * A ordem vinha de config/estudo.php e passou a vir da base de dados,
         * onde as categorias são agora editáveis.
         */
        $categorias = SignCategory::raiz()->ativas()->ordenadas()->get();
        $ordemCategorias = $categorias->pluck('sort_order', 'id')->all();

        $signs = Sign::with(['topic', 'category', 'subcategory'])->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->sortBy(fn (Sign $sign) => $ordemCategorias[$sign->sign_category_id] ?? 99, SORT_NUMERIC)
            ->values();

        // Idem para as fichas: a ordem pedagógica é a de config/estudo.php
        // (código, sinalização, condução…) e não a alfabética do slug do grupo.
        $ordemGrupos = array_flip(array_keys(config('estudo.grupos_licoes', [])));

        $lessons = Lesson::with('topic')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('title')->get()
            ->sortBy(fn (Lesson $lesson) => $ordemGrupos[$lesson->group] ?? 99, SORT_NUMERIC)
            ->values();

        $articles = Article::where('is_active', true)
            ->orderBy('chapter_number')->orderBy('sort_order')->orderBy('number')->get();

        return [
            'taxonomia' => [
                // Mantém a forma que o app já lê (slug, nome, descrição,
                // ícone, ordem); só a fonte mudou de configuração para tabela.
                'categoriasSinais' => $categorias->map(fn (SignCategory $c) => $c->toPackageArray())->all(),
                // Novo e opcional: o app ignora-o sem consequência enquanto
                // não souber o que fazer com subcategorias.
                'subcategoriasSinais' => SignCategory::whereNotNull('parent_id')->ativas()->ordenadas()->get()
                    ->map(fn (SignCategory $c) => $c->toPackageArray() + ['categoria' => $c->parent?->slug])->all(),
                'gruposLicoes' => $this->taxonomy('estudo.grupos_licoes'),
            ],
            'sinais' => $signs->map(fn (Sign $sign) => $sign->toPackageArray())->all(),
            'licoes' => $lessons->map(fn (Lesson $lesson) => $lesson->toPackageArray())->all(),
            'capitulos' => $this->chapters($articles),
            'artigos' => $articles->map(fn (Article $article) => $article->toPackageArray())->all(),
            'glossario' => GlossaryTerm::where('is_active', true)
                ->orderBy('sort_order')->orderBy('term')->get()
                ->map(fn (GlossaryTerm $term) => $term->toPackageArray())->all(),
        ];
    }

    /** Rótulos das categorias, para o app não repetir listas no código. */
    private function taxonomy(string $chave): array
    {
        $itens = [];

        foreach (config($chave, []) as $slug => $dados) {
            $itens[] = [
                'slug' => $slug,
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'] ?? null,
                'icone' => $dados['icone'] ?? null,
                'ordem' => $dados['ordem'] ?? 99,
            ];
        }

        usort($itens, fn ($a, $b) => $a['ordem'] <=> $b['ordem']);

        return $itens;
    }

    /**
     * Índice de capítulos do Código.
     *
     * Artigos sem capítulo atribuído caem num grupo "Outras disposições", para
     * a leitura continuar organizada durante a importação do PDF.
     */
    private function chapters(Collection $articles): array
    {
        $capitulos = [];

        foreach ($articles as $article) {
            $numero = $article->chapter_number;
            $chave = $numero ?: 0;

            $capitulos[$chave] ??= [
                'numero' => $numero,
                'titulo' => $article->chapter_title ?: ($numero ? 'Capítulo '.$numero : 'Outras disposições'),
                'artigos' => [],
            ];

            $capitulos[$chave]['artigos'][] = $article->number;
        }

        ksort($capitulos);

        // O grupo sem capítulo fica no fim.
        if (isset($capitulos[0])) {
            $semCapitulo = $capitulos[0];
            unset($capitulos[0]);
            $capitulos[] = $semCapitulo;
        }

        return array_values($capitulos);
    }

    private function mapExam(Exam $exam): array
    {
        $category = $exam->gradingCategory();
        $total = $exam->questions->count();

        return [
            'id' => $exam->id,
            'nome' => $exam->name,
            'categoriasCarta' => $exam->license_categories ?: [$exam->license_category],
            'tipo' => $exam->type,
            'notaPassagem' => $exam->pass_score ?: Grading::passScore($total, $category),
            'percentagemPassagem' => Grading::passPercentage($category),
            'valoresPassagem' => Grading::passValues($category),
            'minutos' => $exam->duration_minutes ?: Grading::durationMinutes($category),
            'bloqueado' => (bool) $exam->is_locked,
            'perguntas' => $exam->questions->map(fn (Question $question) => $question->toPackageArray())->values()->all(),
        ];
    }
}
