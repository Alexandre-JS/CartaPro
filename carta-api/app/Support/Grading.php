<?php

namespace App\Support;

/**
 * Único ponto que decide aprovação, nota em valores e aptidão.
 * Lê sempre de config/grading.php — ver a nota nesse ficheiro.
 */
class Grading
{
    public static function rules(?string $category = null): array
    {
        $default = config('grading.default');

        return array_merge($default, config('grading.categories.'.(string) $category, []));
    }

    public static function passPercentage(?string $category = null): float
    {
        return (float) self::rules($category)['pass_percentage'];
    }

    public static function questionCount(?string $category = null): int
    {
        return (int) self::rules($category)['question_count'];
    }

    public static function durationMinutes(?string $category = null): int
    {
        return (int) self::rules($category)['duration_minutes'];
    }

    /** Nº mínimo de acertos para aprovar numa prova de $total perguntas. */
    public static function passScore(int $total, ?string $category = null): int
    {
        return $total > 0 ? (int) ceil($total * self::passPercentage($category) / 100) : 0;
    }

    public static function percentage(int $score, int $total): float
    {
        return $total > 0 ? round($score / $total * 100, 1) : 0.0;
    }

    /** Converte a pontuação para a escala 0-20 usada pelas escolas. */
    public static function values(int $score, int $total): float
    {
        return $total > 0 ? round($score / $total * self::maxValues(), 1) : 0.0;
    }

    public static function maxValues(): float
    {
        return (float) config('grading.max_values');
    }

    public static function passed(int $score, int $total, ?string $category = null): bool
    {
        return $total > 0 && $score >= self::passScore($total, $category);
    }

    /** Nota mínima (em valores) para a tentativa contar para a aptidão. */
    public static function minimumAptitudeValues(): float
    {
        return (float) config('grading.aptitude.minimum_values');
    }

    public static function requiredValidGrades(): int
    {
        return (int) config('grading.aptitude.required_valid_grades');
    }

    public static function qualifiesForAptitude(int $score, int $total): bool
    {
        return self::values($score, $total) >= self::minimumAptitudeValues();
    }

    /** Nota em valores equivalente ao limiar de aprovação (ex.: 72% → 14.4). */
    public static function passValues(?string $category = null): float
    {
        return round(self::passPercentage($category) / 100 * self::maxValues(), 1);
    }

    /**
     * Condição SQL para "esta tentativa conta para a aptidão".
     *
     * Usa aritmética inteira de propósito. Ligar o limiar como float via `?`
     * não é portável: o PDO liga floats como texto e, por afinidade de tipo, o
     * SQLite avalia `1.0 >= '0.7'` como FALSO enquanto o MySQL coage e dá
     * VERDADEIRO — o mesmo aluno aparecia apto em produção e inapto nos testes.
     * O limiar vem de config (nunca de input do utilizador), pelo que é seguro
     * embutir como literal inteiro.
     */
    public static function validGradeSql(string $table = 'exam_attempts'): string
    {
        $scaled = (int) round(self::minimumAptitudeValues() / self::maxValues() * 10000);

        return sprintf('%1$s.score * 10000 >= %1$s.total * %2$d', $table, $scaled);
    }

    /** Bloco enviado ao app dentro do pacote publicado. */
    public static function packageRules(): array
    {
        $categories = [];
        foreach (array_keys(config('grading.categories')) as $slug) {
            $categories[$slug] = [
                'totalPerguntas' => self::questionCount($slug),
                'percentagemPassagem' => self::passPercentage($slug),
                'notaPassagem' => self::passScore(self::questionCount($slug), $slug),
                'valoresPassagem' => self::passValues($slug),
                'minutos' => self::durationMinutes($slug),
            ];
        }

        return [
            'valoresMaximos' => self::maxValues(),
            'aptidao' => [
                'valoresMinimos' => self::minimumAptitudeValues(),
                'notasNecessarias' => self::requiredValidGrades(),
            ],
            'porCategoria' => $categories,
            'omissao' => [
                'totalPerguntas' => self::questionCount(),
                'percentagemPassagem' => self::passPercentage(),
                'notaPassagem' => self::passScore(self::questionCount()),
                'valoresPassagem' => self::passValues(),
                'minutos' => self::durationMinutes(),
            ],
        ];
    }
}
