<?php

namespace App\Console\Commands;

use App\Models\ExamAttempt;
use App\Services\ExamScorer;
use App\Support\Grading;
use Illuminate\Console\Command;

/**
 * Preenche `topic_breakdown` nas tentativas anteriores à telemetria por tema.
 *
 * Sem isto, a analítica por turma aparece vazia para as escolas que já tinham
 * aplicado provas: o painel só sabe calcular "temas onde a turma erra mais" a
 * partir deste campo. As respostas já estavam guardadas, pelo que o
 * diagnóstico é recalculável sem perda.
 */
class BackfillTopicBreakdown extends Command
{
    protected $signature = 'cartapro:backfill-breakdown {--dry-run : Mostra o que faria sem gravar}';

    protected $description = 'Recalcula o diagnóstico por tema das tentativas antigas';

    public function handle(ExamScorer $scorer): int
    {
        $seco = (bool) $this->option('dry-run');
        $atualizadas = 0;
        $ignoradas = 0;

        ExamAttempt::with('session.exam.questions.topic')
            ->whereNull('topic_breakdown')
            ->chunkById(200, function ($tentativas) use ($scorer, $seco, &$atualizadas, &$ignoradas): void {
                foreach ($tentativas as $tentativa) {
                    $exame = $tentativa->session?->exam;

                    if (! $exame || $exame->questions->isEmpty()) {
                        $ignoradas++;

                        continue;
                    }

                    $breakdown = $scorer->breakdown($exame, $tentativa->answers ?? []);
                    $limiar = Grading::passPercentage($exame->gradingCategory()) / 100;

                    $fracos = [];
                    foreach ($breakdown as $tema => $stats) {
                        if ($stats['total'] >= 2 && $stats['taxa'] < $limiar) {
                            $fracos[$tema] = $stats['taxa'];
                        }
                    }
                    asort($fracos);

                    if (! $seco) {
                        $tentativa->forceFill([
                            'topic_breakdown' => $breakdown,
                            'weak_topics' => array_keys($fracos),
                        ])->saveQuietly();
                    }

                    $atualizadas++;
                }
            });

        $this->info(($seco ? '[simulação] ' : '')."Tentativas recalculadas: {$atualizadas}. Sem prova associada: {$ignoradas}.");

        return self::SUCCESS;
    }
}
