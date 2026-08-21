<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Exam;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\MobileUser;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Database\Seeder;

/**
 * Volume suficiente para ver a amostra gratuita a funcionar.
 *
 * Com seis perguntas em três temas, qualquer limite razoável deixa tudo livre
 * e não se percebe se o cadeado funciona. Isto cria conteúdo em quantidade
 * suficiente para a regra "as primeiras N de cada grupo" produzir um resultado
 * visível, e uma conta nova em plano gratuito para o testar.
 *
 * Executar com:  php artisan db:seed --class=TesteMonetizacaoSeeder
 *
 * É idempotente — usa `updateOrCreate` em toda a parte, pelo que correr duas
 * vezes não duplica nada.
 */
class TesteMonetizacaoSeeder extends Seeder
{
    /** Conta de teste em plano gratuito. */
    public const EMAIL = 'teste@cartapro.co.mz';

    public const TELEFONE = '+258 84 900 0001';

    public const PASSWORD = 'teste1234';

    public function run(): void
    {
        $temas = $this->temas();

        $this->perguntas($temas);
        $this->sinais();
        $this->fichas();
        $this->artigos();
        $this->glossario();
        $this->provas();
        $this->conta();

        $this->command?->info('Conteúdo de teste criado.');
        $this->command?->line('  Conta gratuita: '.self::EMAIL.' / '.self::PASSWORD.' ('.self::TELEFONE.')');
    }

    /** Os temas do exame do INATRO que o app já usa, mais os que faltavam. */
    private function temas(): array
    {
        $definicoes = [
            'sinais_perigo' => 'Sinais de perigo',
            'prioridade' => 'Regras de prioridade',
            'velocidade' => 'Velocidade e distâncias',
            'ultrapassagem' => 'Ultrapassagem',
            'estacionamento' => 'Paragem e estacionamento',
            'condutor' => 'O condutor e o veículo',
            'primeiros_socorros' => 'Primeiros socorros',
        ];

        $temas = [];

        foreach ($definicoes as $slug => $nome) {
            $temas[$slug] = Topic::updateOrCreate(['slug' => $slug], ['name' => $nome, 'is_active' => true]);
        }

        return $temas;
    }

    /**
     * Doze perguntas por tema.
     *
     * O número não é arbitrário: com uma amostra de 5 por tema, ficam 5 livres
     * e 7 bloqueadas em cada um — dá para ver o cadeado sem ter de percorrer
     * dezenas de perguntas até lá chegar.
     */
    private function perguntas(array $temas): void
    {
        $modelos = [
            'sinais_perigo' => ['Um sinal triangular de bordo vermelho indica:', ['Uma obrigação', 'Um perigo a assinalar', 'Uma proibição', 'Uma indicação'], 1, 'Os sinais de perigo são triangulares com bordo vermelho e avisam o condutor de um risco na via.'],
            'prioridade' => ['Num cruzamento sem sinalização, a prioridade é de quem:', ['Vem da esquerda', 'Vem da direita', 'Circula mais depressa', 'Chegou primeiro'], 1, 'Na ausência de sinalização, cede-se passagem aos veículos que se apresentem pela direita.'],
            'velocidade' => ['Dentro das localidades, a velocidade máxima geral é de:', ['40 km/h', '50 km/h', '60 km/h', '80 km/h'], 1, 'Dentro das localidades a velocidade máxima é 50 km/h, salvo sinalização em contrário.'],
            'ultrapassagem' => ['A ultrapassagem faz-se, em regra, pela:', ['Direita', 'Esquerda', 'Berma', 'Faixa mais livre'], 1, 'A ultrapassagem efectua-se pela esquerda, salvo as excepções previstas no Código.'],
            'estacionamento' => ['É proibido estacionar:', ['A 20 metros de um cruzamento', 'Em cima de uma passadeira', 'Junto a uma escola', 'Em via de sentido único'], 1, 'Estacionar sobre passagens para peões impede o atravessamento seguro e é proibido.'],
            'condutor' => ['Antes de iniciar a marcha, o condutor deve:', ['Buzinar', 'Certificar-se de que o pode fazer sem perigo', 'Acelerar rapidamente', 'Ligar os máximos'], 1, 'Iniciar a marcha só é permitido depois de o condutor se certificar de que não cria perigo nem embaraço.'],
            'primeiros_socorros' => ['Perante um acidente, a primeira acção é:', ['Mover os feridos', 'Sinalizar o local e pedir socorro', 'Fotografar a cena', 'Abandonar o local'], 1, 'Sinalizar evita um segundo acidente; só depois se presta socorro e se aguarda a emergência médica.'],
        ];

        foreach ($temas as $slug => $tema) {
            [$enunciado, $opcoes, $correcta, $explicacao] = $modelos[$slug];

            foreach (range(1, 12) as $numero) {
                Question::updateOrCreate(
                    ['external_id' => sprintf('%s-%03d', $slug, $numero)],
                    [
                        'topic_id' => $tema->id,
                        'categories' => ['ligeiro'],
                        'type' => 'teorico',
                        'statement' => "({$numero}) {$enunciado}",
                        'options' => $opcoes,
                        'correct_index' => $correcta,
                        'explanation' => $explicacao,
                        'sort_order' => $numero,
                        'status' => 'approved',
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * Forma e cor de cada categoria, para os SVG gerados se distinguirem.
     *
     * Sinais de teste sem imagem tornam a grelha impossível de avaliar — e a
     * imagem é metade do que se está a testar num ecrã de sinalização.
     */
    private const DESENHO = [
        'perigo' => ['triangulo', '#e3131c', '#fff'],
        'proibicao' => ['circulo', '#e3131c', '#fff'],
        'obrigacao' => ['circulo', '#0b5fa5', '#0b5fa5'],
        'prioridade' => ['losango', '#f5a623', '#fff'],
        'indicacao' => ['quadrado', '#0b5fa5', '#0b5fa5'],
        'marcas_rodoviarias' => ['quadrado', '#4a4a4a', '#4a4a4a'],
        'semaforos' => ['quadrado', '#1f1f1f', '#1f1f1f'],
        'agentes' => ['circulo', '#0b5fa5', '#0b5fa5'],
        'complementar' => ['quadrado', '#218b38', '#218b38'],
    ];

    /** Oito sinais em cada categoria da taxonomia. */
    private function sinais(): void
    {
        foreach (array_keys(config('estudo.categorias_sinais', [])) as $indice => $categoria) {
            foreach (range(1, 8) as $numero) {
                $slug = "{$categoria}-{$numero}";
                $caminho = "/images/signs/{$slug}.svg";

                $this->gravarSvg($caminho, $categoria, (string) $numero);

                Sign::updateOrCreate(['slug' => $slug], [
                    'name' => ucfirst(str_replace('_', ' ', $categoria))." {$numero}",
                    'category' => $categoria,
                    'meaning' => "Significado do sinal {$numero} da categoria ".str_replace('_', ' ', $categoria).'.',
                    'description' => 'Aplica-se a partir do local onde está colocado e mantém-se até indicação em contrário.',
                    'file_path' => $caminho,
                    'sort_order' => $indice * 100 + $numero,
                    'is_active' => true,
                ]);
            }
        }
    }

    /** Desenha um sinal simples: a forma da categoria com o número dentro. */
    private function gravarSvg(string $caminho, string $categoria, string $rotulo): void
    {
        $destino = public_path(ltrim($caminho, '/'));

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0755, true);
        }

        [$forma, $cor, $corTexto] = self::DESENHO[$categoria] ?? ['circulo', '#4a4a4a', '#fff'];

        $figura = match ($forma) {
            'triangulo' => '<path d="M50 8 L94 86 H6 Z" fill="#fff" stroke="'.$cor.'" stroke-width="9" stroke-linejoin="round"/>',
            'losango' => '<path d="M50 6 L94 50 L50 94 L6 50 Z" fill="#fff" stroke="'.$cor.'" stroke-width="9" stroke-linejoin="round"/>',
            'quadrado' => '<rect x="8" y="8" width="84" height="84" rx="8" fill="'.$cor.'"/>',
            default => '<circle cx="50" cy="50" r="42" fill="#fff" stroke="'.$cor.'" stroke-width="9"/>',
        };

        $y = $forma === 'triangulo' ? 70 : 62;

        file_put_contents($destino, sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">%s'
            .'<text x="50" y="%d" text-anchor="middle" font-family="Arial,sans-serif" font-size="30" font-weight="bold" fill="%s">%s</text></svg>',
            $figura,
            $y,
            $forma === 'quadrado' ? '#fff' : $corTexto,
            $rotulo,
        ));
    }

    /** Seis fichas em cada grupo pedagógico. */
    private function fichas(): void
    {
        foreach (array_keys(config('estudo.grupos_licoes', [])) as $indice => $grupo) {
            foreach (range(1, 6) as $numero) {
                $slug = "{$grupo}-ficha-{$numero}";

                Lesson::updateOrCreate(['slug' => $slug], [
                    'title' => ucfirst(str_replace('_', ' ', $grupo))." — ficha {$numero}",
                    'summary' => 'Resumo da ficha para efeitos de teste da amostra gratuita.',
                    'body' => "Conteúdo da ficha {$numero} do grupo ".str_replace('_', ' ', $grupo).".\n\nSegundo parágrafo com o desenvolvimento do tema.",
                    'group' => $grupo,
                    'license_categories' => ['ligeiro'],
                    'sign_slugs' => [],
                    'article_numbers' => [],
                    'reading_minutes' => 3,
                    'sort_order' => $indice * 100 + $numero,
                    'is_active' => true,
                ]);
            }
        }
    }

    /** Trinta artigos distribuídos por cinco capítulos. */
    private function artigos(): void
    {
        $capitulos = [
            1 => 'Princípios gerais',
            2 => 'Sinalização',
            3 => 'Regras de circulação',
            4 => 'Velocidade',
            5 => 'Condutores e veículos',
        ];

        $numero = 100;

        foreach ($capitulos as $capitulo => $titulo) {
            foreach (range(1, 6) as $ordem) {
                $numero++;

                Article::updateOrCreate(['number' => $numero], [
                    'chapter_number' => $capitulo,
                    'chapter_title' => $titulo,
                    'title' => "Artigo {$numero} — {$titulo}",
                    'text' => "1. Texto do artigo {$numero}, do capítulo {$capitulo}.\n2. Segundo número do mesmo artigo.",
                    'sort_order' => $numero,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function glossario(): void
    {
        $termos = [
            'Berma', 'Cruzamento', 'Entroncamento', 'Faixa de rodagem', 'Localidade',
            'Passagem para peões', 'Paragem', 'Estacionamento', 'Trânsito', 'Ultrapassagem',
            'Via pública', 'Veículo automóvel', 'Peão', 'Prioridade', 'Rotunda',
            'Sinal luminoso', 'Marca rodoviária', 'Portagem', 'Reboque', 'Velocípede',
        ];

        foreach ($termos as $ordem => $termo) {
            GlossaryTerm::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($termo)],
                [
                    'term' => $termo,
                    'definition' => "Definição de \"{$termo}\" para efeitos do Código da Estrada.",
                    'sort_order' => $ordem + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Oito provas, com perguntas mesmo ligadas.
     *
     * Criar a prova não chega: sem `questions()->sync(...)` ela fica sem
     * perguntas nenhumas e o aluno abre um exame vazio. O modo é `manual`
     * de propósito — o `aleatorio` precisa de um blueprint que este seeder
     * não define.
     */
    private function provas(): void
    {
        $banco = Question::where('status', 'approved')->where('is_active', true)
            ->orderBy('topic_id')->orderBy('sort_order')->get();

        if ($banco->isEmpty()) {
            return;
        }

        $porProva = min(20, $banco->count());

        /*
         * As duas primeiras provas são compostas só com as perguntas do início
         * de cada tema — as mesmas que a amostra gratuita costuma deixar
         * livres. Sem isto nenhuma prova consegue ficar aberta: basta uma
         * pergunta bloqueada para fechar a prova inteira, e as provas montadas
         * a partir do banco todo apanham sempre alguma.
         */
        $inicioDeCadaTema = $banco->groupBy('topic_id')->flatMap(fn ($doTema) => $doTema->take(5))->values();

        foreach (range(1, 8) as $numero) {
            $prova = Exam::updateOrCreate(
                ['name' => sprintf('Exame %02d', $numero)],
                [
                    'license_category' => 'ligeiro',
                    'license_categories' => ['ligeiro'],
                    'type' => 'simulado',
                    'selection_mode' => 'manual',
                    'question_count' => $porProva,
                    'duration_minutes' => 30,
                    'is_active' => true,
                    'is_public' => true,
                    'publication_status' => 'published',
                    'published_at' => now(),
                ],
            );

            $escolhidas = $numero <= 2
                ? $inicioDeCadaTema->slice(($numero - 1) * 10)->concat($inicioDeCadaTema)->take($porProva)
                : $banco->slice(($numero - 1) * 5)->concat($banco)->take($porProva);

            $prova->questions()->sync(
                $escolhidas->values()->mapWithKeys(
                    fn (Question $pergunta, int $indice) => [$pergunta->id => ['sort_order' => $indice + 1]],
                )->all(),
            );
        }
    }

    /**
     * Conta de teste, sempre em plano gratuito.
     *
     * Não se cria nenhum `Unlock` para ela de propósito: é essa a conta que
     * serve para ver os cadeados e percorrer o ecrã de desbloqueio.
     */
    private function conta(): void
    {
        MobileUser::updateOrCreate(['email' => self::EMAIL], [
            'name' => 'Aluno de Teste',
            'phone' => self::TELEFONE,
            'password' => self::PASSWORD,
            'license_category' => 'ligeiro',
            'is_active' => true,
        ]);
    }
}
