<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::with('school')->where('email', $data['email'])->where('is_active', true)->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Credenciais inválidas.');
        $plainToken = Str::random(80);
        ApiToken::create(['user_id' => $user->id, 'name' => 'painel', 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(30)]);

        return response()->json(['token' => $plainToken, 'tokenType' => 'Bearer', 'expiresIn' => 2592000, 'user' => $this->userData($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json(['message' => 'Sessão terminada.']);
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'school' => $user->school?->only(['id', 'name', 'code'])];
    }
}
