<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamSessionController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $request->user()->school_id;

        return view('admin.sessions.index', [
            'sessions' => ExamSession::with(['exam.school', 'classroom'])->withCount('attempts')
                ->when($request->user()->isSchool(), fn ($query) => $query->whereHas('exam', fn ($exam) => $exam->where('school_id', $schoolId)))
                ->when($request->user()->isInstructor(), fn ($query) => $query->whereHas('classroom.instructors', fn ($instructors) => $instructors->where('user_id', $request->user()->id)))
                ->latest()->paginate(15),
            'exams' => Exam::with('school')->where('is_active', true)->where('is_public', false)->whereNotNull('school_id')->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $schoolId))->orderBy('name')->get(),
            'classrooms' => Classroom::with('school')->where('is_active', true)->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $schoolId))->when($request->user()->isInstructor(), fn ($query) => $query->whereHas('instructors', fn ($instructors) => $instructors->where('user_id', $request->user()->id)))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['exam_id' => ['required', 'exists:exams,id'], 'classroom_id' => ['required', 'exists:classrooms,id']]);
        $exam = Exam::findOrFail($data['exam_id']);
        $classroom = Classroom::findOrFail($data['classroom_id']);
        if ($exam->school_id !== $classroom->school_id) {
            throw ValidationException::withMessages(['classroom_id' => 'Selecione uma turma da mesma escola da prova.']);
        }
        abort_if($request->user()->isSchool() && $exam->school_id !== $request->user()->school_id, 403);
        abort_unless($request->user()->canAccessClassroom($classroom), 403);
        do {
            $code = Str::upper(Str::random(6));
        } while (ExamSession::where('code', $code)->exists());
        ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => $code, 'status' => 'scheduled']);

        return back()->with('status', 'Sessão criada com o código '.$code.'.');
    }

    public function update(Request $request, ExamSession $session): RedirectResponse
    {
        $this->assertAccess($request, $session);
        $status = $request->validate(['status' => ['required', 'in:in_progress,finished']])['status'];
        $session->update([
            'status' => $status,
            'starts_at' => $status === 'in_progress' ? ($session->starts_at ?: now()) : $session->starts_at,
            'ends_at' => $status === 'finished' ? now() : null,
        ]);

        return back()->with('status', $status === 'in_progress' ? 'Sessão iniciada.' : 'Sessão terminada.');
    }

    public function destroy(Request $request, ExamSession $session): RedirectResponse
    {
        $this->assertAccess($request, $session);
        $session->delete();

        return back()->with('status', 'Sessão removida.');
    }

    private function assertAccess(Request $request, ExamSession $session): void
    {
        abort_unless($request->user()->canAccessClassroom($session->classroom), 403);
    }
}
