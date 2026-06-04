<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador IP Whitelist (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$action = $_GET['action'] ?? 'add';
$adminId = (int)$_SESSION['admin_id'];

switch ($action) {
    case 'add':
        $ip    = trim($_POST['ip_address'] ?? '');
        $label = trim($_POST['label'] ?? '');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(['success' => false, 'message' => 'Endereço IP inválido.']);
            exit;
        }

        // Verificar duplicado
        $stmt = $db->prepare("SELECT COUNT(*) FROM _admin_ip_whitelist WHERE ip_address = ?");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este IP já está na whitelist.']);
            exit;
        }

        $db->prepare("INSERT INTO _admin_ip_whitelist (ip_address, label, added_by) VALUES (?, ?, ?)")
           ->execute([$ip, $label, $adminId]);

        logAudit($adminId, null, 'security.ip_whitelist.add', '_admin_ip_whitelist', null, null, ['ip' => $ip]);

        echo json_encode(['success' => true, 'message' => 'IP adicionado com sucesso.']);
        break;

    case 'toggle':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT active FROM _admin_ip_whitelist WHERE id_ip = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'IP não encontrado.']);
            exit;
        }
        $newActive = $row['active'] ? 0 : 1;
        $db->prepare("UPDATE _admin_ip_whitelist SET active = ? WHERE id_ip = ?")->execute([$newActive, $id]);
        logAudit($adminId, null, 'security.ip_whitelist.toggle', '_admin_ip_whitelist', $id, ['active' => $row['active']], ['active' => $newActive]);
        echo json_encode(['success' => true, 'active' => $newActive]);
        break;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT ip_address FROM _admin_ip_whitelist WHERE id_ip = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'IP não encontrado.']);
            exit;
        }
        $db->prepare("DELETE FROM _admin_ip_whitelist WHERE id_ip = ?")->execute([$id]);
        logAudit($adminId, null, 'security.ip_whitelist.delete', '_admin_ip_whitelist', $id, ['ip' => $row['ip_address']], null);
        echo json_encode(['success' => true, 'message' => 'IP removido.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acção inválida.']);
}
exit;