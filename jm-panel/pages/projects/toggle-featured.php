<?php
// JMbenga Portfolio — Alternar destaque do projecto (AJAX)
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$db = $GLOBALS['pdo'];
if (empty($_SESSION['admin_id'])) {
    $respond(401, ['success' => false, 'message' => 'A sessão expirou. Inicia sessão novamente.']);
}

if (function_exists('isLockscreenActive') && isLockscreenActive()) {
    $respond(423, ['success' => false, 'message' => 'O painel está bloqueado. Desbloqueia a sessão e tenta novamente.']);
}

try {
    $authStmt = $db->prepare('SELECT status_employees, role FROM _employees WHERE id_employees = ? LIMIT 1');
    $authStmt->execute([(int) $_SESSION['admin_id']]);
    $activeAdmin = $authStmt->fetch(PDO::FETCH_ASSOC);
    if (!$activeAdmin || ($activeAdmin['status_employees'] ?? '') !== 'active') {
        $respond(401, ['success' => false, 'message' => 'A conta administrativa já não está activa. Inicia sessão novamente.']);
    }
    $_SESSION['admin_role'] = (string) ($activeAdmin['role'] ?? '');
} catch (Throwable $exception) {
    error_log('[PROJECT AJAX AUTH] ' . $exception->getMessage());
    $respond(500, ['success' => false, 'message' => 'Não foi possível validar a sessão administrativa.']);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf(is_string($token) ? $token : '')) {
    $respond(403, ['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.']);
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    $respond(400, ['success' => false, 'message' => 'ID de projecto inválido.']);
}
$id = (int) $id;
try {
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT is_featured, title_project FROM _projects WHERE id_project = ? FOR UPDATE');
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        $db->rollBack();
        $respond(404, ['success' => false, 'message' => 'Projecto não encontrado.']);
    }

    $currentFeatured = (int) $project['is_featured'];
    $newFeatured = $currentFeatured === 1 ? 0 : 1;
    $db->prepare('UPDATE _projects SET is_featured = ? WHERE id_project = ?')->execute([$newFeatured, $id]);

    logAudit(
        (int) $_SESSION['admin_id'], null, 'project.toggle_featured', '_projects', $id,
        ['is_featured' => $currentFeatured], ['is_featured' => $newFeatured]
    );
    $db->commit();

    $respond(200, [
        'success' => true,
        'is_featured' => $newFeatured,
        'message' => $newFeatured ? 'Projecto adicionado ao destaque.' : 'Projecto removido do destaque.',
    ]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[TOGGLE PROJECT FEATURED] ' . $exception->getMessage());
    $respond(500, ['success' => false, 'message' => 'Não foi possível alterar o destaque. Tenta novamente.']);
}
