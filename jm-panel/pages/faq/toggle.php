<?php
// JMbenga Portfolio — Alternar visibilidade da FAQ (AJAX)
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
    // Bloqueia a linha durante a leitura/actualização para evitar dois toggles concorrentes.
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT status_faq FROM _faq WHERE id_faq = ? FOR UPDATE');
    $stmt->execute([(int)$id]);
    $faq = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$faq) {
        $db->rollBack();
        faqJsonResponse(['success' => false, 'message' => 'FAQ não encontrada.'], 404);
    }

    $currentStatus = (string)$faq['status_faq'];
    if (!in_array($currentStatus, ['visible', 'hidden'], true)) {
        $db->rollBack();
        faqJsonResponse(['success' => false, 'message' => 'O estado actual da FAQ é inválido.'], 409);
    }
    $newStatus = $currentStatus === 'visible' ? 'hidden' : 'visible';
    $stmt = $db->prepare('UPDATE _faq SET status_faq = ? WHERE id_faq = ?');
    $stmt->execute([$newStatus, (int)$id]);
    $db->commit();

    faqAuditSafely((int)$_SESSION['admin_id'], null, 'faq.toggle_visibility', '_faq', (int)$id,
        ['status' => $currentStatus], ['status' => $newStatus]);

    faqJsonResponse(['success' => true, 'status_faq' => $newStatus, 'message' => $newStatus === 'visible' ? 'FAQ visível.' : 'FAQ ocultada.']);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[TOGGLE FAQ] ' . $e->getMessage());
    faqJsonResponse(['success' => false, 'message' => 'Erro ao alterar a visibilidade da FAQ.'], 500);
}
    