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
define('ADMIN_SESSION_NAME',  'jm_panel_sess');
define('ADMIN_COOKIE_NAME',   'jm_panel_remember');
define('ADMIN_COOKIE_DAYS',   7);
define('ADMIN_MAX_ATTEMPTS',  5);
define('ADMIN_BLOCK_1_MIN',   10);   // 3 tentativas → 10 min
define('ADMIN_BLOCK_2_MIN',   30);   // 5 tentativas → 30 min
define('ADMIN_BLOCK_3_MIN',   60);   // 7+ tentativas → 60 min

function getAdminCookiePath(): string
{
    $path = parse_url(ADMIN_PATH, PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') . '/';
}

// ══════════════════════════════════════════════════════════════
// SESSÃO & CSRF
// ══════════════════════════════════════════════════════════════

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(ADMIN_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => getAdminCookiePath(),
            'domain'   => $_SERVER['HTTP_HOST'],
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!isset($_SESSION['admin_login_time']) && !empty($_SESSION['admin_id'])) {
        $_SESSION['admin_login_time'] = time();
    }
}

function validateAdminCsrf(string $token): bool
{
    return isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function regenerateAdminCsrf(): void
{
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * CORREÇÃO: Restaurada lógica de deduplicação do BASE_URL no REQUEST_URI
 * para evitar URLs duplicados quando o projeto está num subdiretório.
 */
function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';

        // Remover ocorrências do basePath do REQUEST_URI para evitar duplicação
        if ($basePath !== '' && $basePath !== '/') {
            $tmp = $requestUri;
            while (str_starts_with($tmp, $basePath)) {
                $tmp = substr($tmp, strlen($basePath));
            }
            $return = $tmp === '' ? '/' : $tmp;
        } else {
            $return = $requestUri;
        }

        if ($return === '' || !str_starts_with($return, '/')) {
            $return = '/' . ltrim($return, '/');
        }

        $_SESSION['admin_return_url'] = $return;
        redirect('/jm-panel/entrar');
    }

    $session_admin_id = (int) $_SESSION['admin_id'];
    try {
        $row = $GLOBALS['pdo']->prepare(
            "SELECT status_employees, role FROM _employees WHERE id_employees = ? LIMIT 1"
        );
        $row->execute([$session_admin_id]);
        $current = $row->fetch();

        if (!$current || $current['status_employees'] !== 'active') {
            logoutAdmin();
            redirect('/jm-panel/entrar');
        }
    } catch (Exception $e) {
        error_log('[ADMIN STATUS CHECK] ' . $e->getMessage());
        logoutAdmin();
        redirect('/jm-panel/entrar');
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
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $GLOBALS['pdo']->prepare("
        UPDATE _employees_security
        SET reset_password_token = ?,
            reset_password_expires = ?
        WHERE id_employees = ?
    ")->execute([$token, $expires, $id]);

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

    $link    = BASE_URL . '/jm-panel/reset-password?token=' . urlencode($token);
    $subject = 'Redefinir senha — Painel JMbenga';

    $bodyHtml = "
    <div style='font-family:\"Segoe UI\",Arial,sans-serif;max-width:540px;margin:auto;color:#1a1a1a'>
      <div style='background:linear-gradient(135deg,#2563eb,#7c3aed);padding:28px 32px;border-radius:10px 10px 0 0'>
        <span style='color:#fff;font-size:1.5rem;font-weight:800;font-family:\"Syne\",sans-serif'>JMbenga</span>
        <span style='color:rgba(255,255,255,.6);font-size:0.85rem;margin-left:8px'>Painel Admin</span>
      </div>
      <div style='background:#fff;padding:36px 32px;border:1px solid #eee;border-top:none;border-radius:0 0 10px 10px'>
        <h2 style='color:#111;margin-top:0;font-size:1.3rem;font-family:\"Syne\",sans-serif'>Redefinição de senha</h2>
        <p>Olá <strong>{$name}</strong>,</p>
        <p>Recebemos um pedido de redefinição de senha para a tua conta de administrador do portfólio <strong>JMbenga</strong>.</p>
        <div style='text-align:center;margin:32px 0'>
          <a href='{$link}'
             style='background:#2563eb;color:#fff;text-decoration:none;
                    padding:14px 36px;border-radius:8px;font-size:0.95rem;
                    font-weight:700;display:inline-block'>
            Redefinir senha
          </a>
        </div>
        <p style='color:#666;font-size:.88rem'>
          Se o botão não funcionar, copia e cola este link:<br>
          <a href='{$link}' style='color:#2563eb;word-break:break-all;font-size:.82rem'>{$link}</a>
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
        $mailer->debug    = (APP_ENV === 'development') ? 1 : 0;

        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME)
            ->addAddress($email, $name)
            ->setSubject($subject)
            ->setBody($bodyHtml)
            ->send();

        return true;
    } catch (\Jmbenga\MailerException $e) {
        error_log('[ADMIN MAILER] Falha ao enviar reset email para ' . $email . ': ' . $e->getMessage());
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
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id_employees'] : null;
}

function consumeAdminResetToken(int $id): void
{
    $GLOBALS['pdo']->prepare("
        UPDATE _employees_security
        SET reset_password_token   = NULL,
            reset_password_expires = NULL
        WHERE id_employees = ?
    ")->execute([$id]);
}

function updateAdminPassword(int $id, string $newPassword): void
{
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $GLOBALS['pdo']->prepare("
        UPDATE _employees
        SET password_employees = ?
        WHERE id_employees = ?
    ")->execute([$hash, $id]);

    consumeAdminResetToken($id);
    logAudit($id, null, 'auth.password_reset', 'employees', $id);
}

// ══════════════════════════════════════════════════════════════
// BLOQUEIO & TENTATIVAS DE LOGIN
// ══════════════════════════════════════════════════════════════

function isAdminBlocked(array $admin): array
{
    if (!empty($admin['block_until'])) {
        if (strtotime($admin['block_until']) > time()) {
            return [
                'blocked' => true,
                'until'   => $admin['block_until'],
                'reason'  => 'attempts',
            ];
        }
        resetAdminLoginAttempts((int)$admin['id_employees']);
    }

    if ($admin['status_employees'] !== 'active') {
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

// ══════════════════════════════════════════════════════════════
// REMEMBER ME
// ══════════════════════════════════════════════════════════════

function setAdminRememberCookie(int $id): void
{
    $token   = bin2hex(random_bytes(32));
    $expires = time() + (ADMIN_COOKIE_DAYS * 24 * 3600);

    setcookie(ADMIN_COOKIE_NAME, $token, [
        'expires'  => $expires,
        'path'     => getAdminCookiePath(),
        'secure'   => (APP_ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    $GLOBALS['pdo']->prepare("UPDATE _employees_security SET remember_token = ? WHERE id_employees = ?")
        ->execute([$token, $id]);
}

function clearAdminRememberCookie(int $id): void
{
    setcookie(ADMIN_COOKIE_NAME, '', [
        'expires'  => 1,
        'path'     => getAdminCookiePath(),
        'secure'   => (APP_ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $GLOBALS['pdo']->prepare("UPDATE _employees_security SET remember_token = NULL WHERE id_employees = ?")
        ->execute([$id]);
}

function checkAdminRememberMe(): void
{
    if (isAdminLoggedIn()) return;

    $token = $_COOKIE[ADMIN_COOKIE_NAME] ?? null;
    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
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
    $stmt->execute([$token]);
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
// LOCKSCREEN
// ══════════════════════════════════════════════════════════════

function activateLockscreen(int $id): void
{
    $db = $GLOBALS['pdo'];

    $existing = $db->prepare("SELECT access_code FROM _employees_security WHERE id_employees = ?");
    $existing->execute([$id]);
    $code = $existing->fetchColumn();

    if (empty($code) || !preg_match('/^\d{6}$/', (string)$code)) {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE _employees_security SET access_code = ? WHERE id_employees = ?")
            ->execute([$code, $id]);
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

    if (!$row || !hash_equals((string) $row['access_code'], trim($code))) {
        return false;
    }

    $GLOBALS['pdo']->prepare("UPDATE _employees_security SET lockscreen = 0 WHERE id_employees = ?")
        ->execute([$id]);

    $_SESSION['admin_lockscreen'] = false;
    unset($_SESSION['admin_lockscreen_attempts'], $_SESSION['admin_lockscreen_block_until']);
    return true;
}

// ══════════════════════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════════════════════

function logoutAdmin(): void
{
    $id = $_SESSION['admin_id'] ?? null;
    if ($id) {
        logAudit((int)$id, null, 'auth.logout', 'employees', (int)$id);
        clearAdminRememberCookie((int)$id);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
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
            $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $ua,
        ]);
    } catch (PDOException $e) {
        error_log('[AUDIT LOG] ' . $e->getMessage());
    }
}

function getRealClientIp(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = $_SERVER[$h];
            if ($h === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
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
        'Edge'    => '/edg?\//i',
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
        'Windows 11'  => '/windows nt 10\.0.*win64/i',
        'Windows 10'  => '/windows nt 10\.0/i',
        'Windows 7'   => '/windows nt 6\.1/i',
        'Windows'     => '/windows/i',
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

function getStatTrend(string $table, string $dateCol, int $days = 7, string $where = ''): float
{
    static $allowedTables = ['_visitor', '_contact_message', '_projects', '_skills', '_testimonials'];
    static $allowedCols  = ['creat_visitor', 'creat_msg', 'creat_project', 'creat_skill', 'creat_testimonial', 'updated_at'];

    if (!in_array($table, $allowedTables, true) || !in_array($dateCol, $allowedCols, true)) {
        throw new InvalidArgumentException('Tabela ou coluna não permitida.');
    }

    $db = $GLOBALS['pdo'];
    $cond = $where ? "AND ({$where})" : '';

    $thisQ = $db->prepare("
        SELECT COUNT(*) FROM {$table}
        WHERE {$dateCol} >= DATE_SUB(NOW(), INTERVAL {$days} DAY) {$cond}
    ");
    $thisQ->execute();
    $thisCount = (int) $thisQ->fetchColumn();

    $prevQ = $db->prepare("
        SELECT COUNT(*) FROM {$table}
        WHERE {$dateCol} BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY)
              AND DATE_SUB(NOW(), INTERVAL {$days} DAY) {$cond}
    ");
    $prevQ->execute([$days * 2]);
    $prevCount = (int) $prevQ->fetchColumn();

    if ($prevCount === 0) return $thisCount > 0 ? 100.0 : 0.0;
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
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT page_url, page_title, COUNT(*) AS views
        FROM _visitor_pageview
        GROUP BY page_url, page_title
        ORDER BY views DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
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
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT country_name, country_code, COUNT(*) AS total
        FROM _visitor
        WHERE is_bot = 0 AND country_code IS NOT NULL
        GROUP BY country_code, country_name
        ORDER BY total DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
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