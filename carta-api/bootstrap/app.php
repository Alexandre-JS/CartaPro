<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateMobileToken;
use App\Http\Middleware\EnsurePaymentsEnabled;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'api.auth' => AuthenticateApiToken::class,
            'mobile.auth' => AuthenticateMobileToken::class,
            'payments.enabled' => EnsurePaymentsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

/*
 * A pasta pública não se decide aqui.
 *
 * Houve uma tentativa de a ler de `env('APP_PUBLIC_PATH')` neste ponto, e
 * falhava em silêncio: quando este ficheiro corre, o .env ainda não foi lido
 * pelo kernel, e `env()` devolve null sem erro nenhum. Quem a define é o
 * `public/index.php`, a partir do seu próprio `__DIR__` — que é, por
 * definição, a pasta que o servidor serve.
 */

return $app;
