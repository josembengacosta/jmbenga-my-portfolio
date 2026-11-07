<?php
declare(strict_types=1);

/**
 * api/visitor.php — POST (JSON body)
 * Recebe eventos complementares do visitor-tracker.js.
 * O registo inicial da pageview é feito em includes/visitor.php; esta API trata
 * apenas de presença e tempo na página. Resposta silenciosa HTTP 204.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$finish = static function (): void {
    http_response_code(204);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

// Validar Origin/Referer quando o navegador os envia. A sessão pública e a
// associação id_visitor + visitor_session_id continuam a ser a autorização efectiva.
$expectedScheme = strtolower((string) parse_url((string) BASE_URL, PHP_URL_SCHEME));
if (!in_array($expectedScheme, ['http', 'https'], true)) {
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $expectedScheme = ($https !== '' && !in_array($https, ['off', '0'], true)) ? 'https' : 'http';
}
$expectedHostParts = parse_url($expectedScheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? ''));
if (!is_array($expectedHostParts) || empty($expectedHostParts['host'])) {
    $finish();
}
$expectedHost = strtolower((string) $expectedHostParts['host']);
$expectedPort = (int) ($expectedHostParts['port'] ?? ($expectedScheme === 'https' ? 443 : 80));

$sourceUrl = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($sourceUrl === '') {
    $sourceUrl = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
}
if ($sourceUrl !== '') {
    $sourceParts = parse_url($sourceUrl);
    if (!is_array($sourceParts) || empty($sourceParts['host'])) {
        $finish();
    }
    $sourceScheme = strtolower((string) ($sourceParts['scheme'] ?? ''));
    $sourcePort = (int) ($sourceParts['port'] ?? ($sourceScheme === 'https' ? 443 : 80));
    if (strtolower((string) $sourceParts['host']) !== $expectedHost
        || $sourceScheme !== $expectedScheme
        || $sourcePort !== $expectedPort) {
        $finish();
    }
}

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
    $finish();
}

$maxBodyBytes = 8192;
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBodyBytes) {
    $finish();
}
$rawBody = file_get_contents('php://input', false, null, 0, $maxBodyBytes + 1);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > $maxBodyBytes) {
    $finish();
}
$data = json_decode($rawBody, true, 16);
if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    $finish();
}

$publicSessionName = defined('PUBLIC_SESSION_NAME') ? PUBLIC_SESSION_NAME : 'jm_visitor';
if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== $publicSessionName) {
    $finish();
}

$visitorSessionId = $_SESSION['visitor_session_id'] ?? '';
$idVisitor = filter_var($_SESSION['id_visitor'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($visitorSessionId)
    || !preg_match('/\A[a-f0-9]{48}\z/i', $visitorSessionId)
    || $idVisitor === false
    || (int) $idVisitor < 1) {
    $finish();
}

// Rate limit por sessão: impede chamadas excessivas sem depender de IP fornecido pelo cliente.
$now = time();
$windowStart = (int) ($_SESSION['visitor_api_window_start'] ?? 0);
$requestCount = (int) ($_SESSION['visitor_api_request_count'] ?? 0);
if ($windowStart < $now - 60 || $windowStart > $now) {
    $windowStart = $now;
    $requestCount = 0;
}
$requestCount++;
$_SESSION['visitor_api_window_start'] = $windowStart;
$_SESSION['visitor_api_request_count'] = $requestCount;
if ($requestCount > 120) {
    $finish();
}

$event = $data['event'] ?? '';
if (!is_string($event) || !in_array($event, ['pageview', 'scroll_depth', 'exit', 'ping', 'tab_focus'], true)) {
    $finish();
}

// PHP já regista a pageview inicial em includes/visitor.php. Ignorar o evento
// legado do JavaScript evita duplicar cada visita na tabela de pageviews.
if ($event === 'pageview' || $event === 'scroll_depth') {
    $finish();
}

$rawPageUrl = $data['page_url'] ?? '/';
if (!is_string($rawPageUrl) || strlen($rawPageUrl) > 2048) {
    $rawPageUrl = '/';
}
$pageParts = parse_url($rawPageUrl);
if (!is_array($pageParts)) {
    $finish();
}
if (isset($pageParts['host'])) {
    $pageScheme = strtolower((string) ($pageParts['scheme'] ?? ''));
    $pagePort = (int) ($pageParts['port'] ?? ($pageScheme === 'https' ? 443 : 80));
    if (!in_array($pageScheme, ['http', 'https'], true)
        || strtolower((string) $pageParts['host']) !== $expectedHost
        || $pageScheme !== $expectedScheme
        || $pagePort !== $expectedPort) {
        $finish();
    }
}
$pagePath = (string) ($pageParts['path'] ?? '/');
if ($pagePath === '' || !str_starts_with($pagePath, '/') || str_starts_with($pagePath, '//')
    || str_contains($pagePath, '\\') || preg_match('/[\x00-\x1F\x7F]/', $pagePath)) {
    $finish();
}
$pagePath = function_exists('mb_substr') ? mb_substr($pagePath, 0, 500, 'UTF-8') : substr($pagePath, 0, 500);

$timeValue = $data['time_on_page'] ?? 0;
$timeOnPage = (is_int($timeValue) || (is_string($timeValue) && ctype_digit($timeValue)))
    ? max(0, min(86400, (int) $timeValue))
    : 0;

try {
    // O ID só é aceite quando pertence à sessão pública actual e não está bloqueado.
    $owner = $pdo->prepare(
        'SELECT id_visitor FROM _visitor
         WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\'
         LIMIT 1'
    );
    $owner->execute([(int) $idVisitor, $visitorSessionId]);
    if (!$owner->fetchColumn()) {
        $finish();
    }

    if ($event === 'exit') {
        $pdo->prepare(
            'UPDATE _visitor
             SET is_online = 0,
                 last_seen = NOW(),
                 page_exit = ?,
                 session_duration = COALESCE(session_duration, 0) + ?
             WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
        )->execute([$pagePath, $timeOnPage, (int) $idVisitor, $visitorSessionId]);

        if ($timeOnPage > 0) {
            $pdo->prepare(
                'UPDATE _visitor_pageview
                 SET time_on_page = ?
                 WHERE id_visitor = ? AND page_url = ?
                 ORDER BY id_pageview DESC
                 LIMIT 1'
            )->execute([$timeOnPage, (int) $idVisitor, $pagePath]);
        }
    } elseif ($event === 'ping' || $event === 'tab_focus') {
        $pdo->prepare(
            'UPDATE _visitor
             SET is_online = 1, last_seen = NOW()
             WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
        )->execute([(int) $idVisitor, $visitorSessionId]);
    }
} catch (Throwable $e) {
    // A telemetria nunca deve interromper a navegação pública.
    error_log('[api/visitor] ' . $e->getMessage());
}

$finish();
