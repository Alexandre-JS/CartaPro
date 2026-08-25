<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Classroom;
use App\Models\Instructor;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_administrator_creates_instructor_and_assigns_own_classroom(): void
    {
        [$school, $admin, $token] = $this->panelUser('school_admin', 'ESC-A');
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);

        $created = $this->withToken($token)->postJson("/api/v1/escolas/{$school->id}/instrutores", [
            'name' => 'Instrutora Ana',
            'email' => 'ana@example.test',
            'password' => 'segredo123',
            'license_number' => 'INST-01',
            'bio' => 'Instrutora de teoria.',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('user.role', 'instructor')
            ->assertJsonPath('school.id', $school->id);

        $instructor = Instructor::findOrFail($created->json('id'));
        $this->withToken($token)->postJson("/api/v1/instrutores/{$instructor->id}/turmas/{$classroom->id}")
            ->assertOk()->assertJsonPath('classrooms.0.id', $classroom->id);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.test', 'role' => 'instructor', 'school_id' => $school->id]);
        $this->assertDatabaseHas('classroom_instructor', ['instructor_id' => $instructor->id, 'classroom_id' => $classroom->id]);
    }

    public function test_school_cannot_manage_instructor_or_classroom_from_another_school(): void
    {
        [$school, $admin, $token] = $this->panelUser('school_admin', 'ESC-A');
        [$other] = $this->panelUser('school_admin', 'ESC-B');
        $otherUser = User::factory()->create(['school_id' => $other->id, 'role' => 'instructor']);
        $instructor = Instructor::create(['user_id' => $otherUser->id, 'school_id' => $other->id, 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);

        $this->withToken($token)->putJson("/api/v1/instrutores/{$instructor->id}", [
            'name' => 'Alterado',
            'email' => 'alterado@example.test',
            'is_active' => true,
        ])->assertForbidden();
        $this->withToken($token)->postJson("/api/v1/instrutores/{$instructor->id}/turmas/{$classroom->id}")->assertForbidden();
    }

    public function test_instructor_receives_role_permissions_but_cannot_manage_school_structure(): void
    {
        [$school, $instructor, $token] = $this->panelUser('instructor', 'ESC-I');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'instructor')
            ->assertJsonPath('user.roleLabel', 'Instrutor')
            ->assertJsonFragment(['exam.create'])
            ->assertJsonFragment(['analytics.view'])
            ->assertJsonMissing(['instructor.manage']);

        $this->withToken($token)->getJson('/api/v1/provas')->assertOk();
        $this->withToken($token)->postJson("/api/v1/escolas/{$school->id}/turmas", [
            'name' => 'Não permitida',
            'code' => 'NEGADA',
        ])->assertForbidden();
        $this->withToken($token)->getJson("/api/v1/escolas/{$school->id}/instrutores")->assertForbidden();
    }

    public function test_instructor_only_lists_classrooms_assigned_to_them(): void
    {
        [$school, $user, $token] = $this->panelUser('instructor', 'ESC-T');
        $instructor = Instructor::create(['user_id' => $user->id, 'school_id' => $school->id, 'is_active' => true]);
        $assigned = Classroom::create(['school_id' => $school->id, 'name' => 'Turma atribuída', 'code' => 'SIM', 'is_active' => true]);
        $other = Classroom::create(['school_id' => $school->id, 'name' => 'Turma privada', 'code' => 'NAO', 'is_active' => true]);
        $instructor->classrooms()->attach($assigned->id);

        $this->withToken($token)->getJson("/api/v1/escolas/{$school->id}/turmas")
            ->assertOk()
            ->assertJsonFragment(['id' => $assigned->id, 'name' => 'Turma atribuída'])
            ->assertJsonMissing(['id' => $other->id, 'name' => 'Turma privada']);
    }

    public function test_platform_admin_and_legacy_roles_remain_compatible(): void
    {
        $platform = User::factory()->create(['role' => 'platform_admin']);
        $legacyAdmin = User::factory()->create(['role' => 'admin']);
        [$school, $legacySchool] = $this->panelUser('school', 'ESC-L');

        $this->assertTrue($platform->hasPermission('instructor.manage'));
        $this->assertTrue($legacyAdmin->hasPermission('question.review'));
        $this->assertTrue($legacySchool->hasPermission('classroom.manage'));
        $this->assertTrue($legacySchool->isSchool());
    }

    public function test_direct_permission_can_extend_a_restricted_role(): void
    {
        [$school, $author] = $this->panelUser('content_author', 'ESC-C', false);
        $permissionId = \App\Models\Permission::where('name', 'analytics.view')->value('id');
        $author->permissions()->attach($permissionId);

        $this->assertTrue($author->hasPermission('question.create'));
        $this->assertFalse($author->hasPermission('question.review'));
        $this->assertTrue($author->hasPermission('analytics.view'));
    }

    private function panelUser(string $role, string $schoolCode, bool $withToken = true): array
    {
        $school = School::firstOrCreate(['code' => $schoolCode], ['name' => 'Escola '.$schoolCode, 'is_active' => true]);
        $user = User::factory()->create([
            'school_id' => str_starts_with($role, 'content_') ? null : $school->id,
            'role' => $role,
        ]);
        $token = 'token-'.$role.'-'.$schoolCode;
        if ($withToken) {
            ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        }

        return [$school, $user, $token];
    }
}
