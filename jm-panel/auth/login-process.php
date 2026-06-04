<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Login do Painel (melhorado)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jm-panel/entrar');
}

// ── CSRF ──────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (!validateAdminCsrf($token)) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token inválido. Recarregue a página.'));
}

// ── Capturar campos ───────────────────────────────────────────
$login    = trim($_POST['login']    ?? '');
$password = $_POST['password']      ?? '';
$remember = isset($_POST['remember']);

if ($login === '' || $password === '') {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Preencha todos os campos.'));
}

// ── Buscar admin (por email OU username) ─────────────────────
$db = $GLOBALS['pdo'];
$stmt = $db->prepare("
    SELECT e.*,
           s.login_attempts, s.block_until, s.block_level,
           s.remember_token, s.lockscreen, s.access_code
    FROM _employees e
    LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
    WHERE (e.email_employees = :login OR e.user_employees = :login2)
    LIMIT 1
");
$stmt->execute(['login' => strtolower($login), 'login2' => $login]);
$admin = $stmt->fetch();

if (!$admin) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Credenciais inválidas.'));
}

// ── Verificar bloqueio ───────────────────────────────────────
$block = isAdminBlocked($admin);
if ($block['blocked']) {
    $reason = $block['reason'];
    // Mapeia o motivo para o parâmetro 'msg' adequado
    $msg_type = match ($reason) {
        'attempts' => 'blocked',
        'status_inactive', 'status_blocked' => 'inactive',
        default => 'error'
    };
    $error_text = match ($reason) {
        'attempts' => 'Conta temporariamente bloqueada. Tente novamente mais tarde.',
        'status_inactive' => 'Esta conta está inactiva. Contacte o super administrador.',
        'status_blocked' => 'Esta conta está bloqueada. Contacte o super administrador.',
        default => 'Credenciais inválidas.'
    };
    redirect('/jm-panel/entrar?msg=' . urlencode($msg_type) . '&error=' . urlencode($error_text));
}

// ── Verificar password ───────────────────────────────────────
if (!password_verify($password, $admin['password_employees'])) {
    recordFailedAdminLogin((int)$admin['id_employees']);
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Credenciais inválidas.'));
}

// ── Sucesso! Registrar login, limpar tentativas ───────────────
resetAdminLoginAttempts((int)$admin['id_employees']);
recordAdminLoginSuccess((int)$admin['id_employees']);

// ── Popular sessão ───────────────────────────────────────────
_populateAdminSession($admin);
$_SESSION['admin_login_time'] = time();

// ── Remember me ───────────────────────────────────────────────
if ($remember) {
    setAdminRememberCookie((int)$admin['id_employees']);
}

// ── Auditoria ────────────────────────────────────────────────
logAudit((int)$admin['id_employees'], null, 'auth.login', 'employees', (int)$admin['id_employees']);

// ── Redirecionar para a página que originou o login (se houver) ──
$return = $_SESSION['admin_return_url'] ?? '/jm-panel/home';
unset($_SESSION['admin_return_url']);
redirect($return);