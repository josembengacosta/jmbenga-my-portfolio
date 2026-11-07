<?php
declare(strict_types=1);
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Alterar Senha (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

/** Resposta JSON uniforme para este endpoint AJAX. */
function changePasswordJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    changePasswordJson(['success' => false, 'message' => 'Método não permitido.'], 405);
}

if (!isAdminLoggedIn()) {
    changePasswordJson(['success' => false, 'message' => 'A sessão expirou. Inicia sessão novamente.'], 401);
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    changePasswordJson(['success' => false, 'message' => 'Sessão administrativa inválida. Inicia sessão novamente.'], 401);
}

$db = $GLOBALS['pdo'];
try {
    // Revalidar o estado da conta. O bloqueio activo é controlado na sessão;
    // a coluna _employees_security.lockscreen configura a funcionalidade para a conta.
    $accountStmt = $db->prepare('
        SELECT password_employees, status_employees
        FROM _employees
        WHERE id_employees = ?
        LIMIT 1
    ');
    $accountStmt->execute([$adminId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[CHANGE PASSWORD] Falha ao consultar a conta: ' . $e->getMessage());
    changePasswordJson(['success' => false, 'message' => 'Não foi possível verificar a conta. Tenta novamente.'], 500);
}

if (!$account || ($account['status_employees'] ?? '') !== 'active') {
    logoutAdmin();
    changePasswordJson(['success' => false, 'message' => 'A conta não está activa. Inicia sessão novamente.'], 401);
}

if (isLockscreenActive()) {
    changePasswordJson(['success' => false, 'message' => 'O painel está bloqueado. Desbloqueia a sessão antes de alterar a senha.'], 423);
}

$tokenValue = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfToken = is_string($tokenValue) ? $tokenValue : '';
if (!validateAdminCsrf($csrfToken)) {
    changePasswordJson(['success' => false, 'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'], 403);
}

$currentPasswordValue = $_POST['current_password'] ?? '';
$newPasswordValue = $_POST['new_password'] ?? '';
$confirmPasswordValue = $_POST['confirm_password'] ?? '';
if (!is_string($currentPasswordValue) || !is_string($newPasswordValue) || !is_string($confirmPasswordValue)) {
    changePasswordJson(['success' => false, 'message' => 'Formato dos campos inválido.'], 422);
}
$currentPassword = $currentPasswordValue;
$newPassword = $newPasswordValue;
$confirmPassword = $confirmPasswordValue;

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    changePasswordJson(['success' => false, 'message' => 'Preenche todos os campos.'], 422);
}
if (strlen($currentPassword) > 4096 || strlen($newPassword) > 4096 || strlen($confirmPassword) > 4096) {
    changePasswordJson(['success' => false, 'message' => 'Um dos campos excede o tamanho permitido.'], 422);
}
if ($newPassword !== $confirmPassword) {
    changePasswordJson(['success' => false, 'message' => 'A nova senha e a confirmação não coincidem.'], 422);
}
if (mb_strlen($newPassword, 'UTF-8') < 8) {
    changePasswordJson(['success' => false, 'message' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
}
// PASSWORD_BCRYPT só considera até 72 bytes; rejeitar o excedente evita colisões por truncagem.
if (strlen($newPassword) > 72) {
    changePasswordJson(['success' => false, 'message' => 'A nova senha não pode ultrapassar 72 bytes. Usa uma combinação mais curta.'], 422);
}
if (hash_equals($currentPassword, $newPassword)) {
    changePasswordJson(['success' => false, 'message' => 'A nova senha deve ser diferente da senha actual.'], 422);
}

$oldHash = $account['password_employees'] ?? null;
if (!is_string($oldHash) || $oldHash === '' || !password_verify($currentPassword, $oldHash)) {
    changePasswordJson(['success' => false, 'message' => 'Senha actual incorrecta.'], 422);
}

try {
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($newHash) || $newHash === '') {
        throw new RuntimeException('password_hash não devolveu um hash válido.');
    }

    $update = $db->prepare('UPDATE _employees SET password_employees = ? WHERE id_employees = ?');
    $update->execute([$newHash, $adminId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('A actualização da senha não confirmou um registo alterado.');
    }

    // Registar a alteração sem guardar a senha ou o hash na auditoria.
    logAudit($adminId, null, 'profile.change_password', '_employees', $adminId);

    // Invalidar o remember-me persistente após a alteração da credencial.
    // A sessão actual continua activa; só se remove o token/cookie de acesso persistente.
    clearAdminRememberCookie($adminId);

    // Renovar o identificador após a alteração da credencial, sem invalidar a sessão actual.
    if (!session_regenerate_id(true)) {
        error_log('[CHANGE PASSWORD] Não foi possível regenerar o ID da sessão após a alteração da senha.');
    }

    // Rodar o CSRF após a operação sensível e devolver o novo token para o formulário.
    regenerateAdminCsrf();

    changePasswordJson([
        'success' => true,
        'message' => 'Senha alterada com sucesso. O acesso persistente foi invalidado por segurança.',
        'csrf_token' => $_SESSION['admin_csrf_token'],
    ]);
} catch (Throwable $e) {
    error_log('[CHANGE PASSWORD] Falha ao actualizar a senha: ' . $e->getMessage());
    changePasswordJson(['success' => false, 'message' => 'Erro interno ao alterar a senha. Tenta novamente.'], 500);
}