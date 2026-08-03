<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registo de pagamentos.
 *
 * Até aqui um pagamento não deixava rasto nenhum: existia o `unlocks`, que
 * descreve o *direito* concedido, mas nada que descrevesse a *transação* que o
 * originou. Não havia como reconciliar com o extrato M-Pesa, saber quanto se
 * cobrou, nem distinguir uma tentativa falhada de uma que nunca aconteceu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();

            $table->string('plan')->default('completo');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MZN');
            $table->string('provider')->default('mpesa');
            $table->string('phone_normalized', 20)->index();

            // 'pendente' enquanto o cliente não escreve o PIN; a partir daí é
            // terminal — um pagamento nunca volta atrás.
            $table->string('status')->default('pendente')->index();

            /*
             * `reference` é o nosso identificador visível ao cliente (aparece
             * no extrato M-Pesa). `conversation_id` é o ThirdPartyConversationID
             * exigido pela OpenAPI e é o que torna a chamada idempotente:
             * repetir com o mesmo valor não cobra duas vezes.
             */
            $table->string('reference', 64)->unique();
            $table->string('conversation_id', 64)->unique();

            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_code')->nullable();
            $table->text('provider_message')->nullable();

            // Guardado em bruto para o apoio ao cliente poder reconstituir o
            // que o fornecedor devolveu, sem depender do que interpretámos.
            $table->json('provider_payload')->nullable();

            $table->foreignId('unlock_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['mobile_user_id', 'status']);
        });

        Schema::table('unlocks', function (Blueprint $table) {
            // Quanto foi efetivamente cobrado. Os desbloqueios manuais ficam a
            // nulo, que é a diferença entre "não sabemos" e "custou zero".
            $table->decimal('amount', 10, 2)->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('unlocks', fn (Blueprint $table) => $table->dropColumn('amount'));
        Schema::dropIfExists('payments');
    }
};
