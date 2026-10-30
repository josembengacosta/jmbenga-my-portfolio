<?php
declare(strict_types=1);
// JMbenga Portfolio — Alternar visibilidade da Skill (AJAX).
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();
header('Content-Type: application/json; charset=utf-8');

function skillVisibilityJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    skillVisibilityJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!validateAdminCsrf($token)) {
    skillVisibilityJsonResponse(['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'], 403);
}

$rawId = $_GET['id'] ?? null;
$id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false || $id === null) {
    skillVisibilityJsonResponse(['success' => false, 'message' => 'ID de skill inválido.'], 400);
}
$id = (int) $id;
$db = $GLOBALS['pdo'] ?? null;
if (!$db instanceof PDO) {
    error_log('[TOGGLE SKILL] PDO connection is unavailable.');
    skillVisibilityJsonResponse(['success' => false, 'message' => 'Serviço de dados indisponível. Tenta novamente.'], 500);
}

try {
    // Bloqueio transaccional evita alternâncias calculadas sobre um estado desactualizado.
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT id_skill, is_visible FROM _skills WHERE id_skill = ? FOR UPDATE');
    $stmt->execute([$id]);
    $skill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$skill) {
        $db->rollBack();
        skillVisibilityJsonResponse(['success' => false, 'message' => 'Skill não encontrada.'], 404);
    }

    $currentVisible = (int) $skill['is_visible'] === 1 ? 1 : 0;
    $newVisible = $currentVisible === 1 ? 0 : 1;
    $update = $db->prepare('UPDATE _skills SET is_visible = ? WHERE id_skill = ?');
    $update->execute([$newVisible, $id]);
    $db->commit();
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) {
        try { $db->rollBack(); } catch (Throwable $rollbackException) {
            error_log('[TOGGLE SKILL ROLLBACK] ' . $rollbackException->getMessage());
        }
    }
    error_log('[TOGGLE SKILL DB] ' . $exception->getMessage());
    skillVisibilityJsonResponse(['success' => false, 'message' => 'Não foi possível actualizar a visibilidade da skill. Tenta novamente.'], 500);
}

try {
    logAudit((int) ($_SESSION['admin_id'] ?? 0), null, 'skill.toggle_visibility', '_skills', $id,
        ['is_visible' => $currentVisible], ['is_visible' => $newVisible]);
} catch (Throwable $exception) {
    error_log('[TOGGLE SKILL AUDIT] ' . $exception->getMessage());
}

skillVisibilityJsonResponse([
    'success' => true,
    'is_visible' => $newVisible,
    'message' => $newVisible ? 'Skill visível.' : 'Skill ocultada.',
]);