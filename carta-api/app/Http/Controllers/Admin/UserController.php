<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::with('school')
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('q')->value().'%')->orWhere('email', 'like', '%'.$request->string('q')->value().'%')))
                ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->value()))
                ->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'schools' => School::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request));

        return redirect()->route('admin.users.index')->with('status', 'Utilizador criado com sucesso.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'schools' => School::where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $user->update($this->validated($request, $user));

        return redirect()->route('admin.users.index')->with('status', 'Utilizador atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'Não pode remover a própria conta.');
        $user->delete();

        return back()->with('status', 'Utilizador removido.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'school', 'platform_admin', 'school_owner', 'school_admin', 'content_author', 'content_reviewer'])],
            'school_id' => ['nullable', 'exists:schools,id', Rule::requiredIf(in_array($request->input('role'), ['school', 'school_owner', 'school_admin'], true))],
            'password' => [$user?->exists ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if (! in_array($data['role'], ['school', 'school_owner', 'school_admin'], true)) {
            $data['school_id'] = null;
        }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
