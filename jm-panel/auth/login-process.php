<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Login do Painel
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Este endpoint só aceita submissões do formulário.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('/jm-panel/entrar');
}

// CSRF: não converter arrays enviados pelo cliente para string.
$csrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($csrfToken) || !validateAdminCsrf($csrfToken)) {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('A sessão do formulário expirou. Recarregue a página e tente novamente.'));
}

$loginInput = $_POST['login'] ?? null;
$passwordInput = $_POST['password'] ?? null;
$login = is_string($loginInput) ? trim($loginInput) : '';
$password = is_string($passwordInput) ? $passwordInput : '';
$rememberInput = $_POST['remember'] ?? null;
$remember = is_string($rememberInput) && $rememberInput === '1';

if ($login === '' || $password === '') {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Preencha o utilizador/e-mail e a senha.'));
}

// Limites defensivos para entradas anormalmente grandes.
if (strlen($login) > 255 || strlen($password) > 4096) {
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Credenciais inválidas.'));
}

$db = $GLOBALS['pdo'];

try {
    // Placeholders separados para compatibilidade com prepared statements nativos do PDO.
    $stmt = $db->prepare("
        SELECT e.*,
               s.login_attempts,
               s.block_until,
               s.block_level,
               s.remember_token,
               s.lockscreen,
               s.access_code
        FROM _employees AS e
        LEFT JOIN _employees_security AS s ON s.id_employees = e.id_employees
        WHERE e.email_employees = :email_login
           OR e.user_employees = :username_login
        LIMIT 1
    ");
    $stmt->execute([
        'email_login'    => strtolower($login),
        'username_login' => $login,
    ]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Não revelar se o e-mail/username existe.
    if (!$admin) {
        redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Credenciais inválidas. Verifique os dados e tente novamente.'));
    }

    $block = isAdminBlocked($admin);
    if (!empty($block['blocked'])) {
        $reason = (string) ($block['reason'] ?? '');

        $message = match ($reason) {
            'attempts' => 'Conta temporariamente bloqueada. Tente novamente mais tarde.',
            'status_inactive' => 'Esta conta está inactiva. Contacte o super administrador.',
            'status_blocked' => 'Esta conta está bloqueada. Contacte o super administrador.',
            default => 'Não foi possível autenticar. Verifique as credenciais ou contacte o administrador.',
        };
        $messageType = $reason === 'attempts' ? 'blocked'
            : (in_array($reason, ['status_inactive', 'status_blocked'], true) ? 'inactive' : 'error');

        redirect('/jm-panel/entrar?msg=' . rawurlencode($messageType) . '&error=' . rawurlencode($message));
    }

    $storedPassword = $admin['password_employees'] ?? null;
    if (!is_string($storedPassword) || !password_verify($password, $storedPassword)) {
        recordFailedAdminLogin((int) $admin['id_employees']);
        redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Credenciais inválidas. Verifique os dados e tente novamente.'));
    }

    $adminId = (int) $admin['id_employees'];

    // Impede session fixation e roda o token CSRF após autenticação.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Não foi possível regenerar o identificador da sessão administrativa.');
    }
    regenerateAdminCsrf();

    _populateAdminSession($admin);
    $_SESSION['admin_login_time'] = time();

    // Esta função já actualiza o último login e limpa as tentativas/bloqueio.
    recordAdminLoginSuccess($adminId);

    // Remember-me é opcional: uma falha nele não deve invalidar o login normal.
    if ($remember) {
        try {
            setAdminRememberCookie($adminId);
        } catch (Throwable $rememberError) {
            error_log('[ADMIN LOGIN] Não foi possível activar remember-me: ' . $rememberError->getMessage());
        }
    }

    logAudit($adminId, null, 'auth.login', 'employees', $adminId);

    // Aceita apenas um destino interno do painel. Nunca redirecciona para um host externo.
    $return = $_SESSION['admin_return_url'] ?? '/jm-panel/home';
    unset($_SESSION['admin_return_url']);

    if (!is_string($return)
        || $return === ''
        || str_starts_with($return, '//')
        || str_contains($return, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $return)
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $return)
        || !str_starts_with($return, '/jm-panel/')) {
        $return = '/jm-panel/home';
    }

    if (!empty($_SESSION['admin_lockscreen'])) {
        redirect('/jm-panel/lockscreen');
    }

    redirect($return);
} catch (Throwable $e) {
    error_log('[ADMIN LOGIN] Falha durante a autenticação: ' . $e->getMessage());
    redirect('/jm-panel/entrar?msg=error&error=' . rawurlencode('Não foi possível iniciar sessão neste momento. Tente novamente.'));
}