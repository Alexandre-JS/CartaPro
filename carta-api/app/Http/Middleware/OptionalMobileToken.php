<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Permite ler conteúdo Free sem conta, mantendo a conta opcional. */
class OptionalMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($plain = $request->bearerToken()) {
            $token = MobileApiToken::with('user')->where('token_hash', hash('sha256', $plain))->first();
            if ($token && $token->user->is_active && (! $token->expires_at || $token->expires_at->isFuture())) {
                $request->setUserResolver(fn () => $token->user);
                $request->attributes->set('mobile_token', $token);
            }
        }

        return $next($request);
    }
}
