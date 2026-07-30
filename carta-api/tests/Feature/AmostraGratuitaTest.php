<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use App\Services\AmostraGratuita;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmostraGratuitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rule_leaves_the_first_n_of_every_group_open(): void
    {
        $temas = collect(['sinais', 'prioridade', 'velocidade'])
            ->map(fn (string $slug) => Topic::create(['slug' => $slug, 'name' => ucfirst($slug)]));

        foreach ($temas as $tema) {
            foreach (range(1, 6) as $ordem) {
                $this->pergunta($tema->id, $ordem);
            }
        }

        app(AmostraGratuita::class)->aplicar(['perguntas' => 2]);

        // Em profundidade: o aluno prova os três temas e bate no cadeado dentro
        // de cada um, em vez de encontrar temas inteiros fechados.
        foreach ($temas as $tema) {
            $doTema = Question::where('topic_id', $tema->id)->orderBy('sort_order')->get();

            $this->assertEquals([false, false, true, true, true, true], $doTema->pluck('is_locked')->all(),
                "Tema {$tema->slug}: deviam ficar livres as duas primeiras.");
        }
    }

    public function test_simulating_changes_nothing(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        foreach (range(1, 5) as $ordem) {
            $this->pergunta($tema->id, $ordem);
        }

        $plano = app(AmostraGratuita::class)->simular(['perguntas' => 2]);

        $this->assertSame(2, $plano['perguntas']['livres']);
        $this->assertSame(3, $plano['perguntas']['bloqueados']);
        // O operador tem de poder ver o efeito antes de se comprometer.
        $this->assertSame(0, Question::where('is_locked', true)->count());
    }

    public function test_the_free_plan_never_receives_a_half_exam(): void
    {
        $payload = ['perguntas' => [], 'provas' => [[
            'id' => 1,
            'nome' => 'Exame 01',
            'notaPassagem' => 27,
            'bloqueado' => false,
            'perguntas' => [
                ['id' => 1, 'bloqueado' => false],
                ['id' => 2, 'bloqueado' => true],
                ['id' => 3, 'bloqueado' => false],
            ],
        ]]];

        $filtrado = app(EntitlementService::class)->filterPackage($payload, paid: false);

        /*
         * Antes entregavam-se as perguntas livres da prova e a nota de passagem
         * continuava calculada sobre o total: o aluno recebia um exame de 2 de
         * 3 perguntas e reprovava sem perceber porquê. Uma prova é inteira ou
         * não é servida.
         */
        $this->assertSame([], $filtrado['provas'][0]['perguntas']);
        $this->assertTrue($filtrado['provas'][0]['bloqueadoPorPlano']);
    }

    public function test_an_exam_can_be_locked_on_its_own(): void
    {
        $payload = ['perguntas' => [], 'provas' => [[
            'id' => 1, 'nome' => 'Exame 03', 'bloqueado' => true,
            'perguntas' => [['id' => 1, 'bloqueado' => false]],
        ]]];

        // Faltava: sem cadeado próprio, uma prova só fechava quando todas as
        // suas perguntas estavam fechadas.
        $filtrado = app(EntitlementService::class)->filterPackage($payload, paid: false);

        $this->assertTrue($filtrado['provas'][0]['bloqueadoPorPlano']);
        $this->assertSame([], $filtrado['provas'][0]['perguntas']);
    }

    public function test_the_paid_plan_receives_everything(): void
    {
        $payload = ['perguntas' => [['id' => 1, 'bloqueado' => true]], 'provas' => [[
            'id' => 1, 'nome' => 'Exame 03', 'bloqueado' => true,
            'perguntas' => [['id' => 1, 'bloqueado' => true]],
        ]]];

        $filtrado = app(EntitlementService::class)->filterPackage($payload, paid: true);

        $this->assertCount(1, $filtrado['provas'][0]['perguntas']);
        $this->assertCount(1, $filtrado['perguntas']);
        $this->assertSame(0, $filtrado['totalBloqueadas']);
    }

    public function test_articles_and_glossary_are_now_lockable(): void
    {
        $estudo = [
            'artigos' => [
                ['numero' => 1, 'titulo' => 'Livre', 'texto' => 'texto livre', 'bloqueado' => false],
                ['numero' => 2, 'titulo' => 'Fechado', 'texto' => 'texto fechado', 'bloqueado' => true],
            ],
            'glossario' => [['slug' => 'berma', 'termo' => 'Berma', 'definicao' => 'a definição', 'bloqueado' => true]],
        ];

        // Eram as duas únicas frentes que seguiam inteiras para o plano
        // gratuito — não por decisão, mas por falta de campo.
        $filtrado = app(EntitlementService::class)->filterStudy($estudo, paid: false);

        $this->assertSame(1, $filtrado['artigosBloqueados']);
        $this->assertSame('texto livre', $filtrado['artigos'][0]['texto']);
        $this->assertArrayNotHasKey('texto', $filtrado['artigos'][1], 'O texto do artigo fechado não pode sair do servidor.');
        $this->assertSame(1, $filtrado['glossarioBloqueado']);
        $this->assertArrayNotHasKey('definicao', $filtrado['glossario'][0]);
    }

    public function test_locked_items_are_shown_but_emptied(): void
    {
        $estudo = [
            'sinais' => [[
                'slug' => 'stop', 'nome' => 'Paragem obrigatória', 'categoria' => 'prioridade',
                'imagem' => 'http://x/stop.svg', 'significado' => 'Parar sempre',
                'descricao' => 'Aplica-se…', 'artigoRef' => 21, 'bloqueado' => true,
            ]],
            'licoes' => [[
                'slug' => 'f1', 'titulo' => 'Ficha 1', 'resumo' => 'resumo',
                'corpo' => 'o corpo todo', 'grupo' => 'codigo', 'minutosLeitura' => 4, 'bloqueado' => true,
            ]],
        ];

        $filtrado = app(EntitlementService::class)->filterStudy($estudo, paid: false);
        $sinal = $filtrado['sinais'][0];
        $ficha = $filtrado['licoes'][0];

        // Aparece na grelha — sem isto o cadeado era invisível e o aluno nunca
        // via o que lhe faltava.
        $this->assertSame('Paragem obrigatória', $sinal['nome']);
        $this->assertSame('http://x/stop.svg', $sinal['imagem']);
        $this->assertTrue($sinal['bloqueado']);

        // Mas o conhecimento não sai do servidor.
        $this->assertArrayNotHasKey('significado', $sinal);
        $this->assertArrayNotHasKey('descricao', $sinal);
        $this->assertArrayNotHasKey('corpo', $ficha);
        $this->assertSame('Ficha 1', $ficha['titulo']);
    }

    public function test_the_paid_plan_keeps_every_field(): void
    {
        $estudo = ['sinais' => [[
            'slug' => 'stop', 'nome' => 'Paragem', 'significado' => 'Parar sempre', 'bloqueado' => true,
        ]]];

        $filtrado = app(EntitlementService::class)->filterStudy($estudo, paid: true);

        $this->assertSame('Parar sempre', $filtrado['sinais'][0]['significado']);
        $this->assertSame(0, $filtrado['sinaisBloqueados']);
    }

    public function test_only_playable_exams_are_left_open(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $perguntas = collect(range(1, 6))->map(fn (int $ordem) => $this->pergunta($tema->id, $ordem));

        // A primeira usa só perguntas que ficam livres; a segunda apanha uma das
        // que vão fechar.
        $jogavel = $this->prova('Exame 01', $perguntas->take(2));
        $inutil = $this->prova('Exame 02', $perguntas->slice(4, 2));

        app(AmostraGratuita::class)->aplicar(['perguntas' => 3, 'exames' => 2]);

        /*
         * Abrir "as duas primeiras" por ordem daria ao aluno uma prova que vê
         * mas não consegue abrir — basta uma pergunta fechada para fechar a
         * prova inteira. Pior do que não a ter.
         */
        $this->assertFalse($jogavel->fresh()->is_locked, 'A prova só com perguntas livres devia abrir.');
        $this->assertTrue($inutil->fresh()->is_locked, 'A prova com uma pergunta fechada não é jogável.');
    }

    public function test_a_locked_exam_cannot_be_opened_by_a_free_account(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $prova = $this->prova('Exame 09', collect([$this->pergunta($tema->id, 1)]));
        $prova->update(['is_locked' => true]);

        [, $token] = $this->mobileUser();

        /*
         * O cadeado da prova era ignorado no endpoint móvel: filtravam-se as
         * perguntas bloqueadas mas nunca se olhava para `is_locked` da prova,
         * pelo que uma prova paga com perguntas livres continuava a ser jogada
         * por quem não pagou.
         */
        $this->withToken($token)->getJson("/api/v1/mobile/exams/{$prova->id}")->assertStatus(402);

        $lista = $this->withToken($token)->getJson('/api/v1/mobile/exams')->assertOk()->json('data');
        $this->assertTrue(collect($lista)->firstWhere('id', $prova->id)['bloqueado'], 'A lista tem de dizer que está fechada.');
    }

    private function prova(string $nome, $perguntas): Exam
    {
        $prova = Exam::create([
            'name' => $nome, 'license_category' => 'ligeiro', 'type' => 'simulado',
            'selection_mode' => 'manual', 'question_count' => $perguntas->count(),
            'duration_minutes' => 30, 'is_active' => true, 'is_public' => true,
            'publication_status' => 'published', 'published_at' => now(),
        ]);

        $prova->questions()->sync($perguntas->values()->mapWithKeys(
            fn ($pergunta, int $i) => [$pergunta->id => ['sort_order' => $i + 1]],
        )->all());

        return $prova;
    }

    public function test_signs_are_sampled_per_category(): void
    {
        foreach (['perigo', 'proibicao'] as $categoria) {
            foreach (range(1, 4) as $ordem) {
                Sign::create([
                    'name' => "{$categoria} {$ordem}", 'slug' => "{$categoria}-{$ordem}",
                    'category' => $categoria, 'meaning' => 'x', 'file_path' => "sinais/{$categoria}-{$ordem}.svg",
                    'sort_order' => $ordem, 'is_active' => true,
                ]);
            }
        }

        app(AmostraGratuita::class)->aplicar(['sinais' => 1]);

        $this->assertSame(2, Sign::where('is_locked', false)->count(), 'Um sinal livre por categoria.');
        $this->assertSame(6, Sign::where('is_locked', true)->count());
    }

    private function pergunta(int $temaId, int $ordem): Question
    {
        return Question::create([
            'topic_id' => $temaId,
            'external_id' => "q-{$temaId}-{$ordem}",
            'categories' => ['ligeiro'],
            'type' => 'texto',
            'statement' => "Pergunta {$ordem}",
            'options' => ['a', 'b', 'c'],
            'correct_index' => 0,
            'explanation' => 'porque sim',
            'sort_order' => $ordem,
            'is_active' => true,
            'status' => 'approved',
        ]);
    }
}
