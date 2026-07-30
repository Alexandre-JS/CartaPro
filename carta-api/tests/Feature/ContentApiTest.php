<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): Topic
    {
        $topic = Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'is_active' => true]);

        Question::create([
            'topic_id' => $topic->id,
            'external_id' => 'pri-001',
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Quem tem prioridade?',
            'options' => ['A', 'B'],
            'correct_index' => 0,
            'explanation' => 'A regra de prioridade aplica-se.',
            'is_active' => true,
            'status' => 'approved',
        ]);

        Question::create([
            'topic_id' => $topic->id,
            'external_id' => 'pri-002-paga',
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Pergunta do plano completo',
            'options' => ['A', 'B'],
            'correct_index' => 1,
            'explanation' => 'Explicacao reservada ao plano pago.',
            'is_active' => true,
            'is_locked' => true,
            'status' => 'approved',
        ]);

        return $topic;
    }

    public function test_content_package_matches_mobile_contract(): void
    {
        $this->seedContent();
        [, $token] = $this->mobileUser();

        $this->withToken($token)->getJson('/api/v1/content-package')
            ->assertOk()
            ->assertJsonPath('temas.0', 'prioridade')
            ->assertJsonPath('perguntas.0.id', 'pri-001')
            ->assertJsonPath('perguntas.0.tema', 'prioridade')
            ->assertJsonPath('perguntas.0.correta', 0)
            // As regras de classificação viajam no pacote (fonte única).
            ->assertJsonPath('regras.omissao.percentagemPassagem', 72)
            // O app deixa de precisar de mapas de temas hardcoded.
            ->assertJsonPath('temasDetalhe.0.nome', 'Prioridade');
    }

    public function test_content_endpoints_reject_anonymous_access(): void
    {
        $this->seedContent();

        // Antes qualquer pessoa descarregava o banco inteiro com a resposta
        // correta e a explicação de cada pergunta.
        $this->getJson('/api/v1/content-package')->assertUnauthorized();
        $this->getJson('/api/v1/questions')->assertUnauthorized();
        $this->getJson('/api/v1/questions?include_locked=1')->assertUnauthorized();
        $this->getJson('/api/v1/packages')->assertUnauthorized();
        $this->getJson('/api/v1/articles')->assertUnauthorized();
    }

    public function test_free_plan_never_receives_locked_content(): void
    {
        $this->seedContent();
        [, $token] = $this->mobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        $this->assertSame('gratis', $response->json('plano'));
        $ids = array_column($response->json('perguntas'), 'id');
        $this->assertContains('pri-001', $ids);
        $this->assertNotContains('pri-002-paga', $ids);

        // Nem o enunciado, nem a explicação, nem a resposta escapam.
        $this->assertStringNotContainsString('Explicacao reservada', $response->getContent());
        // Mas o app sabe quanto conteúdo existe por trás do cadeado.
        $this->assertSame(1, $response->json('totalBloqueadas'));
        $this->assertSame(1, $response->json('bloqueadasPorTema.prioridade'));
    }

    public function test_paid_plan_receives_locked_content(): void
    {
        $this->seedContent();
        [, $token] = $this->paidMobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        $this->assertSame('pago', $response->json('plano'));
        $this->assertContains('pri-002-paga', array_column($response->json('perguntas'), 'id'));
        $this->assertSame(0, $response->json('totalBloqueadas'));
    }

    public function test_client_cannot_unlock_content_via_query_parameter(): void
    {
        $this->seedContent();
        [, $token] = $this->mobileUser();

        // `include_locked` era honrado a partir do cliente; agora é ignorado.
        $response = $this->withToken($token)->getJson('/api/v1/questions?include_locked=1')->assertOk();

        $this->assertNotContains('pri-002-paga', array_column($response->json('data'), 'id'));
    }
}
