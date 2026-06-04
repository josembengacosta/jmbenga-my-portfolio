<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Editar Skill (AJAX)
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
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── Função auxiliar para retornar erros ───────────────────────
$jsonError = function (array $errors, int $status = 422) {
    http_response_code($status);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
};

// ── ID da skill ───────────────────────────────────────────────
$id = (int)($_POST['id_skill'] ?? 0);
if ($id <= 0) {
    $jsonError(['ID de skill inválido.'], 400);
}

// Verificar se a skill existe
$stmt = $db->prepare("SELECT * FROM _skills WHERE id_skill = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();
if (!$old) {
    $jsonError(['Skill não encontrada.'], 404);
}

// ── Capturar e sanitizar campos ───────────────────────────────
$name          = trim($_POST['name_skill']       ?? '');
$percentage    = (int)($_POST['percentage_skill'] ?? 80);
$category      = $_POST['category_skill']        ?? 'frontend';
$icon          = trim($_POST['icon_skill']        ?? 'fas fa-code');
$displayOrder  = (int)($_POST['display_order']    ?? 0);
$isVisible     = isset($_POST['is_visible']) ? 1 : 0;

// ── Validações ─────────────────────────────────────────────────
$errors = [];

if (mb_strlen($name) < 2) {
    $errors[] = 'O nome da skill deve ter pelo menos 2 caracteres.';
}
if ($percentage < 0 || $percentage > 100) {
    $errors[] = 'A percentagem deve estar entre 0 e 100.';
}
$allowedCategories = ['frontend', 'backend', 'devops', 'design', 'other'];
if (!in_array($category, $allowedCategories)) {
    $errors[] = 'Categoria inválida.';
}
if (mb_strlen($icon) < 3) {
    $errors[] = 'O ícone deve ter pelo menos 3 caracteres.';
}

// ── Verificar duplicados (mesmo nome, excluindo o próprio) ────
$stmt = $db->prepare("SELECT COUNT(*) FROM _skills WHERE name_skill = ? AND id_skill != ?");
$stmt->execute([$name, $id]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = 'Já existe uma skill com este nome. Escolhe um nome diferente.';
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Actualizar na base de dados ───────────────────────────────
try {
    $stmt = $db->prepare("
        UPDATE _skills SET
            name_skill       = ?,
            percentage_skill = ?,
            category_skill   = ?,
            icon_skill       = ?,
            display_order    = ?,
            is_visible       = ?
        WHERE id_skill       = ?
    ");
    $stmt->execute([$name, $percentage, $category, $icon, $displayOrder, $isVisible, $id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'skill.update',
        '_skills',
        $id,
        $old,
        ['name' => $name, 'percentage' => $percentage, 'category' => $category, 'is_visible' => $isVisible]
    );

    echo json_encode(['success' => true, 'message' => 'Skill actualizada com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[EDIT SKILL] ' . $e->getMessage());
    $jsonError(['Erro interno ao actualizar a skill. Tenta novamente.'], 500);
}