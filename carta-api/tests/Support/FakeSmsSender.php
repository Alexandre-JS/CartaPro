<?php

namespace Tests\Support;

use App\Services\SmsSender;

/** Captura as mensagens em vez de as enviar, para os testes lerem o OTP. */
class FakeSmsSender extends SmsSender
{
    /** @var array<int, array{phone: string, message: string}> */
    public array $sent = [];

    public function send(string $phone, string $message): bool
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];

        return true;
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        if (! $last || ! preg_match('/\b(\d{6})\b/', $last['message'], $matches)) {
            return null;
        }

        return $matches[1];
    }
}
