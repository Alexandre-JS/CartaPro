<?php

namespace Tests\Feature;

use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningCoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_deduplicated_learning_events_mastery_readiness_and_recommendations(): void
    {
        Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'sort_order' => 1, 'is_active' => true]);
        [$user, $token] = $this->mobileUser();
        $now = now();
        $answers = collect(range(1, 5))->map(fn (int $number) => [
            'clientId' => (string) Str::uuid(),
            'perguntaId' => 'prioridade-'.$number,
            'tema' => 'prioridade',
            'acertou' => $number !== 5,
            'data' => $now->copy()->subMinutes(6 - $number)->getTimestampMs(),
            'escolhida' => 0,
            'duracaoMs' => 12000,
            'origem' => 'treino',
        ])->all();
        $examId = (string) Str::uuid();
        $payload = [
            'answers' => $answers,
            'exams' => [[
                'clientId' => $examId,
                'numero' => 1,
                'total' => 10,
                'acertos' => 8,
                'tempoSegundos' => 600,
                'data' => $now->getTimestampMs(),
            ]],
            'revisions' => [[
                'perguntaId' => 'prioridade-5',
                'tema' => 'prioridade',
                'agendadaPara' => $now->copy()->subMinute()->getTimestampMs(),
                'intervaloDias' => 1,
            ]],
            'readContents' => ['licao:prioridade'],
        ];

        $this->withToken($token)->postJson('/api/v1/mobile/sync', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/v1/mobile/sync', $payload)->assertOk();

        $this->assertDatabaseCount('learning_events', 7);
        $this->assertDatabaseHas('learning_events', ['mobile_user_id' => $user->id, 'type' => 'simulation_completed', 'deduplication_key' => $examId]);
        $this->assertDatabaseHas('topic_masteries', ['mobile_user_id' => $user->id, 'score' => 73, 'answers_count' => 5, 'correct_answers' => 4]);

        $this->withToken($token)->getJson('/api/v1/readiness')
            ->assertOk()->assertJsonPath('score', 75)->assertJsonPath('level', 'progressing')
            ->assertJsonPath('breakdown.prioridade.score', 73);
        $this->withToken($token)->getJson('/api/v1/recommendations')
            ->assertOk()
            ->assertJsonFragment(['type' => 'review_due'])
            ->assertJsonFragment(['type' => 'practice_topic']);
    }

    public function test_new_candidate_receives_a_starting_recommendation_without_fabricated_readiness(): void
    {
        [$user, $token] = $this->mobileUser();

        $this->withToken($token)->getJson('/api/v1/learning/profile')
            ->assertOk()->assertJsonCount(0, 'masteries');
        $this->withToken($token)->getJson('/api/v1/readiness')
            ->assertOk()->assertJsonPath('score', 0)->assertJsonPath('level', 'not_started');
        $this->withToken($token)->getJson('/api/v1/recommendations')
            ->assertOk()->assertJsonPath('data.0.type', 'start_practice');
    }

    public function test_learning_data_is_private_to_the_authenticated_candidate(): void
    {
        Topic::create(['name' => 'Sinais', 'slug' => 'sinais', 'sort_order' => 1, 'is_active' => true]);
        [$first, $firstToken] = $this->mobileUser();
        [$second, $secondToken] = $this->mobileUser();
        $clientId = (string) Str::uuid();

        $this->withToken($firstToken)->postJson('/api/v1/mobile/sync', [
            'answers' => [[
                'clientId' => $clientId,
                'perguntaId' => 'sinal-1',
                'tema' => 'sinais',
                'acertou' => true,
                'data' => now()->getTimestampMs(),
            ]],
        ])->assertOk();

        $this->withToken($firstToken)->getJson('/api/v1/learning/events')
            ->assertOk()->assertJsonPath('data.0.deduplication_key', $clientId);
        $this->withToken($secondToken)->getJson('/api/v1/learning/events')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('learning_profiles', ['mobile_user_id' => $first->id]);
        $this->assertDatabaseMissing('learning_profiles', ['mobile_user_id' => $second->id]);
    }
}
