<?php
/**
 * includes/visitor.php
 * ─────────────────────────────────────────────────────────────
 * Rastreia visitantes no carregamento da página (lado PHP).
 * Chamado no topo de cada página pública APÓS db.php.
 *
 * Cria ou actualiza um registo em _visitor com session_id único.
 * O visitor-tracker.js envia eventos adicionais via api/visitor.php.
 */

if (!isset($pdo)) return;

// Não rastrear bots conhecidos
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/bot|crawler|spider|curl|wget|python|headless|prerender/i', $ua)) return;

// ── Session de visitante ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('jm_visitor');
    session_start();
}

$session_id = $_SESSION['visitor_session_id'] ?? null;
if (!$session_id) {
    $session_id = bin2hex(random_bytes(24));
    $_SESSION['visitor_session_id'] = $session_id;
}

// ── IP ────────────────────────────────────────────────────────
$ip = get_ip();

// ── Verificar se está bloqueado ───────────────────────────────
$stmt = $pdo->prepare("
    SELECT id_visitor, status_visitor, block_until
    FROM _visitor
    WHERE session_id = ? OR ip_address = ?
    LIMIT 1
");
$stmt->execute([$session_id, $ip]);
$existing = $stmt->fetch();

if ($existing) {
    $status = $existing['status_visitor'];
    $until  = $existing['block_until'];

    if ($status === 'blocked') {
        // Se bloqueio temporário expirou → desbloquear
        if ($until && strtotime($until) < time()) {
            $pdo->prepare("UPDATE _visitor SET status_visitor='active', block_until=NULL WHERE id_visitor=?")
                ->execute([$existing['id_visitor']]);
        } else {
            // Bloquear acesso
            header('HTTP/1.1 403 Forbidden');
            require_once __DIR__ . '/../status/403.php';
            exit;
        }
    }
}

// ── Parser do User Agent ──────────────────────────────────────
function _parse_browser(string $ua): array {
    $browser = 'other'; $bver = '';
    $patterns = [
        'edge'    => '/Edg\/([0-9.]+)/',
        'chrome'  => '/Chrome\/([0-9.]+)/',
        'firefox' => '/Firefox\/([0-9.]+)/',
        'safari'  => '/Version\/([0-9.]+).+Safari/',
        'opera'   => '/OPR\/([0-9.]+)/',
    ];
    foreach ($patterns as $name => $re) {
        if (preg_match($re, $ua, $m)) { $browser = $name; $bver = $m[1]; break; }
    }

    $os = 'other'; $osver = '';
    $osPatterns = [
        'windows' => '/Windows NT ([0-9.]+)/',
        'macos'   => '/Mac OS X ([0-9._]+)/',
        'android' => '/Android ([0-9.]+)/',
        'ios'     => '/OS ([0-9_]+) like Mac OS X/',
        'linux'   => '/Linux/',
    ];
    foreach ($osPatterns as $name => $re) {
        if (preg_match($re, $ua, $m)) {
            $os = $name; $osver = str_replace('_', '.', $m[1] ?? ''); break;
        }
    }

    $device = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
        $device = preg_match('/iPad/i', $ua) ? 'tablet' : 'mobile';
    }

    return compact('browser','bver','os','osver','device');
}

$parsed = _parse_browser($ua);
$page   = $_SERVER['REQUEST_URI'] ?? '/';
$ref    = $_SERVER['HTTP_REFERER'] ?? null;
$ip_v   = strpos($ip, ':') !== false ? 'v6' : 'v4';

// ── Upsert _visitor ──────────────────────────────────────────
try {
    if ($existing) {
        // Actualizar visita existente
        $pdo->prepare("
            UPDATE _visitor SET
                page_exit       = ?,
                pages_viewed    = pages_viewed + 1,
                visit_count     = visit_count + 1,
                user_agent      = ?,
                browser         = ?,
                browser_version = ?,
                os              = ?,
                os_version      = ?,
                device_type     = ?,
                is_online       = 1,
                last_seen       = NOW(),
                modif_visitor   = NOW()
            WHERE id_visitor = ?
        ")->execute([
            $page,
            substr($ua, 0, 500),
            $parsed['browser'],
            $parsed['bver'],
            $parsed['os'],
            $parsed['osver'],
            $parsed['device'],
            $existing['id_visitor'],
        ]);
        $_SESSION['id_visitor'] = $existing['id_visitor'];

    } else {
        // Novo visitante
        $pdo->prepare("
            INSERT INTO _visitor (
                ip_address, ip_version, user_agent,
                browser, browser_version, os, os_version, device_type,
                page_entry, page_exit, pages_viewed,
                referrer, session_id, is_online, last_seen,
                status_visitor, visit_count
            ) VALUES (
                ?,?,?,
                ?,?,?,?,?,
                ?,?,1,
                ?,?,1,NOW(),
                'active',1
            )
        ")->execute([
            $ip, $ip_v, substr($ua, 0, 500),
            $parsed['browser'], $parsed['bver'], $parsed['os'], $parsed['osver'], $parsed['device'],
            $page, $page,
            $ref ? substr($ref, 0, 500) : null,
            $session_id,
        ]);
        $_SESSION['id_visitor'] = (int)$pdo->lastInsertId();
    }

    // Registar pageview
    if (!empty($_SESSION['id_visitor'])) {
        $pdo->prepare("
            INSERT INTO _visitor_pageview (id_visitor, page_url, page_title)
            VALUES (?, ?, ?)
        ")->execute([
            $_SESSION['id_visitor'],
            substr($page, 0, 500),
            substr(($_SERVER['HTTP_X_PAGE_TITLE'] ?? ''), 0, 255),
        ]);
    }

} catch (PDOException $e) {
    // Falha silenciosa — não bloquear o carregamento da página
    error_log('[visitor.php] ' . $e->getMessage());
}