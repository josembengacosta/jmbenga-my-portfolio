<?php
declare(strict_types=1);

/**
 * includes/visitor.php
 * Rastreamento de página do visitante (lado PHP).
 * Deve ser incluído antes de qualquer saída HTML, depois de db.php/functions.php.
 * O visitor-tracker.js continua responsável pelos eventos adicionais via API.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if (preg_match('/bot|crawler|spider|curl|wget|python|headless|prerender/i', $ua)) {
    return;
}

// A sessão pública é escolhida centralmente por includes/config.php antes deste include.
$publicSessionName = defined('PUBLIC_SESSION_NAME') ? PUBLIC_SESSION_NAME : 'jm_visitor';

if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent()) {
        error_log('[visitor.php] Não foi possível iniciar a sessão: os headers já foram enviados.');
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');

    session_name($publicSessionName);

    $publicCookiePath = defined('APP_PUBLIC_COOKIE_PATH')
        ? (string) APP_PUBLIC_COOKIE_PATH
        : '/';
    $httpsValue = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
    $isHttps = (defined('APP_ENV') && APP_ENV === 'production')
        || ($httpsValue !== '' && !in_array($httpsValue, ['off', '0'], true));

    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 0,
        'path' => $publicCookiePath,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        error_log('[visitor.php] Não foi possível iniciar a sessão de visitante.');
        return;
    }
}

// Se este include for carregado por engano dentro do painel, não rastrear a
// sessão administrativa nem misturar os seus dados com o visitante público.
if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== $publicSessionName) {
    error_log('[visitor.php] Contexto de sessão não público; rastreamento ignorado.');
    return;
}

if (empty($_SESSION['visitor_session_id'])) {
    try {
        $_SESSION['visitor_session_id'] = bin2hex(random_bytes(24));
    } catch (Throwable $e) {
        error_log('[visitor.php] Não foi possível gerar o ID de sessão: ' . $e->getMessage());
        return;
    }
}
$sessionId = (string) $_SESSION['visitor_session_id'];

// Usa get_ip() quando disponível; caso contrário, recorre ao IP remoto directo.
$ip = function_exists('get_ip') ? trim((string) get_ip()) : '';
if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
    $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ip = filter_var($remoteIp, FILTER_VALIDATE_IP) !== false ? $remoteIp : '0.0.0.0';
}
$ipVersion = str_contains($ip, ':') ? 'v6' : 'v4';

// Página sem query string para evitar guardar tokens ou outros dados de URL sensíveis.
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$pagePath = parse_url($requestUri, PHP_URL_PATH);
$page = is_string($pagePath) && $pagePath !== '' ? substr($pagePath, 0, 500) : '/';

// Referenciador sem query string nem fragmento.
$referrer = null;
$referrerRaw = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
if ($referrerRaw !== '') {
    $refParts = parse_url($referrerRaw);
    if (is_array($refParts) && !empty($refParts['host'])) {
        $scheme = strtolower((string) ($refParts['scheme'] ?? 'https')) === 'http' ? 'http' : 'https';
        $referrer = substr(
            $scheme . '://' . $refParts['host'] . (string) ($refParts['path'] ?? ''),
            0,
            500
        );
    }
}

// Evita colisões de função se o include for carregado por mais do que um caminho.
if (!function_exists('jm_parse_visitor_browser')) {
    function jm_parse_visitor_browser(string $userAgent): array
    {
        $browser = 'other';
        $browserVersion = '';
        // Opera deve ser verificado antes do Chrome, pois o UA do Opera também contém Chrome.
        $browserPatterns = [
            'edge'    => '/Edg\/([0-9.]+)/',
            'opera'   => '/OPR\/([0-9.]+)/',
            'chrome'  => '/Chrome\/([0-9.]+)/',
            'firefox' => '/Firefox\/([0-9.]+)/',
            'safari'  => '/Version\/([0-9.]+).+Safari/',
        ];
        foreach ($browserPatterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $browser = $name;
                $browserVersion = $matches[1];
                break;
            }
        }

        $os = 'other';
        $osVersion = '';
        $osPatterns = [
            'windows' => '/Windows NT ([0-9.]+)/',
            'macos'   => '/Mac OS X ([0-9._]+)/',
            'android' => '/Android ([0-9.]+)/',
            'ios'     => '/OS ([0-9_]+) like Mac OS X/',
            'linux'   => '/Linux/',
        ];
        foreach ($osPatterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $os = $name;
                $osVersion = str_replace('_', '.', (string) ($matches[1] ?? ''));
                break;
            }
        }

        $device = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            $device = preg_match('/iPad/i', $userAgent) ? 'tablet' : 'mobile';
        }

        return [
            'browser' => $browser,
            'bver' => $browserVersion,
            'os' => $os,
            'osver' => $osVersion,
            'device' => $device,
        ];
    }
}

$parsed = jm_parse_visitor_browser($ua);
$pageTitleRaw = trim((string) ($page_title ?? $_SERVER['HTTP_X_PAGE_TITLE'] ?? ''));
$pageTitle = substr(strip_tags($pageTitleRaw), 0, 255);

try {
    // Primeiro, desbloquear bloqueios temporários já expirados para este IP/sessão.
    $pdo->prepare("
        UPDATE _visitor
        SET status_visitor = 'active', block_until = NULL
        WHERE status_visitor = 'blocked'
          AND (session_id = ? OR ip_address = ?)
          AND block_until IS NOT NULL
          AND block_until <= NOW()
    ")->execute([$sessionId, $ip]);

    // Bloqueio permanente (block_until NULL) ou temporário ainda activo.
    // A verificação por IP é mantida para respeitar os bloqueios administrativos existentes.
    $blockedStmt = $pdo->prepare("
        SELECT id_visitor
        FROM _visitor
        WHERE status_visitor = 'blocked'
          AND (session_id = ? OR ip_address = ?)
          AND (block_until IS NULL OR block_until > NOW())
        ORDER BY CASE WHEN session_id = ? THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $blockedStmt->execute([$sessionId, $ip, $sessionId]);
    if ($blockedStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(403);
        require __DIR__ . '/../status/403.php';
        exit;
    }

    // Só associar automaticamente à sessão exacta. Não fundir visitantes distintos
    // apenas porque partilham o mesmo IP público (por exemplo, rede doméstica/escola).
    $existingStmt = $pdo->prepare("
        SELECT id_visitor, status_visitor
        FROM _visitor
        WHERE session_id = ?
        ORDER BY id_visitor DESC
        LIMIT 1
    ");
    $existingStmt->execute([$sessionId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existing) {
        $visitorId = (int) $existing['id_visitor'];
        $pdo->prepare("
            UPDATE _visitor SET
                page_exit = ?,
                pages_viewed = COALESCE(pages_viewed, 0) + 1,
                user_agent = ?,
                browser = ?,
                browser_version = ?,
                os = ?,
                os_version = ?,
                device_type = ?,
                is_online = 1,
                last_seen = NOW(),
                modif_visitor = NOW()
            WHERE id_visitor = ?
        ")->execute([
            $page,
            substr($ua, 0, 500),
            $parsed['browser'],
            $parsed['bver'],
            $parsed['os'],
            $parsed['osver'],
            $parsed['device'],
            $visitorId,
        ]);
        // visit_count não é incrementado a cada pageview; pages_viewed já mede isso.
        $_SESSION['id_visitor'] = $visitorId;
    } else {
        $pdo->prepare("
            INSERT INTO _visitor (
                ip_address, ip_version, user_agent,
                browser, browser_version, os, os_version, device_type,
                page_entry, page_exit, pages_viewed,
                referrer, session_id, is_online, last_seen,
                status_visitor, visit_count
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, 1,
                ?, ?, 1, NOW(),
                'active', 1
            )
        ")->execute([
            $ip,
            $ipVersion,
            substr($ua, 0, 500),
            $parsed['browser'],
            $parsed['bver'],
            $parsed['os'],
            $parsed['osver'],
            $parsed['device'],
            $page,
            $page,
            $referrer,
            $sessionId,
        ]);
        $_SESSION['id_visitor'] = (int) $pdo->lastInsertId();
    }

    $visitorId = (int) ($_SESSION['id_visitor'] ?? 0);
    if ($visitorId > 0) {
        $pdo->prepare("
            INSERT INTO _visitor_pageview (id_visitor, page_url, page_title)
            VALUES (?, ?, ?)
        ")->execute([
            $visitorId,
            $page,
            $pageTitle,
        ]);
    }
} catch (PDOException $e) {
    // O rastreamento não deve impedir a apresentação da página pública.
    error_log('[visitor.php] ' . $e->getMessage());
} catch (Throwable $e) {
    error_log('[visitor.php] Erro inesperado: ' . $e->getMessage());
}