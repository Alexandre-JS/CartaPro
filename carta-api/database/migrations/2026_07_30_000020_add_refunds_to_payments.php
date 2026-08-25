<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devolução em 7 dias.
 *
 * A promessa obriga o sistema: sem isto, reembolsar significava mover dinheiro
 * na carteira e o aluno ficar com o plano completo para sempre, porque nada
 * ligava a devolução ao acesso concedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->text('refund_reason')->nullable()->after('refunded_at');
            $table->foreignId('refunded_by')->nullable()->after('refund_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['refunded_at', 'refund_reason']);
        });
    }
};
