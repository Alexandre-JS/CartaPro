<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Classroom;
use App\Models\MobileAnswer;
use App\Models\School;
use App\Models\SchoolMembership;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolMembershipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_invites_an_existing_candidate_with_a_consistent_student_and_classroom(): void
    {
        [$school, $operator, $panelToken] = $this->schoolOperator('ESC-A');
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);
        $student = Student::create(['classroom_id' => $classroom->id, 'name' => 'Ana', 'identifier' => 'A-1', 'is_active' => true]);
        [$candidate] = $this->mobileUser();

        $this->withToken($panelToken)->postJson("/api/v1/escolas/{$school->id}/vinculos", [
            'mobile_user_id' => $candidate->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
        ])->assertCreated()
            ->assertJsonPath('status', 'invited')
            ->assertJsonPath('mobile_user.id', $candidate->id)
            ->assertJsonPath('classroom.id', $classroom->id);

        $this->assertDatabaseHas('school_memberships', [
            'school_id' => $school->id,
            'mobile_user_id' => $candidate->id,
            'student_id' => $student->id,
            'status' => 'invited',
        ]);
    }

    public function test_school_cannot_invite_with_entities_from_another_school(): void
    {
        [$school, $operator, $panelToken] = $this->schoolOperator('ESC-A');
        [$other] = $this->schoolOperator('ESC-B');
        $otherClass = Classroom::create(['school_id' => $other->id, 'name' => 'Outra', 'code' => 'B', 'is_active' => true]);
        [$candidate] = $this->mobileUser();

        $this->withToken($panelToken)->postJson("/api/v1/escolas/{$school->id}/vinculos", [
            'mobile_user_id' => $candidate->id,
            'classroom_id' => $otherClass->id,
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'A turma não pertence a esta escola.']);

        $this->assertDatabaseCount('school_memberships', 0);
    }

    public function test_only_candidate_can_accept_and_accepting_a_new_school_closes_the_previous_membership(): void
    {
        $first = School::create(['name' => 'Primeira', 'code' => 'ESC-1', 'is_active' => true]);
        $second = School::create(['name' => 'Segunda', 'code' => 'ESC-2', 'is_active' => true]);
        [$candidate, $candidateToken] = $this->mobileUser();
        [$other, $otherToken] = $this->mobileUser();

        $old = SchoolMembership::create([
            'school_id' => $first->id,
            'mobile_user_id' => $candidate->id,
            'status' => 'active',
            'joined_at' => now()->subMonth(),
        ]);
        $invitation = SchoolMembership::create([
            'school_id' => $second->id,
            'mobile_user_id' => $candidate->id,
            'status' => 'invited',
        ]);
        MobileAnswer::create([
            'mobile_user_id' => $candidate->id,
            'client_id' => (string) Str::uuid(),
            'question_external_id' => 'q-1',
            'topic' => 'prioridade',
            'correct' => true,
            'answered_at' => now(),
        ]);

        $this->withToken($otherToken)->patchJson("/api/v1/school-memberships/{$invitation->id}/accept")->assertNotFound();
        $this->withToken($candidateToken)->patchJson("/api/v1/school-memberships/{$invitation->id}/accept")
            ->assertOk()->assertJsonPath('status', 'active')->assertJsonPath('school.id', $second->id);

        $this->assertSame('left', $old->fresh()->status);
        $this->assertNotNull($old->fresh()->left_at);
        $this->assertDatabaseCount('mobile_answers', 1);
    }

    public function test_candidate_can_list_and_leave_own_membership_without_losing_learning_data(): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC', 'is_active' => true]);
        [$candidate, $token] = $this->mobileUser();
        $membership = SchoolMembership::create([
            'school_id' => $school->id,
            'mobile_user_id' => $candidate->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        MobileAnswer::create([
            'mobile_user_id' => $candidate->id,
            'client_id' => (string) Str::uuid(),
            'question_external_id' => 'q-2',
            'topic' => 'sinais',
            'correct' => false,
            'answered_at' => now(),
        ]);

        $this->withToken($token)->getJson('/api/v1/school-memberships')
            ->assertOk()->assertJsonPath('data.0.school.id', $school->id);
        $this->withToken($token)->patchJson("/api/v1/school-memberships/{$membership->id}/leave")
            ->assertOk()->assertJsonPath('status', 'left');

        $this->assertDatabaseCount('mobile_answers', 1);
        $this->assertNotNull($membership->fresh()->left_at);
    }

    public function test_school_cannot_activate_an_invitation_without_candidate_consent(): void
    {
        [$school, $operator, $panelToken] = $this->schoolOperator('ESC-A');
        [$candidate] = $this->mobileUser();
        $membership = SchoolMembership::create([
            'school_id' => $school->id,
            'mobile_user_id' => $candidate->id,
            'status' => 'invited',
        ]);

        $this->withToken($panelToken)->patchJson("/api/v1/school-memberships/{$membership->id}/status", [
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'Transição de estado inválida.']);

        $this->assertSame('invited', $membership->fresh()->status);
    }

    public function test_school_operator_cannot_access_another_schools_memberships(): void
    {
        [$school, $operator, $panelToken] = $this->schoolOperator('ESC-A');
        [$other] = $this->schoolOperator('ESC-B');

        $this->withToken($panelToken)->getJson("/api/v1/escolas/{$other->id}/vinculos")->assertForbidden();
    }

    private function schoolOperator(string $code): array
    {
        $school = School::create(['name' => 'Escola '.$code, 'code' => $code, 'is_active' => true]);
        $operator = User::factory()->create(['school_id' => $school->id, 'role' => 'school']);
        $token = 'panel-'.$code;
        ApiToken::create(['user_id' => $operator->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        return [$school, $operator, $token];
    }
}
