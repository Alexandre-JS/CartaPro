<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio de SMS (OTP de desbloqueio).
 *
 * Sem gateway configurado, o código é registado no log — permite operar o
 * fluxo manualmente na fase 1 sem bloquear o desenvolvimento. Configure
 * SMS_ENDPOINT/SMS_TOKEN em .env para passar a envio real.
 */
class SmsSender
{
    public function send(string $phone, string $message): bool
    {
        $endpoint = config('services.sms.endpoint');

        if (! $endpoint) {
            Log::info('SMS (sem gateway configurado)', ['telefone' => $phone, 'mensagem' => $message]);

            return true;
        }

        $response = Http::withToken((string) config('services.sms.token'))
            ->asJson()
            ->timeout(10)
            ->post($endpoint, [
                'sender' => config('services.sms.sender'),
                'to' => $phone,
                'message' => $message,
            ]);

        if ($response->failed()) {
            Log::error('Falha no envio de SMS', ['telefone' => $phone, 'status' => $response->status()]);
        }

        return $response->successful();
    }
}
