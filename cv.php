<?php
// cv.php — Visualizar Currículo Online
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$cvFile = get_config($pdo, 'cv_file', '');
$filePath = ROOT_PATH . '/assets/downloads/' . $cvFile;

if (empty($cvFile) || !file_exists($filePath)) {
    http_response_code(404);
    echo "Currículo não disponível.";
    exit;
}

$displayName = 'Curriculo_JMbenga.pdf';   // nome amigável para o visualizador

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $displayName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;