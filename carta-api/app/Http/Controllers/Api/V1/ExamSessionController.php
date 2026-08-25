<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Student;
use App\Services\ExamScorer;
use App\Support\ExamTicket;
use App\Support\Grading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Prova da escola pela API.
 *
 * Reescrito por causa de duas falhas graves da versão anterior:
 *  - `GET /sessions/{code}` devolvia publicamente a pauta completa da turma
 *    (nomes e identificadores de alunos, muitos deles menores);
 *  - `POST /sessions/{code}/submit` aceitava qualquer `student_id` da turma sem
 *    autenticar o aluno, permitindo submeter — e queimar a única tentativa —
 *    em nome de outra pessoa.
 *
 * O fluxo passa a espelhar o da web: identificação primeiro, bilhete cifrado
 * por aluno depois, e só então leitura das perguntas e submissão.
 */
class ExamSessionController extends Controller
{
    public function __construct(private readonly ExamScorer $scorer) {}

    /** Metadados mínimos para o ecrã de entrada. Sem alunos, sem perguntas. */
    public function show(string $code): JsonResponse
    {
        $session = ExamSession::with(['exam', 'classroom'])->where('code', strtoupper($code))->firstOrFail();

        return response()->json([
            'codigo' => $session->code,
            'estado' => $session->status,
            'aberta' => $session->status === 'in_progress',
            'prova' => [
                'nome' => $session->exam->name,
                'perguntas' => $session->exam->questions()->count(),
                'minutos' => $session->exam->duration_minutes,
            ],
            'turma' => ['nome' => $session->classroom->name],
        ]);
    }

    /** Identificação do aluno → bilhete cifrado válido 4 horas. */
    public function enter(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['nome' => ['required', 'string', 'min:3', 'max:150']]);

        $session = ExamSession::with('classroom')->where('code', strtoupper($code))->firstOrFail();
        abort_unless($session->status === 'in_progress', 409, 'A sessão não está em curso.');

        $name = trim(preg_replace('/\s+/', ' ', $data['nome']));
        $students = Student::where('classroom_id', $session->classroom_id)->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->get();

        if ($students->count() > 1) {
            throw ValidationException::withMessages(['nome' => 'Existem alunos com o mesmo nome nesta turma. Peça à escola para diferenciar o cadastro.']);
        }

        $student = $students->first();
        abort_unless($student, 404, 'Não encontrámos este nome na pauta da turma. Confirme com a escola.');

        abort_if(
            ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(),
            409,
            'Esta prova já foi submetida com este nome.',
        );

        return response()->json([
            'bilhete' => ExamTicket::issue($session->id, $student->id),
            'aluno' => ['nome' => $student->name],
            'expiraEmHoras' => 4,
        ]);
    }

    /** Perguntas da prova — exige bilhete. Nunca devolve a resposta correta. */
    public function questions(Request $request, string $code): JsonResponse
    {
        [$session, $student] = $this->authorizeTicket($request, $code);

        abort_if(
            ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(),
            409,
            'Esta prova já foi submetida.',
        );

        $session->load(['exam.questions.topic', 'exam.questions.sign']);
        $category = $session->exam->gradingCategory();
        $total = $session->exam->questions->count();

        return response()->json([
            'codigo' => $session->code,
            'aluno' => ['nome' => $student->name],
            'prova' => [
                'nome' => $session->exam->name,
                'minutos' => $session->exam->duration_minutes,
                'notaPassagem' => $session->exam->pass_score ?: Grading::passScore($total, $category),
                'percentagemPassagem' => Grading::passPercentage($category),
            ],
            'perguntas' => $session->exam->questions->map(fn ($question) => [
                'id' => $question->external_id,
                'tema' => $question->topic->slug,
                'enunciado' => $question->statement,
                'imagem' => $question->imagemPublica() ? url($question->imagemPublica()) : null,
                'opcoes' => $question->options,
            ])->values(),
        ]);
    }

    public function submit(Request $request, string $code): JsonResponse
    {
        [$session, $student] = $this->authorizeTicket($request, $code);

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'tempoSegundos' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_if(
            ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(),
            409,
            'Esta prova já foi submetida.',
        );

        $session->load(['exam.questions.topic', 'exam.questions.sign']);
        $attempt = $this->scorer->score($session, $student, $data['answers'], $data['tempoSegundos'] ?? null);

        return response()->json([
            'pontuacao' => $attempt->score,
            'total' => $attempt->total,
            'percentagem' => $attempt->percentage(),
            'valores' => $attempt->gradeValues(),
            'notaPassagem' => $session->exam->pass_score,
            'aprovado' => $attempt->passed,
            'contaParaAptidao' => $attempt->qualifiesForAptitude(),
            'temasFracos' => $attempt->weak_topics,
            'detalhePorTema' => $attempt->topic_breakdown,
        ], 201);
    }

    /** @return array{0: ExamSession, 1: Student} */
    private function authorizeTicket(Request $request, string $code): array
    {
        $ticket = ExamTicket::parse($request->header('X-Exam-Ticket') ?: $request->string('bilhete')->value());
        abort_unless($ticket, 401, 'Bilhete de prova inválido ou expirado.');

        $session = ExamSession::with('classroom')->where('code', strtoupper($code))->firstOrFail();
        abort_unless($session->id === $ticket->sessionId, 403, 'O bilhete não pertence a esta sessão.');
        abort_unless($session->status === 'in_progress', 409, 'A sessão não está em curso.');

        $student = Student::where('id', $ticket->studentId)
            ->where('classroom_id', $session->classroom_id)
            ->where('is_active', true)
            ->firstOrFail();

        return [$session, $student];
    }
}
