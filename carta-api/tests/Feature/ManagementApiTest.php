<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\School;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_use_token_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'admin@cartapro.co.mz']);

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'admin');

        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.email', $user->email);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_school_only_sees_its_questions_and_cannot_approve(): void
    {
        [$school, $user, $token] = $this->schoolUser('ESC-A');
        $other = School::create(['name' => 'Outra escola', 'code' => 'ESC-B', 'is_active' => true]);
        $topic = Topic::create(['name' => 'Sinais', 'slug' => 'sinais', 'sort_order' => 0, 'is_active' => true]);
        $mine = $this->question($topic, $school, 'minha-1');
        $otherQuestion = $this->question($topic, $other, 'outra-1');

        $this->withToken($token)->getJson('/api/v1/perguntas')
            ->assertOk()->assertJsonFragment(['external_id' => $mine->external_id])
            ->assertJsonMissing(['external_id' => $otherQuestion->external_id]);

        $this->withToken($token)->postJson('/api/v1/perguntas/'.$mine->id.'/aprovar')->assertForbidden();
        $this->assertSame('review', $mine->fresh()->status);
    }

    public function test_school_question_is_always_sent_to_review(): void
    {
        [$school, $user, $token] = $this->schoolUser('ESC-C');
        $topic = Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'sort_order' => 0, 'is_active' => true]);
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 0, 'is_active' => true]);

        $this->withToken($token)->postJson('/api/v1/perguntas', [
            'topic_id' => $topic->id,
            'external_id' => 'pri-api-1',
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Quem tem prioridade?',
            'options' => ['Veículo A', 'Veículo B'],
            'correct_index' => 0,
            'explanation' => 'Aplica-se a regra de prioridade.',
            'is_active' => true,
            'is_locked' => false,
            'status' => 'approved',
        ])->assertCreated()->assertJsonPath('status', 'review');

        $this->assertDatabaseHas('questions', ['external_id' => 'pri-api-1', 'school_id' => $school->id, 'author_id' => $user->id, 'status' => 'review']);
    }

    public function test_creating_school_in_panel_also_creates_access_account(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.schools.store'), [
            'name' => 'Escola de Condução Central',
            'code' => 'ECC-01',
            'email' => 'central@example.test',
            'contact_person' => 'Ana Manuel',
            'is_active' => '1',
        ])->assertRedirect(route('admin.schools.index'))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'Palavra-passe temporária'));

        $school = School::where('code', 'ECC-01')->firstOrFail();
        $this->assertDatabaseHas('users', ['school_id' => $school->id, 'email' => 'central@example.test', 'role' => 'school', 'is_active' => true]);
    }

    private function schoolUser(string $code): array
    {
        $school = School::create(['name' => 'Escola '.$code, 'code' => $code, 'is_active' => true]);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => 'school']);
        $plain = 'token-'.$code;
        ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        return [$school, $user, $plain];
    }

    private function question(Topic $topic, School $school, string $externalId): Question
    {
        return Question::create([
            'topic_id' => $topic->id,
            'school_id' => $school->id,
            'external_id' => $externalId,
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Pergunta '.$externalId,
            'options' => ['A', 'B'],
            'correct_index' => 0,
            'explanation' => 'Explicação.',
            'is_active' => true,
            'status' => 'review',
        ]);
    }
}
