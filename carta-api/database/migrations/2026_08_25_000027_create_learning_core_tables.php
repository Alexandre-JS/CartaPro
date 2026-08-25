<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('entity_type', 40)->nullable();
            $table->string('entity_id', 190)->nullable();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('result')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->string('deduplication_key', 190);
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->unique(['mobile_user_id', 'type', 'deduplication_key'], 'learning_events_deduplication_unique');
            $table->index(['mobile_user_id', 'occurred_at']);
        });

        Schema::create('topic_masteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('average_duration_ms')->nullable();
            $table->timestamp('last_practiced_at')->nullable();
            // MySQL strict mode rejects a non-null timestamp without a default.
            // The learning service fills this value whenever it recalculates.
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['mobile_user_id', 'topic_id']);
        });

        Schema::create('study_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('reason');
            $table->unsignedTinyInteger('priority')->default(1)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['mobile_user_id', 'topic_id', 'type']);
        });

        Schema::create('readiness_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('breakdown');
            $table->string('level', 30);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_scores');
        Schema::dropIfExists('study_recommendations');
        Schema::dropIfExists('topic_masteries');
        Schema::dropIfExists('learning_events');
        Schema::dropIfExists('learning_profiles');
    }
};
