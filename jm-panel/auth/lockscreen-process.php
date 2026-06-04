<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Lockscreen (AJAX) — Refactored
// ══════════════════════════════════════════════════════════════
declare(strict_types=1);

require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();
header('Content-Type: application/json; charset=utf-8');

// Resposta padronizada
function jsonResponse(bool $success, string $message = '', array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Verificações preliminares
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.');
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    jsonResponse(false, 'Token de segurança inválido. Recarrega a página.');
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    jsonResponse(false, 'Sessão inválida.');
}

// Rate limiting por sessão (lockscreen específico)
$now = time();
$lockoutKey = 'admin_lockscreen_block_until';
$attemptsKey = 'admin_lockscreen_attempts';

if (!empty($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > $now) {
    $remaining = $_SESSION[$lockoutKey] - $now;
    jsonResponse(false, "Muitas tentativas. Aguarde {$remaining} segundos.");
}

// Validar formato do PIN antes de tocar na BD
$code = preg_replace('/\D/', '', trim($_POST['access_code'] ?? ''));
if (strlen($code) !== 6) {
    recordFailedLockscreenAttempt($adminId);
    jsonResponse(false, 'Código deve conter 6 dígitos numéricos.');
}

// Verificar se lockscreen está realmente ativo na BD
$db = $GLOBALS['pdo'];
$stmt = $db->prepare("
    SELECT s.access_code, s.lockscreen, e.status_employees
    FROM _employees_security s
    JOIN _employees e ON e.id_employees = s.id_employees
    WHERE s.id_employees = ?
    LIMIT 1
");
$stmt->execute([$adminId]);
$row = $stmt->fetch();

if (!$row || $row['status_employees'] !== 'active' || empty($row['lockscreen'])) {
    // Se não está bloqueado, redireciona
    unset($_SESSION['admin_lockscreen']);
    jsonResponse(true, 'Sessão já desbloqueada.', ['return_url' => BASE_URL . '/jm-panel/home']);
}

// Comparação segura contra timing attacks
if (!hash_equals((string) $row['access_code'], $code)) {
    recordFailedLockscreenAttempt($adminId);
    logAudit($adminId, null, 'auth.lockscreen_fail', 'employees', $adminId);
    jsonResponse(false, 'Código incorrecto.');
}

// Sucesso — desbloquear
$db->prepare("UPDATE _employees_security SET lockscreen = 0 WHERE id_employees = ?")
    ->execute([$adminId]);

$_SESSION['admin_lockscreen'] = false;
unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);

// Regenerar CSRF após ação bem-sucedida
regenerateAdminCsrf();

logAudit($adminId, null, 'auth.lockscreen_unlock', 'employees', $adminId);

$returnUrl = $_SESSION['admin_return_url'] ?? (BASE_URL . '/jm-panel/home');
unset($_SESSION['admin_return_url']);

jsonResponse(true, 'Desbloqueado com sucesso.', ['return_url' => $returnUrl]);