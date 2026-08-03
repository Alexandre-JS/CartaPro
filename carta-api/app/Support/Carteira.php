<?php

namespace App\Support;

/**
 * Que carteira móvel serve um número moçambicano.
 *
 * Em Moçambique o prefixo identifica a operadora, e cada operadora tem a sua
 * carteira: Vodacom (84, 85) → M-Pesa, Movitel (86, 87) → e-Mola. Pagar
 * M-Pesa com um número Movitel falha sempre, e sem esta verificação o aluno só
 * descobria isso depois de a transação ser recusada.
 *
 * Os prefixos vêm da configuração, e não estão aqui fixos, porque a atribuição
 * de numeração muda: um prefixo novo não pode impedir alguém de pagar.
 */
class Carteira
{
    /** Método adequado ao número, ou null se o prefixo for desconhecido. */
    public static function paraNumero(?string $phone): ?string
    {
        $prefixo = self::prefixo($phone);

        if ($prefixo === null) {
            return null;
        }

        foreach (config('payments.methods', []) as $chave => $metodo) {
            if (in_array($prefixo, $metodo['prefixos'] ?? [], true)) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * O número serve este método?
     *
     * Um prefixo desconhecido devolve `true` de propósito: mais vale deixar a
     * operadora recusar do que bloquear alguém por a nossa lista estar velha.
     */
    public static function serve(?string $phone, string $metodo): bool
    {
        $detetado = self::paraNumero($phone);

        return $detetado === null || $detetado === $metodo;
    }

    /** Os dois dígitos nacionais que identificam a operadora. */
    private static function prefixo(?string $phone): ?string
    {
        $normalizado = Phone::normalize($phone);

        return strlen($normalizado) === 12 ? substr($normalizado, 3, 2) : null;
    }
}
