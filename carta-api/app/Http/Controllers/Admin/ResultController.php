<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamAttempt;
use App\Services\ClassroomAnalytics;
use App\Support\Grading;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultController extends Controller
{
    public function __construct(private readonly ClassroomAnalytics $analytics) {}

    public function index(Request $request): View
    {
        // Agregados em SQL. Antes fazia-se `(clone $query)->get()` — carregava
        // todas as tentativas em memória só para calcular a média.
        // `score * 1.0` em vez de CAST(... AS FLOAT), que MariaDB não aceita.
        $totals = $this->query($request)->toBase()
            ->selectRaw('COUNT(*) as tentativas')
            ->selectRaw('AVG(exam_attempts.score * 1.0 / NULLIF(exam_attempts.total, 0)) as taxa_media')
            ->selectRaw('SUM(CASE WHEN '.Grading::validGradeSql().' THEN 1 ELSE 0 END) as notas_validas')
            ->first();

        return view('admin.results.index', [
            'attempts' => $this->query($request)->with(['student', 'session.exam.school', 'session.classroom'])
                ->latest('submitted_at')->paginate(10)->withQueryString(),
            'average' => (int) round((float) ($totals->taxa_media ?? 0) * 100),
            'validGradesCount' => (int) ($totals->notas_validas ?? 0),
            'attemptsCount' => (int) ($totals->tentativas ?? 0),
            'classrooms' => $this->visibleClassrooms($request),
        ]);
    }

    /** Painel por turma: médias, temas mais falhados, evolução e prontidão. */
    public function classroom(Request $request, Classroom $classroom): View
    {
        abort_unless($request->user()->canAccessClassroom($classroom), 403);

        $classroom->load('school');

        return view('admin.results.classroom', [
            'classroom' => $classroom,
            'summary' => $this->analytics->summary($classroom),
            'weakestTopics' => $this->analytics->weakestTopics($classroom),
            'progress' => $this->analytics->progressBySession($classroom),
            'readiness' => $this->analytics->studentReadiness($classroom),
            'requiredValidGrades' => Grading::requiredValidGrades(),
            'minimumValues' => Grading::minimumAptitudeValues(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Aluno', 'Turma', 'Prova', 'Pontuação', 'Total', 'Percentagem', 'Nota (0-20)', 'Conta para aptidão', 'Temas fracos', 'Data']);

            // Em blocos, para exportar turmas grandes sem esgotar a memória.
            $query->with(['student', 'session.exam', 'session.classroom'])
                ->chunkById(500, function (Collection $attempts) use ($output): void {
                    foreach ($attempts as $attempt) {
                        fputcsv($output, [
                            $attempt->student->name,
                            $attempt->session->classroom->name,
                            $attempt->session->exam->name,
                            $attempt->score,
                            $attempt->total,
                            $attempt->percentage(),
                            $attempt->gradeValues(),
                            $attempt->qualifiesForAptitude() ? 'Sim' : 'Não',
                            implode(', ', $attempt->weak_topics ?? []),
                            $attempt->submitted_at->format('d/m/Y H:i'),
                        ]);
                    }
                });

            fclose($output);
        }, 'resultados-prontovia.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request): Builder
    {
        return ExamAttempt::query()
            ->when($request->user()->isSchool(), fn (Builder $query) => $query->whereHas('session.exam', fn ($exam) => $exam->where('school_id', $request->user()->school_id)))
            ->when($request->user()->isInstructor(), fn (Builder $query) => $query->whereHas('session.classroom.instructors', fn (Builder $instructors) => $instructors->where('user_id', $request->user()->id)))
            ->when($request->filled('classroom_id'), fn (Builder $query) => $query->whereHas('session', fn ($session) => $session->where('classroom_id', $request->integer('classroom_id'))));
    }

    private function visibleClassrooms(Request $request): Collection
    {
        return Classroom::query()
            ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
            ->when($request->user()->isInstructor(), fn ($query) => $query->whereHas('instructors', fn ($instructors) => $instructors->where('user_id', $request->user()->id)))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
