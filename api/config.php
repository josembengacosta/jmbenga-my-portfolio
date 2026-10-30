<?php
/**
 * api/config.php — GET
 * ─────────────────────────────────────────────────────────────
 * Devolve as configurações públicas do site (_site_config is_public=1).
 * Usado pelo JS para carregar configs sem expor credenciais.
 * Cache: 10 minutos via header.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600'); // 10 min

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $rows = $pdo->query("
        SELECT config_key, config_value
        FROM _site_config
        WHERE is_public = 1
        ORDER BY config_group, config_key
    ")->fetchAll(PDO::FETCH_KEY_PAIR); // ['key' => 'value']

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'cached'  => date('c'),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal error']);
    error_log('[api/config] ' . $e->getMessage());
}