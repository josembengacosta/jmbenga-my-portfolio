<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Desbloquear Visitante
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM _visitor WHERE id_visitor = ?");
$stmt->execute([$id]);
$visitor = $stmt->fetch();

if (!$visitor) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visitante não encontrado.']);
    exit;
}

$db->prepare("UPDATE _visitor SET status_visitor = 'active', block_type = NULL, block_reason = NULL, block_until = NULL, blocked_by = NULL, blocked_at = NULL WHERE id_visitor = ?")
   ->execute([$id]);

logAudit((int)$_SESSION['admin_id'], null, 'visitor.unblock', '_visitor', $id, ['status' => $visitor['status_visitor']], ['status' => 'active']);

echo json_encode(['success' => true, 'message' => 'Visitante desbloqueado com sucesso.']);
exit;