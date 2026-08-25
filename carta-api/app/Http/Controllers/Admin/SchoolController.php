<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.schools.index', [
            'schools' => School::withCount('users')
                ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->value().'%'))
                ->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.schools.form', ['school' => new School]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_if($data['email'] && User::where('email', $data['email'])->exists(), 422, 'Já existe um utilizador com este email.');
        $temporaryPassword = Str::password(12);
        DB::transaction(function () use ($data, $temporaryPassword): void {
            $school = School::create($data);
            if ($school->email) {
                User::create([
                    'school_id' => $school->id,
                    'name' => $school->contact_person ?: $school->name,
                    'email' => $school->email,
                    'password' => $temporaryPassword,
                    'role' => 'school',
                    'is_active' => $school->is_active,
                ]);
            }
        });

        $message = $data['email']
            ? "Escola e conta criadas. Acesso: {$data['email']} · Palavra-passe temporária: {$temporaryPassword}"
            : 'Escola criada. Adicione um email para gerar uma conta de acesso.';

        return redirect()->route('admin.schools.index')->with('status', $message);
    }

    public function edit(School $school): View
    {
        return view('admin.schools.form', compact('school'));
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $school->update($this->validated($request, $school));

        return redirect()->route('admin.schools.index')->with('status', 'Escola atualizada com sucesso.');
    }

    public function destroy(School $school): RedirectResponse
    {
        abort_if($school->users()->exists(), 422, 'Desative a escola; existem utilizadores associados.');
        $school->delete();

        return back()->with('status', 'Escola removida.');
    }

    private function validated(Request $request, ?School $school = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('schools')->ignore($school)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
