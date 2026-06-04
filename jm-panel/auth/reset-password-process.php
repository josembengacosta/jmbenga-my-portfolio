<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Redefinir Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jm-panel/entrar');
}

// ── CSRF ──────────────────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateAdminCsrf($csrfToken)) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token inválido. Recarregue a página.'));
}

// ── Capturar campos ───────────────────────────────────────────
$token           = $_POST['token'] ?? '';
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// ── Validação do token ────────────────────────────────────────
$adminId = validateAdminResetToken($token);
if (!$adminId) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token de recuperação inválido ou expirado.'));
}

// ── Validação da password ─────────────────────────────────────
if ($password === '' || mb_strlen($password) < 8) {
    redirect('/jm-panel/reset-password?token=' . urlencode($token) . '&error=' . urlencode('A senha deve ter pelo menos 8 caracteres.'));
}

if (
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
) {
    redirect('/jm-panel/reset-password?token=' . urlencode($token) . '&error=' . urlencode('A senha deve conter maiúsculas, minúsculas, números e caracteres especiais.'));
}

if ($password !== $confirmPassword) {
    redirect('/jm-panel/reset-password?token=' . urlencode($token) . '&error=' . urlencode('As senhas não coincidem.'));
}

// ── Actualizar password ───────────────────────────────────────
updateAdminPassword($adminId, $password);

// ── Auditoria ────────────────────────────────────────────────
logAudit($adminId, null, 'auth.password_reset', 'employees', $adminId);

// ── Redirecionar para a própria página com msg=success ────────
redirect('/jm-panel/reset-password?token=' . urlencode($token) . '&msg=success');