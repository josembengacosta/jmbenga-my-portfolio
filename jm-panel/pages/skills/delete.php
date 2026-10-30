<?php
declare(strict_types=1);
// JMbenga Portfolio — Eliminar Skill (AJAX).
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();
header('Content-Type: application/json; charset=utf-8');

function skillDeleteJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    skillDeleteJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!validateAdminCsrf($token)) {
    skillDeleteJsonResponse(['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'], 403);
}

$rawId = $_GET['id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false || $id === null) {
    skillDeleteJsonResponse(['success' => false, 'message' => 'ID de skill inválido.'], 400);
}
$id = (int) $id;
$db = $GLOBALS['pdo'] ?? null;
if (!$db instanceof PDO) {
    error_log('[DELETE SKILL] PDO connection is unavailable.');
    skillDeleteJsonResponse(['success' => false, 'message' => 'Serviço de dados indisponível. Tenta novamente.'], 500);
}

try {
    $stmt = $db->prepare('SELECT id_skill, name_skill FROM _skills WHERE id_skill = ? LIMIT 1');
    $stmt->execute([$id]);
    $skill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$skill) skillDeleteJsonResponse(['success' => false, 'message' => 'Skill não encontrada.'], 404);

    $deleteStmt = $db->prepare('DELETE FROM _skills WHERE id_skill = ?');
    $deleteStmt->execute([$id]);
    if ($deleteStmt->rowCount() < 1) {
        skillDeleteJsonResponse(['success' => false, 'message' => 'A skill já não existe ou foi eliminada por outra operação.'], 404);
    }
} catch (PDOException $exception) {
    error_log('[DELETE SKILL DB] ' . $exception->getMessage());
    skillDeleteJsonResponse(['success' => false, 'message' => 'Não foi possível eliminar a skill. Verifica se existem registos associados e tenta novamente.'], 500);
}

try {
    logAudit((int) ($_SESSION['admin_id'] ?? 0), null, 'skill.delete', '_skills', $id,
        ['name' => $skill['name_skill']], null);
} catch (Throwable $exception) {
    error_log('[DELETE SKILL AUDIT] ' . $exception->getMessage());
}

skillDeleteJsonResponse(['success' => true, 'message' => 'Skill eliminada com sucesso.']);