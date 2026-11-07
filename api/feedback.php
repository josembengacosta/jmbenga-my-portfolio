<?php
declare(strict_types=1);

/**
 * api/feedback.php — POST
 * Recebe feedback do formulário público, valida CSRF/honeypot e aplica rate limit.
 * As respostas são sempre JSON.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// Aceitar apenas valores escalares, evitando TypeError com parâmetros em array.
$postString = static function (string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
};

// A configuração central já iniciou a sessão pública jm_visitor.
if (session_status() !== PHP_SESSION_ACTIVE
    || (defined('PUBLIC_SESSION_NAME') && session_name() !== PUBLIC_SESSION_NAME)) {
    json_response(['success' => false, 'message' => 'A sessão expirou. Recarregue a página e tente novamente.'], 403);
}

if (!csrf_verify()) {
    json_response(['success' => false, 'message' => 'Token inválido ou expirado. Recarregue a página e tente novamente.'], 403);
}

// Honeypot: responder sem revelar que a submissão foi classificada como spam.
$honeypot = $_POST['_hp'] ?? '';
if ((!is_string($honeypot) && !is_numeric($honeypot)) || trim((string) $honeypot) !== '') {
    json_response(['success' => true, 'message' => 'Feedback recebido. Obrigado!']);
}

$name = $postString('name_fb');
$subject = $postString('subject_fb');
$message = $postString('message_fb');

$length = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

$allowedSubjects = ['Sugestão', 'Elogio', 'Crítica', 'Bug', 'Outro'];
if ($length($name) < 2 || $length($name) > 120) {
    json_response(['success' => false, 'message' => 'O nome deve ter entre 2 e 120 caracteres.'], 422);
}
if (!in_array($subject, $allowedSubjects, true)) {
    json_response(['success' => false, 'message' => 'Selecciona um tipo de feedback válido.'], 422);
}
if ($length($message) < 5 || $length($message) > 2000) {
    json_response(['success' => false, 'message' => 'A mensagem deve ter entre 5 e 2000 caracteres.'], 422);
}

$ip = function_exists('get_ip') ? (string) get_ip() : '0.0.0.0';
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);

// Registar apenas o caminho do referenciador, sem query string, fragmento ou host externo.
$pageOrigin = '';
$referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
$requestHost = strtolower((string) parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
if ($referer !== '' && $requestHost !== '' && $refererHost === $requestHost) {
    $refererPath = parse_url($referer, PHP_URL_PATH);
    if (is_string($refererPath) && $refererPath !== '' && !preg_match('/[\x00-\x1F\x7F]/', $refererPath)) {
        $pageOrigin = function_exists('mb_substr')
            ? mb_substr($refererPath, 0, 255, 'UTF-8')
            : substr($refererPath, 0, 255);
    }
}

try {
    // Respeitar a opção administrativa que desactiva o feedback público.
    $platformStmt = $pdo->query('SELECT allow_feedback FROM _platform ORDER BY id_platform ASC LIMIT 1');
    $allowFeedback = $platformStmt->fetchColumn();
    if ($allowFeedback !== false && (int) $allowFeedback !== 1) {
        json_response(['success' => false, 'message' => 'O formulário de feedback está temporariamente indisponível.'], 403);
    }

    // Limite: cinco feedbacks guardados por IP em qualquer janela de uma hora.
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM _feedback
         WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $count->execute([$ip]);
    if ((int) $count->fetchColumn() >= 5) {
        header('Retry-After: 3600');
        json_response(['success' => false, 'message' => 'Limite de feedbacks atingido. Tente novamente mais tarde.'], 429);
    }

    $insert = $pdo->prepare(
        'INSERT INTO _feedback
            (name_fb, subject_fb, message_fb, page_origin, ip_address, user_agent, status_fb)
         VALUES (?, ?, ?, ?, ?, ?, \'new\')'
    );
    $insert->execute([$name, $subject, $message, $pageOrigin, $ip, $userAgent]);

    json_response(['success' => true, 'message' => 'Feedback recebido. Obrigado!']);
} catch (Throwable $e) {
    error_log('[api/feedback] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Não foi possível registar o feedback. Tente novamente mais tarde.'], 500);
}
