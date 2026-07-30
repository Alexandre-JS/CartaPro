<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telemetria por resposta: sem isto o sistema não consegue medir
        // dificuldade real, detetar adivinhação nem prever aprovação.
        Schema::table('mobile_answers', function (Blueprint $table) {
            $table->unsignedTinyInteger('selected_index')->nullable()->after('correct');
            $table->unsignedInteger('duration_ms')->nullable()->after('selected_index');
            $table->string('source', 20)->default('simulado')->after('duration_ms');
            $table->index(['mobile_user_id', 'topic', 'answered_at']);
        });

        // Repetição espaçada SM-2: intervalo deixa de ser uma escada fixa.
        Schema::table('mobile_revisions', function (Blueprint $table) {
            $table->decimal('ease_factor', 4, 2)->default(2.50)->after('interval_days');
            $table->unsignedSmallInteger('repetitions')->default(0)->after('ease_factor');
            $table->unsignedSmallInteger('lapses')->default(0)->after('repetitions');
            $table->timestamp('last_reviewed_at')->nullable()->after('lapses');
            $table->index(['mobile_user_id', 'scheduled_for']);
        });

        // Diagnóstico por tema guardado com taxa, não com "errou ≥1".
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->json('topic_breakdown')->nullable()->after('weak_topics');
            $table->unsignedInteger('duration_seconds')->nullable()->after('topic_breakdown');
        });

        // Provas geradas por critérios em vez de escolha manual de 25 caixas.
        Schema::table('exams', function (Blueprint $table) {
            $table->string('selection_mode', 20)->default('manual')->after('type');
            $table->json('blueprint')->nullable()->after('selection_mode');
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn(['selection_mode', 'blueprint']));

        Schema::table('exam_attempts', fn (Blueprint $table) => $table->dropColumn(['topic_breakdown', 'duration_seconds']));

        Schema::table('mobile_revisions', function (Blueprint $table) {
            $table->dropIndex(['mobile_user_id', 'scheduled_for']);
            $table->dropColumn(['ease_factor', 'repetitions', 'lapses', 'last_reviewed_at']);
        });

        Schema::table('mobile_answers', function (Blueprint $table) {
            $table->dropIndex(['mobile_user_id', 'topic', 'answered_at']);
            $table->dropColumn(['selected_index', 'duration_ms', 'source']);
        });
    }
};
