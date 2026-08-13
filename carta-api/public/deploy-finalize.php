<?php

use App\Services\DeployFinalizer;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$notFound = static function (): never {
    http_response_code(404);
    echo json_encode(['estado' => 'não encontrado']);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $notFound();
}

// Em desenvolvimento a aplicação é a pasta acima de public/. No artefacto do
// Hostinger fica em public_html/carta-api, ao lado deste endpoint.
$appPath = is_file(__DIR__.'/carta-api/artisan')
    ? __DIR__.'/carta-api'
    : dirname(__DIR__);

$hashFile = $appPath.'/.deploy-token.sha256';
$expectedHash = is_file($hashFile) ? trim((string) file_get_contents($hashFile)) : '';
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ')
    ? substr($authorization, 7)
    : '';
$expectedVersion = trim((string) @file_get_contents(__DIR__.'/deploy-version.txt'));
$providedVersion = trim((string) ($_SERVER['HTTP_X_DEPLOY_VERSION'] ?? ''));

if (
    $expectedHash === ''
    || $providedToken === ''
    || ! hash_equals($expectedHash, hash('sha256', $providedToken))
    || $expectedVersion === ''
    || ! hash_equals($expectedVersion, $providedVersion)
) {
    $notFound();
}

$lock = fopen($appPath.'/storage/framework/deploy.lock', 'c+');

if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    http_response_code(409);
    echo json_encode(['estado' => 'em curso']);
    exit;
}

try {
    require $appPath.'/vendor/autoload.php';
    $app = require $appPath.'/bootstrap/app.php';
    $app->usePublicPath(__DIR__);
    $app->make(DeployFinalizer::class)->run();

    echo json_encode([
        'estado' => 'concluído',
        'versão' => $expectedVersion,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('CartaPro: falha ao finalizar deploy: '.$exception->getMessage());
    http_response_code(500);
    echo json_encode(['estado' => 'falhou']);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
