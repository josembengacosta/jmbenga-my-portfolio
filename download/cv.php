<?php
// download/cv.php — Download do Currículo PDF
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$cvFile = get_config($pdo, 'cv_file', '');
$filePath = ROOT_PATH . '/assets/downloads/' . $cvFile;

if (empty($cvFile) || !file_exists($filePath)) {
    http_response_code(404);
    echo "Ficheiro não encontrado.";
    exit;
}

$displayName = 'Curriculo_JMbenga.pdf';   // nome com que o ficheiro será descarregado

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $displayName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;