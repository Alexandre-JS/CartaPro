<?php

/*
|--------------------------------------------------------------------------
| Mensagens de validação (pt)
|--------------------------------------------------------------------------
|
| O projeto usa APP_LOCALE=pt e APP_FALLBACK_LOCALE=pt, mas não existia
| nenhuma pasta lang/. Sem estes ficheiros o Laravel não encontra tradução e
| devolve a própria chave: o app mostrava "validation.unique (and 1 more
| error)" em vez de dizer que o email já estava registado.
|
| `attributes` traduz os nomes dos campos da API (name, phone, …) para termos
| que o aluno reconhece.
|
*/

return [

    'accepted' => 'Tem de aceitar :attribute.',
    'active_url' => ':attribute não é um endereço válido.',
    'after' => ':attribute tem de ser uma data depois de :date.',
    'alpha' => ':attribute só pode conter letras.',
    'alpha_dash' => ':attribute só pode conter letras, números, hífenes e underscores.',
    'alpha_num' => ':attribute só pode conter letras e números.',
    'array' => ':attribute tem de ser uma lista.',
    'before' => ':attribute tem de ser uma data antes de :date.',
    'between' => [
        'array' => ':attribute tem de ter entre :min e :max itens.',
        'file' => ':attribute tem de ter entre :min e :max kilobytes.',
        'numeric' => ':attribute tem de estar entre :min e :max.',
        'string' => ':attribute tem de ter entre :min e :max caracteres.',
    ],
    'boolean' => ':attribute tem de ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não coincide.',
    'date' => ':attribute não é uma data válida.',
    'date_format' => ':attribute não corresponde ao formato :format.',
    'different' => ':attribute e :other têm de ser diferentes.',
    'digits' => ':attribute tem de ter :digits dígitos.',
    'digits_between' => ':attribute tem de ter entre :min e :max dígitos.',
    'email' => 'Escreva um endereço de email válido.',
    'exists' => ':attribute selecionado não existe.',
    'file' => ':attribute tem de ser um ficheiro.',
    'filled' => ':attribute tem de ser preenchido.',
    'gt' => [
        'numeric' => ':attribute tem de ser maior que :value.',
        'string' => ':attribute tem de ter mais de :value caracteres.',
    ],
    'gte' => [
        'numeric' => ':attribute tem de ser maior ou igual a :value.',
        'string' => ':attribute tem de ter :value caracteres ou mais.',
    ],
    'image' => ':attribute tem de ser uma imagem.',
    'in' => ':attribute selecionado não é válido.',
    'integer' => ':attribute tem de ser um número inteiro.',
    'json' => ':attribute tem de ser um texto JSON válido.',
    'lt' => [
        'numeric' => ':attribute tem de ser menor que :value.',
        'string' => ':attribute tem de ter menos de :value caracteres.',
    ],
    'lte' => [
        'numeric' => ':attribute tem de ser menor ou igual a :value.',
        'string' => ':attribute não pode ter mais de :value caracteres.',
    ],
    'max' => [
        'array' => ':attribute não pode ter mais de :max itens.',
        'file' => ':attribute não pode ter mais de :max kilobytes.',
        'numeric' => ':attribute não pode ser maior que :max.',
        'string' => ':attribute não pode ter mais de :max caracteres.',
    ],
    'mimes' => ':attribute tem de ser um ficheiro do tipo: :values.',
    'min' => [
        'array' => ':attribute tem de ter pelo menos :min itens.',
        'file' => ':attribute tem de ter pelo menos :min kilobytes.',
        'numeric' => ':attribute tem de ser pelo menos :min.',
        'string' => ':attribute tem de ter pelo menos :min caracteres.',
    ],
    'not_in' => ':attribute selecionado não é válido.',
    'numeric' => ':attribute tem de ser um número.',
    'present' => ':attribute tem de estar presente.',
    'regex' => 'O formato de :attribute não é válido.',
    'required' => 'Preencha :attribute.',
    'required_if' => 'Preencha :attribute quando :other for :value.',
    'required_with' => 'Preencha :attribute quando :values estiver presente.',
    'required_without' => 'Preencha :attribute quando :values não estiver presente.',
    'same' => ':attribute e :other têm de coincidir.',
    'size' => [
        'array' => ':attribute tem de conter :size itens.',
        'file' => ':attribute tem de ter :size kilobytes.',
        'numeric' => ':attribute tem de ser :size.',
        'string' => ':attribute tem de ter :size caracteres.',
    ],
    'string' => ':attribute tem de ser texto.',
    'unique' => 'Este :attribute já está registado.',
    'uploaded' => 'Não foi possível carregar :attribute.',
    'url' => 'O formato de :attribute não é válido.',
    'uuid' => ':attribute tem de ser um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens específicas
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'unique' => 'Este email já tem conta CartaPro. Entre em vez de criar uma nova conta.',
        ],
        'phone' => [
            'unique' => 'Este número já tem conta CartaPro. Entre em vez de criar uma nova conta.',
        ],
        'password' => [
            'min' => 'A palavra-passe tem de ter pelo menos :min caracteres.',
        ],
        'code' => [
            'size' => 'O código de ativação tem 6 dígitos.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes dos campos
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'answers' => 'as respostas',
        'blueprint_category' => 'a categoria de carta',
        'blueprint_question_count' => 'o número de perguntas',
        'categories' => 'as categorias de carta',
        'classroom_id' => 'a turma',
        'code' => 'o código',
        'correct_index' => 'a opção correta',
        'duration_minutes' => 'a duração',
        'email' => 'o email',
        'exam_id' => 'a prova',
        'expires_at' => 'a validade',
        'explanation' => 'a explicação',
        'identifier' => 'o identificador',
        'identifier_or_email' => 'o email ou telefone',
        'license_category' => 'a categoria de carta',
        'name' => 'o nome',
        'nome' => 'o nome',
        'notes' => 'as notas',
        'options' => 'as opções',
        'password' => 'a palavra-passe',
        'payment_method' => 'o método de pagamento',
        'payment_reference' => 'a referência do pagamento',
        'phone' => 'o número de telefone',
        'plan' => 'o plano',
        'question_ids' => 'as perguntas da prova',
        'school_id' => 'a escola',
        'statement' => 'o enunciado',
        'topic_id' => 'o tema',
        'type' => 'o tipo',
        'unlocked_at' => 'a data do desbloqueio',
        'visibility' => 'a visibilidade',
    ],

];
