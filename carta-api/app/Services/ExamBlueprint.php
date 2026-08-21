<?php

namespace App\Services;

use App\Models\Question;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Support\Collection;

/**
 * Geração de provas por critérios, prometida no documento §7.2 e inexistente:
 * a escola tinha de escolher manualmente as 25 perguntas de cada prova.
 *
 * Dado um conjunto de critérios (categoria, tipo, temas, total), sorteia do
 * banco aprovado distribuindo as perguntas equilibradamente pelos temas.
 */
class ExamBlueprint
{
    /**
     * @param  array{category:string, type:string, topic_ids?:array<int>, question_count:int}  $criteria
     * @return Collection<int, Question>
     */
    public function build(array $criteria, ?User $actor = null): Collection
    {
        $pool = Question::query()->with('topic')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('type', $criteria['type'])
            ->whereJsonContains('categories', $criteria['category'])
            ->whereHas('topic', fn ($query) => $query->where('is_active', true))
            ->when(! empty($criteria['topic_ids']), fn ($query) => $query->whereIn('topic_id', $criteria['topic_ids']))
            // Escola só usa o banco comum e o seu próprio.
            ->when($actor?->isSchool(), fn ($query) => $query->where(fn ($nested) => $nested
                ->whereNull('school_id')->orWhere('school_id', $actor->school_id)))
            ->get();

        $target = max(1, (int) $criteria['question_count']);

        return $this->distribute($pool, $target);
    }

    /**
     * Distribui o total pedido pelos temas disponíveis, em rondas, para que
     * uma prova de 25 perguntas não saia toda do tema com mais conteúdo.
     *
     * @param  Collection<int, Question>  $pool
     * @return Collection<int, Question>
     */
    private function distribute(Collection $pool, int $target): Collection
    {
        $byTopic = $pool->groupBy(fn (Question $question) => $question->topic_id)
            ->map(fn (Collection $questions) => $questions->shuffle()->values());

        $selected = collect();
        $round = 0;

        while ($selected->count() < $target) {
            $addedThisRound = 0;

            foreach ($byTopic as $questions) {
                if ($selected->count() >= $target) {
                    break;
                }

                if ($questions->has($round)) {
                    $selected->push($questions->get($round));
                    $addedThisRound++;
                }
            }

            // Banco esgotado antes de atingir o total pedido.
            if ($addedThisRound === 0) {
                break;
            }

            $round++;
        }

        return $selected->shuffle()->values();
    }

    /** Nota de passagem derivada da regra única de classificação. */
    public function passScore(int $questionCount, string $category): int
    {
        return Grading::passScore($questionCount, $category);
    }
}
