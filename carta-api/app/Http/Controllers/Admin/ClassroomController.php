<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $classrooms = Classroom::with(['school', 'students'])->withCount(['students', 'sessions'])
            ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
            ->when($request->user()->isInstructor(), fn ($query) => $query->whereHas('instructors', fn ($instructors) => $instructors->where('user_id', $request->user()->id)))
            ->when($request->filled('school_id'), fn ($query) => $query->where('school_id', $request->integer('school_id')))
            ->latest()->paginate(10)->withQueryString();

        return view('admin.classrooms.index', ['classrooms' => $classrooms, 'schools' => School::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Classroom::create($this->validated($request));

        return back()->with('status', 'Turma criada.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->assertAccess($request, $classroom);
        $classroom->update($this->validated($request, $classroom));

        return back()->with('status', 'Turma atualizada.');
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->assertAccess($request, $classroom);
        $classroom->delete();

        return back()->with('status', 'Turma removida.');
    }

    private function validated(Request $request, ?Classroom $classroom = null): array
    {
        $schoolId = $request->user()->isSchool() ? $request->user()->school_id : $request->integer('school_id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('classrooms')->where('school_id', $schoolId)->ignore($classroom)],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        abort_unless($schoolId, 422, 'Selecione a escola.');
        $data['school_id'] = $schoolId;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function assertAccess(Request $request, Classroom $classroom): void
    {
        abort_unless($request->user()->canAccessClassroom($classroom), 403);
    }
}
