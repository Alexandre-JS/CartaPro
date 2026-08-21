<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Student;
use App\Services\ExamScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentExamController extends Controller
{
    public function __construct(private readonly ExamScorer $scorer) {}

    public function entry(string $code): View
    {
        $session = ExamSession::with(['exam', 'classroom'])->where('code', strtoupper($code))->firstOrFail();

        return view('student-exam.entry', compact('session'));
    }

    public function enter(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:150'], 'code' => ['required', 'string', 'size:6']]);
        $session = ExamSession::with('classroom')->where('code', strtoupper($code))->firstOrFail();
        if (strtoupper($data['code']) !== $session->code) {
            throw ValidationException::withMessages(['code' => 'O código informado não corresponde a esta sessão.']);
        }
        if ($session->status !== 'in_progress') {
            throw ValidationException::withMessages(['code' => 'A sessão ainda não foi iniciada ou já terminou.']);
        }
        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $students = Student::where('classroom_id', $session->classroom_id)->where('is_active', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->get();
        if ($students->count() > 1) {
            throw ValidationException::withMessages(['name' => 'Existem alunos com o mesmo nome nesta turma. Solicite à escola a diferenciação do cadastro.']);
        }
        $student = $students->first() ?: Student::create(['classroom_id' => $session->classroom_id, 'name' => $name, 'is_active' => true]);
        abort_if(ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(), 409, 'Esta prova já foi submetida por este estudante.');

        return redirect(URL::temporarySignedRoute('student-exam.take', now()->addHours(4), ['code' => $session->code, 'student' => $student->id]));
    }

    public function take(string $code, Student $student): View
    {
        $session = $this->activeSession($code, $student);
        abort_if(ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(), 409, 'Esta prova já foi submetida.');
        $submitUrl = URL::temporarySignedRoute('student-exam.submit', now()->addHours(4), ['code' => $session->code, 'student' => $student->id]);

        return view('student-exam.take', compact('session', 'student', 'submitUrl'));
    }

    public function submit(Request $request, string $code, Student $student): View
    {
        $session = $this->activeSession($code, $student);
        abort_if(ExamAttempt::where(['exam_session_id' => $session->id, 'student_id' => $student->id])->exists(), 409, 'Esta prova já foi submetida.');
        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'tempo_segundos' => ['nullable', 'integer', 'min:0'],
        ]);

        // Mesma correção usada pela API — antes cada via tinha a sua cópia.
        $attempt = $this->scorer->score($session, $student, $data['answers'] ?? [], $data['tempo_segundos'] ?? null);

        return view('student-exam.result', [
            'session' => $session,
            'student' => $student,
            'score' => $attempt->score,
            'total' => $attempt->total,
            'percentage' => $attempt->percentage(),
            'values' => $attempt->gradeValues(),
            'passed' => $attempt->passed,
            'weakTopics' => $attempt->weak_topics,
        ]);
    }

    private function activeSession(string $code, Student $student): ExamSession
    {
        $session = ExamSession::with('exam.questions.topic')->where('code', strtoupper($code))->firstOrFail();
        abort_unless($session->status === 'in_progress', 409, 'A sessão não está em curso.');
        abort_unless($student->is_active && $student->classroom_id === $session->classroom_id, 403);

        return $session;
    }
}
