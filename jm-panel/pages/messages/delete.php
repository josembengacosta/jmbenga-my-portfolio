<?php
declare(strict_types=1);

// Endpoints AJAX da caixa de mensagens: resposta JSON consistente.
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$respond = static function (array $payload, int $status = 200): never {
    if (ob_get_level() > 0) {
        @ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

$db = null;
try {
    require_once __DIR__ . '/../../include/functions_admin.php';
    startAdminSession();
    checkAdminRememberMe();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        $respond(['success' => false, 'message' => 'Método não permitido.'], 405);
    }

    $db = $GLOBALS['pdo'] ?? null;
    if (!$db instanceof PDO) {
        throw new RuntimeException('A ligação à base de dados não está disponível.');
    }

    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        $respond(['success' => false, 'message' => 'A sessão expirou. Inicia sessão novamente.'], 401);
    }

    if (function_exists('isLockscreenActive') && isLockscreenActive()) {
        $respond(['success' => false, 'message' => 'O painel está bloqueado. Desbloqueia a sessão para continuar.'], 423);
    }

    // Revalidar conta ativa também para pedidos AJAX.
    $adminStmt = $db->prepare("SELECT e.status_employees, COALESCE(s.lockscreen, 0) AS lockscreen
        FROM _employees e
        LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
        WHERE e.id_employees = ? LIMIT 1");
    $adminStmt->execute([$adminId]);
    $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if (!$adminRow || ($adminRow['status_employees'] ?? null) !== 'active') {
        $respond(['success' => false, 'message' => 'A conta não está autorizada. Inicia sessão novamente.'], 401);
    }
    if (!empty($adminRow['lockscreen'])) {
        $_SESSION['admin_lockscreen'] = true;
        $respond(['success' => false, 'message' => 'O painel está bloqueado. Desbloqueia a sessão para continuar.'], 423);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !validateAdminCsrf($token)) {
        $respond(['success' => false, 'message' => 'Token de segurança inválido. Atualiza a página e tenta novamente.'], 403);
    }

    $rawId = $_GET['id'] ?? null;
    $id = is_string($rawId) || is_int($rawId)
        ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if (!is_int($id) || $id < 1) {
        $respond(['success' => false, 'message' => 'Identificador de mensagem inválido.'], 400);
    }

    $stmt = $db->prepare('SELECT name_msg, subject_msg FROM _contact_message WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$msg) {
        $respond(['success' => false, 'message' => 'Mensagem não encontrada.'], 404);
    }

    $db->beginTransaction();
    try {
        $deleteStmt = $db->prepare('DELETE FROM _contact_message WHERE id = ?');
        $deleteStmt->execute([$id]);
        if ($deleteStmt->rowCount() !== 1) {
            throw new RuntimeException('A mensagem não foi eliminada.');
        }
        logAudit($adminId, null, 'message.delete', '_contact_message', $id,
            ['name' => (string) $msg['name_msg'], 'subject' => (string) $msg['subject_msg']], null);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    $respond(['success' => true, 'message' => 'Mensagem eliminada.']);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('[MESSAGE DELETE API] ' . $e->getMessage());
    $respond(['success' => false, 'message' => 'Não foi possível eliminar a mensagem. Tenta novamente.'], 500);
}