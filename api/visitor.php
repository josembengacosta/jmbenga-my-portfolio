<?php
/**
 * api/visitor.php — POST (JSON body)
 * ─────────────────────────────────────────────────────────────
 * Recebe eventos do visitor-tracker.js:
 *   pageview, scroll_depth, exit, ping, tab_focus
 * Actualiza _visitor e _visitor_pageview.
 * Resposta: HTTP 204 (sem body).
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Sem output — resposta silenciosa
header('Content-Type: application/json');
http_response_code(204);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

// Ler body JSON
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) exit;

$event      = $data['event']        ?? 'pageview';
$page_url   = mb_substr($data['page_url']   ?? '', 0, 500);
$page_title = mb_substr($data['page_title'] ?? '', 0, 255);
$time_on    = (int)($data['time_on_page']   ?? 0);
$max_scroll = (int)($data['max_scroll']     ?? 0);

$ip = get_ip();

// ── Recuperar id_visitor ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('jm_visitor');
    @session_start();
}
$id_visitor = $_SESSION['id_visitor'] ?? null;

if (!$id_visitor) {
    // Tentar pelo IP
    $row = $pdo->prepare("SELECT id_visitor FROM _visitor WHERE ip_address = ? ORDER BY last_seen DESC LIMIT 1");
    $row->execute([$ip]);
    $found = $row->fetchColumn();
    if ($found) {
        $id_visitor = $found;
        $_SESSION['id_visitor'] = $id_visitor;
    }
}

if (!$id_visitor) exit; // não encontrado — ignorar

try {
    switch ($event) {
        case 'pageview':
            $pdo->prepare("
                INSERT INTO _visitor_pageview (id_visitor, page_url, page_title)
                VALUES (?, ?, ?)
            ")->execute([$id_visitor, $page_url, $page_title]);
            $pdo->prepare("UPDATE _visitor SET is_online=1, last_seen=NOW(), pages_viewed=pages_viewed+1 WHERE id_visitor=?")
                ->execute([$id_visitor]);
            break;

        case 'exit':
            $pdo->prepare("
                UPDATE _visitor SET
                    is_online       = 0,
                    last_seen       = NOW(),
                    page_exit       = ?,
                    session_duration = COALESCE(session_duration, 0) + ?
                WHERE id_visitor = ?
            ")->execute([$page_url, $time_on, $id_visitor]);

            // Actualizar time_on_page da última pageview
            if ($time_on > 0) {
                $pdo->prepare("
                    UPDATE _visitor_pageview
                    SET time_on_page = ?
                    WHERE id_visitor = ?
                    ORDER BY id_pageview DESC
                    LIMIT 1
                ")->execute([$time_on, $id_visitor]);
            }
            break;

        case 'ping':
            $pdo->prepare("UPDATE _visitor SET is_online=1, last_seen=NOW() WHERE id_visitor=?")
                ->execute([$id_visitor]);
            break;

        case 'tab_focus':
            $pdo->prepare("UPDATE _visitor SET is_online=1, last_seen=NOW() WHERE id_visitor=?")
                ->execute([$id_visitor]);
            break;
    }
} catch (PDOException $e) {
    error_log('[api/visitor] ' . $e->getMessage());
}
exit;