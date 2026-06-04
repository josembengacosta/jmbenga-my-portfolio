<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar destaque do projecto (AJAX)
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

// ── CSRF (via header) ────────────────────────────────────────
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── ID do projecto ────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de projecto inválido.']);
    exit;
}

// Verificar se o projecto existe
$stmt = $db->prepare("SELECT * FROM _projects WHERE id_project = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Projecto não encontrado.']);
    exit;
}

// ── Alternar destaque ─────────────────────────────────────────
$currentFeatured = (int)$project['is_featured'];
$newFeatured     = $currentFeatured ? 0 : 1;

$db->prepare("UPDATE _projects SET is_featured = ? WHERE id_project = ?")
   ->execute([$newFeatured, $id]);

// Auditoria
logAudit(
    (int)$_SESSION['admin_id'],
    null,
    'project.toggle_featured',
    '_projects',
    $id,
    ['is_featured' => $currentFeatured],
    ['is_featured' => $newFeatured]
);

echo json_encode([
    'success'     => true,
    'is_featured' => $newFeatured,
    'message'     => $newFeatured ? 'Projecto adicionado ao destaque.' : 'Projecto removido do destaque.',
]);
exit;