<?php
declare(strict_types=1);
// JMbenga Portfolio — Processador de edição de Skill (AJAX).
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();
header('Content-Type: application/json; charset=utf-8');

function skillEditJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function skillEditPostString(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    skillEditJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = skillEditPostString('csrf_token');
if ($token === '' && is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
}
if (!validateAdminCsrf($token)) {
    skillEditJsonResponse(['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'], 403);
}

$rawId = skillEditPostString('id_skill');
if (!preg_match('/\A[1-9]\d{0,9}\z/', $rawId)) {
    skillEditJsonResponse(['success' => false, 'errors' => ['ID de skill inválido.']], 400);
}
$id = (int) $rawId;
$name = skillEditPostString('name_skill');
$rawPercentage = skillEditPostString('percentage_skill');
$category = skillEditPostString('category_skill');
$icon = skillEditPostString('icon_skill');
$rawOrder = skillEditPostString('display_order', '0');
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
    if ($percentage > 100) $errors[] = 'A percentagem deve estar entre 0 e 100.';
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
}
if ($errors) skillEditJsonResponse(['success' => false, 'errors' => $errors], 422);

$db = $GLOBALS['pdo'] ?? null;
if (!$db instanceof PDO) {
    error_log('[EDIT SKILL] PDO connection is unavailable.');
    skillEditJsonResponse(['success' => false, 'message' => 'Serviço de dados indisponível. Tenta novamente.'], 500);
}

try {
    $stmt = $db->prepare('SELECT id_skill, name_skill, percentage_skill, category_skill, icon_skill, display_order, is_visible FROM _skills WHERE id_skill = ? LIMIT 1');
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) skillEditJsonResponse(['success' => false, 'message' => 'Skill não encontrada.'], 404);

    $duplicate = $db->prepare('SELECT COUNT(*) FROM _skills WHERE name_skill = ? AND id_skill <> ?');
    $duplicate->execute([$name, $id]);
    if ((int) $duplicate->fetchColumn() > 0) {
        skillEditJsonResponse(['success' => false, 'errors' => ['Já existe uma skill com este nome. Escolhe um nome diferente.']], 409);
    }

    $stmt = $db->prepare('UPDATE _skills SET name_skill = ?, percentage_skill = ?, category_skill = ?, icon_skill = ?, display_order = ?, is_visible = ? WHERE id_skill = ?');
    $stmt->execute([$name, $percentage, $category, $icon, $displayOrder, $isVisible, $id]);
} catch (PDOException $exception) {
    error_log('[EDIT SKILL DB] ' . $exception->getMessage());
    skillEditJsonResponse(['success' => false, 'message' => 'Erro interno ao actualizar a skill. Tenta novamente.'], 500);
}

// Auditoria não altera o resultado de uma actualização que já foi concluída.
try {
    logAudit(
        (int) ($_SESSION['admin_id'] ?? 0),
        null,
        'skill.update',
        '_skills',
        $id,
        $old,
        ['name_skill' => $name, 'percentage_skill' => $percentage, 'category_skill' => $category,
         'icon_skill' => $icon, 'display_order' => $displayOrder, 'is_visible' => $isVisible]
    );
} catch (Throwable $exception) {
    error_log('[EDIT SKILL AUDIT] ' . $exception->getMessage());
}

skillEditJsonResponse(['success' => true, 'message' => 'Skill actualizada com sucesso.', 'id' => $id]);