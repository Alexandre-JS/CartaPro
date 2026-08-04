<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateMobileToken;
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
        $middleware->alias(['role' => EnsureRole::class, 'api.auth' => AuthenticateApiToken::class, 'mobile.auth' => AuthenticateMobileToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

/*
 * Onde fica a pasta que a web serve.
 *
 * Por omissão o Laravel assume `public/` dentro da aplicação, e em
 * desenvolvimento é isso mesmo. No alojamento partilhado não é: a pasta
 * servida é `public_html/`, e a aplicação vive numa subpasta dela — fora do
 * alcance da web, que é o que protege o .env.
 *
 * Sem esta correção, `public_path()` aponta para dentro da pasta bloqueada.
 * Os ficheiros carregados no painel (os SVG dos sinais) eram gravados onde o
 * servidor nunca os serviria, e o painel mostrava 404 numa imagem que existia
 * em disco.
 */
if ($publicPath = env('APP_PUBLIC_PATH')) {
    $app->usePublicPath($publicPath);
}

return $app;
