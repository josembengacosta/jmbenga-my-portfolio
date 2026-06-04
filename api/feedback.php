<?php

/**
 * api/feedback.php — POST
 * ─────────────────────────────────────────────────────────────
 * Recebe feedback via modal do site público.
 * Retorna JSON. Aceita apenas POST.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$name    = trim($_POST['name']    ?? '');
$subject = trim($_POST['subject'] ?? 'Sugestão');
$message = trim($_POST['message'] ?? '');
$page    = trim($_POST['page']    ?? '');

// Validação mínima
if (mb_strlen($name) < 2 || mb_strlen($message) < 5) {
    json_response(['success' => false, 'message' => 'Nome e mensagem são obrigatórios.'], 422);
}

$ip = get_ip();

// Rate limit: 5 feedbacks por IP por hora
$count = $pdo->prepare("
    SELECT COUNT(*) FROM _feedback
    WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$count->execute([$ip]);
if ((int)$count->fetchColumn() >= 5) {
    json_response(['success' => false, 'message' => 'Limite atingido. Tente mais tarde.'], 429);
}

try {
    $pdo->prepare("
        INSERT INTO _feedback (name_fb, subject_fb, message_fb, page_origin, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        mb_substr($name,    0, 120),
        mb_substr($subject, 0,  80),
        mb_substr($message, 0, 2000),
        mb_substr($page,    0, 255),
        $ip,
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
    ]);

    json_response(['success' => true, 'message' => 'Feedback recebido. Obrigado!']);
} catch (PDOException $e) {
    error_log('[api/feedback] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro interno.'], 500);
}
