<?php
declare(strict_types=1);
// JMbenga Portfolio — Processador de criação de Skill (AJAX).
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

/** Resposta JSON consistente para o frontend. */
function skillJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function skillPostString(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    skillJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = skillPostString('csrf_token') ?: (is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
if (!validateAdminCsrf($token)) {
    skillJsonResponse(['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'], 403);
}

$name = skillPostString('name_skill');
$rawPercentage = skillPostString('percentage_skill', '80');
$category = skillPostString('category_skill', 'frontend');
$icon = skillPostString('icon_skill', 'fas fa-code');
$rawOrder = skillPostString('display_order', '0');
$isVisible = (($_POST['is_visible'] ?? null) === '1') ? 1 : 0;

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'O nome da skill deve ter entre 2 e 100 caracteres.';
}
if (!preg_match('/\A\d{1,3}\z/', $rawPercentage)) {
    $errors[] = 'A percentagem deve ser um número inteiro entre 0 e 100.';
    $percentage = -1;
} else {
    $percentage = (int) $rawPercentage;
    if ($percentage < 0 || $percentage > 100) {
        $errors[] = 'A percentagem deve estar entre 0 e 100.';
    }
}
$allowedCategories = ['frontend', 'backend', 'devops', 'design', 'other'];
if (!in_array($category, $allowedCategories, true)) {
    $errors[] = 'Categoria inválida.';
}
$iconPattern = '/\A(?:fas|far|fab|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*\z/i';
if ($icon === '' || mb_strlen($icon) > 100 || !preg_match($iconPattern, $icon)) {
    $errors[] = 'Indica classes Font Awesome válidas, por exemplo: fas fa-code ou fab fa-php.';
}
if (!preg_match('/\A\d{1,9}\z/', $rawOrder)) {
    $errors[] = 'A ordem de exibição deve ser um número inteiro igual ou superior a zero.';
    $displayOrder = -1;
} else {
    $displayOrder = (int) $rawOrder;
    if ($displayOrder < 0) {
        $errors[] = 'A ordem de exibição não pode ser negativa.';
    }
}

if ($errors) {
    skillJsonResponse(['success' => false, 'errors' => $errors], 422);
}

$db = $GLOBALS['pdo'];
try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM _skills WHERE name_skill = ?');
    $stmt->execute([$name]);
    if ((int) $stmt->fetchColumn() > 0) {
        skillJsonResponse(['success' => false, 'errors' => ['Já existe uma skill com este nome. Escolhe um nome diferente.']], 409);
    }

    $stmt = $db->prepare('INSERT INTO _skills (name_skill, percentage_skill, category_skill, icon_skill, display_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $percentage, $category, $icon, $displayOrder, $isVisible]);
    $newId = (int) $db->lastInsertId();
} catch (PDOException $exception) {
    error_log('[ADD SKILL] ' . $exception->getMessage());
    skillJsonResponse(['success' => false, 'message' => 'Erro interno ao guardar a skill. Tenta novamente.'], 500);
}

// A falha de auditoria não deve levar o browser a repetir uma inserção já concluída.
try {
    logAudit(
        (int) $_SESSION['admin_id'],
        null,
        'skill.create',
        '_skills',
        $newId,
        null,
        ['name' => $name, 'percentage' => $percentage, 'category' => $category]
    );
} catch (Throwable $exception) {
    error_log('[ADD SKILL AUDIT] ' . $exception->getMessage());
}

skillJsonResponse(['success' => true, 'message' => 'Skill criada com sucesso.', 'id' => $newId], 201);