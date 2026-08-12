<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A explicação deixa de ser obrigatória.
 *
 * Era `NOT NULL` e obrigatória no formulário, o que travava o trabalho no
 * sítio errado: quem transcreve um banco de perguntas tem o enunciado, as
 * alíneas e a resposta certa à frente, e a explicação pedagógica só aparece
 * depois — muitas vezes escrita por outra pessoa. A exigência produzia
 * explicações de circunstância («ver artigo 33») que ninguém voltava a rever,
 * pior do que campo nenhum.
 *
 * As perguntas que já a têm mantêm-na; o app passa a saber lidar com a
 * ausência em vez de mostrar um bloco vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('explanation')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Descer com nulos numa coluna que volta a ser obrigatória rebentava a
        // migração a meio; o texto vazio é o equivalente mais próximo.
        DB::table('questions')->whereNull('explanation')->update(['explanation' => '']);

        Schema::table('questions', function (Blueprint $table) {
            $table->text('explanation')->nullable(false)->change();
        });
    }
};
