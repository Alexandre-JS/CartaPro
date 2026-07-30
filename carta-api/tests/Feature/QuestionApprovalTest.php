<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_question_in_review(): void
    {
        $admin = User::factory()->create();
        $topic = Topic::create(['name' => 'Sinais', 'slug' => 'sinais', 'is_active' => true]);
        $question = Question::create([
            'topic_id' => $topic->id,
            'external_id' => 'sig-100',
            'categories' => ['ligeiro'],
            'statement' => 'Este sinal indica?',
            'options' => ['Parar', 'Avançar'],
            'correct_index' => 0,
            'explanation' => 'É um sinal de paragem.',
            'is_active' => true,
            'status' => 'review',
        ]);

        $this->actingAs($admin)->get(route('admin.approvals.index'))->assertOk()->assertSee('Este sinal indica?');

        $this->actingAs($admin)->patch(route('admin.approvals.approve', $question))->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_draft_question_is_not_exposed_by_api(): void
    {
        $topic = Topic::create(['name' => 'Sinais', 'slug' => 'sinais', 'is_active' => true]);
        Question::create([
            'topic_id' => $topic->id,
            'external_id' => 'sig-draft',
            'categories' => ['ligeiro'],
            'statement' => 'Rascunho',
            'options' => ['A', 'B'],
            'correct_index' => 0,
            'explanation' => 'Ainda não publicada.',
            'is_active' => true,
            'status' => 'draft',
        ]);

        [, $token] = $this->mobileUser();

        $this->withToken($token)->getJson('/api/v1/questions')->assertOk()->assertJsonCount(0, 'data');
    }
}
