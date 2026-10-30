<?php
// JMbenga Portfolio — Eliminar FAQ (AJAX)
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$db = $GLOBALS['pdo'];

function faqJsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function faqPostString(string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : '';
}

function faqAuditSafely(...$args): void {
    try {
        logAudit(...$args);
    } catch (Throwable $auditError) {
        error_log('[FAQ AUDIT] ' . $auditError->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    faqJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !validateAdminCsrf($csrfToken)) {
    faqJsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
}

$idRaw = $_GET['id'] ?? '';
$id = is_string($idRaw) ? filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false) faqJsonResponse(['success' => false, 'message' => 'ID de FAQ inválido.'], 400);

try {
    $stmt = $db->prepare('SELECT question FROM _faq WHERE id_faq = ?');
    $stmt->execute([(int)$id]);
    $faq = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$faq) faqJsonResponse(['success' => false, 'message' => 'FAQ não encontrada.'], 404);

    $stmt = $db->prepare('DELETE FROM _faq WHERE id_faq = ?');
    $stmt->execute([(int)$id]);
    if ($stmt->rowCount() !== 1) faqJsonResponse(['success' => false, 'message' => 'A FAQ já não existe ou não foi eliminada.'], 404);

    faqAuditSafely((int)$_SESSION['admin_id'], null, 'faq.delete', '_faq', (int)$id, ['question' => $faq['question']], null);
    faqJsonResponse(['success' => true, 'message' => 'FAQ eliminada com sucesso.']);
} catch (PDOException $e) {
    error_log('[DELETE FAQ] ' . $e->getMessage());
    faqJsonResponse(['success' => false, 'message' => 'Erro ao eliminar a FAQ.'], 500);
}