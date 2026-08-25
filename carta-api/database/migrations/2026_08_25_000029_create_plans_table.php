<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('audience', 20);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('MZN');
            $table->unsignedInteger('duration_days')->nullable();
            $table->json('features');
            $table->boolean('is_purchasable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('plans')->insert([
            [
                'code' => 'free', 'name' => 'ProntoVia Free', 'audience' => 'candidate',
                'description' => 'Conteúdo essencial, treino e progresso básico.',
                'price' => 0, 'currency' => 'MZN', 'duration_days' => null,
                'features' => json_encode(['conteudo_essencial', 'sinais', 'treino_limitado', 'simulados_limitados', 'progresso_basico']),
                'is_purchasable' => false, 'is_active' => true, 'sort_order' => 10,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'plus', 'name' => 'ProntoVia+', 'audience' => 'candidate',
                'description' => 'Treino personalizado, simulados e recursos premium.',
                'price' => (float) config('payments.plans.plus.preco', 129), 'currency' => config('payments.currency', 'MZN'),
                'duration_days' => (int) config('payments.plans.plus.dias', 90),
                'features' => json_encode(['simulados_ilimitados', 'revisao_inteligente', 'prontidao', 'treino_personalizado', 'historico_completo', 'recursos_premium']),
                'is_purchasable' => true, 'is_active' => true, 'sort_order' => 20,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'school', 'name' => 'ProntoVia Escolas', 'audience' => 'school',
                'description' => 'Turmas, instrutores, tarefas, testes, resultados e analytics.',
                'price' => 0, 'currency' => 'MZN', 'duration_days' => null,
                'features' => json_encode(['painel', 'turmas', 'instrutores', 'testes', 'tarefas', 'resultados', 'analytics', 'banco_privado']),
                'is_purchasable' => false, 'is_active' => true, 'sort_order' => 30,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
