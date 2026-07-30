<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_register_and_sync_offline_progress(): void
    {
        $registration = $this->postJson('/api/v1/mobile/register', [
            'name' => 'Aluno CartaPro', 'email' => 'aluno@example.test', 'phone' => '841112223', 'password' => 'segredo123',
        ])->assertCreated()->assertJsonPath('user.email', 'aluno@example.test');
        $token = $registration->json('token');
        $clientId = fake()->uuid();

        $this->withToken($token)->postJson('/api/v1/mobile/sync', [
            'answers' => [['clientId' => $clientId, 'perguntaId' => 'pri-001', 'tema' => 'prioridade', 'acertou' => true, 'data' => now()->timestamp * 1000]],
            'exams' => [], 'revisions' => [], 'readContents' => ['codigo-estrada:30'],
        ])->assertOk()->assertJsonPath('answers.0.clientId', $clientId);

        $this->assertDatabaseHas('mobile_answers', ['client_id' => $clientId, 'correct' => true]);
        $this->withToken($token)->getJson('/api/v1/mobile/snapshot')->assertOk()->assertJsonPath('readContents.0', 'codigo-estrada:30');
    }
}
