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
            'artigos' => [['numero' => 1, 'bloqueado' => false], ['numero' => 2, 'bloqueado' => true]],
            'glossario' => [['termo' => 'a', 'bloqueado' => true]],
        ];

        // Eram as duas únicas frentes que seguiam inteiras para o plano
        // gratuito — não por decisão, mas por falta de campo.
        $filtrado = app(EntitlementService::class)->filterStudy($estudo, paid: false);

        $this->assertCount(1, $filtrado['artigos']);
        $this->assertSame(1, $filtrado['artigosBloqueados']);
        $this->assertSame([], $filtrado['glossario']);
        $this->assertSame(1, $filtrado['glossarioBloqueado']);
    }

    public function test_exams_are_locked_as_a_whole_group(): void
    {
        foreach (range(1, 4) as $numero) {
            Exam::create([
                'name' => "Exame 0{$numero}", 'license_category' => 'ligeiro', 'type' => 'simulado',
                'question_count' => 30, 'duration_minutes' => 30, 'is_active' => true,
            ]);
        }

        app(AmostraGratuita::class)->aplicar(['exames' => 2]);

        $this->assertSame(2, Exam::where('is_locked', false)->count());
        $this->assertSame(2, Exam::where('is_locked', true)->count());
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
