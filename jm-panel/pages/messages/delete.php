<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar mensagem
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

$stmt = $db->prepare("SELECT * FROM _contact_message WHERE id = ?");
$stmt->execute([$id]);
$msg = $stmt->fetch();

if (!$msg) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Mensagem não encontrada.']);
    exit;
}

$db->prepare("DELETE FROM _contact_message WHERE id = ?")->execute([$id]);

logAudit((int)$_SESSION['admin_id'], null, 'message.delete', '_contact_message', $id, ['name' => $msg['name_msg'], 'subject' => $msg['subject_msg']], null);

echo json_encode(['success' => true, 'message' => 'Mensagem eliminada.']);
exit;