<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/carta-api/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/carta-api/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/carta-api/bootstrap/app.php';

/*
 * A pasta pública é, por definição, aquela onde este ficheiro está.
 *
 * O Laravel assume `public/` dentro da aplicação, e em desenvolvimento é isso
 * mesmo. Em alojamento partilhado não é: a aplicação vive numa subpasta de
 * `public_html/`, fora do alcance da web — é o que protege o .env — e só este
 * ficheiro fica na pasta servida.
 *
 * Sem esta linha, `public_path()` apontava para dentro da pasta bloqueada: as
 * imagens carregadas no painel eram gravadas onde o servidor nunca as serviria,
 * e o sinal aparecia criado com a imagem em 404.
 *
 * Deduz-se de `__DIR__` em vez de vir de configuração porque uma variável de
 * ambiente não serviria: quando o `bootstrap/app.php` corre, o .env ainda não
 * foi lido, e `env()` devolveria null sem dar erro nenhum.
 */
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
