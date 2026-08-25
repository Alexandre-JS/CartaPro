<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = $user?->is_active && collect($roles)->contains(fn (string $role) => match ($role) {
            'admin' => $user->isAdmin(),
            'school' => $user->isSchool(),
            default => $user->role === $role,
        });
        abort_unless($allowed, 403);

        return $next($request);
    }
}
