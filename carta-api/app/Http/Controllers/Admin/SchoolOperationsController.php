<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Instructor;
use App\Models\MobileUser;
use App\Models\School;
use App\Models\SchoolAssignment;
use App\Models\SchoolMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $schools = $user->isAdmin() ? School::where('is_active', true)->orderBy('name')->get() : School::whereKey($user->school_id)->where('is_active', true)->get();
        $school = $schools->firstWhere('id', $request->integer('school_id')) ?: $schools->first();
        abort_unless($school, 404);
        $this->assertSchool($request, $school);

        return view('admin.school-operations.index', [
            'schools' => $schools,
            'school' => $school,
            'instructors' => $school->instructors()->with(['user', 'classrooms'])->latest()->get(),
            'classrooms' => $school->classrooms()->where('is_active', true)->orderBy('name')->get(),
            'mobileUsers' => MobileUser::where('is_active', true)->orderBy('name')->limit(500)->get(),
            'memberships' => $school->memberships()->with(['mobileUser', 'classroom'])->latest()->limit(100)->get(),
            'assignments' => $school->assignments()->with(['classroom', 'mobileUser'])->withCount('progress')->latest()->limit(100)->get(),
        ]);
    }

    public function instructorStore(Request $request): RedirectResponse
    {
        $school = $this->schoolFrom($request);
        $data = $request->validate(['name' => ['required','string','max:150'], 'email' => ['required','email','max:150','unique:users,email'], 'password' => ['required','string','min:8'], 'license_number' => ['nullable','string','max:80'], 'bio' => ['nullable','string','max:2000']]);
        DB::transaction(function () use ($data, $school): void {
            $user = User::create(['school_id' => $school->id, 'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'instructor', 'is_active' => true]);
            Instructor::create(['user_id' => $user->id, 'school_id' => $school->id, 'license_number' => $data['license_number'] ?? null, 'bio' => $data['bio'] ?? null, 'is_active' => true]);
        });
        return $this->back($school, 'Instrutor criado.');
    }

    public function instructorUpdate(Request $request, Instructor $instructor): RedirectResponse
    {
        $this->assertSchool($request, $instructor->school);
        $data = $request->validate(['name' => ['required','string','max:150'], 'email' => ['required','email','max:150', Rule::unique('users')->ignore($instructor->user_id)], 'password' => ['nullable','string','min:8'], 'license_number' => ['nullable','string','max:80'], 'bio' => ['nullable','string','max:2000'], 'is_active' => ['nullable','boolean']]);
        $instructor->user->update(array_filter(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'] ?? null, 'is_active' => $request->boolean('is_active')], fn ($v) => $v !== null));
        $instructor->update(['license_number' => $data['license_number'] ?? null, 'bio' => $data['bio'] ?? null, 'is_active' => $request->boolean('is_active')]);
        return $this->back($instructor->school, 'Instrutor atualizado.');
    }

    public function instructorAttach(Request $request, Instructor $instructor): RedirectResponse
    {
        $this->assertSchool($request, $instructor->school);
        $classroom = Classroom::whereKey($request->integer('classroom_id'))->where('school_id', $instructor->school_id)->firstOrFail();
        $instructor->classrooms()->syncWithoutDetaching([$classroom->id]);
        return $this->back($instructor->school, 'Turma atribuída ao instrutor.');
    }

    public function membershipInvite(Request $request): RedirectResponse
    {
        $school = $this->schoolFrom($request);
        $data = $request->validate(['mobile_user_id' => ['required','exists:mobile_users,id'], 'classroom_id' => ['nullable','exists:classrooms,id']]);
        $classroom = isset($data['classroom_id']) ? Classroom::whereKey($data['classroom_id'])->where('school_id', $school->id)->firstOrFail() : null;
        SchoolMembership::updateOrCreate(['school_id' => $school->id, 'mobile_user_id' => $data['mobile_user_id']], ['classroom_id' => $classroom?->id, 'status' => 'invited', 'joined_at' => null, 'left_at' => null]);
        return $this->back($school, 'Convite criado para o candidato.');
    }

    public function membershipStatus(Request $request, SchoolMembership $membership): RedirectResponse
    {
        $this->assertSchool($request, $membership->school);
        $status = $request->validate(['status' => ['required', Rule::in(['suspended','completed','left'])]])['status'];
        $membership->update(['status' => $status, 'left_at' => in_array($status, ['left','completed'], true) ? now() : null]);
        return $this->back($membership->school, 'Estado do vínculo atualizado.');
    }

    public function assignmentStore(Request $request): RedirectResponse
    {
        $school = $this->schoolFrom($request);
        $data = $request->validate(['classroom_id' => ['nullable','exists:classrooms,id'], 'mobile_user_id' => ['nullable','exists:mobile_users,id'], 'type' => ['required', Rule::in(SchoolAssignment::TYPES)], 'title' => ['required','string','max:180'], 'instructions' => ['nullable','string','max:4000'], 'due_at' => ['nullable','date','after:now']]);
        abort_if(filled($data['classroom_id'] ?? null) === filled($data['mobile_user_id'] ?? null), 422, 'Escolha uma turma ou um candidato.');
        if (! empty($data['classroom_id'])) Classroom::whereKey($data['classroom_id'])->where('school_id', $school->id)->firstOrFail();
        SchoolAssignment::create($data + ['school_id' => $school->id, 'created_by' => $request->user()->id, 'status' => 'draft']);
        return $this->back($school, 'Tarefa criada como rascunho.');
    }

    public function assignmentStatus(Request $request, SchoolAssignment $assignment): RedirectResponse
    {
        $this->assertSchool($request, $assignment->school);
        $action = $request->validate(['action' => ['required', Rule::in(['publish','close'])]])['action'];
        if ($action === 'publish') {
            abort_unless($assignment->status === 'draft', 422, 'A tarefa já foi publicada ou encerrada.');
            $assignment->update(['status' => 'published', 'published_at' => now()]);
        } else {
            abort_unless($assignment->status === 'published', 422, 'Só tarefas publicadas podem ser encerradas.');
            $assignment->update(['status' => 'closed']);
        }
        return $this->back($assignment->school, $action === 'publish' ? 'Tarefa publicada.' : 'Tarefa encerrada.');
    }

    private function schoolFrom(Request $request): School
    {
        $school = School::findOrFail($request->integer('school_id'));
        $this->assertSchool($request, $school);
        return $school;
    }

    private function assertSchool(Request $request, School $school): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $school->id, 403);
    }

    private function back(School $school, string $message): RedirectResponse
    {
        return redirect()->route('admin.school-operations.index', ['school_id' => $school->id])->with('status', $message);
    }
}
