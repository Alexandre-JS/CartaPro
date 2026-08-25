<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * As categorias de sinais deixam de viver em config/estudo.php.
 *
 * Estavam fixas em código: acrescentar uma categoria — ou uma subcategoria,
 * que nem sequer existia — obrigava a um deploy. Quem cataloga sinalização não
 * tem como editar um ficheiro PHP, e a lista de nove categorias nunca cobriu
 * tudo o que o Código da Estrada distingue.
 *
 * Categoria e subcategoria vivem na mesma tabela, separadas por `parent_id`:
 * uma subcategoria é uma categoria que tem pai. Duas tabelas quase idênticas
 * duplicariam o CRUD, as validações e as vistas para ganhar nada — os campos
 * são os mesmos nos dois níveis.
 */
return new class extends Migration
{
    /**
     * As nove categorias que existiam em configuração.
     *
     * Vão inline e não lidas de `config()` de propósito: a migração tem de
     * continuar a correr igual daqui a um ano, quando essa chave de
     * configuração já não existir.
     */
    private const INICIAIS = [
        ['perigo', 'Sinais de perigo', 'Avisam de um perigo à frente. Forma triangular com orla vermelha.', 'warning-outline', 1],
        ['proibicao', 'Sinais de proibição', 'Proíbem ou limitam. Redondos com orla vermelha.', 'ban-outline', 2],
        ['obrigacao', 'Sinais de obrigação', 'Impõem um comportamento. Redondos e azuis.', 'arrow-forward-circle-outline', 3],
        ['prioridade', 'Sinais de prioridade', 'Definem quem passa primeiro.', 'git-compare-outline', 4],
        ['indicacao', 'Sinais de indicação', 'Informam sobre o que existe na via.', 'information-circle-outline', 5],
        ['marcas_rodoviarias', 'Marcas rodoviárias', 'Linhas e símbolos pintados no pavimento.', 'remove-outline', 6],
        ['semaforos', 'Semáforos', 'Sinalização luminosa.', 'traffic-cone-outline', 7],
        ['agentes', 'Sinais dos agentes', 'Gestos que prevalecem sobre a restante sinalização.', 'hand-left-outline', 8],
        ['complementar', 'Painéis complementares', 'Acompanham outro sinal e precisam-no.', 'albums-outline', 9],
    ];

    public function up(): void
    {
        Schema::create('sign_categories', function (Blueprint $table) {
            $table->id();
            // Nulo é categoria de topo; preenchido é subcategoria. Apagar uma
            // categoria leva as suas subcategorias — sem pai não significam nada.
            $table->foreignId('parent_id')->nullable()->constrained('sign_categories')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        foreach (self::INICIAIS as [$slug, $nome, $descricao, $icone, $ordem]) {
            DB::table('sign_categories')->insert([
                'slug' => $slug,
                'name' => $nome,
                'description' => $descricao,
                'icon' => $icone,
                'sort_order' => $ordem,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('signs', function (Blueprint $table) {
            $table->foreignId('sign_category_id')->nullable()->after('slug')->constrained('sign_categories')->restrictOnDelete();
            $table->foreignId('sign_subcategory_id')->nullable()->after('sign_category_id')->constrained('sign_categories')->nullOnDelete();
        });

        /*
         * Liga os sinais existentes. Um sinal cuja categoria não conste da
         * lista acima — escrito à mão numa importação, por exemplo — dava-se
         * como perdido se o ignorássemos: cria-se a categoria em falta, e o
         * catálogo fica com o que já lá estava.
         */
        foreach (DB::table('signs')->select('id', 'category')->get() as $sinal) {
            $slug = (string) $sinal->category;

            if ($slug === '') {
                continue;
            }

            $categoria = DB::table('sign_categories')->where('slug', $slug)->value('id');

            $categoria ??= DB::table('sign_categories')->insertGetId([
                'slug' => $slug,
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'sort_order' => 99,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('signs')->where('id', $sinal->id)->update(['sign_category_id' => $categoria]);
        }

        Schema::table('signs', function (Blueprint $table) {
            // O índice tem de sair antes da coluna: o SQLite recusa-se a
            // largar uma coluna que um índice ainda referencia.
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('signs', function (Blueprint $table) {
            $table->string('category')->default('')->after('slug');
            $table->index('category');
        });

        foreach (DB::table('signs')->select('id', 'sign_category_id')->get() as $sinal) {
            DB::table('signs')->where('id', $sinal->id)->update([
                'category' => (string) DB::table('sign_categories')->where('id', $sinal->sign_category_id)->value('slug'),
            ]);
        }

        Schema::table('signs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sign_subcategory_id');
            $table->dropConstrainedForeignId('sign_category_id');
        });

        Schema::dropIfExists('sign_categories');
    }
};
