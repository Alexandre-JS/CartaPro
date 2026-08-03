<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadeado próprio para as provas.
 *
 * Faltava, e era um erro à espera de acontecer: uma prova só contava como
 * bloqueada quando *todas* as perguntas dela estavam bloqueadas. Bastava
 * começar a bloquear perguntas em profundidade para as provas que as usam
 * chegarem ao aluno mutiladas — um exame de 30 perguntas a mostrar 11, com a
 * nota de passagem calculada sobre 30. Não é uma prova bloqueada: é uma prova
 * partida, e o aluno reprovava sem perceber porquê.
 *
 * Com este campo, uma prova é inteira ou não é servida de todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('is_locked'));
    }
};
