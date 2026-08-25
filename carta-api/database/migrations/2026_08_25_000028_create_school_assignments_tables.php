<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('resource_type', 30)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'classroom_id', 'status']);
        });

        Schema::create('school_assignment_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('assigned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['school_assignment_id', 'mobile_user_id'], 'school_assignment_progress_unique');
        });

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'assignment.manage',
            'description' => 'Criar, distribuir e acompanhar tarefas escolares',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['school', 'school_owner', 'school_admin', 'instructor'] as $role) {
            DB::table('permission_role')->insert(['role' => $role, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('name', 'assignment.manage')->delete();
        }
        Schema::dropIfExists('school_assignment_progress');
        Schema::dropIfExists('school_assignments');
    }
};
