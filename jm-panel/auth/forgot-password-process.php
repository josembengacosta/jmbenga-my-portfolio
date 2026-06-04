<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Esqueceu a Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jm-panel/entrar');
}

// ── CSRF ──────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (!validateAdminCsrf($token)) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token inválido. Recarregue a página.'));
}

// ── Rate limit por IP (máximo 3 pedidos em 15 minutos) ───────
$ip      = get_ip();
$countStmt = $GLOBALS['pdo']->prepare(
    "SELECT COUNT(*) FROM _audit_log
     WHERE ip_address = ? AND action = 'auth.forgot_password'
       AND creat_log > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
$countStmt->execute([$ip]);
$recentAttempts = (int)$countStmt->fetchColumn();

if ($recentAttempts >= 3) {
    redirect('/jm-panel/forgot-password?msg=error&error=' . urlencode('Demasiadas tentativas. Aguarde 15 minutos.'));
}

// ── Capturar e validar email ──────────────────────────────────
$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/jm-panel/forgot-password?msg=invalid');
}

// ── Procurar admin pelo email ─────────────────────────────────
$admin = getAdminByEmail($email);

if ($admin) {
    // Verificar se a conta está ativa
    if ($admin['status_employees'] !== 'active') {
        redirect('/jm-panel/forgot-password?msg=sent');
    }

    // Gerar token de reset (armazenado em _employees_security)
    $resetToken = createAdminResetToken((int)$admin['id_employees']);

    // Enviar email com o link (usa a função do functions_admin.php)
    $name = trim($admin['first_name'] . ' ' . ($admin['second_name'] ?? ''));
    $sent = sendAdminResetEmail($email, $name, $resetToken);

    // Auditoria (mesmo que o envio falhe)
    logAudit(
        (int)$admin['id_employees'],
        null,
        'auth.forgot_password',
        'employees',
        (int)$admin['id_employees'],
        ['sent' => $sent]
    );

    // Se o envio falhou, ainda assim redirecionamos com msg=sent
    // (não revelamos detalhes técnicos ao utilizador)
    if (!$sent) {
        error_log('[FORGOT PASSWORD] Falha ao enviar email para ' . $email);
    }
}

// ── Redirecionar SEMPRE com msg=sent (não revela se o email existe ou não) ──
redirect('/jm-panel/forgot-password?msg=sent');