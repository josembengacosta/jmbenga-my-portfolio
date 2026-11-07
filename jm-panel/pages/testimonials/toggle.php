<?php
// JMbenga Portfolio — Alternar visibilidade do depoimento (AJAX)
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

function testimonialJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function testimonialPostString(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function testimonialStoredPhotoPath(?string $filename): ?string
{
    if (
        $filename === null || $filename === '' || basename($filename) !== $filename ||
        !preg_match('/^[A-Za-z0-9._-]+$/', $filename) || $filename === '.' || $filename === '..'
    ) {
        return null;
    }

    $directory = ROOT_PATH . '/assets/img/testimonials';
    $realDirectory = realpath($directory);
    $candidate = $directory . DIRECTORY_SEPARATOR . $filename;
    $realFile = realpath($candidate);

    if ($realDirectory === false || $realFile === false || !is_file($realFile) || dirname($realFile) !== $realDirectory) {
        return null;
    }

    return $realFile;
}

function testimonialAuditSafely(...$args): void
{
    try {
        logAudit(...$args);
    } catch (Throwable $auditError) {
        error_log('[TESTIMONIAL AUDIT] ' . $auditError->getMessage());
    }
}

$db = $GLOBALS['pdo'] ?? null;
if (!($db instanceof PDO)) {
    testimonialJsonResponse(['success' => false, 'message' => 'Base de dados indisponível.'], 503);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    testimonialJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !validateAdminCsrf($csrf)) {
    testimonialJsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    testimonialJsonResponse(['success' => false, 'message' => 'ID de depoimento inválido.'], 400);
}

try {
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT status_testimonial FROM _testimonials WHERE id_testimonial = ? FOR UPDATE');
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();
    if ($currentStatus === false) {
        $db->rollBack();
        testimonialJsonResponse(['success' => false, 'message' => 'Depoimento não encontrado.'], 404);
    }
    $newStatus = ($currentStatus === 'visible') ? 'hidden' : 'visible';
    $stmt = $db->prepare('UPDATE _testimonials SET status_testimonial = ? WHERE id_testimonial = ?');
    $stmt->execute([$newStatus, $id]);
    $db->commit();

    testimonialAuditSafely(
        (int)$_SESSION['admin_id'],
        null,
        'testimonial.toggle_visibility',
        '_testimonials',
        (int)$id,
        ['status' => $currentStatus],
        ['status' => $newStatus]
    );
    testimonialJsonResponse([
        'success' => true,
        'status_testimonial' => $newStatus,
        'message' => $newStatus === 'visible' ? 'Depoimento visível.' : 'Depoimento ocultado.'
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[TOGGLE TESTIMONIAL] ' . $error->getMessage());
    testimonialJsonResponse(['success' => false, 'message' => 'Não foi possível alterar a visibilidade.'], 500);
}
