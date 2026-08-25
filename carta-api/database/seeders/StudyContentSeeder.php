<?php

namespace Database\Seeders;

use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Conteúdo de estudo de arranque.
 *
 * ATENÇÃO — CONTEÚDO A REVER: os significados dos sinais aqui usados são os
 * convencionais (a sinalização segue a Convenção de Viena e é consistente
 * internacionalmente), mas os textos das fichas e as remissões para artigos
 * têm de ser confirmados pela equipa ProntoVia contra o Código da Estrada de
 * Moçambique antes de irem para produção. Este seeder serve para os ecrãs
 * terem estrutura real com que trabalhar, não como fonte autoritativa.
 *
 * Executar com:  php artisan db:seed --class=StudyContentSeeder
 */
class StudyContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSigns();
        $this->seedGlossary();
        $this->seedLessons();
        $this->groupExistingArticles();
    }

    /**
     * Atribui capítulos aos artigos de demonstração que ainda não os tenham,
     * para o ecrã de estudo mostrar a leitura organizada. Os capítulos reais
     * têm de ser confirmados contra o Código na importação definitiva.
     */
    private function groupExistingArticles(): void
    {
        $porTema = [
            'Sinalização' => [1, 17],
            'Velocidade e distâncias' => [18, 30],
            'Manobras e ultrapassagem' => [31, 60],
            'Prioridades e cruzamentos' => [61, 90],
        ];

        $capitulo = 1;
        foreach ($porTema as $titulo => [$de, $ate]) {
            \App\Models\Article::whereNull('chapter_number')
                ->whereBetween('number', [$de, $ate])
                ->update(['chapter_number' => $capitulo, 'chapter_title' => $titulo]);
            $capitulo++;
        }
    }

    private function seedSigns(): void
    {
        $topics = Topic::pluck('id', 'slug');

        $signs = [
            // slug, nome, categoria, significado, descrição, tema
            ['stop', 'STOP — paragem obrigatória', 'prioridade', 'Paragem obrigatória no cruzamento.', 'Obriga a parar completamente antes da linha de paragem, mesmo que a via pareça livre. Só se retoma a marcha depois de verificar que não vem ninguém.', 'prioridade'],
            ['cedencia-passagem', 'Cedência de passagem', 'prioridade', 'Ceda a passagem aos veículos da via em que vai entrar.', 'Não obriga a parar, mas obriga a reduzir e a ceder. Se não houver visibilidade suficiente, é preciso parar.', 'prioridade'],
            ['curva-perigosa', 'Curva perigosa', 'perigo', 'Aviso de curva perigosa à frente.', 'Reduza a velocidade antes de entrar na curva. Travar dentro da curva desequilibra o veículo.', 'sinais_perigo'],
            ['curva-contra-curva', 'Curva e contracurva', 'perigo', 'Duas curvas seguidas em sentidos contrários.', 'A segunda curva costuma surpreender quem acelera à saída da primeira. Mantenha a velocidade baixa nas duas.', 'sinais_perigo'],
            ['passagem-pedoes', 'Passagem para pedões', 'perigo', 'Aviso de passagem para pedões à frente.', 'Aproxime-se a velocidade que permita parar. O pedão que já entrou na passadeira tem prioridade.', 'sinais_perigo'],
            ['proibido-ultrapassar', 'Proibido ultrapassar', 'proibicao', 'Proibida a ultrapassagem de veículos a motor.', 'Vigora até ao sinal de fim de proibição ou ao próximo cruzamento. Aplica-se mesmo que a via pareça livre.', 'ultrapassagem'],
            ['velocidade-maxima-60', 'Velocidade máxima 60', 'proibicao', 'Velocidade máxima permitida de 60 km/h.', 'É um limite, não uma obrigação: com chuva, nevoeiro ou trânsito intenso deve circular abaixo do valor indicado.', 'velocidade'],
            ['sentido-proibido', 'Sentido proibido', 'proibicao', 'Proibido o trânsito no sentido indicado.', 'Colocado à entrada de vias de sentido único. Entrar contra o sentido é uma das infrações mais graves.', 'sinais_proibicao'],
            ['sentido-obrigatorio', 'Sentido obrigatório', 'obrigacao', 'Obrigatório seguir na direção indicada.', 'Sinal circular azul. Indica o único percurso permitido a partir daquele ponto.', 'sinais_obrigacao'],
            ['rotunda', 'Rotunda', 'obrigacao', 'Obrigatório contornar a rotunda no sentido indicado.', 'Quem circula na rotunda tem, em regra, prioridade sobre quem entra. Sinalize a saída.', 'prioridade'],
            ['linha-continua', 'Linha contínua', 'marcas_rodoviarias', 'Proibido transpor ou pisar a linha.', 'Separa sentidos de trânsito. Não pode ser transposta para ultrapassar nem para inverter a marcha.', 'ultrapassagem'],
            ['linha-descontinua', 'Linha descontínua', 'marcas_rodoviarias', 'Permite transpor a linha com segurança.', 'A ultrapassagem é permitida desde que haja visibilidade e a manobra possa concluir-se sem risco.', 'ultrapassagem'],
            ['semaforo-vermelho', 'Semáforo vermelho', 'semaforos', 'Obrigação de parar antes da linha.', 'A luz amarela que o antecede significa parar, salvo se já estiver tão perto que travar seja perigoso.', 'sinalizacao_luminosa'],
            ['semaforo-amarelo-intermitente', 'Amarelo intermitente', 'semaforos', 'Passagem permitida com precaução especial.', 'Não dá prioridade: obriga a respeitar as regras gerais do cruzamento e a reduzir a velocidade.', 'sinalizacao_luminosa'],
            ['agente-braco-levantado', 'Agente com braço levantado', 'agentes', 'Obrigação de parar para todos os sentidos.', 'Os sinais dos agentes prevalecem sobre a sinalização luminosa e vertical.', 'sinalizacao_luminosa'],
        ];

        foreach ($signs as $index => [$slug, $name, $category, $meaning, $description, $topicSlug]) {
            $existing = Sign::where('slug', $slug)->first();

            // Só se cria o registo se houver um SVG correspondente, para não
            // gerar sinais sem imagem — inúteis no treino de reconhecimento.
            $filePath = $existing?->file_path ?: '/images/signs/'.$slug.'.svg';
            if (! $existing && ! File::exists(public_path(ltrim($filePath, '/')))) {
                $this->command?->warn("Sinal '{$slug}' ignorado: falta o ficheiro public{$filePath}");

                continue;
            }

            Sign::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'category' => $category,
                'topic_id' => $topics[$topicSlug] ?? null,
                'meaning' => $meaning,
                'description' => $description,
                'file_path' => $filePath,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedGlossary(): void
    {
        $terms = [
            ['berma', 'Faixa lateral da via, fora da faixa de rodagem, não destinada à circulação normal de veículos.'],
            ['faixa-de-rodagem', 'Parte da via destinada à circulação de veículos, podendo comportar uma ou mais vias de trânsito.'],
            ['via-de-transito', 'Cada uma das faixas longitudinais em que a faixa de rodagem se divide, com largura suficiente para uma fila de veículos.'],
            ['localidade', 'Zona edificada com entrada e saída sinalizadas como tal, onde vigoram limites de velocidade mais baixos.'],
            ['passagem-de-nivel', 'Cruzamento ao mesmo nível entre uma via rodoviária e uma linha férrea.'],
            ['distancia-de-seguranca', 'Espaço que deve separar dois veículos em marcha, de modo a permitir travar sem colidir em caso de travagem brusca do que segue à frente.'],
            ['ultrapassagem', 'Manobra de passar à frente de outro veículo que circula no mesmo sentido.'],
            ['cedencia-de-passagem', 'Obrigação de deixar passar primeiro outro utilizador da via, reduzindo ou parando se necessário.'],
            ['inversao-de-marcha', 'Manobra de mudar o sentido de marcha do veículo, passando a circular em sentido contrário.'],
            ['transito-proibido', 'Proibição de circulação num determinado sentido ou via, sinalizada verticalmente.'],
        ];

        foreach ($terms as $index => [$slug, $definition]) {
            GlossaryTerm::updateOrCreate(['slug' => $slug], [
                'term' => ucfirst(str_replace('-', ' ', $slug)),
                'definition' => $definition,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedLessons(): void
    {
        $topics = Topic::pluck('id', 'slug');

        $lessons = [
            [
                'slug' => 'distancia-de-seguranca',
                'title' => 'Distância de segurança',
                'group' => 'conducao',
                'topic' => 'velocidade',
                'summary' => 'Quanto espaço deixar para o carro da frente e como calculá-lo sem contas complicadas.',
                'minutes' => 4,
                'signs' => ['velocidade-maxima-60'],
                'body' => <<<'TEXTO'
A distância de segurança é o espaço que permite travar sem bater no veículo à frente, contando com o tempo que leva a reagir.

A regra dos dois segundos é a forma mais simples de a medir. Escolha um ponto fixo na estrada — um poste, uma marca no pavimento. Quando o veículo à frente passar por ele, conte "mil e um, mil e dois". Se chegar ao ponto antes de acabar a contagem, está demasiado perto.

Porque é que a regra funciona a qualquer velocidade: quanto mais depressa circula, mais metros percorre em dois segundos. A contagem ajusta-se sozinha.

Quando é preciso aumentar a distância:
- Com chuva ou piso molhado, use quatro segundos.
- À noite ou com nevoeiro, aumente ainda mais: só pode travar para o que consegue ver.
- Atrás de veículos pesados, porque não vê o que se passa à frente deles.
- Quando alguém o segue demasiado perto — aumentar o espaço à sua frente dá-lhe margem para travar suavemente.

O erro mais comum é medir a distância pelo espaço parado, no trânsito. A distância que conta é a de quem circula: em movimento, o espaço tem de crescer com a velocidade.
TEXTO,
            ],
            [
                'slug' => 'prioridade-em-rotundas',
                'title' => 'Prioridade em rotundas',
                'group' => 'codigo',
                'topic' => 'prioridade',
                'summary' => 'Quem passa primeiro, que via escolher e quando sinalizar.',
                'minutes' => 5,
                'signs' => ['rotunda', 'cedencia-passagem'],
                'body' => <<<'TEXTO'
Na rotunda, quem já circula no anel tem, em regra, prioridade sobre quem quer entrar. Quem entra cede a passagem.

Antes de entrar, aproxime-se devagar e olhe para a esquerda. Não é preciso parar se o anel estiver livre, mas tem de conseguir parar.

Escolha da via, quando a rotunda tem mais do que uma:
- Para sair na primeira saída, mantenha-se pela direita.
- Para seguir em frente, use normalmente a via da direita, salvo indicação em contrário nas marcas do pavimento.
- Para sair nas últimas saídas ou inverter a marcha, aproxime-se pela esquerda e desloque-se para a direita à medida que se aproxima da saída.

Sinalização: não sinalize à entrada quando vai seguir em frente. Sinalize sempre para a direita antes de sair — é o que avisa quem espera para entrar de que a via vai ficar livre.

Cuidados frequentes: nunca mude de via dentro da rotunda sem verificar o ângulo morto, e conte com os veículos longos, que precisam de ocupar mais espaço para contornar o anel.
TEXTO,
            ],
            [
                'slug' => 'ler-os-sinais-pela-forma',
                'title' => 'Ler os sinais pela forma e pela cor',
                'group' => 'sinalizacao',
                'topic' => 'sinais_perigo',
                'summary' => 'Um atalho para reconhecer qualquer sinal, mesmo sem o ter estudado.',
                'minutes' => 3,
                'signs' => ['curva-perigosa', 'proibido-ultrapassar', 'sentido-obrigatorio', 'stop'],
                'body' => <<<'TEXTO'
A forma e a cor de um sinal dizem-lhe o tipo de mensagem antes de o interpretar em detalhe. É o atalho mais útil no exame.

Triângulo com orla vermelha: perigo. Avisa do que vem à frente e pede redução de velocidade. Não proíbe nada.

Círculo com orla vermelha: proibição. Impede ou limita uma manobra. O que está desenhado dentro é o que está proibido.

Círculo azul: obrigação. Impõe um comportamento — seguir numa direção, usar uma via.

Quadrado ou retângulo azul: indicação ou informação. Não obriga nem proíbe; informa sobre a via e os serviços.

Octógono vermelho: só existe um, o STOP. A forma é única para ser reconhecível mesmo coberta de neve ou lama.

Triângulo invertido: cedência de passagem. Também tem forma única.

Regra prática de leitura: primeiro identifique a forma para saber se é aviso, proibição ou obrigação; depois leia o símbolo para saber a que se aplica; por último, procure painéis complementares abaixo, que limitam o alcance do sinal.
TEXTO,
            ],
            [
                'slug' => 'primeiros-socorros-no-local',
                'title' => 'Primeiros socorros no local do acidente',
                'group' => 'primeiros_socorros',
                'topic' => 'primeiros_socorros',
                'summary' => 'A sequência que evita fazer mais mal do que bem: proteger, alertar, socorrer.',
                'minutes' => 5,
                'signs' => [],
                'body' => <<<'TEXTO'
A sequência é sempre a mesma: proteger, alertar, socorrer. Por esta ordem.

Proteger. Antes de tudo, evite um segundo acidente. Pare em local seguro, ligue as luzes de emergência, vista o colete refletor antes de sair do veículo e coloque o triângulo a distância suficiente para os outros condutores travarem a tempo. Desligue a ignição dos veículos envolvidos, se conseguir chegar em segurança.

Alertar. Ligue para o número nacional de emergência. Diga com clareza: onde é, quantos veículos, quantos feridos e se há pessoas presas ou inconscientes. Não desligue antes de a central o autorizar — podem dar indicações enquanto a ajuda se desloca.

Socorrer. Regra fundamental: não desloque um ferido, salvo se houver risco imediato de incêndio ou de novo atropelamento. Movimentar alguém com lesão na coluna pode agravar a lesão de forma irreversível.

O que pode fazer com segurança:
- Falar com o ferido para avaliar se está consciente.
- Se estiver inconsciente mas a respirar, mantenha as vias respiratórias livres.
- Comprimir uma hemorragia com um pano limpo, sem retirar objetos encravados.
- Cobrir o ferido para evitar perda de calor.

O que não deve fazer: dar água ou comida, retirar o capacete de um motociclista, ou tentar recolocar membros em posição.
TEXTO,
            ],
            [
                'slug' => 'verificacoes-antes-de-conduzir',
                'title' => 'Verificações antes de conduzir',
                'group' => 'mecanica',
                'topic' => 'mecanica',
                'summary' => 'O que confirmar em dois minutos, e por que razão os pneus são a verificação mais importante.',
                'minutes' => 4,
                'signs' => [],
                'body' => <<<'TEXTO'
Duas verificações rápidas evitam a maior parte das avarias em viagem e muitas travagens falhadas.

Pneus. É a verificação mais importante, porque são o único contacto do veículo com a estrada. Confirme a pressão a frio, com o veículo parado há algum tempo — medir a quente dá valores mais altos e engana. Veja a profundidade do desenho: quando os indicadores no fundo dos sulcos ficam à superfície, o pneu chegou ao limite legal e perde muita capacidade de travagem em piso molhado. Procure cortes laterais e deformações.

Luzes. Com o motor ligado, confirme médios, máximos, indicadores de mudança de direção, luzes de travagem e de marcha-atrás. As luzes de travagem exigem outra pessoa a olhar, ou uma parede à retaguarda.

Níveis. Óleo do motor com o veículo em plano e o motor frio; líquido de refrigeração pelo depósito, nunca abrindo o radiador quente; líquido dos travões, que se baixa sozinho sinaliza fuga e exige oficina imediata; água do lava-vidros.

Travões. Ao arrancar, à velocidade baixa, trave uma vez. O pedal deve oferecer resistência firme. Pedal esponjoso, curso longo ou ruído metálico são motivo para não seguir viagem.

Sinais de aviso no painel: uma luz vermelha significa parar assim que for seguro; amarela significa oficina em breve.
TEXTO,
            ],
        ];

        foreach ($lessons as $index => $lesson) {
            Lesson::updateOrCreate(['slug' => $lesson['slug']], [
                'title' => $lesson['title'],
                'summary' => $lesson['summary'],
                'body' => $lesson['body'],
                'group' => $lesson['group'],
                'topic_id' => $topics[$lesson['topic']] ?? null,
                'license_categories' => [],
                'sign_slugs' => $lesson['signs'],
                'article_numbers' => [],
                'reading_minutes' => $lesson['minutes'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
