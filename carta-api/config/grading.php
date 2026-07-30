<?php

/*
|--------------------------------------------------------------------------
| Regras de classificação — fonte única de verdade
|--------------------------------------------------------------------------
|
| Antes desta configuração a regra de aprovação estava replicada (e em
| contradição) em cinco locais: config do app (24/25 = 96%), ExamController
| (72%), MobileController (72% hardcoded), ExamAttempt::qualifiesForAptitude
| (14 valores) e StudentController (3 notas válidas).
|
| Tudo o que decide "aprovado / reprovado / apto" tem de ler daqui, e o app
| recebe estes valores dentro do pacote publicado (chave "regras").
|
*/

return [

    // Escala de classificação usada nos relatórios das escolas (0-20 valores).
    'max_values' => 20.0,

    // Regra por omissão, aplicada quando a categoria de carta não tem override.
    'default' => [
        'question_count' => 25,
        'pass_percentage' => 72.0,
        'duration_minutes' => 30,
    ],

    // Overrides por categoria de carta. Ajustar aqui quando o INATRO
    // confirmar valores diferentes por categoria — nada mais precisa mudar.
    'categories' => [
        'ligeiro' => [
            'question_count' => 25,
            'pass_percentage' => 72.0,
            'duration_minutes' => 30,
        ],
        'pesado' => [
            'question_count' => 25,
            'pass_percentage' => 72.0,
            'duration_minutes' => 30,
        ],
        'profissional_publico' => [
            'question_count' => 25,
            'pass_percentage' => 72.0,
            'duration_minutes' => 30,
        ],
    ],

    // Critério de aptidão acompanhado pelas escolas.
    'aptitude' => [
        'minimum_values' => 14.0,
        'required_valid_grades' => 3,
    ],

];
