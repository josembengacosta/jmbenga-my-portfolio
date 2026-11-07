<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Funções do Painel Admin (Versão Corrigida)
// Arquivo: jm-panel/include/functions_admin.php
// ══════════════════════════════════════════════════════════════

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// ── Constantes exclusivas do painel admin ───────────────────
if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'jm_panel_sess');
}
if (!defined('ADMIN_COOKIE_NAME')) {
    define('ADMIN_COOKIE_NAME', 'jm_panel_remember');
}
if (!defined('ADMIN_COOKIE_DAYS')) {
    define('ADMIN_COOKIE_DAYS', 7);
}
if (!defined('ADMIN_MAX_ATTEMPTS')) {
    define('ADMIN_MAX_ATTEMPTS', 5);
}
if (!defined('ADMIN_BLOCK_1_MIN')) {
    define('ADMIN_BLOCK_1_MIN', 10); // 3 tentativas → 10 min
}
if (!defined('ADMIN_BLOCK_2_MIN')) {
    define('ADMIN_BLOCK_2_MIN', 30); // 5 tentativas → 30 min
}
if (!defined('ADMIN_BLOCK_3_MIN')) {
    define('ADMIN_BLOCK_3_MIN', 60); // 7+ tentativas → 60 min
}
if (!defined('ADMIN_LOGIN_IP_WINDOW_MINUTES')) {
    define('ADMIN_LOGIN_IP_WINDOW_MINUTES', 15);
}
if (!defined('ADMIN_LOGIN_IP_MAX_FAILURES')) {
    define('ADMIN_LOGIN_IP_MAX_FAILURES', 25);
}

/** Indica se os cookies administrativos devem ser marcados como Secure. */
function adminCookieRequiresSecure(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    return (defined('APP_ENV') && APP_ENV === 'production')
        || ($https !== '' && $https !== 'off' && $https !== '0')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function getAdminCookiePath(): string
{
    // Deve coincidir com o caminho configurado centralmente em config.php.
    if (defined('APP_ADMIN_COOKIE_PATH')) {
        return (string) APP_ADMIN_COOKIE_PATH;
    }

    // Fallback para chamadas isoladas, incluindo o prefixo do XAMPP quando aplicável.
    $basePath = parse_url((string) BASE_URL, PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
    $configuredPath = parse_url((string) ADMIN_PATH, PHP_URL_PATH);
    $adminPath = is_string($configuredPath) && $configuredPath !== ''
        ? '/' . trim($configuredPath, '/')
        : '/jm-panel';

    $path = rtrim($basePath . $adminPath, '/');
    return $path !== '' ? $path : '/';
}

// ══════════════════════════════════════════════════════════════
// SESSÃO & CSRF
// ══════════════════════════════════════════════════════════════

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');

        session_name(ADMIN_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 0,
            'path'     => getAdminCookiePath(),
            // Cookie host-only: evita domínio inválido por causa de :porta no HTTP_HOST.
            'secure'   => adminCookieRequiresSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão administrativa.');
        }
    }

    // config.php escolhe a sessão antes de iniciar o PHP session handler.
    // Se a rota estiver a usar outra sessão, falhar explicitamente é mais seguro
    // do que misturar a autenticação administrativa com a sessão pública.
    if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== ADMIN_SESSION_NAME) {
        error_log('[ADMIN SESSION] Contexto de sessão incompatível com a rota administrativa.');
        throw new RuntimeException('A sessão administrativa não está correctamente isolada.');
    }

    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!isset($_SESSION['admin_login_time']) && !empty($_SESSION['admin_id'])) {
        $_SESSION['admin_login_time'] = time();
    }
}

function validateAdminCsrf(string $token): bool
{
    return $token !== ''
        && session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['admin_csrf_token'])
        && is_string($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function regenerateAdminCsrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startAdminSession();
    }
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Identifica a área do painel solicitada. Considera tanto as rotas amigáveis
 * como o acesso directo aos ficheiros PHP, para que a autorização não dependa
 * exclusivamente das regras de rewrite do .htaccess.
 */
function getAdminRequestedSection(): string
{
    $basePath = parse_url((string) BASE_URL, PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
    $adminPath = parse_url((string) ADMIN_PATH, PHP_URL_PATH);
    $adminPath = is_string($adminPath) && $adminPath !== ''
        ? '/' . trim($adminPath, '/')
        : '/jm-panel';

    $candidates = [
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
        (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        (string) ($_SERVER['PHP_SELF'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $path = parse_url($candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            continue;
        }
        $path = '/' . ltrim($path, '/');

        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
            $path = $path === '' ? '/' : $path;
        }

        if ($path !== $adminPath && !str_starts_with($path, $adminPath . '/')) {
            continue;
        }

        $relative = trim(substr($path, strlen($adminPath)), '/');
        $relative = strtolower($relative);

        if ($relative === '' || $relative === 'home' || $relative === 'home.php') {
            return 'dashboard';
        }
        if (preg_match('~^(?:entrar|login|logout|lockscreen|forgot-password|reset-password)(?:/|\.|$)~', $relative)
            || str_starts_with($relative, 'auth/')) {
            return 'auth';
        }

        // A rota amigável profile-process não inclui o segmento /profile/.
        if ($relative === 'profile-process') {
            return 'profile';
        }

        $parts = explode('/', $relative);
        $section = $parts[0] ?? '';
        if ($section === 'pages') {
            $section = $parts[1] ?? '';
        }

        if (in_array($section, ['projects', 'skills', 'testimonials', 'faq'], true)) {
            return 'content';
        }
        if (in_array($section, ['messages', 'feedback'], true)) {
            return 'communication';
        }
        if (in_array($section, ['visitors', 'analytics'], true)) {
            return 'analytics';
        }
        if (in_array($section, ['settings', 'security'], true)) {
            return 'system';
        }
        if ($section === 'profile') {
            return 'profile';
        }

        return 'other';
    }

    return 'other';
}

/** Matriz inicial de menor privilégio para os três perfis existentes. */
function adminRoleCanAccessSection(string $role, string $section): bool
{
    $permissions = [
        'super_admin' => ['dashboard', 'content', 'communication', 'analytics', 'system', 'profile', 'auth', 'other'],
        'admin'       => ['dashboard', 'content', 'communication', 'analytics', 'profile', 'auth'],
        'editor'      => ['content', 'profile', 'auth'],
    ];

    return in_array($section, $permissions[$role] ?? [], true);
}

/**
 * Exige sessão autenticada, revalida a conta e aplica a autorização por função.
 * Editor é encaminhado para Projectos quando tenta abrir o dashboard.
 */
function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
        $requestQuery = parse_url($requestUri, PHP_URL_QUERY);

        $basePath = parse_url((string) BASE_URL, PHP_URL_PATH);
        $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

        // Remove o prefixo apenas quando corresponde a um segmento completo.
        if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
            $requestPath = substr($requestPath, strlen($basePath));
        }

        $requestPath = '/' . ltrim($requestPath, '/');
        if (str_starts_with($requestPath, '//') || str_contains($requestPath, "\\")) {
            $requestPath = '/';
        }

        $return = $requestPath;
        if (is_string($requestQuery) && $requestQuery !== '') {
            $requestQuery = str_replace(["\r", "\n"], '', $requestQuery);
            $return .= '?' . $requestQuery;
        }

        $_SESSION['admin_return_url'] = $return;
        redirect('/jm-panel/entrar');
        return;
    }

    $sessionAdminId = (int) $_SESSION['admin_id'];
    try {
        $row = $GLOBALS['pdo']->prepare(
            "SELECT status_employees, role FROM _employees WHERE id_employees = ? LIMIT 1"
        );
        $row->execute([$sessionAdminId]);
        $current = $row->fetch(PDO::FETCH_ASSOC);

        if (!$current || ($current['status_employees'] ?? null) !== 'active') {
            logoutAdmin();
            redirect('/jm-panel/entrar');
            return;
        }

        // Mantém a role da sessão sincronizada caso o nível de acesso seja alterado no ADMIN.
        $_SESSION['admin_role'] = (string) ($current['role'] ?? '');
    } catch (Throwable $e) {
        error_log('[ADMIN STATUS CHECK] ' . $e->getMessage());
        logoutAdmin();
        redirect('/jm-panel/entrar');
        return;
    }

    $role = (string) ($_SESSION['admin_role'] ?? '');
    $section = getAdminRequestedSection();
    if (!adminRoleCanAccessSection($role, $section)) {
        if ($role === 'editor' && $section === 'dashboard') {
            redirect('/jm-panel/projects');
            return;
        }

        http_response_code(403);
        $forbiddenPage = defined('ROOT_PATH') ? ROOT_PATH . '/status/403.php' : '';
        if ($forbiddenPage !== '' && is_file($forbiddenPage)) {
            require $forbiddenPage;
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acesso não autorizado para o teu perfil.';
        }
        exit;
    }
}

function isLockscreenActive(): bool
{
    return !empty($_SESSION['admin_lockscreen']);
}

function requireNoLockscreen(): void
{
    if (isLockscreenActive()) {
        redirect('/jm-panel/lockscreen');
    }
}

function createAdminResetToken(int $id): string
{
    if ($id < 1) {
        throw new InvalidArgumentException(
            'Identificador de administrador inválido.'
        );
    }

    $db = $GLOBALS['pdo'];
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare(
        'SELECT id_sec_emp
         FROM _employees_security
         WHERE id_employees = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $securityId = $stmt->fetchColumn();

    if ($securityId !== false) {
        $stmt = $db->prepare(
            'UPDATE _employees_security
             SET reset_password_token = ?,
                 reset_password_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE id_sec_emp = ?'
        );
        $stmt->execute([$tokenHash, (int) $securityId]);
    } else {
        // recovery_key é obrigatório e tem 60 caracteres no máximo.
        $recoveryKey = bin2hex(random_bytes(30));

        $stmt = $db->prepare(
            'INSERT INTO _employees_security
                (id_employees, recovery_key,
                 reset_password_token, reset_password_expires)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );
        $stmt->execute([$id, $recoveryKey, $tokenHash]);
    }

    // Confirma que o token existe e ainda está dentro da validade.
    $stmt = $db->prepare(
        'SELECT 1
         FROM _employees_security
         WHERE id_employees = ?
           AND reset_password_token = ?
           AND reset_password_expires > NOW()
         LIMIT 1'
    );
    $stmt->execute([$id, $tokenHash]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Não foi possível confirmar o token de recuperação.'
        );
    }

    return $token;
}

// ══════════════════════════════════════════════════════════════
// LEITURA DE EMPLOYEES
// ══════════════════════════════════════════════════════════════

function getAdminByEmail(string $email): ?array
{
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT e.*,
               s.login_attempts, s.block_until, s.block_level,
               s.remember_token, s.lockscreen, s.access_code,
               s.last_login_at, s.last_login_ip
        FROM _employees e
        LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
        WHERE e.email_employees = ?
        LIMIT 1
    ");
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

function getAdminById(int $id): ?array
{
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT e.*,
               s.login_attempts, s.block_until, s.block_level,
               s.remember_token, s.lockscreen, s.access_code,
               s.last_login_at, s.last_login_ip
        FROM _employees e
        LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
        WHERE e.id_employees = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function sendAdminResetEmail(string $email, string $name, string $token): bool
{
    $email = trim($email);
    $name = trim($name);
    $token = trim($token);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
        error_log('[ADMIN MAILER] Pedido de redefinição com email ou token inválido.');
        return false;
    }

    $mailer_path = __DIR__ . '/../../includes/JmbengaMailer.php';

    if (!file_exists($mailer_path)) {
        error_log('[ADMIN MAILER] JmbengaMailer.php não encontrado em: ' . $mailer_path);
        return false;
    }

    require_once $mailer_path;

    if (!class_exists('\Jmbenga\Mailer')) {
        error_log('[ADMIN MAILER] Classe Jmbenga\Mailer não carregada.');
        return false;
    }

    $link = rtrim((string) BASE_URL, '/') . '/jm-panel/reset-password?token=' . rawurlencode($token);
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'Redefinir senha — Painel JMbenga';

    $bodyHtml = "
    <div style='font-family:\"Segoe UI\",Arial,sans-serif;max-width:540px;margin:auto;color:#1a1a1a'>
      <div style='background:linear-gradient(135deg,#2563eb,#7c3aed);padding:28px 32px;border-radius:10px 10px 0 0'>
        <span style='color:#fff;font-size:1.5rem;font-weight:800;font-family:\"Syne\",sans-serif'>JMbenga</span>
        <span style='color:rgba(255,255,255,.6);font-size:0.85rem;margin-left:8px'>Painel Admin</span>
      </div>
      <div style='background:#fff;padding:36px 32px;border:1px solid #eee;border-top:none;border-radius:0 0 10px 10px'>
        <h2 style='color:#111;margin-top:0;font-size:1.3rem;font-family:\"Syne\",sans-serif'>Redefinição de senha</h2>
        <p>Olá <strong>{$safeName}</strong>,</p>
        <p>Recebemos um pedido de redefinição de senha para a tua conta de administrador do portfólio <strong>JMbenga</strong>.</p>
        <div style='text-align:center;margin:32px 0'>
          <a href='{$safeLink}'
             style='background:#2563eb;color:#fff;text-decoration:none;
                    padding:14px 36px;border-radius:8px;font-size:0.95rem;
                    font-weight:700;display:inline-block'>
            Redefinir senha
          </a>
        </div>
        <p style='color:#666;font-size:.88rem'>
          Se o botão não funcionar, copia e cola este link:<br>
          <a href='{$safeLink}' style='color:#2563eb;word-break:break-all;font-size:.82rem'>{$safeLink}</a>
        </p>
        <div style='background:#f8faff;border-left:3px solid #2563eb;padding:12px 16px;border-radius:0 6px 6px 0;margin:20px 0'>
          <p style='margin:0;font-size:.85rem;color:#555'>
            ⚠️ Este link expira em <strong>1 hora</strong>.<br>
            Se não pediste esta redefinição, ignora este e-mail.
          </p>
        </div>
        <hr style='border:none;border-top:1px solid #f0f0f0;margin:24px 0'>
        <small style='color:#bbb'>Painel JMbenga — Acesso restrito.</small>
      </div>
    </div>";

    try {
        $mailer = new \Jmbenga\Mailer();
        $mailer->host     = MAIL_HOST;
        $mailer->port     = MAIL_PORT;
        $mailer->secure   = MAIL_SECURE;
        $mailer->username = MAIL_USER;
        $mailer->password = MAIL_PASS;
        $mailer->debug    = (APP_ENV === 'production') ? 0 : 1;

        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME)
            ->addAddress($email, $name)
            ->setSubject($subject)
            ->setBody($bodyHtml)
            ->send();

        return true;
    } catch (Throwable $e) {
        error_log('[ADMIN MAILER] Falha no envio de email de redefinição: ' . $e->getMessage());
        return false;
    }
}

// ══════════════════════════════════════════════════════════════
// RESET DE PASSWORD
// ══════════════════════════════════════════════════════════════

function validateAdminResetToken(string $token): ?int
{
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT id_employees
        FROM _employees_security
        WHERE reset_password_token = ?
          AND reset_password_expires > NOW()
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id_employees'] : null;
}

function consumeAdminResetToken(int $id): void
{
    $GLOBALS['pdo']->prepare("
        UPDATE _employees_security
        SET reset_password_token   = NULL,
            reset_password_expires = NULL,
            remember_token = NULL
        WHERE id_employees = ?
    ")->execute([$id]);
}

function updateAdminPassword(int $id, string $newPassword): void
{
    if ($id < 1 || strlen($newPassword) > 72) {
        throw new InvalidArgumentException('Dados inválidos para actualizar a palavra-passe.');
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Não foi possível gerar o hash da palavra-passe.');
    }

    $db = $GLOBALS['pdo'];
    $db->beginTransaction();
    try {
        $update = $db->prepare(
            'UPDATE _employees SET password_employees = ? WHERE id_employees = ?'
        );
        $update->execute([$hash, $id]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('A conta não foi actualizada.');
        }

        // Invalidar tokens antigos de recuperação e Remember Me em todos os dispositivos.
        consumeAdminResetToken($id);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    logAudit($id, null, 'auth.password_reset', 'employees', $id);
}

// ══════════════════════════════════════════════════════════════
// BLOQUEIO & TENTATIVAS DE LOGIN
// ══════════════════════════════════════════════════════════════

function isAdminBlocked(array $admin): array
{
    if (!empty($admin['block_until'])) {
        $blockedUntil = strtotime((string) $admin['block_until']);
        if ($blockedUntil !== false && $blockedUntil > time()) {
            return [
                'blocked' => true,
                'until'   => $admin['block_until'],
                'reason'  => 'attempts',
            ];
        }

        // Expira apenas o bloqueio actual. Mantemos tentativas/nível para que
        // os bloqueios progressivos de 10, 30 e 60 minutos possam evoluir.
        $GLOBALS['pdo']->prepare(
            "UPDATE _employees_security SET block_until = NULL WHERE id_employees = ? AND block_until <= NOW()"
        )->execute([(int) ($admin['id_employees'] ?? 0)]);
        $admin['block_until'] = null;
    }

    if (($admin['status_employees'] ?? null) !== 'active') {
        return [
            'blocked' => true,
            'until'   => null,
            'reason'  => 'status_' . $admin['status_employees'],
        ];
    }

    return ['blocked' => false, 'until' => null, 'reason' => ''];
}

function recordFailedAdminLogin(int $id): void
{
    $db   = $GLOBALS['pdo'];
    $stmt = $db->prepare("SELECT login_attempts, block_level FROM _employees_security WHERE id_employees = ?");
    $stmt->execute([$id]);
    $sec = $stmt->fetch();

    $attempts    = (int)($sec['login_attempts'] ?? 0) + 1;
    $level       = (int)($sec['block_level'] ?? 0);
    $block_until = null;

    if ($attempts === 3) {
        $block_until = date('Y-m-d H:i:s', time() + (ADMIN_BLOCK_1_MIN * 60));
        $level = 1;
    } elseif ($attempts === 5) {
        $block_until = date('Y-m-d H:i:s', time() + (ADMIN_BLOCK_2_MIN * 60));
        $level = 2;
    } elseif ($attempts >= 7) {
        $block_until = date('Y-m-d H:i:s', time() + (ADMIN_BLOCK_3_MIN * 60));
        $level = 3;
    }

    $db->prepare("UPDATE _employees_security SET login_attempts = ?, block_level = ?, block_until = ? WHERE id_employees = ?")
        ->execute([$attempts, $level, $block_until, $id]);
}

function resetAdminLoginAttempts(int $id): void
{
    $GLOBALS['pdo']->prepare(
        "UPDATE _employees_security SET login_attempts = 0, block_until = NULL, block_level = 0 WHERE id_employees = ?"
    )->execute([$id]);
}

function recordAdminLoginSuccess(int $id): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $GLOBALS['pdo']->prepare(
        "UPDATE _employees_security SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0, block_until = NULL, block_level = 0 WHERE id_employees = ?"
    )->execute([$ip, $id]);
}

/** Conta falhas de autenticação recentes na origem IP actual. */
function isAdminLoginIpRateLimited(): bool
{
    $ip = getRealClientIp();
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        // A limitação por conta continua activa quando o servidor não fornece IP válido.
        return false;
    }

    $stmt = $GLOBALS['pdo']->prepare(
        "SELECT COUNT(*)
         FROM _audit_log
         WHERE ip_address = ?
           AND action = 'auth.failed_login'
           AND creat_log >= DATE_SUB(NOW(), INTERVAL " . (int) ADMIN_LOGIN_IP_WINDOW_MINUTES . " MINUTE)"
    );
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= (int) ADMIN_LOGIN_IP_MAX_FAILURES;
}

/** Regista falhas sem guardar o nome de utilizador ou endereço de email submetido. */
function recordAdminLoginIpFailure(?int $adminId = null): void
{
    logAudit(
        $adminId,
        null,
        'auth.failed_login',
        'employees',
        $adminId,
        null,
        ['result' => 'invalid_credentials']
    );
}

// ══════════════════════════════════════════════════════════════
// REMEMBER ME
// ══════════════════════════════════════════════════════════════

function setAdminRememberCookie(int $id): void
{
    if (headers_sent()) {
        error_log('[ADMIN REMEMBER] Não foi possível emitir o cookie porque os headers já foram enviados.');
        return;
    }

    $token   = bin2hex(random_bytes(32));
    $expires = time() + (ADMIN_COOKIE_DAYS * 24 * 3600);

    // Guardar apenas o SHA-256; o token em texto original fica apenas no cookie HttpOnly.
    $tokenHash = hash('sha256', $token);
    $GLOBALS['pdo']->prepare("UPDATE _employees_security SET remember_token = ? WHERE id_employees = ?")
        ->execute([$tokenHash, $id]);

    setcookie(ADMIN_COOKIE_NAME, $token, [
        'expires'  => $expires,
        'path'     => getAdminCookiePath(),
        'secure'   => adminCookieRequiresSecure(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function clearAdminRememberCookie(int $id): void
{
    if (!headers_sent()) {
        setcookie(ADMIN_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => getAdminCookiePath(),
            'secure'   => adminCookieRequiresSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    // ID 0 é usado quando o cookie recebido não corresponde a nenhuma conta.
    if ($id <= 0) {
        return;
    }

    try {
        $GLOBALS['pdo']->prepare("UPDATE _employees_security SET remember_token = NULL WHERE id_employees = ?")
            ->execute([$id]);
    } catch (Throwable $e) {
        // Falha de BD não deve impedir a limpeza da sessão no logout.
        error_log('[ADMIN REMEMBER] Não foi possível invalidar o token persistido: ' . $e->getMessage());
    }
}

function checkAdminRememberMe(): void
{
    if (isAdminLoggedIn()) return;

    $token = $_COOKIE[ADMIN_COOKIE_NAME] ?? null;
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
        if (isset($_COOKIE[ADMIN_COOKIE_NAME])) {
            clearAdminRememberCookie(0);
        }
        return;
    }

    $stmt = $GLOBALS['pdo']->prepare("
        SELECT e.id_employees, e.first_name, e.second_name,
               e.email_employees, e.role, e.status_employees,
               e.photo_employees, s.lockscreen
        FROM _employees_security s
        JOIN _employees e ON e.id_employees = s.id_employees
        WHERE s.remember_token = ? AND e.status_employees = 'active'
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $admin = $stmt->fetch();

    if (!$admin) {
        clearAdminRememberCookie(0);
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        startAdminSession();
    }
    session_regenerate_id(true);
    _populateAdminSession($admin);
    $_SESSION['admin_login_time'] = time();
    setAdminRememberCookie((int) $admin['id_employees']);
}

function _populateAdminSession(array $admin): void
{
    $_SESSION['admin_id']         = (int)$admin['id_employees'];
    $_SESSION['admin_name']       = $admin['first_name'];
    $_SESSION['admin_full_name']  = trim($admin['first_name'] . ' ' . ($admin['second_name'] ?? ''));
    $_SESSION['admin_email']      = $admin['email_employees'];
    $_SESSION['admin_role']       = $admin['role'];
    $_SESSION['admin_photo']      = $admin['photo_employees'] ?? null;
    $_SESSION['admin_lockscreen'] = !empty($admin['lockscreen']);
}

// ══════════════════════════════════════════════════════════════
// LOCKSCREEN — PIN armazenado com hash bcrypt
// ══════════════════════════════════════════════════════════════

function hashAdminLockscreenPin(string $pin): string
{
    if (!preg_match('/\A[0-9]{6}\z/', $pin)) {
        throw new InvalidArgumentException('O PIN deve conter exactamente seis dígitos.');
    }
    $hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Não foi possível proteger o PIN.');
    }
    return $hash;
}

function isAdminLockscreenPinHash(string $stored): bool
{
    return preg_match('/\A\$2[aby]\$[0-9]{2}\$/', $stored) === 1;
}

/** Aceita temporariamente PIN legado em texto simples e faz upgrade após validação. */
function verifyAdminLockscreenPin(string $provided, string $stored): bool
{
    if (!preg_match('/\A[0-9]{6}\z/', $provided) || $stored === '') {
        return false;
    }
    if (preg_match('/\A[0-9]{6}\z/', $stored)) {
        return hash_equals($stored, $provided);
    }
    return isAdminLockscreenPinHash($stored) && password_verify($provided, $stored);
}

function adminLockscreenPinNeedsRehash(string $stored): bool
{
    if (preg_match('/\A[0-9]{6}\z/', $stored)) {
        return true;
    }
    return isAdminLockscreenPinHash($stored)
        && password_needs_rehash($stored, PASSWORD_BCRYPT, ['cost' => 12]);
}

function activateLockscreen(int $id): void
{
    $db = $GLOBALS['pdo'];

    $existing = $db->prepare("SELECT access_code FROM _employees_security WHERE id_employees = ? LIMIT 1");
    $existing->execute([$id]);
    $code = $existing->fetchColumn();

    if ($code === false) {
        error_log('[ADMIN LOCKSCREEN] Não existe registo de segurança para o administrador ' . $id . '.');
        return;
    }

    $storedCode = is_string($code) ? $code : '';
    $isLegacyPin = preg_match('/\A[0-9]{6}\z/', $storedCode) === 1;
    if ($storedCode === '' || (!$isLegacyPin && !isAdminLockscreenPinHash($storedCode))) {
        error_log('[ADMIN LOCKSCREEN] O PIN deve ser definido pelo administrador antes de activar o bloqueio.');
        return;
    }

    $db->prepare("UPDATE _employees_security SET lockscreen = 1 WHERE id_employees = ?")
        ->execute([$id]);

    $_SESSION['admin_lockscreen'] = true;
    unset($_SESSION['admin_lockscreen_attempts'], $_SESSION['admin_lockscreen_block_until']);
}

function validateAndUnlock(int $id, string $code): bool
{
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT access_code FROM _employees_security
        WHERE id_employees = ? AND lockscreen = 1 LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    $storedCode = is_array($row) ? (string) ($row['access_code'] ?? '') : '';
    $providedCode = trim($code);
    if (!verifyAdminLockscreenPin($providedCode, $storedCode)) {
        return false;
    }

    $replacement = adminLockscreenPinNeedsRehash($storedCode)
        ? hashAdminLockscreenPin($providedCode)
        : $storedCode;
    $update = $GLOBALS['pdo']->prepare(
        'UPDATE _employees_security
         SET lockscreen = 0, access_code = ?
         WHERE id_employees = ? AND lockscreen = 1 AND access_code = ?'
    );
    $update->execute([$replacement, $id, $storedCode]);
    if ($update->rowCount() !== 1) {
        return false;
    }

    $_SESSION['admin_lockscreen'] = false;
    unset($_SESSION['admin_lockscreen_attempts'], $_SESSION['admin_lockscreen_block_until']);
    return true;
}

// ══════════════════════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════════════════════

function logoutAdmin(): void
{
    $id = (int) ($_SESSION['admin_id'] ?? 0);

    if ($id > 0) {
        logAudit($id, null, 'auth.logout', 'employees', $id);
    }

    // Invalida o remember-me sem permitir que uma falha de BD impeça o logout local.
    clearAdminRememberCookie($id);

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        if (!headers_sent()) {
            $sessionCookieOptions = [
                'expires'  => time() - 3600,
                'path'     => $params['path'] ?? '/',
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Strict',
            ];
            if (!empty($params['domain'])) {
                $sessionCookieOptions['domain'] = $params['domain'];
            }
            setcookie(session_name(), '', $sessionCookieOptions);
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// ══════════════════════════════════════════════════════════════
// AUDITORIA
// ══════════════════════════════════════════════════════════════

function logAudit(
    ?int   $id_emp,
    ?int   $id_user,
    string $action,
    ?string $entity = null,
    ?int   $entity_id = null,
    mixed  $old = null,
    mixed  $new = null
): void {
    try {
        $ip = getRealClientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $GLOBALS['pdo']->prepare("
            INSERT INTO _audit_log (id_employees, action, entity, entity_id, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $id_emp,
            $action,
            $entity,
            $entity_id,
            $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            $ip,
            $ua,
        ]);
    } catch (Throwable $e) {
        error_log('[AUDIT LOG] ' . $e->getMessage());
    }
}

/**
 * Obtém o endereço remoto sem confiar cegamente em X-Forwarded-For/X-Real-IP.
 * Esses cabeçalhos só devem ser usados após configurar explicitamente um proxy confiável.
 */
function getRealClientIp(): string
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
        return $remoteAddress;
    }

    // Fallback apenas para ambientes que não fornecem REMOTE_ADDR válido.
    // Não é usado quando existe um endereço remoto válido, evitando spoofing comum.
    foreach (['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
        $candidate = trim((string) ($_SERVER[$header] ?? ''));
        if ($header === 'HTTP_X_FORWARDED_FOR') {
            $candidate = trim(explode(',', $candidate)[0] ?? '');
        }
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '—';
}

function recordFailedLockscreenAttempt(int $id): void
{
    $attemptsKey = 'admin_lockscreen_attempts';
    $lockoutKey  = 'admin_lockscreen_block_until';

    $_SESSION[$attemptsKey] = ($_SESSION[$attemptsKey] ?? 0) + 1;

    $count = $_SESSION[$attemptsKey];
    if ($count >= 7) {
        $_SESSION[$lockoutKey] = time() + 300;
    } elseif ($count >= 5) {
        $_SESSION[$lockoutKey] = time() + 60;
    } elseif ($count >= 3) {
        $_SESSION[$lockoutKey] = time() + 15;
    }
}

function isLockscreenBlocked(): bool
{
    $lockoutKey = 'admin_lockscreen_block_until';
    return !empty($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time();
}

// ══════════════════════════════════════════════════════════════
// USER-AGENT PARSING
// ══════════════════════════════════════════════════════════════

function parseBrowserFromUA(string $ua): string
{
    $raw = $ua;
    $browsers = [
        'Edge'    => '/edg(?:e|a|ios)?\//i',
        'Opera'   => '/opr\//i',
        'Brave'   => '/brave/i',
        'Chrome'  => '/chrome\/([\d.]+)/i',
        'Firefox' => '/firefox\/([\d.]+)/i',
        'Safari'  => '/version\/([\d.]+).*safari/i',
        'IE'      => '/msie ([\d.]+)/i',
        'IE 11'   => '/trident\//i',
    ];

    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $raw, $m)) {
            return $name . (isset($m[1]) ? ' ' . explode('.', $m[1])[0] : '');
        }
    }
    return 'Desconhecido';
}

function parseOSFromUA(string $ua): string
{
    $raw = $ua;
    $patterns = [
        // Windows 10 e 11 usam frequentemente o mesmo token NT 10.0; não inferir um deles.
        'Windows 10/11' => '/windows nt 10\.0/i',
        'Windows 7'     => '/windows nt 6\.1/i',
        'Windows'       => '/windows/i',
        'macOS'       => '/mac os x ([\d_]+)/i',
        'Android'     => '/android ([\d.]+)/i',
        'iOS'         => '/iphone|ipad/i',
        'Linux'       => '/linux/i',
    ];

    foreach ($patterns as $os => $pattern) {
        if (preg_match($pattern, $raw, $m)) {
            if ($os === 'macOS' && isset($m[1])) {
                return 'macOS ' . str_replace('_', '.', $m[1]);
            }
            if ($os === 'Android' && isset($m[1])) {
                return 'Android ' . $m[1];
            }
            return $os;
        }
    }
    return 'Desconhecido';
}

// ══════════════════════════════════════════════════════════════
// ANALYTICS DO DASHBOARD
// ══════════════════════════════════════════════════════════════

function getVisitorChartData(int $days = 7): array
{
    // Limita o intervalo para impedir consultas acidentalmente enormes.
    $days = max(1, min(90, $days));
    $db = $GLOBALS['pdo'];

    $rawRows = $db->prepare("
        SELECT DATE(creat_visitor) AS day, COUNT(*) AS total
        FROM _visitor
        WHERE creat_visitor >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND is_bot = 0
        GROUP BY DATE(creat_visitor)
        ORDER BY day ASC
    ");
    $rawRows->execute([$days - 1]);
    $raw = $rawRows->fetchAll(PDO::FETCH_KEY_PAIR);

    $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $labels = $data = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $wd   = (int) date('w', strtotime($date));

        if ($days <= 7) {
            $labels[] = $weekdays[$wd];
        } else {
            $labels[] = date('d/m', strtotime($date));
        }
        $data[] = (int)($raw[$date] ?? 0);
    }

    return ['labels' => $labels, 'data' => $data, 'total' => array_sum($data)];
}

/**
 * Converte filtros simples em condições SQL estritamente permitidas.
 * Aceita apenas comparações por igualdade unidas por AND. Nunca insere SQL livre.
 */
function buildAdminTrendCondition(string $table, string $where): string
{
    $where = trim($where);
    if ($where === '') {
        return '';
    }

    $allowed = [
        '_visitor' => [
            'is_bot' => ['0', '1'],
            'is_online' => ['0', '1'],
            'status_visitor' => ["'active'", "'blocked'", "'suspicious'"],
            'device_type' => ["'desktop'", "'mobile'", "'tablet'", "'bot'", "'unknown'"],
        ],
        '_contact_message' => [
            'status_msg' => ["'new'", "'read'", "'replied'", "'archived'"],
            'is_starred' => ['0', '1'],
        ],
        '_projects' => [
            'status_project' => ["'published'", "'draft'", "'archived'"],
            'category_project' => ["'web'", "'mobile'", "'api'", "'desktop'", "'design'", "'other'"],
            'is_featured' => ['0', '1'],
        ],
        '_skills' => [
            'is_visible' => ['0', '1'],
            'category_skill' => ["'frontend'", "'backend'", "'devops'", "'design'", "'other'"],
        ],
        '_testimonials' => [
            'status_testimonial' => ["'visible'", "'hidden'"],
        ],
    ];

    if (!isset($allowed[$table])) {
        throw new InvalidArgumentException('Tabela não permitida para filtros de tendência.');
    }

    $parts = preg_split('/\s+AND\s+/i', $where) ?: [];
    if (!$parts) {
        throw new InvalidArgumentException('Filtro de tendência inválido.');
    }

    $sqlParts = [];
    foreach ($parts as $part) {
        if (!preg_match("/\\A([a-zA-Z_][a-zA-Z0-9_]*)\\s*=\\s*(?:'([a-zA-Z0-9_-]+)'|([0-9]+))\\z/", trim($part), $matches)) {
            throw new InvalidArgumentException('Filtro inválido: use apenas comparações permitidas ligadas por AND.');
        }

        $column = $matches[1];
        $value = isset($matches[2]) && $matches[2] !== ''
            ? "'" . $matches[2] . "'"
            : ($matches[3] ?? '');

        if (!isset($allowed[$table][$column]) || !in_array($value, $allowed[$table][$column], true)) {
            throw new InvalidArgumentException('Coluna ou valor não permitido no filtro de tendência.');
        }

        $sqlParts[] = '`' . $column . '` = ' . $value;
    }

    return 'AND (' . implode(' AND ', $sqlParts) . ')';
}

function getStatTrend(string $table, string $dateCol, int $days = 7, string $where = ''): float
{
    // _skills não tem uma coluna temporal no esquema actual, por isso não é
    // possível calcular uma tendência temporal real sem alterar a base de dados.
    if ($table === '_skills') {
        static $skillsTrendWarningLogged = false;
        if (!$skillsTrendWarningLogged) {
            error_log('[ADMIN TREND] _skills não tem coluna temporal; tendência devolvida como 0.');
            $skillsTrendWarningLogged = true;
        }
        return 0.0;
    }

    $dateColumns = [
        '_visitor' => ['creat_visitor'],
        // Alias legado creat_msg é normalizado para a coluna real da tabela.
        '_contact_message' => ['created_at', 'creat_msg'],
        '_projects' => ['creat_project'],
        '_testimonials' => ['creat_testimonial'],
    ];

    if (!isset($dateColumns[$table]) || !in_array($dateCol, $dateColumns[$table], true)) {
        throw new InvalidArgumentException('Tabela ou coluna temporal não permitida para tendência.');
    }

    if ($table === '_contact_message') {
        $dateCol = 'created_at';
    }

    $days = max(1, min(365, $days));
    $condition = buildAdminTrendCondition($table, $where);
    $db = $GLOBALS['pdo'];

    $thisQ = $db->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE {$dateCol} >= DATE_SUB(NOW(), INTERVAL {$days} DAY) {$condition}"
    );
    $thisQ->execute();
    $thisCount = (int) $thisQ->fetchColumn();

    $previousDays = $days * 2;
    $prevQ = $db->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE {$dateCol} BETWEEN DATE_SUB(NOW(), INTERVAL {$previousDays} DAY)
               AND DATE_SUB(NOW(), INTERVAL {$days} DAY) {$condition}"
    );
    $prevQ->execute();
    $prevCount = (int) $prevQ->fetchColumn();

    if ($prevCount === 0) {
        return $thisCount > 0 ? 100.0 : 0.0;
    }

    return round((($thisCount - $prevCount) / $prevCount) * 100, 1);
}

function getMessageBreakdown(): array
{
    $rows = $GLOBALS['pdo']->query("
        SELECT status_msg, COUNT(*) AS n
        FROM _contact_message
        GROUP BY status_msg
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $result = [
        'new'      => (int)($rows['new']      ?? 0),
        'read'     => (int)($rows['read']      ?? 0),
        'replied'  => (int)($rows['replied']   ?? 0),
        'archived' => (int)($rows['archived']  ?? 0),
    ];
    $result['total'] = array_sum($result);
    return $result;
}

function getTopPages(int $limit = 5): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT page_url, page_title, COUNT(*) AS views
        FROM _visitor_pageview
        GROUP BY page_url, page_title
        ORDER BY views DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDeviceBreakdown(): array
{
    $rows = $GLOBALS['pdo']->query("
        SELECT device_type, COUNT(*) AS total
        FROM _visitor
        WHERE is_bot = 0
        GROUP BY device_type
        ORDER BY total DESC
    ")->fetchAll();

    $grand = array_sum(array_column($rows, 'total')) ?: 1;
    return array_map(fn($r) => [
        ...$r,
        'pct' => round(($r['total'] / $grand) * 100),
    ], $rows);
}

function getTopCountries(int $limit = 5): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT country_name, country_code, COUNT(*) AS total
        FROM _visitor
        WHERE is_bot = 0 AND country_code IS NOT NULL
        GROUP BY country_code, country_name
        ORDER BY total DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPortfolioHealthScore(): int
{
    $db = $GLOBALS['pdo'];

    $projects     = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
    $skills       = (int) $db->query("SELECT COUNT(*) FROM _skills WHERE is_visible = 1")->fetchColumn();
    $testimonials = (int) $db->query("SELECT COUNT(*) FROM _testimonials WHERE status_testimonial = 'visible'")->fetchColumn();
    $unread       = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
    $total_msg    = (int) $db->query("SELECT COUNT(*) FROM _contact_message")->fetchColumn();

    $score = 0;
    $score += min(40, $projects * 4);
    $score += min(25, $skills * 3);
    $score += min(20, $testimonials * 4);
    if ($total_msg > 0) {
        $unread_ratio = $unread / $total_msg;
        $score += max(0, 15 - round($unread_ratio * 30));
    } else {
        $score += 15;
    }

    return min(100, max(0, $score));
}

function formatSessionDuration(int $seconds): string
{
    if ($seconds < 60)  return $seconds . ' s';
    if ($seconds < 3600) return floor($seconds / 60) . ' min ' . ($seconds % 60) . ' s';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return $h . ' h ' . $m . ' min';
}

function getGreeting(): string
{
    $hour = (int) date('G');
    if ($hour < 12) return 'Bom dia';
    if ($hour < 18) return 'Boa tarde';
    return 'Boa noite';
}

function formatPageUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?? $url;
    $path = trim($path, '/');
    $parts = array_filter(explode('/', $path), fn($p) => $p !== '' && $p !== 'jm-panel');
    return implode(' › ', array_map('ucfirst', $parts)) ?: $url;
}

function getChartColors(int $count): array
{
    $palette = [
        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#14b8a6', '#ec4899', '#f97316', '#06b6d4', '#84cc16',
    ];
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $palette[$i % count($palette)];
    }
    return $result;
}

function isIpWhitelisted(string $ip): bool
{
    try {
        $stmt = $GLOBALS['pdo']->prepare(
            "SELECT COUNT(*) FROM _admin_ip_whitelist WHERE ip_address = ? AND active = 1"
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('[IP WHITELIST] ' . $e->getMessage());
        return false;
    }
}

function logBlockedAccess(string $ip, string $path, string $reason): void
{
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $GLOBALS['pdo']->prepare("
            INSERT INTO _admin_access_log (ip_address, path_tried, reason, user_agent)
            VALUES (?, ?, ?, ?)
        ")->execute([$ip, $path, $reason, $ua]);
    } catch (Throwable $e) {
        error_log('[BLOCKED ACCESS LOG] ' . $e->getMessage());
    }
}