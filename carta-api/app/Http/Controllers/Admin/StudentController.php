<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function show(Request $request, Student $student): View
    {
        $this->assertAccess($request, $student->classroom);
        $student->load(['classroom.school', 'attempts' => fn ($query) => $query->with(['session.exam', 'session.classroom'])->latest('submitted_at')]);
        $validAttempts = $student->attempts->filter->qualifiesForAptitude();
        $validCount = $validAttempts->count();

        return view('admin.students.show', [
            'student' => $student,
            'validCount' => $validCount,
            'isApt' => $validCount >= 3,
            'remaining' => max(0, 3 - $validCount),
            'averageValues' => $student->attempts->count() ? round($student->attempts->avg(fn ($attempt) => $attempt->gradeValues()), 1) : 0,
        ]);
    }

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->assertAccess($request, $classroom);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'identifier' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $classroom->students()->create($data + ['is_active' => true]);

        return back()->with('status', 'Aluno adicionado à turma.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->assertAccess($request, $student->classroom);
        $student->delete();

        return back()->with('status', 'Aluno removido.');
    }

    private function assertAccess(Request $request, Classroom $classroom): void
    {
        abort_if($request->user()->isSchool() && $classroom->school_id !== $request->user()->school_id, 403);
    }
}
