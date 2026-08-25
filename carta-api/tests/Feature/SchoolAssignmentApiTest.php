<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Classroom;
use App\Models\Instructor;
use App\Models\School;
use App\Models\SchoolAssignment;
use App\Models\SchoolMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_publishes_classroom_task_and_tracks_candidate_completion(): void
    {
        [$school, $admin, $panelToken] = $this->schoolUser('school_admin', 'ESC-A');
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);
        [$candidate, $candidateToken] = $this->mobileUser();
        SchoolMembership::create(['school_id' => $school->id, 'mobile_user_id' => $candidate->id, 'classroom_id' => $classroom->id, 'status' => 'active', 'joined_at' => now()]);

        $created = $this->withToken($panelToken)->postJson("/api/v1/escolas/{$school->id}/tarefas", [
            'classroom_id' => $classroom->id,
            'type' => 'training',
            'title' => 'Treinar prioridade',
            'instructions' => 'Responda a dez perguntas.',
            'due_at' => now()->addWeek()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('status', 'draft');
        $assignmentId = $created->json('id');

        $this->withToken($panelToken)->patchJson("/api/v1/tarefas/{$assignmentId}/publicar")
            ->assertOk()->assertJsonPath('status', 'published')->assertJsonPath('progress_count', 1);
        $candidateTask = $this->withToken($candidateToken)->getJson('/api/v1/school-assignments')
            ->assertOk()->assertJsonPath('data.0.assignment.title', 'Treinar prioridade');
        $progressId = $candidateTask->json('data.0.id');

        $this->withToken($candidateToken)->patchJson("/api/v1/school-assignment-progress/{$progressId}", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('status', 'completed');
        $this->withToken($panelToken)->getJson("/api/v1/tarefas/{$assignmentId}/progresso")
            ->assertOk()->assertJsonPath('data.0.mobile_user.id', $candidate->id)->assertJsonPath('data.0.status', 'completed');
    }

    public function test_task_only_reaches_active_memberships_in_selected_classroom(): void
    {
        [$school, $admin, $panelToken] = $this->schoolUser('school_admin', 'ESC-B');
        $target = Classroom::create(['school_id' => $school->id, 'name' => 'Alvo', 'code' => 'ALVO', 'is_active' => true]);
        $other = Classroom::create(['school_id' => $school->id, 'name' => 'Outra', 'code' => 'OUTRA', 'is_active' => true]);
        [$included] = $this->mobileUser();
        [$excluded] = $this->mobileUser();
        SchoolMembership::create(['school_id' => $school->id, 'mobile_user_id' => $included->id, 'classroom_id' => $target->id, 'status' => 'active']);
        SchoolMembership::create(['school_id' => $school->id, 'mobile_user_id' => $excluded->id, 'classroom_id' => $other->id, 'status' => 'active']);
        $assignment = SchoolAssignment::create(['school_id' => $school->id, 'classroom_id' => $target->id, 'created_by' => $admin->id, 'type' => 'reading', 'title' => 'Leitura', 'status' => 'draft']);

        $this->withToken($panelToken)->patchJson("/api/v1/tarefas/{$assignment->id}/publicar")->assertOk();

        $this->assertDatabaseHas('school_assignment_progress', ['school_assignment_id' => $assignment->id, 'mobile_user_id' => $included->id]);
        $this->assertDatabaseMissing('school_assignment_progress', ['school_assignment_id' => $assignment->id, 'mobile_user_id' => $excluded->id]);
    }

    public function test_instructor_can_assign_own_classroom_but_not_another_classroom(): void
    {
        [$school, $user, $token] = $this->schoolUser('instructor', 'ESC-I');
        $instructor = Instructor::create(['user_id' => $user->id, 'school_id' => $school->id, 'is_active' => true]);
        $mine = Classroom::create(['school_id' => $school->id, 'name' => 'Minha', 'code' => 'MINHA', 'is_active' => true]);
        $other = Classroom::create(['school_id' => $school->id, 'name' => 'Outra', 'code' => 'OUTRA', 'is_active' => true]);
        $instructor->classrooms()->attach($mine->id);

        $base = ['type' => 'revision', 'title' => 'Revisão semanal', 'due_at' => now()->addDay()->toIso8601String()];
        $this->withToken($token)->postJson("/api/v1/escolas/{$school->id}/tarefas", $base + ['classroom_id' => $mine->id])->assertCreated();
        $this->withToken($token)->postJson("/api/v1/escolas/{$school->id}/tarefas", $base + ['classroom_id' => $other->id])->assertForbidden();
    }

    public function test_candidate_loses_open_task_access_after_leaving_school_but_keeps_completed_history(): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-C', 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma', 'code' => 'T', 'is_active' => true]);
        [$candidate, $token] = $this->mobileUser();
        $membership = SchoolMembership::create(['school_id' => $school->id, 'mobile_user_id' => $candidate->id, 'classroom_id' => $classroom->id, 'status' => 'active']);
        $open = SchoolAssignment::create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'type' => 'training', 'title' => 'Aberta', 'status' => 'published', 'published_at' => now()]);
        $done = SchoolAssignment::create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'type' => 'reading', 'title' => 'Concluída', 'status' => 'closed', 'published_at' => now()]);
        $open->progress()->create(['mobile_user_id' => $candidate->id, 'status' => 'assigned']);
        $done->progress()->create(['mobile_user_id' => $candidate->id, 'status' => 'completed', 'completed_at' => now()]);
        $membership->update(['status' => 'left', 'left_at' => now()]);

        $this->withToken($token)->getJson('/api/v1/school-assignments')
            ->assertOk()->assertJsonFragment(['title' => 'Concluída'])->assertJsonMissing(['title' => 'Aberta']);
    }

    private function schoolUser(string $role, string $code): array
    {
        $school = School::create(['name' => 'Escola '.$code, 'code' => $code, 'is_active' => true]);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => $role]);
        $token = 'panel-'.$role.'-'.$code;
        ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        return [$school, $user, $token];
    }
}
