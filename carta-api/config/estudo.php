<?php

/*
|--------------------------------------------------------------------------
| Taxonomia do material de estudo
|--------------------------------------------------------------------------
|
| Fonte única para as categorias de sinalização e para os grupos de fichas de
| estudo. O painel constrói os seletores a partir daqui e o app recebe os
| rótulos dentro do pacote — não há listas repetidas no código do app, que foi
| o erro que obrigava a alterar o app cada vez que se criava um tema novo.
|
*/

return [

    /*
     * Categorias da biblioteca de sinalização.
     *
     * `signs.category` é uma string livre; esta lista dá-lhe rótulo, ordem e
     * ícone. Inclui sinalização não vertical (marcas rodoviárias, semáforos e
     * sinais dos agentes), que antes não tinha lugar nenhum.
     */
    'categorias_sinais' => [
        'perigo' => [
            'nome' => 'Sinais de perigo',
            'descricao' => 'Avisam de um perigo à frente. Forma triangular com orla vermelha.',
            'icone' => 'warning-outline',
            'ordem' => 1,
        ],
        'proibicao' => [
            'nome' => 'Sinais de proibição',
            'descricao' => 'Proíbem ou limitam uma manobra. Forma circular com orla vermelha.',
            'icone' => 'close-circle-outline',
            'ordem' => 2,
        ],
        'obrigacao' => [
            'nome' => 'Sinais de obrigação',
            'descricao' => 'Impõem um comportamento obrigatório. Forma circular azul.',
            'icone' => 'arrow-forward-circle-outline',
            'ordem' => 3,
        ],
        'prioridade' => [
            'nome' => 'Sinais de prioridade',
            'descricao' => 'Regulam quem passa primeiro nos cruzamentos.',
            'icone' => 'swap-horizontal-outline',
            'ordem' => 4,
        ],
        'indicacao' => [
            'nome' => 'Sinais de indicação',
            'descricao' => 'Informam sobre a via, serviços e direções.',
            'icone' => 'information-circle-outline',
            'ordem' => 5,
        ],
        'marcas_rodoviarias' => [
            'nome' => 'Marcas rodoviárias',
            'descricao' => 'Linhas, setas e símbolos pintados no pavimento.',
            'icone' => 'remove-outline',
            'ordem' => 6,
        ],
        'semaforos' => [
            'nome' => 'Semáforos',
            'descricao' => 'Sinalização luminosa para veículos, peões e faixas reservadas.',
            'icone' => 'stop-circle-outline',
            'ordem' => 7,
        ],
        'agentes' => [
            'nome' => 'Sinais dos agentes',
            'descricao' => 'Gestos dos agentes reguladores, que prevalecem sobre a restante sinalização.',
            'icone' => 'hand-left-outline',
            'ordem' => 8,
        ],
        'complementar' => [
            'nome' => 'Painéis complementares',
            'descricao' => 'Painéis adicionais que precisam o alcance do sinal principal.',
            'icone' => 'albums-outline',
            'ordem' => 9,
        ],
    ],

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
