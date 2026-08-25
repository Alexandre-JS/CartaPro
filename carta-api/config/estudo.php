<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Categorias de sinais
    |--------------------------------------------------------------------------
    |
    | Mudaram-se para a base de dados (tabela `sign_categories`), onde são
    | editáveis no painel e passaram a ter subcategorias. Estavam aqui fixas em
    | código: acrescentar uma exigia um deploy, e quem cataloga sinalização não
    | mexe em ficheiros PHP. As nove originais foram migradas.
    |
    */

    /*
     * Grupos das fichas de estudo (lessons.group).
     */
    'grupos_licoes' => [
        'codigo' => [
            'nome' => 'Regras de trânsito',
            'descricao' => 'As regras do Código explicadas em linguagem simples.',
            'icone' => 'book-outline',
            'ordem' => 1,
        ],
        'sinalizacao' => [
            'nome' => 'Sinalização',
            'descricao' => 'Como ler e reagir à sinalização na estrada.',
            'icone' => 'warning-outline',
            'ordem' => 2,
        ],
        'conducao' => [
            'nome' => 'Condução e manobras',
            'descricao' => 'Ultrapassagens, estacionamento, rotundas e condução defensiva.',
            'icone' => 'car-outline',
            'ordem' => 3,
        ],
        'primeiros_socorros' => [
            'nome' => 'Primeiros socorros',
            'descricao' => 'O que fazer no local de um acidente. Matéria de exame fora do Código.',
            'icone' => 'medkit-outline',
            'ordem' => 4,
        ],
        'mecanica' => [
            'nome' => 'Mecânica básica',
            'descricao' => 'Verificações essenciais, pneus, luzes e avarias comuns.',
            'icone' => 'construct-outline',
            'ordem' => 5,
        ],
    ],

];
