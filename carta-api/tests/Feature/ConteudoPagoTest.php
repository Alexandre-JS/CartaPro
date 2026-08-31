<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conteúdo pago.
 *
 * O plano de cada conteúdo é decidido no painel, item a item — não há regra
 * automática por trás. O app limita-se a consumir a decisão: nunca a toma.
 */
class ConteudoPagoTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_ao_abrir_uma_prova_o_app_recebe_os_enunciados_e_nao_a_contagem(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $prova = $this->prova('Exame 10', collect([$this->pergunta($tema->id, 1), $this->pergunta($tema->id, 2)]));

        [, $token] = $this->mobileUser();

        /*
         * O resumo da prova traz `perguntas` com a contagem e o detalhe tem de
         * a substituir pela lista. Enquanto isso era feito com `+`, a união de
         * arrays do PHP mantinha a contagem, a resposta dava 200 com
         * `perguntas: 2`, e o app rebentava a tentar percorrer um número —
         * acabando a dizer que a prova não estava descarregada para uso offline.
         */
        $resposta = $this->withToken($token)->getJson("/api/v1/mobile/exams/{$prova->id}")->assertOk();

        $resposta->assertJsonCount(2, 'perguntas')
            ->assertJsonPath('perguntas.0.enunciado', 'Pergunta 1')
            ->assertJsonPath('perguntas.1.enunciado', 'Pergunta 2');
        $this->assertIsArray($resposta->json('perguntas'));
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

    public function test_the_public_catalog_lists_free_and_locked_exams_without_login(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $livre = $this->prova('Exame Free', collect([$this->pergunta($tema->id, 1)]));
        $fechada = $this->prova('Exame Plus', collect([$this->pergunta($tema->id, 2)]));
        $fechada->update(['is_locked' => true]);

        $lista = $this->getJson('/api/v1/mobile/exams')->assertOk()->json('data');

        $this->assertFalse(collect($lista)->firstWhere('id', $livre->id)['bloqueado']);
        $this->assertTrue(collect($lista)->firstWhere('id', $fechada->id)['bloqueado']);
        $this->getJson("/api/v1/mobile/exams/{$livre->id}")->assertOk()->assertJsonCount(1, 'perguntas');
        $this->getJson("/api/v1/mobile/exams/{$fechada->id}")->assertStatus(402);
    }

    public function test_admin_can_switch_an_exam_between_free_and_paid(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $prova = $this->prova('Exame 01', collect([$this->pergunta($tema->id, 1)]));

        $admin = User::factory()->create(['role' => 'admin']);

        /*
         * Alternar em vez de editar: as provas não têm formulário de edição, e
         * obrigar a apagar e recriar uma prova só para mudar o plano seria
         * absurdo.
         */
        $this->actingAs($admin)->patch(route('admin.exams.plan', $prova))->assertRedirect();
        $this->assertTrue($prova->fresh()->is_locked);

        $this->actingAs($admin)->patch(route('admin.exams.plan', $prova))->assertRedirect();
        $this->assertFalse($prova->fresh()->is_locked);
    }

    public function test_a_private_exam_has_no_plan_to_switch(): void
    {
        $tema = Topic::create(['slug' => 'sinais', 'name' => 'Sinais']);
        $prova = $this->prova('Prova da escola', collect([$this->pergunta($tema->id, 1)]));
        $prova->update(['is_public' => false]);

        $admin = User::factory()->create(['role' => 'admin']);

        // Uma prova privada não chega ao aplicativo: não há plano a definir.
        $this->actingAs($admin)->patch(route('admin.exams.plan', $prova))->assertStatus(422);
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
