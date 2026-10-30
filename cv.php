<?php
/**
 * cv.php — Visualizar Currículo Online
 *
 * Serve o PDF configurado em _site_config.cv_file.
 * Os ficheiros são limitados à pasta assets/downloads/.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * Resposta de ficheiro indisponível.
 */
function cvNotFound(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    echo 'Currículo não disponível.';
    exit;
}

/*
|--------------------------------------------------------------------------
| MÉTODO HTTP
|--------------------------------------------------------------------------
*/

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Método não permitido.';
    exit;
}

/*
|--------------------------------------------------------------------------
| OBTER O FICHEIRO CONFIGURADO
|--------------------------------------------------------------------------
*/

try {
    $cvFile = trim(get_config($pdo, 'cv_file', ''));
} catch (Throwable $e) {
    error_log('[cv.php] Não foi possível obter a configuração do currículo.');
    cvNotFound();
}

if ($cvFile === '') {
    cvNotFound();
}

/*
 * A configuração deve conter apenas o nome do ficheiro,
 * por exemplo: Curriculo_JMbenga.pdf
 *
 * Não aceitamos caminhos ou referências a outras pastas.
 */

$fileName = basename(str_replace('\\', '/', $cvFile));

if (
    $fileName !== $cvFile
    || str_contains($cvFile, "\0")
    || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf'
) {
    error_log('[cv.php] Configuração do currículo inválida.');
    cvNotFound();
}

/*
|--------------------------------------------------------------------------
| RESOLVER E VALIDAR O CAMINHO
|--------------------------------------------------------------------------
*/

$downloadsDir = realpath(
    ROOT_PATH . DIRECTORY_SEPARATOR . 'assets'
    . DIRECTORY_SEPARATOR . 'downloads'
);

if ($downloadsDir === false || !is_dir($downloadsDir)) {
    error_log('[cv.php] Pasta de currículos indisponível.');
    cvNotFound();
}

$filePath = realpath(
    $downloadsDir . DIRECTORY_SEPARATOR . $fileName
);

if ($filePath === false || !is_file($filePath) || !is_readable($filePath)) {
    cvNotFound();
}

/*
 * Confirmar que o caminho real continua dentro da pasta permitida.
 * Normalizar separadores para funcionar no Windows e em Linux.
 */

$normalizedDir = rtrim(
    str_replace('\\', '/', $downloadsDir),
    '/'
) . '/';

$normalizedFile = str_replace('\\', '/', $filePath);

if (DIRECTORY_SEPARATOR === '\\') {
    $normalizedDir = strtolower($normalizedDir);
    $normalizedFile = strtolower($normalizedFile);
}

if (!str_starts_with($normalizedFile, $normalizedDir)) {
    error_log('[cv.php] Tentativa de acesso fora da pasta de currículos.');
    cvNotFound();
}

/*
|--------------------------------------------------------------------------
| ENVIAR O PDF
|--------------------------------------------------------------------------
*/

$size = filesize($filePath);

if ($size === false) {
    cvNotFound();
}

$displayName = 'Curriculo_JMbenga.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $displayName . '"');
header('Content-Length: ' . (string) $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');

if ($method === 'HEAD') {
    exit;
}

if (readfile($filePath) === false) {
    error_log('[cv.php] Falha ao ler o ficheiro PDF.');
}

exit;