<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material de estudo.
 *
 * O que existia: os artigos do Código eram despejados numa única categoria
 * falsa ("Código da Estrada") porque nada os agrupava, e a biblioteca de sinais
 * — que já existia na API — não tinha ecrã nenhum no app nem texto de estudo
 * suficiente para ensinar.
 *
 * Acrescenta-se: fichas de estudo (lições), glossário, capítulos nos artigos e
 * campos de estudo nos sinais.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fichas de estudo — o material que efetivamente ensina. Os artigos
        // legais em bruto são difíceis de estudar sem explicação.
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            // Agrupamento no ecrã de estudos (ver config/estudo.php):
            // codigo | sinalizacao | conducao | primeiros_socorros | mecanica
            $table->string('group', 40)->default('codigo')->index();
            $table->json('license_categories')->nullable();
            // Ligações para consulta cruzada, guardadas de forma simples para
            // viajarem no pacote offline sem joins.
            $table->json('sign_slugs')->nullable();
            $table->json('article_numbers')->nullable();
            $table->unsignedInteger('reading_minutes')->default(3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Glossário: definições curtas e pesquisáveis.
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->string('slug')->unique();
            $table->text('definition');
            $table->unsignedInteger('article_ref')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('signs', function (Blueprint $table) {
            // `meaning` é a frase curta; `description` é o texto de estudo.
            $table->text('description')->nullable()->after('meaning');
            $table->foreignId('topic_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->unsignedInteger('article_ref')->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('article_ref');
            $table->boolean('is_locked')->default(false)->after('sort_order');
            $table->index('category');
        });

        Schema::table('articles', function (Blueprint $table) {
            // Sem capítulo não era possível organizar a leitura.
            $table->unsignedInteger('chapter_number')->nullable()->after('number')->index();
            $table->string('chapter_title')->nullable()->after('chapter_number');
            $table->unsignedInteger('sort_order')->default(0)->after('chapter_title');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['chapter_number']);
            $table->dropColumn(['chapter_number', 'chapter_title', 'sort_order']);
        });

        Schema::table('signs', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropConstrainedForeignId('topic_id');
            $table->dropColumn(['description', 'article_ref', 'sort_order', 'is_locked']);
        });

        Schema::dropIfExists('glossary_terms');
        Schema::dropIfExists('lessons');
    }
};
