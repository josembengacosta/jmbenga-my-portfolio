<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar visibilidade da FAQ (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── ID da FAQ ─────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de FAQ inválido.']);
    exit;
}

// Verificar se a FAQ existe
$stmt = $db->prepare("SELECT * FROM _faq WHERE id_faq = ?");
$stmt->execute([$id]);
$faq = $stmt->fetch();

if (!$faq) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'FAQ não encontrada.']);
    exit;
}

// ── Alternar visibilidade ─────────────────────────────────────
$currentStatus = $faq['status_faq'];
$newStatus     = ($currentStatus === 'visible') ? 'hidden' : 'visible';

$db->prepare("UPDATE _faq SET status_faq = ? WHERE id_faq = ?")
   ->execute([$newStatus, $id]);

// Auditoria
logAudit(
    (int)$_SESSION['admin_id'],
    null,
    'faq.toggle_visibility',
    '_faq',
    $id,
    ['status' => $currentStatus],
    ['status' => $newStatus]
);

echo json_encode([
    'success'    => true,
    'status_faq' => $newStatus,
    'message'    => $newStatus === 'visible' ? 'FAQ visível.' : 'FAQ ocultada.',
]);
exit;