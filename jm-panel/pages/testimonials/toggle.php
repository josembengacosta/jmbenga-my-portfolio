<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar visibilidade do Depoimento (AJAX)
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

// ── ID do depoimento ──────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de depoimento inválido.']);
    exit;
}

// Verificar se o depoimento existe
$stmt = $db->prepare("SELECT * FROM _testimonials WHERE id_testimonial = ?");
$stmt->execute([$id]);
$testimonial = $stmt->fetch();

if (!$testimonial) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Depoimento não encontrado.']);
    exit;
}

// ── Alternar visibilidade ─────────────────────────────────────
$currentStatus = $testimonial['status_testimonial'];
$newStatus     = ($currentStatus === 'visible') ? 'hidden' : 'visible';

$db->prepare("UPDATE _testimonials SET status_testimonial = ? WHERE id_testimonial = ?")
   ->execute([$newStatus, $id]);

// Auditoria
logAudit(
    (int)$_SESSION['admin_id'],
    null,
    'testimonial.toggle_visibility',
    '_testimonials',
    $id,
    ['status' => $currentStatus],
    ['status' => $newStatus]
);

echo json_encode([
    'success'            => true,
    'status_testimonial'  => $newStatus,
    'message'            => $newStatus === 'visible' ? 'Depoimento visível.' : 'Depoimento ocultado.',
]);
exit;