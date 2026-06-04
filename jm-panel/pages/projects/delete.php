<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar projecto (AJAX)
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

// ── Apagar imagem de capa ─────────────────────────────────────
if (!empty($project['cover_project'])) {
    $coverPath = ROOT_PATH . '/assets/img/projects/' . $project['cover_project'];
    if (file_exists($coverPath)) {
        unlink($coverPath);
    }
}

// ── Apagar imagens da galeria (media) ─────────────────────────
$mediaStmt = $db->prepare("SELECT url_media FROM _projects_media WHERE id_project = ?");
$mediaStmt->execute([$id]);
while ($media = $mediaStmt->fetch()) {
    $mediaPath = ROOT_PATH . '/assets/img/projects/' . $media['url_media'];
    if (file_exists($mediaPath)) {
        unlink($mediaPath);
    }
}
// A tabela _projects_media tem ON DELETE CASCADE, mas vamos apagar manualmente para garantir
// que os ficheiros são removidos antes do registo pai.
// Após eliminar o projecto, as media são automaticamente removidas pela FK.

// ── Eliminar projecto (a FK elimina as media em cascata) ──────
try {
    $db->prepare("DELETE FROM _projects WHERE id_project = ?")->execute([$id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'project.delete',
        '_projects',
        $id,
        ['title' => $project['title_project']],
        null
    );

    echo json_encode(['success' => true, 'message' => 'Projecto eliminado com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[DELETE PROJECT] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar o projecto.']);
    exit;
}