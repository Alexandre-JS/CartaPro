<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Exam;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Topic;
use App\Models\User;
use App\Support\BancoPerguntas;
use App\Support\Grading;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa o banco de exames do INATRO de uma só vez.
 *
 * São 500 perguntas em 20 provas: inseri-las à mão no painel seriam semanas de
 * trabalho e uma garantia de erros de transcrição. O ficheiro de trabalho do
 * instrutor já traz tudo o que a base de dados precisa — enunciado, alíneas,
 * resposta certa, tema e artigo de suporte — e é essa a fonte.
 *
 * O que a importação cria, além das perguntas:
 *
 *  - os **temas** que faltarem (o banco usa vinte; a base trazia três de demo);
 *  - os **sinais** referidos pelas perguntas, sem imagem, para o catálogo ficar
 *    com a lista do que há a ilustrar — a imagem é anexada depois no painel;
 *  - as **provas** de 25 perguntas, em rascunho. Publicar é um acto deliberado
 *    de quem revê o conteúdo, não um efeito secundário de uma importação.
 *
 * Correr duas vezes não duplica nada: as perguntas já importadas são saltadas,
 * salvo com `--forcar`, que as reescreve a partir do ficheiro. O que foi
 * corrigido à mão no painel sobrevive a uma reimportação.
 */
class ImportarBancoPerguntas extends Command
{
    protected $signature = 'inatro:importar
        {ficheiro=INATRO_Banco_Perguntas_com_Respostas.md : Caminho do banco em Markdown}
        {--estado=approved : Estado editorial das perguntas criadas (draft, review, approved)}
        {--sem-sinais : Não cria os sinais referidos pelas perguntas}
        {--sem-provas : Não cria as provas de 25 perguntas}
        {--forcar : Reescreve as perguntas já importadas}
        {--dry-run : Lê o ficheiro e mostra o resumo sem gravar nada}';

    protected $description = 'Importa o banco de exames teóricos do INATRO (perguntas, temas, sinais e provas)';

    /**
     * Temas do banco e o respectivo slug.
     *
     * O mapa é explícito e não derivado do nome por três razões: `velocidade`,
     * `sinais_perigo` e `prioridade` já existiam na base e têm de ser
     * reaproveitados em vez de duplicados; os slugs gerados a partir de nomes
     * longos ficariam ilegíveis (`sinalizacao-luminosa-agentes-e-sinalizacao-
     * temporaria`); e o app e os pacotes publicados referem os temas por slug,
     * pelo que estes passam a ser contrato — mudá-los parte instalações.
     */
    private const TEMAS = [
        'Sinais de perigo' => 'sinais_perigo',
        'Sinais de proibição' => 'sinais_proibicao',
        'Sinais de obrigação' => 'sinais_obrigacao',
        'Sinais de cedência de passagem e combinados' => 'sinais_cedencia',
        'Sinais de indicação e informação' => 'sinais_indicacao',
        'Sinalização horizontal (marcas no pavimento)' => 'sinalizacao_horizontal',
        'Sinalização luminosa, agentes e sinalização temporária' => 'sinalizacao_luminosa',
        'Velocidade' => 'velocidade',
        'Prioridade e cedência de passagem' => 'prioridade',
        'Manobras' => 'manobras',
        'Paragem e estacionamento' => 'paragem_estacionamento',
        'Iluminação' => 'iluminacao',
        'Trânsito de peões' => 'transito_peoes',
        'Habilitação legal para conduzir' => 'habilitacao_conduzir',
        'Veículos, matrícula, inspecção e poluição' => 'veiculos_matricula',
        'Transporte de passageiros, carga e segurança' => 'transporte_carga',
        'Álcool, estupefacientes e aparelhos proibidos' => 'alcool_estupefacientes',
        'Acidentes, avarias e socorro' => 'acidentes_socorro',
        'Auto-estradas, vias reservadas e passagens de nível' => 'autoestradas',
        'Fiscalização e contravenções' => 'fiscalizacao',
    ];

    /** Categoria de sinais a que cada tema corresponde. */
    private const CATEGORIA_POR_TEMA = [
        'sinais_perigo' => 'perigo',
        'sinais_proibicao' => 'proibicao',
        'sinais_obrigacao' => 'obrigacao',
        'sinais_cedencia' => 'prioridade',
        'sinais_indicacao' => 'indicacao',
        'sinalizacao_horizontal' => 'marcas_rodoviarias',
        'sinalizacao_luminosa' => 'semaforos',
    ];

    /**
     * Categoria deduzida da letra do código, para os sinais citados por
     * perguntas de temas que não são de sinalização (por exemplo o D14 numa
     * pergunta de velocidade).
     */
    private const CATEGORIA_POR_LETRA = [
        'D' => 'perigo',
        'E' => 'proibicao',
        'F' => 'obrigacao',
        'M' => 'prioridade',
        'N' => 'prioridade',
        'G' => 'indicacao',
        'H' => 'indicacao',
        'I' => 'indicacao',
        'J' => 'indicacao',
        'O' => 'marcas_rodoviarias',
        'P' => 'marcas_rodoviarias',
        'Q' => 'marcas_rodoviarias',
        'R' => 'marcas_rodoviarias',
    ];

    public function handle(): int
    {
        $caminho = $this->caminho((string) $this->argument('ficheiro'));

        if ($caminho === null) {
            return self::FAILURE;
        }

        $estado = (string) $this->option('estado');

        if (! in_array($estado, ['draft', 'review', 'approved'], true)) {
            $this->error("Estado inválido: {$estado}. Use draft, review ou approved.");

            return self::FAILURE;
        }

        $banco = BancoPerguntas::deFicheiro($caminho);

        if ($banco->erros() !== []) {
            $this->error('O ficheiro tem '.count($banco->erros()).' problema(s):');
            foreach ($banco->erros() as $erro) {
                $this->line('  '.$erro);
            }

            return self::FAILURE;
        }

        $perguntas = $banco->perguntas();
        $this->info(sprintf('%d perguntas em %d exames lidas de %s.', count($perguntas), count($banco->exames()), basename($caminho)));

        if ($this->option('dry-run')) {
            $this->resumoSeco($perguntas);

            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->importar($perguntas, $estado));

        return self::SUCCESS;
    }

    /** @param  list<array<string, mixed>>  $perguntas */
    private function importar(array $perguntas, string $estado): void
    {
        $autor = User::where('email', 'admin@prontovia.co.mz')->value('id') ?? User::orderBy('id')->value('id');
        $categorias = LicenseCategory::where('is_active', true)->orderBy('sort_order')->pluck('slug')->all();

        if ($categorias === []) {
            $categorias = ['ligeiro'];
        }

        $temas = $this->garantirTemas($perguntas);
        $sinais = $this->option('sem-sinais') ? collect() : $this->garantirSinais($perguntas, $temas);
        $artigos = Article::pluck('id', 'number');

        $criadas = 0;
        $atualizadas = 0;
        $saltadas = 0;
        $porExame = [];

        foreach ($perguntas as $pergunta) {
            $externo = sprintf('inatro-e%02d-q%02d', $pergunta['exame'], $pergunta['numero']);
            $existente = Question::where('external_id', $externo)->first();

            if ($existente && ! $this->option('forcar')) {
                $porExame[$pergunta['exame']][] = $existente->id;
                $saltadas++;

                continue;
            }

            $tema = $temas->get(self::TEMAS[$pergunta['tema']] ?? $this->slugTema($pergunta['tema']));
            $sinal = $pergunta['sinal'] ? $sinais->get($pergunta['sinal']['codigos'][0] ?? null) : null;
            $artigo = $this->artigoDoCodigoDaEstrada($pergunta['referencia']);

            $dados = [
                'topic_id' => $tema->id,
                'author_id' => $autor,
                'type' => 'teorico',
                'categories' => $categorias,
                'statement' => $pergunta['enunciado'],
                'image' => $sinal?->file_path ?: null,
                'sign_id' => $sinal?->id,
                'options' => $pergunta['opcoes'],
                'correct_index' => $pergunta['correta'],
                'explanation' => $this->explicacao($pergunta),
                'article_ref' => $artigo,
                'article_id' => $artigo ? $artigos->get($artigo) : null,
                'is_locked' => false,
                'is_active' => true,
                'sort_order' => ($pergunta['exame'] - 1) * 25 + $pergunta['numero'],
                'status' => $estado,
                'reviewed_by' => $estado === 'approved' ? $autor : null,
                'reviewed_at' => $estado === 'approved' ? now() : null,
            ];

            if ($existente) {
                $existente->update($dados);
                $atualizadas++;
            } else {
                $existente = Question::create($dados + ['external_id' => $externo]);
                $criadas++;
            }

            $porExame[$pergunta['exame']][] = $existente->id;
        }

        $this->info("Perguntas: {$criadas} criadas, {$atualizadas} actualizadas, {$saltadas} já existentes.");

        if (! $this->option('sem-provas')) {
            $this->garantirProvas($porExame, $autor, $categorias);
        }
    }

    /**
     * Cria os temas em falta e devolve-os por slug.
     *
     * @param  list<array<string, mixed>>  $perguntas
     * @return Collection<string, Topic>
     */
    private function garantirTemas(array $perguntas)
    {
        $ordem = array_flip(array_values(self::TEMAS));
        $usados = array_values(array_unique(array_column($perguntas, 'tema')));

        foreach ($usados as $nome) {
            $slug = self::TEMAS[$nome] ?? $this->slugTema($nome);

            if (! isset(self::TEMAS[$nome])) {
                $this->warn("Tema fora do mapa conhecido: «{$nome}» → {$slug}.");
            }

            Topic::updateOrCreate(['slug' => $slug], [
                'name' => $nome,
                'sort_order' => ($ordem[$slug] ?? 98) + 1,
                'is_active' => true,
            ]);
        }

        return Topic::whereIn('slug', array_map(fn ($n) => self::TEMAS[$n] ?? $this->slugTema($n), $usados))
            ->get()->keyBy('slug');
    }

    /**
     * Regista os sinais citados pelas perguntas, sem imagem.
     *
     * O banco refere os sinais por código oficial e o catálogo estava
     * praticamente vazio: sem estes registos as perguntas de sinalização
     * chegariam ao aluno sem nada para ver e ninguém saberia o que faltava
     * ilustrar. Com eles, o painel passa a ter a lista exacta do trabalho por
     * fazer, e cada imagem carregada aparece de imediato nas perguntas que a
     * referem — o `file_path` vazio é o que marca o sinal como por ilustrar.
     *
     * @param  list<array<string, mixed>>  $perguntas
     * @param  Collection<string, Topic>  $temas
     * @return Collection<string, Sign>
     */
    private function garantirSinais(array $perguntas, $temas)
    {
        $categorias = SignCategory::whereNull('parent_id')->pluck('id', 'slug');
        $existentes = Sign::pluck('id', 'slug');
        $novos = 0;
        $porCodigo = collect();

        foreach ($perguntas as $pergunta) {
            if (! $pergunta['sinal']) {
                continue;
            }

            $slugTema = self::TEMAS[$pergunta['tema']] ?? $this->slugTema($pergunta['tema']);
            $codigos = $pergunta['sinal']['codigos'];
            $nomes = $pergunta['sinal']['nomes'];

            foreach ($codigos as $indice => $codigo) {
                // Quando os nomes vêm em igual número que os códigos, cada sinal
                // fica com o seu; caso contrário partilham a designação do grupo.
                $nome = count($nomes) === count($codigos)
                    ? ($nomes[$indice] ?? '')
                    : implode(' / ', $nomes);
                $nome = $nome !== '' ? $nome : $pergunta['tema'];
                $slug = Str::slug($codigo.'-'.$nome);

                if ($porCodigo->has($codigo)) {
                    continue;
                }

                $categoria = self::CATEGORIA_POR_TEMA[$slugTema]
                    ?? self::CATEGORIA_POR_LETRA[substr($codigo, 0, 1)]
                    ?? null;

                $sinal = Sign::updateOrCreate(['slug' => $slug], [
                    // O código entra no nome porque é assim que o instrutor
                    // procura o sinal no painel — e não há coluna própria.
                    'name' => "{$codigo} — {$nome}",
                    'sign_category_id' => $categoria ? $categorias->get($categoria) : null,
                    'topic_id' => $temas->get($slugTema)?->id,
                    'meaning' => $pergunta['sinal']['linha'],
                    'file_path' => Sign::where('slug', $slug)->value('file_path') ?: '',
                    'is_active' => true,
                ]);

                $novos += $existentes->has($slug) ? 0 : 1;
                $porCodigo->put($codigo, $sinal);
            }
        }

        $porIlustrar = $porCodigo->filter(fn (Sign $sinal) => blank($sinal->file_path))->count();
        $this->info("Sinais: {$novos} criados, {$porCodigo->count()} referidos ao todo, {$porIlustrar} ainda sem imagem.");

        return $porCodigo;
    }

    /**
     * Monta as 20 provas, cada uma com as suas 25 perguntas, em rascunho.
     *
     * @param  array<int, list<int>>  $porExame
     * @param  list<string>  $categorias
     */
    private function garantirProvas(array $porExame, ?int $autor, array $categorias): void
    {
        ksort($porExame);
        $criadas = 0;

        foreach ($porExame as $numero => $perguntas) {
            $nome = sprintf('INATRO — Exame n.º %02d', $numero);
            $existente = Exam::whereNull('school_id')->where('name', $nome)->exists();
            $total = count($perguntas);

            $prova = Exam::updateOrCreate(['school_id' => null, 'name' => $nome], [
                'created_by' => $autor,
                'license_category' => $categorias[0],
                'license_categories' => $categorias,
                'type' => 'teorico',
                'selection_mode' => 'manual',
                'topic_ids' => Question::whereIn('id', $perguntas)->distinct()->pluck('topic_id')->all(),
                'question_count' => $total,
                'pass_score' => Grading::passScore($total, $categorias[0]),
                'duration_minutes' => Grading::durationMinutes($categorias[0]),
                'is_active' => true,
            ]);

            $prova->questions()->sync(collect($perguntas)
                ->mapWithKeys(fn (int $id, int $i) => [$id => ['sort_order' => $i + 1]])
                ->all());

            $criadas += $existente ? 0 : 1;
        }

        $this->info(sprintf('Provas: %d criadas, %d no total (em rascunho — publique-as no painel).', $criadas, count($porExame)));
    }

    /**
     * Número do artigo do Código da Estrada referido no rodapé.
     *
     * Só os artigos do CE são ligados. Os do Regulamento de Sinais têm a mesma
     * numeração e a tabela `articles` tem o número como chave única: ligar
     * «Art. 27 RST» apontaria a pergunta para o artigo 27 do Código, que trata
     * de outra coisa. A referência completa fica na explicação, que é onde o
     * aluno a lê.
     */
    private function artigoDoCodigoDaEstrada(?string $referencia): ?int
    {
        foreach (explode('/', (string) $referencia) as $troco) {
            if (str_contains($troco, 'CE') && preg_match('/(\d+)/', $troco, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $pergunta */
    private function explicacao(array $pergunta): string
    {
        $certa = $pergunta['opcoes'][$pergunta['correta']];

        return "Resposta correcta: {$certa}.\n\nBase legal: {$pergunta['referencia']} — tema «{$pergunta['tema']}».";
    }

    private function slugTema(string $nome): string
    {
        return str_replace('-', '_', Str::slug($nome));
    }

    /** @param  list<array<string, mixed>>  $perguntas */
    private function resumoSeco(array $perguntas): void
    {
        $porTema = [];
        foreach ($perguntas as $pergunta) {
            $porTema[$pergunta['tema']] = ($porTema[$pergunta['tema']] ?? 0) + 1;
        }
        arsort($porTema);

        $this->table(['Tema', 'Slug', 'Perguntas'], collect($porTema)->map(fn ($total, $nome) => [
            $nome,
            self::TEMAS[$nome] ?? $this->slugTema($nome).' (novo)',
            $total,
        ])->values()->all());

        $sinais = collect($perguntas)->pluck('sinal')->filter()->pluck('codigos')->flatten()->unique();
        $this->info("Sinais referidos: {$sinais->count()} códigos distintos.");
        $this->comment('Simulação: nada foi gravado.');
    }

    /** Aceita caminho absoluto, relativo à raiz do projecto ou ao directório actual. */
    private function caminho(string $ficheiro): ?string
    {
        foreach ([$ficheiro, base_path($ficheiro), getcwd().'/'.$ficheiro] as $tentativa) {
            if (is_file($tentativa)) {
                return $tentativa;
            }
        }

        $this->error("Ficheiro não encontrado: {$ficheiro}");

        return null;
    }
}
