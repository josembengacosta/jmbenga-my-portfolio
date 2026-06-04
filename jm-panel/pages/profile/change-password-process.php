<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Alterar Senha (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];
$adminId = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Preenche todos os campos.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

if (mb_strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 8 caracteres.']);
    exit;
}

// Verificar senha actual
$stmt = $db->prepare("SELECT password_employees FROM _employees WHERE id_employees = ?");
$stmt->execute([$adminId]);
$hash = $stmt->fetchColumn();

if (!$hash || !password_verify($currentPassword, $hash)) {
    echo json_encode(['success' => false, 'message' => 'Senha actual incorrecta.']);
    exit;
}

// Actualizar
$newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$db->prepare("UPDATE _employees SET password_employees = ? WHERE id_employees = ?")->execute([$newHash, $adminId]);

logAudit($adminId, null, 'profile.change_password', '_employees', $adminId);

echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso.']);
exit;