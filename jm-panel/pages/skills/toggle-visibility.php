<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar visibilidade da Skill (AJAX)
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

// ── ID da skill ───────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de skill inválido.']);
    exit;
}

// Verificar se a skill existe
$stmt = $db->prepare("SELECT * FROM _skills WHERE id_skill = ?");
$stmt->execute([$id]);
$skill = $stmt->fetch();

if (!$skill) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Skill não encontrada.']);
    exit;
}

// ── Alternar visibilidade ─────────────────────────────────────
$currentVisible = (int)$skill['is_visible'];
$newVisible     = $currentVisible ? 0 : 1;

$db->prepare("UPDATE _skills SET is_visible = ? WHERE id_skill = ?")->execute([$newVisible, $id]);

// Auditoria
logAudit(
    (int)$_SESSION['admin_id'],
    null,
    'skill.toggle_visibility',
    '_skills',
    $id,
    ['is_visible' => $currentVisible],
    ['is_visible' => $newVisible]
);

echo json_encode([
    'success'    => true,
    'is_visible' => $newVisible,
    'message'    => $newVisible ? 'Skill visível.' : 'Skill ocultada.',
]);
exit;