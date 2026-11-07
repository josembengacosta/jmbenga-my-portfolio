<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Lockscreen (AJAX)
// ══════════════════════════════════════════════════════════════
declare(strict_types=1);

require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

/** Emite uma resposta JSON e termina o pedido. */
function lockscreenJsonResponse(bool $success, string $message = '', array $extra = []): never
{
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    lockscreenJsonResponse(false, 'Método não permitido.');
}

$csrfCandidate = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrfCandidate) || !validateAdminCsrf($csrfCandidate)) {
    lockscreenJsonResponse(false, 'Token de segurança inválido. Recarrega a página.');
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    lockscreenJsonResponse(false, 'Sessão inválida. Inicia sessão novamente.', [
        'force_logout' => true,
        'return_url' => rtrim((string) BASE_URL, '/') . '/jm-panel/entrar',
    ]);
}

$now = time();
$lockoutKey = 'admin_lockscreen_block_until';
$attemptsKey = 'admin_lockscreen_attempts';
$blockedUntil = (int) ($_SESSION[$lockoutKey] ?? 0);
if ($blockedUntil > $now) {
    $remaining = $blockedUntil - $now;
    lockscreenJsonResponse(false, "Muitas tentativas. Aguarde {$remaining} segundos.");
}

$codeInput = $_POST['access_code'] ?? '';
$code = is_string($codeInput) ? trim($codeInput) : '';
if (!preg_match('/\A[0-9]{6}\z/', $code)) {
    recordFailedLockscreenAttempt($adminId);
    lockscreenJsonResponse(false, 'O código deve conter exatamente 6 dígitos numéricos.');
}

$db = $GLOBALS['pdo'];
try {
    // LEFT JOIN permite distinguir conta inexistente, falta de registo de segurança e lockscreen desligado.
    $stmt = $db->prepare(
        "SELECT e.id_employees, e.status_employees,
                s.id_employees AS security_admin_id, s.access_code, s.lockscreen
         FROM _employees e
         LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
         WHERE e.id_employees = ?
         LIMIT 1"
    );
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[ADMIN LOCKSCREEN] Falha ao consultar o estado da conta: ' . $e->getMessage());
    lockscreenJsonResponse(false, 'Não foi possível verificar o bloqueio. Tenta novamente.');
}

if (!$row || ($row['status_employees'] ?? null) !== 'active') {
    logoutAdmin();
    lockscreenJsonResponse(false, 'A sessão deixou de ser válida. Inicia sessão novamente.', [
        'force_logout' => true,
        'return_url' => rtrim((string) BASE_URL, '/') . '/jm-panel/entrar',
    ]);
}

if (empty($row['security_admin_id'])) {
    // Falhar em modo fechado: sem registo de segurança não é possível validar o PIN.
    error_log('[ADMIN LOCKSCREEN] Falta o registo de segurança para o administrador ' . $adminId . '.');
    lockscreenJsonResponse(false, 'Não foi possível validar o código de segurança. Contacta o administrador do sistema.');
}

if (empty($row['lockscreen'])) {
    $_SESSION['admin_lockscreen'] = false;
    unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);
    lockscreenJsonResponse(true, 'A sessão já está desbloqueada.', [
        'return_url' => rtrim((string) BASE_URL, '/') . '/jm-panel/home',
    ]);
}

$storedCode = is_string($row['access_code'] ?? null) ? $row['access_code'] : '';
if ($storedCode === '' || (!preg_match('/\A[0-9]{6}\z/', $storedCode) && !isAdminLockscreenPinHash($storedCode))) {
    error_log('[ADMIN LOCKSCREEN] PIN ausente ou inválido para o administrador ' . $adminId . '.');
    lockscreenJsonResponse(false, 'O código de segurança não está configurado corretamente. Contacta o administrador do sistema.');
}

if (!verifyAdminLockscreenPin($code, $storedCode)) {
    recordFailedLockscreenAttempt($adminId);
    logAudit($adminId, null, 'auth.lockscreen_fail', 'employees', $adminId);
    lockscreenJsonResponse(false, 'Código incorrecto.');
}

try {
    // PINs legados em texto simples são convertidos para bcrypt no primeiro desbloqueio.
    $replacementPin = adminLockscreenPinNeedsRehash($storedCode)
        ? hashAdminLockscreenPin($code)
        : $storedCode;
    $update = $db->prepare(
        'UPDATE _employees_security
         SET lockscreen = 0, access_code = ?
         WHERE id_employees = ? AND lockscreen = 1 AND access_code = ?'
    );
    $update->execute([$replacementPin, $adminId, $storedCode]);

    if ($update->rowCount() !== 1) {
        // Evita declarar sucesso se o registo tiver mudado entre SELECT e UPDATE.
        lockscreenJsonResponse(false, 'O estado do bloqueio mudou. Recarrega a página e tenta novamente.');
    }
} catch (Throwable $e) {
    error_log('[ADMIN LOCKSCREEN] Falha ao actualizar o bloqueio: ' . $e->getMessage());
    lockscreenJsonResponse(false, 'Não foi possível desbloquear a sessão. Tenta novamente.');
}

if (!session_regenerate_id(true)) {
    error_log('[ADMIN LOCKSCREEN] Não foi possível regenerar o ID da sessão após desbloqueio.');
}
$_SESSION['admin_lockscreen'] = false;
unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);
regenerateAdminCsrf();
logAudit($adminId, null, 'auth.lockscreen_unlock', 'employees', $adminId);

// O destino vem da sessão, mas continua a ser validado para não permitir redirecionamentos externos.
$candidate = $_SESSION['admin_return_url'] ?? '';
unset($_SESSION['admin_return_url']);
$returnUrl = rtrim((string) BASE_URL, '/') . '/jm-panel/home';
if (is_string($candidate) && $candidate !== '' && !str_contains($candidate, '\\')) {
    $candidateParts = parse_url($candidate);
    $candidatePath = is_array($candidateParts) ? ($candidateParts['path'] ?? '') : '';
    if (
        is_string($candidatePath)
        && str_starts_with($candidatePath, '/jm-panel/')
        && !isset($candidateParts['scheme'], $candidateParts['host'], $candidateParts['user'], $candidateParts['pass'])
        && !str_starts_with($candidate, '//')
    ) {
        $returnUrl = rtrim((string) BASE_URL, '/') . $candidatePath;
        if (isset($candidateParts['query']) && is_string($candidateParts['query'])) {
            $returnUrl .= '?' . str_replace(["\r", "\n"], '', $candidateParts['query']);
        }
    }
}

lockscreenJsonResponse(true, 'Desbloqueado com sucesso.', ['return_url' => $returnUrl]);