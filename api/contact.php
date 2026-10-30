<?php
declare(strict_types=1);

/**
 * api/contact.php — POST
 * Recebe o formulário público, valida CSRF e honeypot,
 * aplica limite de envio e guarda em _contact_message.
 * Respostas sempre em JSON.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Este endpoint aceita exclusivamente POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Obter apenas valores escalares para evitar TypeError com parâmetros em array.
$postString = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
};

// ── CSRF ──────────────────────────────────────────────────────
$sessionToken = $_SESSION['csrf_token'] ?? '';
$token = $postString('csrf_token');
if ($token === '') {
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = is_string($headerToken) ? trim($headerToken) : '';
}

// Nunca considerar dois tokens vazios como uma validação bem-sucedida.
if (!is_string($sessionToken) || $sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
    // Não registar tokens CSRF em logs: são segredos de sessão.
    json_response(['success' => false, 'message' => 'Token inválido ou expirado. Recarregue a página e tente novamente.'], 403);
}

// ── Honeypot anti-spam ────────────────────────────────────────
// Responder genericamente para não revelar a detecção automática.
$honeypot = $_POST['_hp'] ?? '';
if (!is_string($honeypot) && !is_numeric($honeypot)) {
    json_response(['success' => true, 'message' => 'Mensagem recebida com sucesso!']);
}
if (trim((string) $honeypot) !== '') {
    json_response(['success' => true, 'message' => 'Mensagem recebida com sucesso!']);
}

// ── Campos ────────────────────────────────────────────────────
$name    = $postString('name');
$email   = $postString('email');
$phone   = $postString('phone');
$subject = $postString('subject');
$package = $postString('package');
$message = $postString('message');

$stringLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

// ── Validação ─────────────────────────────────────────────────
$errors = [];

// Lista permitida de pacotes de Desenvolvimento Web. Não confiar no valor do URL/formulário.
$allowedPackages = ['JMbenga Start', 'JMbenga Business', 'JMbenga Custom'];

// Compatibilidade com o formulário/URL antigo que enviava o nome do pacote no campo subject.
if ($package === '' && in_array($subject, $allowedPackages, true)) {
    $package = $subject;
    $subject = 'Desenvolvimento Web';
}

if ($package !== '') {
    if (!in_array($package, $allowedPackages, true)) {
        $errors[] = 'O pacote seleccionado não é válido. Actualize a página e tente novamente.';
    } elseif ($subject !== 'Desenvolvimento Web') {
        $errors[] = 'Este pacote só pode ser solicitado como serviço de Desenvolvimento Web.';
    }
}

if ($stringLength($name) < 2 || $stringLength($name) > 120) {
    $errors[] = 'O nome deve ter entre 2 e 120 caracteres.';
}
if ($email === '' || $stringLength($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email inválido.';
}
if ($stringLength($phone) > 30) {
    $errors[] = 'O telefone não pode exceder 30 caracteres.';
}
if ($stringLength($subject) < 3 || $stringLength($subject) > 120) {
    $errors[] = 'O assunto deve ter entre 3 e 120 caracteres.';
}
if ($stringLength($message) < 10 || $stringLength($message) > 5000) {
    $errors[] = 'A mensagem deve ter entre 10 e 5000 caracteres.';
}

if ($errors !== []) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$ip = (string) get_ip();
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
$storedSubject = $package !== '' ? $subject . ' — ' . $package : $subject;
if ($stringLength($storedSubject) > 120) {
    json_response(['success' => false, 'message' => 'O assunto e o pacote excedem o limite permitido.'], 422);
}

try {
    // Respeitar a opção administrativa que permite ou bloqueia contactos.
    $platformStmt = $pdo->query('SELECT allow_contact FROM _platform ORDER BY id_platform ASC LIMIT 1');
    $allowContact = $platformStmt->fetchColumn();
    if ($allowContact !== false && (int) $allowContact !== 1) {
        json_response(['success' => false, 'message' => 'O formulário de contacto está temporariamente indisponível. Tente novamente mais tarde.'], 403);
    }

    // Limite: 3 mensagens guardadas por IP em qualquer janela de uma hora.
    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM _contact_message
         WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $count->execute([$ip]);
    if ((int) $count->fetchColumn() >= 3) {
        json_response(['success' => false, 'message' => 'Limite de mensagens por hora atingido. Tente mais tarde.'], 429);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO _contact_message
            (name_msg, email_msg, phone_msg, subject_msg, message_msg,
             ip_address, user_agent, status_msg)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'new')"
    );
    $stmt->execute([$name, $email, $phone, $storedSubject, $message, $ip, $userAgent]);

    json_response(['success' => true, 'message' => 'Mensagem recebida com sucesso!']);
} catch (PDOException $e) {
    // Registar o erro do servidor sem expor detalhes internos ao visitante.
    error_log('[api/contact] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro interno. Tente novamente mais tarde.'], 500);
}