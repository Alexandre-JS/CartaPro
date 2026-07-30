<?php

namespace App\Console\Commands;

use App\Models\Unlock;
use App\Models\UnlockChallenge;
use Illuminate\Console\Command;

/**
 * Fecha o ciclo de vida das assinaturas: sem esta tarefa, `expires_at` era
 * um campo decorativo e um plano expirado continuava ativo indefinidamente.
 */
class ExpireUnlocks extends Command
{
    protected $signature = 'cartapro:expire-unlocks';

    protected $description = 'Desativa desbloqueios expirados e limpa desafios OTP caducados';

    public function handle(): int
    {
        $expired = Unlock::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        $challenges = UnlockChallenge::where('expires_at', '<=', now()->subDay())->delete();

        $this->info("Desbloqueios expirados: {$expired}. Desafios OTP removidos: {$challenges}.");

        return self::SUCCESS;
    }
}
