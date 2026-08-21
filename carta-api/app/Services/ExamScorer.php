<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Student;
use App\Support\Grading;

/**
 * Correção única das provas da escola (web e API usavam cópias divergentes).
 *
 * Diferença importante face à versão anterior: `weak_topics` já não é
 * "qualquer tema com ≥1 erro" — 9/10 em sinais deixava o tema marcado como
 * fraco. Passa a ser calculado por taxa de acerto, com o mesmo limiar de
 * aprovação, e é acompanhado de `topic_breakdown` com os números por tema.
 */
class ExamScorer
{
    public function score(ExamSession $session, Student $student, array $answers, ?int $durationSeconds = null): ExamAttempt
    {
        $exam = $session->exam;
        $breakdown = $this->breakdown($exam, $answers);
        $score = array_sum(array_column($breakdown, 'acertos'));
        $total = $exam->questions->count();
        $category = $exam->gradingCategory();

        return ExamAttempt::create([
            'exam_session_id' => $session->id,
            'student_id' => $student->id,
            'answers' => $answers,
            'score' => $score,
            'total' => $total,
            'passed' => Grading::passed($score, $total, $category),
            'weak_topics' => $this->weakTopics($breakdown, $category),
            'topic_breakdown' => $breakdown,
            'duration_seconds' => $durationSeconds,
            'submitted_at' => now(),
        ]);
    }

    /** @return array<string, array{total:int, acertos:int, taxa:float}> */
    public function breakdown(Exam $exam, array $answers): array
    {
        $breakdown = [];

        foreach ($exam->questions as $question) {
            $topic = $question->topic->slug;
            $breakdown[$topic] ??= ['total' => 0, 'acertos' => 0, 'taxa' => 0.0];
            $breakdown[$topic]['total']++;

            if ($this->isCorrect($question, $answers[$question->external_id] ?? null)) {
                $breakdown[$topic]['acertos']++;
            }
        }

        foreach ($breakdown as $topic => $stats) {
            $breakdown[$topic]['taxa'] = round($stats['acertos'] / max(1, $stats['total']), 3);
        }

        return $breakdown;
    }

    public function isCorrect(Question $question, mixed $answer): bool
    {
        return $answer !== null && is_numeric($answer) && (int) $answer === $question->correct_index;
    }

    /**
     * Tema fraco = taxa abaixo do limiar de aprovação, com pelo menos duas
     * perguntas no tema (uma pergunta não é amostra suficiente para acusar).
     */
    private function weakTopics(array $breakdown, ?string $category): array
    {
        $threshold = Grading::passPercentage($category) / 100;
        $weak = [];

        foreach ($breakdown as $topic => $stats) {
            if ($stats['total'] >= 2 && $stats['taxa'] < $threshold) {
                $weak[$topic] = $stats['taxa'];
            }
        }

        asort($weak);

        return array_keys($weak);
    }
}
