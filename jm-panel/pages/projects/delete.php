<?php
// JMbenga Portfolio — Eliminar projecto (AJAX)
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

    $projectStmt = $db->prepare('SELECT id_project, title_project, cover_project FROM _projects WHERE id_project = ? FOR UPDATE');
    $projectStmt->execute([$id]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        $db->rollBack();
        $respond(404, ['success' => false, 'message' => 'Projecto não encontrado.']);
    }

    $mediaStmt = $db->prepare('SELECT url_media FROM _projects_media WHERE id_project = ?');
    $mediaStmt->execute([$id]);
    $mediaFiles = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

    $deleteStmt = $db->prepare('DELETE FROM _projects WHERE id_project = ?');
    $deleteStmt->execute([$id]);
    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('O projecto não foi eliminado pela base de dados.');
    }

    logAudit(
        (int) $_SESSION['admin_id'],
        null,
        'project.delete',
        '_projects',
        $id,
        ['title' => $project['title_project']],
        null
    );

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[DELETE PROJECT] ' . $exception->getMessage());
    $respond(500, ['success' => false, 'message' => 'Não foi possível eliminar o projecto. Os ficheiros foram preservados.']);
}

// Só depois do commit tentamos remover as imagens. Uma falha aqui não deve desfazer a resposta da BD.
$filesToDelete = array_unique(array_filter(array_merge(
    [(string) ($project['cover_project'] ?? '')],
    array_map(static fn ($name): string => is_string($name) ? $name : '', $mediaFiles)
)));
foreach ($filesToDelete as $filename) {
    if ($filename === '' || basename($filename) !== $filename || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z/i', $filename)) {
        continue;
    }

    // Não apagar um ficheiro ainda referenciado por outro projecto ou item de media.
    $referenceStmt = $db->prepare(
        'SELECT (SELECT COUNT(*) FROM _projects WHERE cover_project = ?) + '
        . '(SELECT COUNT(*) FROM _projects_media WHERE url_media = ?)'
    );
    $referenceStmt->execute([$filename, $filename]);
    if ((int) $referenceStmt->fetchColumn() > 0) continue;

    $path = ROOT_PATH . '/assets/img/projects/' . $filename;
    if (is_file($path) && !@unlink($path)) {
        error_log('[DELETE PROJECT] Não foi possível remover o ficheiro de imagem: ' . $path);
    }
}

$respond(200, ['success' => true, 'message' => 'Projecto eliminado com sucesso.']);
