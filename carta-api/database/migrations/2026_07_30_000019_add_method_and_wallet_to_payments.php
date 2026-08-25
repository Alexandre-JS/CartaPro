<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo método de pagamento e carteira própria.
 *
 * Duas coisas que a primeira versão assumia e deixaram de ser verdade:
 *  - que só havia M-Pesa (`provider` chegava para saber tudo);
 *  - que o número da carteira era o da conta. Muita gente tem a conta num
 *    número e a carteira móvel noutro — sem separar os dois, essas pessoas
 *    simplesmente não conseguiam pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method')->default('mpesa')->after('provider')->index();

            // Endereço para onde a PaySuite manda o aluno concluir o pagamento.
            // O C2B do M-Pesa não usa isto: empurra o PIN directamente.
            $table->string('checkout_url')->nullable()->after('provider_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['method', 'checkout_url']));
    }
};
