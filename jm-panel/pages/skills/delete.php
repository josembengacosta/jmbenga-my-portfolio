<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar Skill (AJAX)
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

// ── Eliminar ──────────────────────────────────────────────────
try {
    $db->prepare("DELETE FROM _skills WHERE id_skill = ?")->execute([$id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'skill.delete',
        '_skills',
        $id,
        ['name' => $skill['name_skill']],
        null
    );

    echo json_encode(['success' => true, 'message' => 'Skill eliminada com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[DELETE SKILL] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar a skill.']);
    exit;
}