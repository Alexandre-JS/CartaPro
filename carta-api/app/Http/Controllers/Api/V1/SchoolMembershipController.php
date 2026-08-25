<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\MobileUser;
use App\Models\School;
use App\Models\SchoolMembership;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolMembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $memberships = $request->user()->schoolMemberships()
            ->with(['school:id,name,code,is_active', 'classroom:id,school_id,name,code', 'student:id,classroom_id,name,identifier'])
            ->latest()->get();

        return response()->json(['data' => $memberships]);
    }

    public function accept(Request $request, SchoolMembership $membership): JsonResponse
    {
        $this->assertCandidateOwns($request, $membership);
        $accepted = DB::transaction(function () use ($membership): SchoolMembership {
            $current = SchoolMembership::lockForUpdate()->findOrFail($membership->id);
            abort_unless($current->status === 'invited', 422, 'Este convite já não pode ser aceite.');
            abort_unless($current->school()->where('is_active', true)->exists(), 422, 'A escola já não está ativa.');

            SchoolMembership::where('mobile_user_id', $current->mobile_user_id)
                ->where('id', '!=', $current->id)
                ->whereIn('status', ['active', 'suspended'])
                ->update(['status' => 'left', 'left_at' => now(), 'updated_at' => now()]);

            $current->forceFill([
                'status' => 'active',
                'joined_at' => now(),
                'left_at' => null,
            ])->save();

            return $current;
        });

        return response()->json($this->candidateMembership($accepted));
    }

    public function leave(Request $request, SchoolMembership $membership): JsonResponse
    {
        $this->assertCandidateOwns($request, $membership);
        abort_unless(in_array($membership->status, ['active', 'suspended'], true), 422, 'Este vínculo já não está ativo.');

        $membership->forceFill(['status' => 'left', 'left_at' => now()])->save();

        return response()->json($this->candidateMembership($membership->fresh()));
    }

    public function schoolIndex(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);

        $memberships = $school->memberships()
            ->with(['mobileUser:id,name,email,phone,is_active', 'classroom:id,school_id,name,code', 'student:id,classroom_id,name,identifier'])
            ->latest()->paginate(min($request->integer('por_pagina', 20), 100));

        return response()->json($memberships);
    }

    public function invite(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);
        abort_unless($school->is_active, 422, 'A escola não está ativa.');

        $data = $request->validate([
            'mobile_user_id' => ['required', Rule::exists('mobile_users', 'id')->where('is_active', true)],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
        ]);

        [$student, $classroom] = $this->resolveSchoolLinks($school, $data);
        $membership = SchoolMembership::where([
            'school_id' => $school->id,
            'mobile_user_id' => $data['mobile_user_id'],
        ])->first();

        abort_if($membership && in_array($membership->status, ['active', 'suspended'], true), 422, 'Este candidato já tem um vínculo atual com a escola.');

        $membership = SchoolMembership::updateOrCreate(
            ['school_id' => $school->id, 'mobile_user_id' => $data['mobile_user_id']],
            [
                'student_id' => $student?->id,
                'classroom_id' => $classroom?->id,
                'status' => 'invited',
                'joined_at' => null,
                'left_at' => null,
            ],
        );

        return response()->json($membership->load(['mobileUser:id,name,email,phone', 'school:id,name,code', 'classroom:id,school_id,name,code', 'student:id,classroom_id,name,identifier']), 201);
    }

    public function updateStatus(Request $request, SchoolMembership $membership): JsonResponse
    {
        $this->assertSchoolAccess($request, $membership->school);
        $status = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'left', 'completed'])],
        ])['status'];

        $allowed = match ($membership->status) {
            'invited' => ['left'],
            'active' => ['suspended', 'left', 'completed'],
            'suspended' => ['active', 'left', 'completed'],
            default => [],
        };
        abort_unless(in_array($status, $allowed, true), 422, 'Transição de estado inválida.');

        $membership->forceFill([
            'status' => $status,
            'left_at' => in_array($status, ['left', 'completed'], true) ? now() : null,
        ])->save();

        return response()->json($membership->fresh()->load(['mobileUser:id,name,email,phone', 'school:id,name,code', 'classroom:id,school_id,name,code', 'student:id,classroom_id,name,identifier']));
    }

    private function resolveSchoolLinks(School $school, array $data): array
    {
        $student = isset($data['student_id']) ? Student::with('classroom')->findOrFail($data['student_id']) : null;
        $classroom = isset($data['classroom_id']) ? Classroom::findOrFail($data['classroom_id']) : $student?->classroom;

        abort_if($student && $student->classroom->school_id !== $school->id, 422, 'O aluno não pertence a esta escola.');
        abort_if($classroom && $classroom->school_id !== $school->id, 422, 'A turma não pertence a esta escola.');
        abort_if($student && $classroom && $student->classroom_id !== $classroom->id, 422, 'O aluno não pertence à turma informada.');

        return [$student, $classroom];
    }

    private function assertCandidateOwns(Request $request, SchoolMembership $membership): void
    {
        abort_unless($membership->mobile_user_id === $request->user()->id, 404);
    }

    private function assertSchoolAccess(Request $request, School $school): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $school->id, 403);
    }

    private function candidateMembership(SchoolMembership $membership): SchoolMembership
    {
        return $membership->load(['school:id,name,code,is_active', 'classroom:id,school_id,name,code', 'student:id,classroom_id,name,identifier']);
    }
}
