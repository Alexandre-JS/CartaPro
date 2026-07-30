<?php

namespace App\Support;

/**
 * Normalização única de telefones moçambicanos.
 *
 * Corrige a divergência que existia entre UnlockController (exigia prefixo '8'
 * para adicionar o indicativo) e MobileController (não exigia): o mesmo número
 * podia aparecer pago numa via e gratuito na outra.
 *
 * O valor devolvido é o que fica gravado em `phone_normalized` e é o único
 * usado em comparações — nunca se compara `phone` em bruto.
 */
class Phone
{
    public const COUNTRY_CODE = '258';

    /** Formato canónico: 258 + 9 dígitos nacionais (ex.: 258841234567). */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // 00258... ou +258...
        if (str_starts_with($digits, '00'.self::COUNTRY_CODE)) {
            $digits = substr($digits, 2);
        }

        // Número nacional com 9 dígitos (8x…) — acrescenta o indicativo.
        if (strlen($digits) === 9 && str_starts_with($digits, '8')) {
            return self::COUNTRY_CODE.$digits;
        }

        // Já vem com indicativo.
        if (strlen($digits) === 12 && str_starts_with($digits, self::COUNTRY_CODE)) {
            return $digits;
        }

        // Formatos com zero à cabeça (0 84…).
        if (strlen($digits) === 10 && str_starts_with($digits, '08')) {
            return self::COUNTRY_CODE.substr($digits, 1);
        }

        // Qualquer outro formato fica normalizado apenas aos dígitos, para que
        // duas escritas do mesmo número continuem a coincidir entre si.
        return $digits;
    }

    public static function matches(?string $a, ?string $b): bool
    {
        $left = self::normalize($a);

        return $left !== '' && $left === self::normalize($b);
    }

    /** Apresentação amigável: +258 84 123 4567. */
    public static function format(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if (strlen($normalized) !== 12) {
            return (string) $phone;
        }

        return sprintf('+%s %s %s %s',
            substr($normalized, 0, 3),
            substr($normalized, 3, 2),
            substr($normalized, 5, 3),
            substr($normalized, 8, 4),
        );
    }
}
