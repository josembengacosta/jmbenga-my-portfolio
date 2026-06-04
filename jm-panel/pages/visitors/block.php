<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Bloquear Visitante
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

$db->prepare("UPDATE _visitor SET status_visitor = 'blocked', block_type = 'permanent', block_reason = 'other', blocked_by = ?, blocked_at = NOW() WHERE id_visitor = ?")
   ->execute([(int)$_SESSION['admin_id'], $id]);

logAudit((int)$_SESSION['admin_id'], null, 'visitor.block', '_visitor', $id, ['status' => $visitor['status_visitor']], ['status' => 'blocked']);

echo json_encode(['success' => true, 'message' => 'Visitante bloqueado com sucesso.']);
exit;