<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar favorito do Feedback
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

$stmt = $db->prepare("SELECT is_starred FROM _feedback WHERE id = ?");
$stmt->execute([$id]);
$fb = $stmt->fetch();

if (!$fb) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Feedback não encontrado.']);
    exit;
}

$newStarred = $fb['is_starred'] ? 0 : 1;
$db->prepare("UPDATE _feedback SET is_starred = ? WHERE id = ?")->execute([$newStarred, $id]);

echo json_encode(['success' => true, 'is_starred' => $newStarred]);
exit;