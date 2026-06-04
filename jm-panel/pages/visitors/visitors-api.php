<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visitors API (JSON)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

$action = $_GET['action'] ?? 'summary';

switch ($action) {
    case 'summary':
        $total     = (int) $db->query("SELECT COUNT(*) FROM _visitor")->fetchColumn();
        $online    = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
        $today     = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE DATE(last_seen) = CURDATE()")->fetchColumn();
        $pageviews = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview")->fetchColumn();
        echo json_encode([
            'success'   => true,
            'total'     => $total,
            'online'    => $online,
            'today'     => $today,
            'pageviews' => $pageviews,
        ]);
        break;

    case 'online':
        $visitors = $db->query("
            SELECT id_visitor, ip_address, country_name, city, device_type, browser, os, pages_viewed, session_duration, last_seen
            FROM _visitor
            WHERE is_online = 1 AND is_bot = 0
            ORDER BY last_seen DESC
        ")->fetchAll();
        echo json_encode(['success' => true, 'visitors' => $visitors]);
        break;

    case 'recent':
        $recent = $db->query("
            SELECT id_visitor, ip_address, country_name, city, device_type, browser, last_seen
            FROM _visitor
            WHERE is_bot = 0
            ORDER BY last_seen DESC
            LIMIT 10
        ")->fetchAll();
        echo json_encode(['success' => true, 'visitors' => $recent]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acção inválida.']);
}
exit;