<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('license_category')->default('ligeiro');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('mobile_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id');
            $table->string('question_external_id');
            $table->string('topic');
            $table->boolean('correct');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['mobile_user_id', 'client_id']);
        });
        Schema::create('mobile_exam_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id');
            $table->unsignedInteger('number');
            $table->unsignedInteger('total');
            $table->unsignedInteger('correct_answers');
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['mobile_user_id', 'client_id']);
        });
        Schema::create('mobile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('question_external_id');
            $table->string('topic');
            $table->timestamp('scheduled_for');
            $table->unsignedSmallInteger('interval_days')->default(0);
            $table->timestamps();
            $table->unique(['mobile_user_id', 'question_external_id']);
        });
        Schema::create('mobile_read_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('content_key');
            $table->timestamps();
            $table->unique(['mobile_user_id', 'content_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_read_contents');
        Schema::dropIfExists('mobile_revisions');
        Schema::dropIfExists('mobile_exam_history');
        Schema::dropIfExists('mobile_answers');
        Schema::dropIfExists('mobile_api_tokens');
        Schema::dropIfExists('mobile_users');
    }
};
