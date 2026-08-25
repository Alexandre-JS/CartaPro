<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->string('role', 40);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role', 'permission_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('license_number')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('classroom_instructor', function (Blueprint $table) {
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['classroom_id', 'instructor_id']);
        });

        $now = now();
        $permissions = [
            'question.create' => 'Criar e editar perguntas',
            'question.submit' => 'Enviar perguntas para revisão',
            'question.review' => 'Aprovar ou rejeitar perguntas',
            'exam.create' => 'Criar e editar provas',
            'exam.publish' => 'Publicar ou aplicar provas',
            'classroom.manage' => 'Gerir turmas, alunos e vínculos',
            'student.view' => 'Consultar alunos e resultados individuais',
            'analytics.view' => 'Consultar relatórios e analítica',
            'instructor.manage' => 'Gerir instrutores e atribuições',
        ];

        DB::table('permissions')->insert(collect($permissions)->map(fn (string $description, string $name) => [
            'name' => $name,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());

        $ids = DB::table('permissions')->pluck('id', 'name');
        $schoolPermissions = array_values(array_diff(array_keys($permissions), ['question.review']));
        $roles = [
            'school_owner' => $schoolPermissions,
            'school_admin' => $schoolPermissions,
            'school' => $schoolPermissions,
            'instructor' => ['question.create', 'question.submit', 'exam.create', 'exam.publish', 'student.view', 'analytics.view'],
            'content_author' => ['question.create', 'question.submit'],
            'content_reviewer' => ['question.review'],
        ];

        foreach ($roles as $role => $names) {
            DB::table('permission_role')->insert(array_map(fn (string $name) => [
                'role' => $role,
                'permission_id' => $ids[$name],
            ], $names));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_instructor');
        Schema::dropIfExists('instructors');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
