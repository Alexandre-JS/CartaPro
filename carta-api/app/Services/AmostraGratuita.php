<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Exam;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Sign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Define o que o plano gratuito mostra.
 *
 * A regra é **posicional, não por consumo**: as primeiras N de cada grupo ficam
 * livres e o resto fecha, igual para toda a gente. A alternativa — uma quota
 * que o aluno gasta ao abrir conteúdo — parece mais generosa mas custa caro:
 * obrigaria a um pacote por utilizador (hoje é um artefacto partilhado e em
 * cache) ou a esconder o conteúdo no cliente, e aí deixaria de ser um cadeado
 * — o conteúdo passaria a ser extraível. E dois alunos veriam cadeados
 * diferentes, o que é impossível de explicar no apoio ao cliente.
 *
 * Assim o aluno prova todos os temas e encontra o cadeado onde está fraco, que
 * é exactamente onde a compra faz sentido.
 */
class AmostraGratuita
{
    /**
     * As frentes que se podem abrir ou fechar.
     *
     * `grupo` é a coluna por que se conta o "primeiras N": as perguntas contam
     * por tema, os sinais por categoria, as fichas por grupo e os artigos por
     * capítulo. As provas e o glossário não têm subdivisão — contam de uma vez.
     */
    private const FRENTES = [
        'perguntas' => [Question::class, 'topic_id'],
        'sinais' => [Sign::class, 'category'],
        'fichas' => [Lesson::class, 'group'],
        'exames' => [Exam::class, null],
        'artigos' => [Article::class, 'chapter_number'],
        'glossario' => [GlossaryTerm::class, null],
    ];

    /** Conta o efeito da regra sem gravar nada. */
    public function simular(array $limites): array
    {
        return $this->calcular($limites);
    }

    /** Aplica a regra. */
    public function aplicar(array $limites): array
    {
        $plano = $this->calcular($limites);

        DB::transaction(function () use ($plano) {
            foreach ($plano as $frente => $resultado) {
                [$modelo] = self::FRENTES[$frente];

                $modelo::query()->whereIn('id', $resultado['ids'])->update(['is_locked' => false]);
                $modelo::query()->whereNotIn('id', $resultado['ids'] ?: [0])->update(['is_locked' => true]);
            }
        });

        return $plano;
    }

    private function calcular(array $limites): array
    {
        $plano = [];

        foreach (self::FRENTES as $frente => [$modelo, $grupo]) {
            if (! array_key_exists($frente, $limites)) {
                continue;
            }

            $limite = max(0, (int) $limites[$frente]);

            $plano[$frente] = $frente === 'exames'
                ? $this->provasJogaveis($limite, $plano['perguntas']['ids'] ?? null)
                : $this->porGrupo($modelo::query(), $grupo, $limite);
        }

        return $plano;
    }

    /**
     * Provas que um aluno gratuito consegue mesmo fazer.
     *
     * Uma prova é servida inteira ou não é servida, pelo que basta uma pergunta
     * bloqueada para a fechar. Abrir "as duas primeiras" por ordem de id daria
     * duas provas que o aluno vê mas não consegue abrir — pior do que não as
     * ter. Abrem-se as primeiras cujas perguntas estejam **todas** livres.
     *
     * `$perguntasLivres` vem do cálculo das perguntas, que corre antes desta
     * frente: sem isso ler-se-ia o `is_locked` antigo e a conta saía errada na
     * simulação.
     */
    private function provasJogaveis(int $limite, ?array $perguntasLivres): array
    {
        $provas = Exam::query()->orderBy('id')->with('questions:id')->get();
        $livres = [];

        foreach ($provas as $prova) {
            if (count($livres) >= $limite) {
                break;
            }

            $ids = $prova->questions->pluck('id');

            $jogavel = $ids->isNotEmpty() && $ids->every(
                fn (int $id) => $perguntasLivres === null
                    ? ! Question::whereKey($id)->value('is_locked')
                    : in_array($id, $perguntasLivres, true),
            );

            if ($jogavel) {
                $livres[] = $prova->id;
            }
        }

        return [
            'total' => $provas->count(),
            'livres' => count($livres),
            'bloqueados' => $provas->count() - count($livres),
            'grupos' => 1,
            'ids' => $livres,
        ];
    }

    /**
     * Escolhe as `$limite` primeiras de cada grupo.
     *
     * A ordem é a mesma que o aluno vê no app — `sort_order` quando existe, e
     * o id a desempatar —, para que "as primeiras cinco" sejam mesmo as cinco
     * que ele encontra primeiro, e não cinco quaisquer.
     */
    private function porGrupo(Builder $query, ?string $grupo, int $limite): array
    {
        $tabela = $query->getModel()->getTable();
        $colunas = ['id'];

        if ($grupo) {
            $colunas[] = $grupo;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'sort_order')) {
            $query->orderBy('sort_order');
        }

        $itens = $query->orderBy('id')->get($colunas);
        $livres = [];

        foreach ($itens->groupBy(fn ($item) => $grupo ? ($item->{$grupo} ?? 'sem_grupo') : 'tudo') as $doGrupo) {
            $livres = array_merge($livres, $doGrupo->take($limite)->pluck('id')->all());
        }

        return [
            'total' => $itens->count(),
            'livres' => count($livres),
            'bloqueados' => $itens->count() - count($livres),
            'grupos' => $grupo ? $itens->groupBy($grupo)->count() : 1,
            'ids' => $livres,
        ];
    }
}
