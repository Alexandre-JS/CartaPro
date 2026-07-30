<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        abort_unless($plain, 401, 'Token não fornecido.');
        $token = MobileApiToken::with('user')->where('token_hash', hash('sha256', $plain))->first();
        abort_unless($token && $token->user->is_active && (! $token->expires_at || $token->expires_at->isFuture()), 401, 'Sessão inválida ou expirada.');

        // `last_used_at` serve para caducar sessões inativas, não para auditoria
        // ao segundo: escrever a cada request criava uma escrita por leitura.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(15))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }
}
