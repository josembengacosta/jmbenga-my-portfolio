<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alternar estado do projecto (AJAX)
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

// ── Determinar novo estado ────────────────────────────────────
$currentStatus = $project['status_project'];
$newStatus     = ($currentStatus === 'published') ? 'draft' : 'published';

// ── Actualizar ─────────────────────────────────────────────────
$db->prepare("UPDATE _projects SET status_project = ? WHERE id_project = ?")
   ->execute([$newStatus, $id]);

// Auditoria
logAudit(
    (int)$_SESSION['admin_id'],
    null,
    'project.toggle_status',
    '_projects',
    $id,
    ['status' => $currentStatus],
    ['status' => $newStatus]
);

echo json_encode([
    'success'    => true,
    'new_status' => $newStatus,
    'message'    => $newStatus === 'published' ? 'Projecto publicado.' : 'Projecto colocado como rascunho.',
]);
exit;