<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visitors API (JSON)
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

function visitorsApiJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$action = $_GET['action'] ?? 'summary';
if (!is_string($action)) {
    visitorsApiJson(['success' => false, 'message' => 'Acção inválida.'], 400);
}

try {
    switch ($action) {
        case 'summary':
            $total = (int)$db->query("SELECT COUNT(*) FROM _visitor")->fetchColumn();
            $online = (int)$db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
            $today = (int)$db->query("
                SELECT COUNT(*) FROM _visitor
                WHERE last_seen >= CURDATE()
                  AND last_seen < CURDATE() + INTERVAL 1 DAY
            ")->fetchColumn();
            $pageviews = (int)$db->query("SELECT COUNT(*) FROM _visitor_pageview")->fetchColumn();
            $bots = (int)$db->query("SELECT COUNT(*) FROM _visitor WHERE is_bot = 1")->fetchColumn();
            $blocked = (int)$db->query("SELECT COUNT(*) FROM _visitor WHERE status_visitor = 'blocked'")->fetchColumn();

            visitorsApiJson([
                'success' => true,
                'total' => $total,
                'online' => $online,
                'today' => $today,
                'pageviews' => $pageviews,
                'bots' => $bots,
                'blocked' => $blocked
            ]);

        case 'online':
            $visitors = $db->query("
                SELECT id_visitor, ip_address, country_name, city, device_type,
                       browser, os, pages_viewed, session_duration, last_seen
                FROM _visitor
                WHERE is_online = 1 AND is_bot = 0 AND status_visitor <> 'blocked'
                ORDER BY last_seen DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            visitorsApiJson(['success' => true, 'visitors' => $visitors]);

        case 'recent':
            $recent = $db->query("
                SELECT id_visitor, ip_address, country_name, city, device_type, browser, last_seen
                FROM _visitor
                WHERE is_bot = 0
                ORDER BY last_seen DESC
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);

            visitorsApiJson(['success' => true, 'visitors' => $recent]);

        default:
            visitorsApiJson(['success' => false, 'message' => 'Acção inválida.'], 400);
    }
} catch (Throwable $error) {
    error_log('[VISITORS API] ' . $error->getMessage());
    visitorsApiJson([
        'success' => false,
        'message' => 'Não foi possível carregar os dados dos visitantes.'
    ], 500);
}