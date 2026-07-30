<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telefone normalizado e indexado: acaba com a varredura da tabela
        // inteira em memória a cada verificação de desbloqueio.
        Schema::table('unlocks', function (Blueprint $table) {
            $table->string('phone_normalized', 20)->nullable()->after('phone')->index();
            $table->foreignId('mobile_user_id')->nullable()->after('phone_normalized')->constrained()->nullOnDelete();
            $table->timestamp('last_verified_at')->nullable()->after('expires_at');
        });

        Schema::table('mobile_users', function (Blueprint $table) {
            $table->string('phone_normalized', 20)->nullable()->after('phone')->index();
            $table->timestamp('phone_verified_at')->nullable()->after('phone_normalized');
        });

        $this->backfill('unlocks');
        $this->backfill('mobile_users');

        // Liga cada desbloqueio existente à conta com o mesmo telefone.
        DB::table('unlocks')->whereNull('mobile_user_id')->orderBy('id')->eachById(function ($unlock): void {
            $userId = DB::table('mobile_users')->where('phone_normalized', $unlock->phone_normalized)->value('id');
            if ($userId) {
                DB::table('unlocks')->where('id', $unlock->id)->update(['mobile_user_id' => $userId]);
            }
        });

        // Desafios OTP: o desbloqueio passa a exigir prova de posse do número.
        Schema::create('unlock_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_normalized', 20)->index();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['mobile_user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlock_challenges');

        Schema::table('mobile_users', fn (Blueprint $table) => $table->dropColumn('phone_normalized'));

        Schema::table('unlocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mobile_user_id');
            $table->dropColumn(['phone_normalized', 'last_verified_at']);
        });
    }

    private function backfill(string $table): void
    {
        DB::table($table)->orderBy('id')->eachById(function ($row) use ($table): void {
            DB::table($table)->where('id', $row->id)->update(['phone_normalized' => Phone::normalize($row->phone)]);
        });
    }
};
