<?php

declare(strict_types=1);

use App\Support\BancoPerguntas;

require dirname(__DIR__).'/vendor/autoload.php';

$origem = $argv[1] ?? dirname(__DIR__).'/INATRO_Banco_Perguntas_com_Respostas.md';
$destino = $argv[2] ?? dirname(__DIR__).'/database/sql/importar-perguntas-inatro.sql';
$banco = BancoPerguntas::deFicheiro($origem);

if ($banco->erros() !== []) {
    fwrite(STDERR, implode(PHP_EOL, $banco->erros()).PHP_EOL);
    exit(1);
}

$temas = [
    'Sinais de perigo' => 'SINAL_VERT',
    'Sinais de proibição' => 'SINAL_VERT',
    'Sinais de obrigação' => 'SINAL_VERT',
    'Sinais de cedência de passagem e combinados' => 'SINAL_VERT',
    'Sinais de indicação e informação' => 'SINAL_VERT',
    'Sinalização horizontal (marcas no pavimento)' => 'SINAL_HORIZ',
    'Sinalização luminosa, agentes e sinalização temporária' => 'SINAL_VERT',
    'Velocidade' => 'VELOCIDADE',
    'Prioridade e cedência de passagem' => 'REGRAS_PRIOR',
    'Manobras' => 'MANOBRAS',
    'Paragem e estacionamento' => 'MANOBRAS',
    'Iluminação' => 'VEICULOS',
    'Trânsito de peões' => 'PEOES_CARGA',
    'Habilitação legal para conduzir' => 'CARTA_COND',
    'Veículos, matrícula, inspecção e poluição' => 'VEICULOS',
    'Transporte de passageiros, carga e segurança' => 'PEOES_CARGA',
    'Álcool, estupefacientes e aparelhos proibidos' => 'SUBST_PROIB',
    'Acidentes, avarias e socorro' => 'ACIDENTES',
    'Auto-estradas, vias reservadas e passagens de nível' => 'VIAS_ESPEC',
    'Fiscalização e contravenções' => 'INFRACCOES',
];

$texto = static fn (string $valor): string => "CONVERT(0x".bin2hex($valor).' USING utf8mb4)';
$numeroArtigo = static function (?string $referencia): ?int {
    foreach (explode('/', (string) $referencia) as $parte) {
        if (str_contains($parte, 'CE') && preg_match('/(\d+)/', $parte, $match)) {
            return (int) $match[1];
        }
    }

    return null;
};

$linhas = [];
$linhasTemas = [];
foreach ($banco->perguntas() as $pergunta) {
    $slug = $temas[$pergunta['tema']] ?? throw new RuntimeException("Tema sem mapeamento: {$pergunta['tema']}");
    $externo = sprintf('inatro-e%02d-q%02d', $pergunta['exame'], $pergunta['numero']);
    $artigo = $numeroArtigo($pergunta['referencia']);
    $opcoes = 'JSON_ARRAY('.implode(', ', array_map($texto, $pergunta['opcoes'])).')';
    $certa = $pergunta['opcoes'][$pergunta['correta']];
    $explicacao = "Resposta correcta: {$certa}.\n\nBase legal: {$pergunta['referencia']} — tema «{$pergunta['tema']}».";

    $valores = [
        "'{$externo}'",
        "'{$slug}'",
        $texto($pergunta['enunciado']),
        $opcoes,
        (string) $pergunta['correta'],
        $texto($explicacao),
        $artigo === null ? 'NULL' : (string) $artigo,
        (string) (($pergunta['exame'] - 1) * 25 + $pergunta['numero']),
    ];
    $linhas[] = $valores;
    $linhasTemas[] = array_slice($valores, 0, 2);
}

$colunas = ['external_id', 'topic_slug', 'statement', 'options', 'correct_index', 'explanation', 'article_number', 'sort_order'];
$uniao = static function (array $registos, array $nomes): string {
    return implode("\n    UNION ALL SELECT ", array_map(
        static fn (array $valores, int $indice): string => implode(', ', array_map(
            static fn (string $valor, int $coluna): string => $indice === 0 ? "{$valor} AS {$nomes[$coluna]}" : $valor,
            $valores,
            array_keys($valores),
        )),
        $registos,
        array_keys($registos),
    ));
};

$sql = <<<'SQL'
-- ProntoVia: importa somente perguntas, respostas e artigos do banco INATRO.
-- Gerado automaticamente; não cria nem altera temas, sinais ou provas.
-- Seguro para repetir: external_id identifica e actualiza a mesma pergunta.
-- As perguntas entram aprovadas para poderem ser usadas na criação de provas.

SET NAMES utf8mb4;
START TRANSACTION;

SET @prontovia_antes := (
    SELECT COUNT(*) FROM questions WHERE external_id REGEXP '^inatro-e[0-9]{2}-q[0-9]{2}$'
);

INSERT INTO questions (
    topic_id, author_id, school_id, external_id, type, categories,
    statement, image, sign_id, article_id, options, correct_index,
    explanation, article_ref, is_locked, is_active, status,
    reviewed_by, reviewed_at, rejection_reason, sort_order, created_at, updated_at
)
SELECT
    topics.id, NULL, NULL, fonte.external_id, 'teorico',
    JSON_ARRAY('ligeiro', 'pesado', 'profissional_publico'),
    fonte.statement, NULL, NULL, articles.id, fonte.options,
    fonte.correct_index, fonte.explanation, fonte.article_number,
    0, 1, 'approved', NULL, NOW(), NULL, fonte.sort_order, NOW(), NOW()
FROM (
SQL;

$sql .= "    SELECT ".$uniao($linhas, $colunas)."\n";
$sql .= <<<'SQL'
) AS fonte
INNER JOIN topics ON topics.slug = fonte.topic_slug
LEFT JOIN articles ON articles.number = fonte.article_number
ON DUPLICATE KEY UPDATE
    topic_id = VALUES(topic_id),
    external_id = VALUES(external_id),
    type = VALUES(type),
    categories = VALUES(categories),
    statement = VALUES(statement),
    article_id = VALUES(article_id),
    options = VALUES(options),
    correct_index = VALUES(correct_index),
    explanation = VALUES(explanation),
    article_ref = VALUES(article_ref),
    is_locked = 0,
    is_active = 1,
    status = 'approved',
    reviewed_at = NOW(),
    rejection_reason = NULL,
    sort_order = VALUES(sort_order),
    updated_at = NOW();

SET @prontovia_depois := (
    SELECT COUNT(*) FROM questions WHERE external_id REGEXP '^inatro-e[0-9]{2}-q[0-9]{2}$'
);

COMMIT;

SELECT
    @prontovia_antes AS perguntas_antes,
    @prontovia_depois AS perguntas_depois,
    @prontovia_depois - @prontovia_antes AS perguntas_inseridas;

-- Estes resultados devem retornar zero linhas. Se retornarem dados, corrija
-- os temas/artigos no painel e execute novamente este mesmo ficheiro.
SELECT DISTINCT fonte.topic_slug AS tema_em_falta
FROM (
SQL;

$sql .= "    SELECT ".$uniao($linhasTemas, ['external_id', 'topic_slug'])."\n";
$sql .= <<<'SQL'
) AS fonte
LEFT JOIN topics ON topics.slug = fonte.topic_slug
WHERE topics.id IS NULL;

SELECT DISTINCT q.article_ref AS artigo_ce_sem_ligacao
FROM questions q
WHERE q.external_id REGEXP '^inatro-e[0-9]{2}-q[0-9]{2}$'
  AND q.article_ref IS NOT NULL
  AND q.article_id IS NULL;
SQL;

if (! is_dir(dirname($destino))) {
    mkdir(dirname($destino), 0775, true);
}

file_put_contents($destino, $sql.PHP_EOL);
fwrite(STDOUT, count($linhas)." perguntas escritas em {$destino}.".PHP_EOL);
