<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Instructor;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstructorController extends Controller
{
    public function index(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);

        return response()->json($school->instructors()
            ->with(['user:id,name,email,is_active', 'classrooms:id,school_id,name,code'])
            ->latest()->paginate(min($request->integer('por_pagina', 20), 100)));
    }

    public function store(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);
        abort_unless($school->is_active, 422, 'A escola não está ativa.');
        $data = $this->validated($request);

        $instructor = DB::transaction(function () use ($data, $school): Instructor {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'instructor',
                'is_active' => $data['is_active'],
            ]);

            return Instructor::create([
                'user_id' => $user->id,
                'school_id' => $school->id,
                'license_number' => $data['license_number'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_active' => $data['is_active'],
            ]);
        });

        return response()->json($this->load($instructor), 201);
    }

    public function update(Request $request, Instructor $instructor): JsonResponse
    {
        $this->assertSchoolAccess($request, $instructor->school);
        $data = $this->validated($request, $instructor);

        DB::transaction(function () use ($data, $instructor): void {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ];
            if (filled($data['password'] ?? null)) {
                $userData['password'] = $data['password'];
            }
            $instructor->user->update($userData);
            $instructor->update([
                'license_number' => $data['license_number'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_active' => $data['is_active'],
            ]);
        });

        return response()->json($this->load($instructor->fresh()));
    }

    public function attachClassroom(Request $request, Instructor $instructor, Classroom $classroom): JsonResponse
    {
        $this->assertSchoolAccess($request, $instructor->school);
        abort_unless($classroom->school_id === $instructor->school_id, 422, 'A turma e o instrutor devem pertencer à mesma escola.');
        abort_unless($instructor->is_active && $classroom->is_active, 422, 'O instrutor e a turma devem estar ativos.');
        $instructor->classrooms()->syncWithoutDetaching([$classroom->id]);

        return response()->json($this->load($instructor->fresh()));
    }

    public function detachClassroom(Request $request, Instructor $instructor, Classroom $classroom): JsonResponse
    {
        $this->assertSchoolAccess($request, $instructor->school);
        abort_unless($classroom->school_id === $instructor->school_id, 422, 'A turma e o instrutor devem pertencer à mesma escola.');
        $instructor->classrooms()->detach($classroom->id);

        return response()->json($this->load($instructor->fresh()));
    }

    private function validated(Request $request, ?Instructor $instructor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($instructor?->user_id)],
            'password' => [$instructor ? 'nullable' : 'required', 'string', 'min:8'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function assertSchoolAccess(Request $request, School $school): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $school->id, 403);
    }

    private function load(Instructor $instructor): Instructor
    {
        return $instructor->load(['user:id,name,email,role,is_active', 'school:id,name,code', 'classrooms:id,school_id,name,code']);
    }
}
