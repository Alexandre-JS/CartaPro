<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tarefas agendadas
|--------------------------------------------------------------------------
| Antes este ficheiro só tinha o comando `inspire` de exemplo, pelo que
| `Unlock.expires_at` nunca era aplicado: um plano expirado continuava ativo.
|
| Em produção é preciso o cron do sistema a apontar para o scheduler:
|   * * * * * cd /caminho/carta-api && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('cartapro:expire-unlocks')->hourly()->withoutOverlapping();
