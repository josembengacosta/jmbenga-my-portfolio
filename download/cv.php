<?php
/**
 * download/cv.php — Download do Currículo PDF
 *
 * Serve apenas o PDF configurado em _site_config.cv_file,
 * limitado à pasta assets/downloads/.
 */

declare(strict_types=1);

// Apenas GET e HEAD podem solicitar o ficheiro.
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Método não permitido.';
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$notFound = static function (): never {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Ficheiro não encontrado.';
    exit;
};

// Obter o nome do currículo da configuração do site.
try {
    $cvFile = trim(get_config($pdo, 'cv_file', ''));
} catch (Throwable $e) {
    error_log('[download/cv.php] Não foi possível obter a configuração do currículo.');
    $notFound();
}

if ($cvFile === '' || str_contains($cvFile, "\0")) {
    $notFound();
}

// O campo deve conter apenas um nome de ficheiro PDF, não um caminho.
$fileName = basename(str_replace('\\', '/', $cvFile));
if (
    $fileName !== $cvFile
    || str_contains($cvFile, '/')
    || str_contains($cvFile, '\\')
    || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf'
) {
    error_log('[download/cv.php] Configuração do nome do currículo inválida.');
    $notFound();
}

// Resolver os caminhos reais para impedir traversal e acesso por symlink externo.
$downloadsDir = realpath(ROOT_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'downloads');
if ($downloadsDir === false || !is_dir($downloadsDir)) {
    $notFound();
}

$filePath = realpath($downloadsDir . DIRECTORY_SEPARATOR . $fileName);
if ($filePath === false || !is_file($filePath) || !is_readable($filePath)) {
    $notFound();
}

$normalizedDir = rtrim(str_replace('\\', '/', $downloadsDir), '/') . '/';
$normalizedFile = str_replace('\\', '/', $filePath);

// O XAMPP utiliza normalmente caminhos Windows; comparar sem distinguir maiúsculas.
if (DIRECTORY_SEPARATOR === '\\') {
    $normalizedDir = strtolower($normalizedDir);
    $normalizedFile = strtolower($normalizedFile);
}

if (!str_starts_with($normalizedFile, $normalizedDir)) {
    error_log('[download/cv.php] Tentativa de acesso fora da pasta de currículos.');
    $notFound();
}

$fileSize = filesize($filePath);
if ($fileSize === false) {
    $notFound();
}

// Nome de download fixo, não controlado pela configuração nem pelo visitante.
$displayName = 'Curriculo_JMbenga.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $displayName . '"');
header('Content-Length: ' . (string) $fileSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

if ($method === 'HEAD') {
    exit;
}

$bytesSent = readfile($filePath);
if ($bytesSent === false) {
    error_log('[download/cv.php] Falha ao ler o PDF configurado.');
}
exit;