<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Redefinir Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('/jm-panel/entrar');
}

$csrfInput = $_POST['csrf_token'] ?? '';
$csrfToken = is_string($csrfInput) ? $csrfInput : '';
if (!validateAdminCsrf($csrfToken)) {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Pedido inválido. Recarregue a página e tente novamente.'));
}

$tokenInput = $_POST['token'] ?? '';
$token = is_string($tokenInput) ? trim($tokenInput) : '';
if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Token de recuperação inválido ou expirado.'));
}

$passwordInput = $_POST['password'] ?? null;
$confirmInput = $_POST['confirm_password'] ?? null;
if (!is_string($passwordInput) || !is_string($confirmInput)) {
    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('Preencha os dois campos de senha.'));
}
$password = $passwordInput;
$confirmPassword = $confirmInput;

$adminId = validateAdminResetToken($token);
if (!$adminId) {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Token de recuperação inválido ou expirado.'));
}

$admin = getAdminById((int) $adminId);
if (!$admin || ($admin['status_employees'] ?? null) !== 'active') {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Token de recuperação inválido ou expirado.'));
}

$passwordLength = function_exists('mb_strlen')
    ? mb_strlen($password, 'UTF-8')
    : strlen($password);

if ($passwordLength < 8) {
    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('A senha deve ter pelo menos 8 caracteres.'));
}

// PASSWORD_BCRYPT considera no máximo 72 bytes; rejeitar senhas mais longas
// evita que sufixos sejam ignorados silenciosamente no hash.
if (strlen($password) > 72) {
    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('A senha não pode exceder 72 bytes. Escolha uma senha mais curta.'));
}

if (
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
) {
    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('A senha deve conter maiúsculas, minúsculas, números e caracteres especiais.'));
}

if (!hash_equals($password, $confirmPassword)) {
    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('As senhas não coincidem.'));
}

try {
    // Esta função já atualiza o hash, consome o token e grava a auditoria.
    // Não duplicar logAudit() aqui.
    updateAdminPassword((int) $adminId, $password);

    // Invalidar o remember-me persistente após a alteração da senha.
    clearAdminRememberCookie((int) $adminId);
} catch (Throwable $e) {
    error_log('[ADMIN PASSWORD RESET] Falha ao redefinir senha: ' . $e->getMessage());

    // Se o token já tiver sido consumido, a alteração pode ter sido concluída
    // antes de ocorrer uma falha secundária; não voltar a expor detalhes técnicos.
    if (validateAdminResetToken($token) === null) {
        redirect('/jm-panel/entrar?msg=reset_ok');
    }

    redirect('/jm-panel/reset-password?token=' . rawurlencode($token) . '&error=' . rawurlencode('Não foi possível concluir a alteração. Tente novamente.'));
}

// O token já foi consumido: não o voltar a colocar na URL.
redirect('/jm-panel/entrar?msg=reset_ok');