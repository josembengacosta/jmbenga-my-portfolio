<?php
/**
 * api/contact.php — POST
 * ─────────────────────────────────────────────────────────────
 * Recebe o formulário de contacto e guarda em _contact_message.
 * Retorna JSON. Aceita apenas POST.
 * Rate limit: 3 mensagens por IP por hora.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// ── CSRF ──────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_POST['csrf_token'] 
      ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
      ?? $_SERVER['HTTP_X_CSRF-TOKEN'] 
      ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    // Log opcional para debug (remover em produção)
    error_log('CSRF falhou. Sessão: ' . ($_SESSION['csrf_token'] ?? 'vazia') . ' Token: ' . $token);
    json_response(['success' => false, 'message' => 'Token inválido. Recarregue a página.'], 403);
}

// ── Campos ────────────────────────────────────────────────────
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? ''); 
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// ── Validação ─────────────────────────────────────────────────
$errors = [];
if (mb_strlen($name) < 2)              $errors[] = 'Nome inválido.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
if (mb_strlen($subject) < 3)           $errors[] = 'Assunto inválido.';
if (mb_strlen($message) < 10)          $errors[] = 'Mensagem muito curta.';

if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

// ── Rate limit por IP (3/hora) ────────────────────────────────
$ip = get_ip();
$count = $pdo->prepare("
    SELECT COUNT(*) FROM _contact_message
    WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$count->execute([$ip]);
if ((int)$count->fetchColumn() >= 3) {
    json_response(['success' => false, 'message' => 'Limite de mensagens por hora atingido. Tente mais tarde.'], 429);
}

// ── Guardar ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO _contact_message
            (name_msg, email_msg, phone_msg, subject_msg, message_msg,
             ip_address, user_agent, status_msg)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([
        mb_substr($name,    0, 120),
        mb_substr($email,   0, 120),
        mb_substr($phone,   0,  30),
        mb_substr($subject, 0, 120),
        mb_substr($message, 0, 5000),
        $ip,
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
    ]);

    json_response(['success' => true, 'message' => 'Mensagem recebida com sucesso!']);

} catch (PDOException $e) {
    error_log('[api/contact] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro interno. Tente novamente mais tarde.'], 500);
}