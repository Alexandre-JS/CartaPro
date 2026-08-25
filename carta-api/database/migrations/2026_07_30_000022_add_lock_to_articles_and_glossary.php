<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadeado no Código da Estrada e no glossário.
 *
 * Eram as duas únicas frentes de estudo sem forma nenhuma de as fechar: as
 * perguntas, os sinais e as fichas tinham `is_locked` desde o início, mas os
 * artigos e os termos seguiam inteiros para o plano gratuito, sem que houvesse
 * sequer um campo para os marcar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_active');
        });

        Schema::table('glossary_terms', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('is_locked'));
        Schema::table('glossary_terms', fn (Blueprint $table) => $table->dropColumn('is_locked'));
    }
};
