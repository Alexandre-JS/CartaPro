<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        abort_unless($plainToken, 401, 'Token não fornecido.');
        $token = ApiToken::with('user.school')->where('token_hash', hash('sha256', $plainToken))->first();
        abort_unless($token && $token->user->is_active && (! $token->expires_at || $token->expires_at->isFuture()), 401, 'Token inválido ou expirado.');
        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
