<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\School;
use App\Models\SchoolAssignment;
use App\Models\SchoolAssignmentProgress;
use App\Models\SchoolMembership;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolAssignmentController extends Controller
{
    public function candidateIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $progress = SchoolAssignmentProgress::where('mobile_user_id', $user->id)
            ->whereHas('assignment', fn (Builder $query) => $query->whereIn('status', ['published', 'closed']))
            ->where(fn (Builder $query) => $query->where('status', 'completed')->orWhereHas('assignment.school.memberships', fn (Builder $memberships) => $memberships
                ->where('mobile_user_id', $user->id)->where('status', 'active')))
            ->with(['assignment.school:id,name,code', 'assignment.classroom:id,school_id,name,code'])
            ->latest()->get();

        return response()->json(['data' => $progress]);
    }

    public function updateProgress(Request $request, SchoolAssignmentProgress $progress): JsonResponse
    {
        abort_unless($progress->mobile_user_id === $request->user()->id, 404);
        $progress->load('assignment');
        abort_unless($progress->assignment->status === 'published', 422, 'Esta tarefa já não aceita atualizações.');
        abort_unless(SchoolMembership::where([
            'school_id' => $progress->assignment->school_id,
            'mobile_user_id' => $request->user()->id,
            'status' => 'active',
        ])->exists(), 403);
        $status = $request->validate(['status' => ['required', Rule::in(['in_progress', 'completed'])]])['status'];
        abort_if($progress->status === 'completed' && $status !== 'completed', 422, 'Uma tarefa concluída não pode voltar atrás.');

        $progress->forceFill([
            'status' => $status,
            'started_at' => $progress->started_at ?: now(),
            'completed_at' => $status === 'completed' ? ($progress->completed_at ?: now()) : null,
        ])->save();

        return response()->json($progress->fresh()->load(['assignment.school:id,name,code', 'assignment.classroom:id,school_id,name,code']));
    }

    public function index(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);
        $assignments = $school->assignments()
            ->when($request->user()->isInstructor(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->where('created_by', $request->user()->id)
                ->orWhereHas('classroom.instructors', fn (Builder $instructors) => $instructors->where('user_id', $request->user()->id))))
            ->with(['classroom:id,school_id,name,code', 'mobileUser:id,name,email', 'creator:id,name'])
            ->withCount(['progress', 'progress as completed_count' => fn (Builder $query) => $query->where('status', 'completed')])
            ->latest()->paginate(min($request->integer('por_pagina', 20), 100));

        return response()->json($assignments);
    }

    public function store(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);
        $data = $this->validated($request, $school);
        $assignment = SchoolAssignment::create($data + [
            'school_id' => $school->id,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json($this->load($assignment), 201);
    }

    public function update(Request $request, SchoolAssignment $assignment): JsonResponse
    {
        $this->assertAssignmentAccess($request, $assignment);
        abort_unless($assignment->status === 'draft', 422, 'Só tarefas em rascunho podem ser editadas.');
        $assignment->update($this->validated($request, $assignment->school));

        return response()->json($this->load($assignment->fresh()));
    }

    public function publish(Request $request, SchoolAssignment $assignment): JsonResponse
    {
        $this->assertAssignmentAccess($request, $assignment);
        abort_unless($assignment->status === 'draft', 422, 'Esta tarefa já foi publicada ou encerrada.');

        $recipients = SchoolMembership::where('school_id', $assignment->school_id)->where('status', 'active')
            ->when($assignment->classroom_id, fn (Builder $query) => $query->where('classroom_id', $assignment->classroom_id))
            ->when($assignment->mobile_user_id, fn (Builder $query) => $query->where('mobile_user_id', $assignment->mobile_user_id))
            ->pluck('mobile_user_id')->unique();
        abort_if($recipients->isEmpty(), 422, 'Não existem candidatos ativos para receber esta tarefa.');

        DB::transaction(function () use ($assignment, $recipients): void {
            $assignment->update(['status' => 'published', 'published_at' => now()]);
            foreach ($recipients as $mobileUserId) {
                SchoolAssignmentProgress::firstOrCreate([
                    'school_assignment_id' => $assignment->id,
                    'mobile_user_id' => $mobileUserId,
                ], ['status' => 'assigned']);
            }
        });

        return response()->json($this->load($assignment->fresh()));
    }

    public function close(Request $request, SchoolAssignment $assignment): JsonResponse
    {
        $this->assertAssignmentAccess($request, $assignment);
        abort_unless($assignment->status === 'published', 422, 'Só tarefas publicadas podem ser encerradas.');
        $assignment->update(['status' => 'closed']);

        return response()->json($this->load($assignment->fresh()));
    }

    public function progress(Request $request, SchoolAssignment $assignment): JsonResponse
    {
        $this->assertAssignmentAccess($request, $assignment);

        return response()->json($assignment->progress()->with('mobileUser:id,name,email,phone')->latest()->paginate(100));
    }

    private function validated(Request $request, School $school): array
    {
        $data = $request->validate([
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id', 'required_without:mobile_user_id'],
            'mobile_user_id' => ['nullable', 'integer', 'exists:mobile_users,id', 'required_without:classroom_id'],
            'type' => ['required', Rule::in(SchoolAssignment::TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'resource_type' => ['nullable', Rule::in(['exam', 'lesson', 'topic'])],
            'resource_id' => ['nullable', 'integer', 'required_with:resource_type'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
        abort_if(filled($data['classroom_id'] ?? null) && filled($data['mobile_user_id'] ?? null), 422, 'Escolha uma turma ou um candidato, não ambos.');

        if ($data['classroom_id'] ?? null) {
            $classroom = Classroom::findOrFail($data['classroom_id']);
            abort_unless($classroom->school_id === $school->id, 422, 'A turma não pertence a esta escola.');
            abort_unless($request->user()->canAccessClassroom($classroom), 403);
        }
        if ($data['mobile_user_id'] ?? null) {
            $membership = SchoolMembership::where(['school_id' => $school->id, 'mobile_user_id' => $data['mobile_user_id'], 'status' => 'active'])->first();
            abort_unless($membership, 422, 'O candidato não tem um vínculo ativo com esta escola.');
            if ($request->user()->isInstructor()) {
                abort_unless($membership->classroom && $request->user()->canAccessClassroom($membership->classroom), 403);
            }
        }

        $this->assertResource($school, $data['resource_type'] ?? null, $data['resource_id'] ?? null);

        return $data;
    }

    private function assertResource(School $school, ?string $type, ?int $id): void
    {
        if (! $type) {
            return;
        }
        $exists = match ($type) {
            'exam' => Exam::whereKey($id)->where(fn (Builder $query) => $query->where('school_id', $school->id)->orWhere('is_public', true))->exists(),
            'lesson' => Lesson::whereKey($id)->where('is_active', true)->exists(),
            'topic' => Topic::whereKey($id)->where('is_active', true)->exists(),
        };
        abort_unless($exists, 422, 'O recurso indicado não está disponível para esta escola.');
    }

    private function assertAssignmentAccess(Request $request, SchoolAssignment $assignment): void
    {
        $this->assertSchoolAccess($request, $assignment->school);
        if ($request->user()->isInstructor()) {
            abort_unless($assignment->created_by === $request->user()->id
                || ($assignment->classroom && $request->user()->canAccessClassroom($assignment->classroom)), 403);
        }
    }

    private function assertSchoolAccess(Request $request, School $school): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $school->id, 403);
    }

    private function load(SchoolAssignment $assignment): SchoolAssignment
    {
        return $assignment->load(['school:id,name,code', 'classroom:id,school_id,name,code', 'mobileUser:id,name,email', 'creator:id,name'])
            ->loadCount(['progress', 'progress as completed_count' => fn (Builder $query) => $query->where('status', 'completed')]);
    }
}
