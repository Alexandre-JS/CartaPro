<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Bilhete cifrado que liga um aluno concreto a uma sessão de prova.
 *
 * Substitui o modelo anterior da API, em que bastava conhecer o código de 6
 * caracteres para ler a pauta da turma e submeter em nome de qualquer aluno.
 * É o equivalente, na API, às URLs assinadas já usadas no fluxo web.
 */
class ExamTicket
{
    public function __construct(
        public readonly int $sessionId,
        public readonly int $studentId,
        public readonly int $expiresAt,
    ) {}

    public static function issue(int $sessionId, int $studentId, int $hours = 4): string
    {
        return Crypt::encryptString(json_encode([
            'sid' => $sessionId,
            'stu' => $studentId,
            'exp' => now()->addHours($hours)->getTimestamp(),
        ]));
    }

    public static function parse(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! isset($data['sid'], $data['stu'], $data['exp']) || $data['exp'] < now()->getTimestamp()) {
            return null;
        }

        return new self((int) $data['sid'], (int) $data['stu'], (int) $data['exp']);
    }
}
