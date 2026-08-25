<?php

namespace App\Services;

use App\Models\Classroom;
use App\Support\Grading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analítica por turma — a entrega que o documento §7.4 chama "o valor que a
 * escola paga" e que não existia: o painel mostrava apenas uma lista de
 * tentativas individuais e uma média global.
 *
 * Todos os agregados são calculados em SQL. A versão anterior do
 * ResultController fazia `->get()` de todas as tentativas só para tirar a média.
 */
class ClassroomAnalytics
{
    public function summary(Classroom $classroom): array
    {
        $row = DB::table('exam_attempts')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_attempts.exam_session_id')
            ->where('exam_sessions.classroom_id', $classroom->id)
            ->selectRaw('COUNT(*) as tentativas')
            ->selectRaw('COUNT(DISTINCT exam_attempts.student_id) as alunos_avaliados')
            ->selectRaw('AVG(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) as taxa_media')
            ->selectRaw('MAX(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) as melhor_taxa')
            ->selectRaw('MIN(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) as pior_taxa')
            ->selectRaw('SUM(CASE WHEN exam_attempts.passed = 1 THEN 1 ELSE 0 END) as aprovacoes')
            ->selectRaw('AVG(exam_attempts.duration_seconds) as tempo_medio')
            ->first();

        $attempts = (int) ($row->tentativas ?? 0);
        $averageRate = (float) ($row->taxa_media ?? 0);

        return [
            'tentativas' => $attempts,
            'alunosAvaliados' => (int) ($row->alunos_avaliados ?? 0),
            'alunosNaTurma' => $classroom->students()->where('is_active', true)->count(),
            'mediaPercentagem' => round($averageRate * 100, 1),
            'mediaValores' => round($averageRate * Grading::maxValues(), 1),
            'melhorPercentagem' => round((float) ($row->melhor_taxa ?? 0) * 100, 1),
            'piorPercentagem' => round((float) ($row->pior_taxa ?? 0) * 100, 1),
            'aprovacoes' => (int) ($row->aprovacoes ?? 0),
            'taxaAprovacao' => $attempts ? round(($row->aprovacoes / $attempts) * 100, 1) : 0.0,
            'tempoMedioSegundos' => (int) round((float) ($row->tempo_medio ?? 0)),
        ];
    }

    /**
     * Temas onde a turma erra mais — o que o instrutor precisa para preparar
     * a próxima aula. Calculado a partir de topic_breakdown de cada tentativa.
     */
    public function weakestTopics(Classroom $classroom, int $limit = 5): Collection
    {
        $rows = DB::table('exam_attempts')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_attempts.exam_session_id')
            ->where('exam_sessions.classroom_id', $classroom->id)
            ->whereNotNull('exam_attempts.topic_breakdown')
            ->pluck('exam_attempts.topic_breakdown');

        $totals = [];

        foreach ($rows as $json) {
            foreach (json_decode((string) $json, true) ?: [] as $topic => $stats) {
                $totals[$topic] ??= ['total' => 0, 'acertos' => 0];
                $totals[$topic]['total'] += (int) ($stats['total'] ?? 0);
                $totals[$topic]['acertos'] += (int) ($stats['acertos'] ?? 0);
            }
        }

        return collect($totals)
            ->map(fn (array $stats, string $topic) => [
                'tema' => $topic,
                'total' => $stats['total'],
                'acertos' => $stats['acertos'],
                'erros' => $stats['total'] - $stats['acertos'],
                'taxa' => $stats['total'] ? round($stats['acertos'] / $stats['total'] * 100, 1) : 0.0,
            ])
            ->sortBy('taxa')
            ->values()
            ->take($limit);
    }

    /** Evolução da turma sessão a sessão, para mostrar progresso ao instrutor. */
    public function progressBySession(Classroom $classroom): Collection
    {
        return DB::table('exam_attempts')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_attempts.exam_session_id')
            ->join('exams', 'exams.id', '=', 'exam_sessions.exam_id')
            ->where('exam_sessions.classroom_id', $classroom->id)
            ->groupBy('exam_sessions.id', 'exam_sessions.code', 'exams.name', 'exam_sessions.starts_at')
            ->orderBy('exam_sessions.starts_at')
            ->selectRaw('exam_sessions.code as codigo, exams.name as prova, exam_sessions.starts_at as inicio')
            ->selectRaw('COUNT(*) as submissoes')
            ->selectRaw('AVG(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) * 100 as media')
            ->selectRaw('SUM(CASE WHEN exam_attempts.passed = 1 THEN 1 ELSE 0 END) as aprovacoes')
            ->get()
            ->map(fn ($row) => [
                'codigo' => $row->codigo,
                'prova' => $row->prova,
                'inicio' => $row->inicio,
                'submissoes' => (int) $row->submissoes,
                'media' => round((float) $row->media, 1),
                'aprovacoes' => (int) $row->aprovacoes,
            ]);
    }

    /**
     * Alunos prontos vs. em risco, pelo critério de aptidão configurado
     * (nº de notas válidas ≥ mínimo em valores).
     */
    public function studentReadiness(Classroom $classroom): Collection
    {
        $required = Grading::requiredValidGrades();

        return DB::table('students')
            ->leftJoin('exam_attempts', 'exam_attempts.student_id', '=', 'students.id')
            ->where('students.classroom_id', $classroom->id)
            ->where('students.is_active', true)
            ->groupBy('students.id', 'students.name')
            ->orderBy('students.name')
            ->selectRaw('students.id, students.name as nome')
            ->selectRaw('COUNT(exam_attempts.id) as tentativas')
            ->selectRaw('AVG(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) as taxa_media')
            ->selectRaw('SUM(CASE WHEN '.Grading::validGradeSql('exam_attempts').' THEN 1 ELSE 0 END) as notas_validas')
            ->get()
            ->map(function ($row) use ($required) {
                $valid = (int) $row->notas_validas;
                $rate = (float) ($row->taxa_media ?? 0);

                return [
                    'id' => (int) $row->id,
                    'nome' => $row->nome,
                    'tentativas' => (int) $row->tentativas,
                    'mediaPercentagem' => round($rate * 100, 1),
                    'mediaValores' => round($rate * Grading::maxValues(), 1),
                    'notasValidas' => $valid,
                    'faltam' => max(0, $required - $valid),
                    'estado' => $this->readinessLabel((int) $row->tentativas, $valid, $required),
                ];
            });
    }

    private function readinessLabel(int $attempts, int $valid, int $required): string
    {
        if ($attempts === 0) {
            return 'sem_provas';
        }

        if ($valid >= $required) {
            return 'pronto';
        }

        return $valid > 0 ? 'em_progresso' : 'em_risco';
    }
}
