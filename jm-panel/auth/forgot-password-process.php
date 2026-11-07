<?php
declare(strict_types=1);

// JMbenga Portfolio — Processador de recuperação de senha
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Este endpoint só aceita submissões de formulário.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('/jm-panel/forgot-password');
}

// CSRF: não converter arrays enviados pelo cliente para string.
$csrfInput = $_POST['csrf_token'] ?? null;
if (!is_string($csrfInput) || !validateAdminCsrf($csrfInput)) {
    redirect('/jm-panel/forgot-password?msg=error&error=' . rawurlencode('A sessão do formulário expirou. Recarregue a página e tente novamente.'));
}

$db = $GLOBALS['pdo'];
$ip = getRealClientIp();

// O limite consulta o mesmo IP guardado pelo logAudit(). Se a consulta falhar,
// falhamos de forma fechada para não permitir pedidos ilimitados por erro de BD.
try {
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM _audit_log
         WHERE ip_address = :ip
           AND action = 'auth.forgot_password'
           AND creat_log > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $countStmt->execute(['ip' => $ip]);
    $recentAttempts = (int) $countStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[FORGOT PASSWORD] Não foi possível verificar o limite de pedidos: ' . $e->getMessage());
    redirect('/jm-panel/forgot-password?msg=error&error=' . rawurlencode('Não foi possível processar o pedido neste momento. Tente novamente mais tarde.'));
}

if ($recentAttempts >= 3) {
    redirect('/jm-panel/forgot-password?msg=error&error=' . rawurlencode('Demasiadas tentativas. Aguarde 15 minutos.'));
}

// Normaliza a entrada antes de validar; arrays e entradas excessivamente grandes são rejeitados.
$emailInput = $_POST['email'] ?? null;
$email = is_string($emailInput) ? trim($emailInput) : '';
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Regista também submissões inválidas para que não contornem o rate limit.
    logAudit(null, null, 'auth.forgot_password', 'employees', null,
        ['email_hash' => $email !== '' ? hash('sha256', strtolower($email)) : null],
        ['result' => 'invalid_input']);
    redirect('/jm-panel/forgot-password?msg=invalid');
}

// Conta todos os pedidos válidos, mesmo quando o email não corresponde a uma conta.
// Só guardamos um hash do email no log; a resposta pública não revela a existência da conta.
logAudit(null, null, 'auth.forgot_password', 'employees', null,
    ['email_hash' => hash('sha256', strtolower($email))],
    ['result' => 'request_received']);

try {
    $admin = getAdminByEmail($email);

    if ($admin && ($admin['status_employees'] ?? null) === 'active') {
        $adminId = (int) ($admin['id_employees'] ?? 0);

        if ($adminId > 0) {
            $resetToken = createAdminResetToken($adminId);

            // Não enviar um link se o token não tiver sido persistido correctamente.
            $verifyToken = $db->prepare(
                'SELECT 1 FROM _employees_security
                 WHERE id_employees = :admin_id
                   AND reset_password_token = :token
                   AND reset_password_expires > NOW()
                 LIMIT 1'
            );
            $verifyToken->execute(['admin_id' => $adminId, 'token' => hash('sha256', $resetToken)]);
            if (!$verifyToken->fetchColumn()) {
                throw new RuntimeException('O token de recuperação não foi persistido na tabela de segurança.');
            }

            $name = trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['second_name'] ?? ''));
            $sent = sendAdminResetEmail($email, $name, $resetToken);

            if (!$sent) {
                error_log('[FORGOT PASSWORD] O serviço de email não confirmou o envio do link de recuperação.');
            }
        }
    }
} catch (Throwable $e) {
    // Não revelar ao visitante se o email existe nem detalhes internos da BD/mailer.
    error_log('[FORGOT PASSWORD] Falha interna ao processar um pedido de recuperação: ' . $e->getMessage());
}

// Resposta genérica, independentemente de a conta existir, estar activa ou o email ter sido enviado.
redirect('/jm-panel/forgot-password?msg=sent');