<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Manutenção (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];
$adminId = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$status         = $_POST['status'] ?? 'active';
$maintenanceMsg = trim($_POST['maintenance_msg'] ?? '');
$maintStart     = trim($_POST['maintenance_start'] ?? '');
$maintEnd       = trim($_POST['maintenance_end'] ?? '');

$allowedStatuses = ['active', 'maintenance', 'blocked'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'errors' => ['Estado inválido.']]);
    exit;
}

$maintStartVal = !empty($maintStart) ? date('Y-m-d H:i:s', strtotime($maintStart)) : null;
$maintEndVal   = !empty($maintEnd)   ? date('Y-m-d H:i:s', strtotime($maintEnd))   : null;

try {
    $db->prepare("UPDATE _platform SET status = ?, maintenance_msg = ?, maintenance_start = ?, maintenance_end = ? WHERE id_platform = 1")
       ->execute([$status, $maintenanceMsg, $maintStartVal, $maintEndVal]);

    logAudit($adminId, null, 'settings.maintenance.update', '_platform', 1, null, ['status' => $status]);

    echo json_encode(['success' => true, 'message' => 'Estado do site actualizado com sucesso.']);
} catch (PDOException $e) {
    error_log('[MAINTENANCE] ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Erro interno.']]);
}
exit;