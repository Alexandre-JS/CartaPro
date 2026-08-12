<?php

namespace App\Support;

/**
 * Leitor do banco de perguntas do INATRO em Markdown.
 *
 * Vive separado do comando de importação de propósito: ler o ficheiro e gravar
 * na base de dados são dois problemas distintos, e só o primeiro é que precisa
 * de ser exercitado com dezenas de casos limite (opções com traços, sinais com
 * vários códigos, temas que ainda não existem). Assim testa-se o parser sem
 * base de dados e o comando fica com uma só responsabilidade.
 *
 * O formato esperado, por pergunta:
 *
 *     **7.** Enunciado da pergunta:
 *     > IMAGEM DO SINAL ▸ N29 – «Báscula»          (opcional)
 *     - **A)** Primeira alínea
 *     - **B)** Segunda alínea ✔
 *     *Tema — Art. 127 CE*
 *
 * Nada aqui aborta a leitura: os problemas são acumulados em `erros()` com o
 * número da linha, para o comando poder mostrar tudo de uma vez em vez de
 * obrigar a corrigir o ficheiro uma pergunta de cada vez.
 */
class BancoPerguntas
{
    /** Marca da alínea certa no ficheiro de trabalho do instrutor. */
    private const CERTA = '✔';

    /** @var list<array<string, mixed>> */
    private array $perguntas = [];

    /** @var list<string> */
    private array $erros = [];

    public function __construct(string $conteudo)
    {
        $this->ler($conteudo);
    }

    public static function deFicheiro(string $caminho): self
    {
        return new self((string) file_get_contents($caminho));
    }

    /** @return list<array<string, mixed>> */
    public function perguntas(): array
    {
        return $this->perguntas;
    }

    /** @return list<string> */
    public function erros(): array
    {
        return $this->erros;
    }

    /** Números dos exames encontrados, pela ordem em que aparecem. */
    public function exames(): array
    {
        return array_values(array_unique(array_column($this->perguntas, 'exame')));
    }

    private function ler(string $conteudo): void
    {
        $exame = 0;
        $atual = null;

        foreach (preg_split('/\R/u', $conteudo) as $indice => $linha) {
            $numeroLinha = $indice + 1;
            $linha = trim($linha);

            if (preg_match('/^##\s+Exame\s+n\.º\s*(\d+)/u', $linha, $m)) {
                $this->fechar($atual);
                $exame = (int) $m[1];

                continue;
            }

            // Início de pergunta. O `**N.**` só aparece nesta posição, pelo que
            // serve de fronteira fiável entre perguntas.
            if (preg_match('/^\*\*(\d+)\.\*\*\s*(.*)$/u', $linha, $m)) {
                $this->fechar($atual);
                $atual = [
                    'exame' => $exame,
                    'numero' => (int) $m[1],
                    'linha' => $numeroLinha,
                    'enunciado' => trim($m[2]),
                    'sinal' => null,
                    'opcoes' => [],
                    'correta' => null,
                    'tema' => null,
                    'referencia' => null,
                ];

                continue;
            }

            if ($atual === null) {
                continue;
            }

            if (preg_match('/^>\s*IMAGEM DO SINAL\s*[▸►>]\s*(.+)$/u', $linha, $m)) {
                $atual['sinal'] = $this->lerSinal(trim($m[1]));

                continue;
            }

            if (preg_match('/^-\s*\*\*([A-Z])\)\*\*\s*(.+)$/u', $linha, $m)) {
                $texto = trim($m[2]);

                if (str_contains($texto, self::CERTA)) {
                    if ($atual['correta'] !== null) {
                        $this->erros[] = "Linha {$numeroLinha}: exame {$atual['exame']}, pergunta {$atual['numero']} tem mais do que uma alínea assinalada.";
                    }

                    $atual['correta'] = count($atual['opcoes']);
                    $texto = trim(str_replace(self::CERTA, '', $texto));
                }

                $atual['opcoes'][] = $texto;

                continue;
            }

            // Rodapé do tema em itálico simples — `*Tema — Art. 127 CE*`. O
            // `[^*]` distingue-o dos títulos a negrito, que começam por `**`.
            if (preg_match('/^\*([^*].*?)\s+[—–-]\s+(.+?)\*$/u', $linha, $m)) {
                $atual['tema'] = trim($m[1]);
                $atual['referencia'] = trim($m[2]);
                $this->fechar($atual);
                $atual = null;

                continue;
            }

            // Continuação do enunciado antes das alíneas: enunciados partidos em
            // duas linhas continuariam a ler-se bem.
            if ($linha !== '' && $atual['opcoes'] === []) {
                $atual['enunciado'] = trim($atual['enunciado'].' '.$linha);
            }
        }

        $this->fechar($atual);
    }

    /**
     * Fecha a pergunta em curso, validando-a.
     *
     * @param  array<string, mixed>|null  $pergunta
     */
    private function fechar(?array &$pergunta): void
    {
        if ($pergunta === null) {
            return;
        }

        $onde = "Linha {$pergunta['linha']}: exame {$pergunta['exame']}, pergunta {$pergunta['numero']}";

        if ($pergunta['enunciado'] === '') {
            $this->erros[] = "{$onde} não tem enunciado.";
        }

        if (count($pergunta['opcoes']) < 2) {
            $this->erros[] = "{$onde} tem menos de duas alíneas.";
        }

        if ($pergunta['correta'] === null) {
            $this->erros[] = "{$onde} não tem alínea assinalada com ".self::CERTA.'.';
        }

        if ($pergunta['tema'] === null) {
            $this->erros[] = "{$onde} não tem a linha do tema e do artigo.";
        }

        $this->perguntas[] = $pergunta;
        $pergunta = null;
    }

    /**
     * Separa a linha do sinal em códigos e nomes oficiais.
     *
     * Há linhas com mais do que um sinal («P1 e P2 – «Linha …» e «linha …»») e
     * linhas só com o código («Q7»). Os nomes são extraídos primeiro e retirados
     * do texto, para que um nome que contenha letras e algarismos não seja
     * confundido com um código.
     *
     * @return array{codigos: list<string>, nomes: list<string>, linha: string}
     */
    private function lerSinal(string $linha): array
    {
        preg_match_all('/«(.+?)»/u', $linha, $nomes);
        $semNomes = preg_replace('/«.+?»/u', '', $linha);
        preg_match_all('/\b([A-Z]{1,2}\d{1,3})\b/u', (string) $semNomes, $codigos);

        return [
            'codigos' => array_values(array_unique($codigos[1])),
            'nomes' => array_map(trim(...), $nomes[1]),
            'linha' => $linha,
        ];
    }
}
