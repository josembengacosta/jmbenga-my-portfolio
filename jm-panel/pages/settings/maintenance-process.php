<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Manutenção (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

/** Responde sempre em JSON e termina o pedido. */
function jmMaintenanceJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/** Lê uma string do POST e rejeita tipos inesperados. */
function jmMaintenancePostString(string $key, array &$errors, string $default = ''): string
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }
    if (!is_string($_POST[$key])) {
        $errors[] = 'Um ou mais campos foram enviados num formato inválido.';
        return $default;
    }
    return trim($_POST[$key]);
}

/** Converte datetime-local para DATETIME local de Angola sem aceitar datas inválidas. */
function jmMaintenanceParseDate(string $value, string $label, array &$errors): ?string
{
    if ($value === '') {
        return null;
    }

    $timezone = new DateTimeZone('Africa/Luanda');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $date->format('Y-m-d\\TH:i') !== $value
    ) {
        $errors[] = sprintf('A data de %s é inválida.', $label);
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jmMaintenanceJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    jmMaintenanceJsonResponse(['success' => false, 'message' => 'Token de segurança inválido ou expirado.'], 403);
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    jmMaintenanceJsonResponse(['success' => false, 'message' => 'Sessão administrativa inválida. Inicia sessão novamente.'], 401);
}

$errors = [];
$status = jmMaintenancePostString('status', $errors);
$maintenanceMsg = jmMaintenancePostString('maintenance_msg', $errors);
$maintStartInput = jmMaintenancePostString('maintenance_start', $errors);
$maintEndInput = jmMaintenancePostString('maintenance_end', $errors);

if (!in_array($status, ['active', 'maintenance', 'blocked'], true)) {
    $errors[] = 'Selecciona um estado válido para o site.';
}

$messageLength = function_exists('mb_strlen') ? mb_strlen($maintenanceMsg, 'UTF-8') : strlen($maintenanceMsg);
if ($messageLength > 2000) {
    $errors[] = 'A mensagem de manutenção não pode ultrapassar 2.000 caracteres.';
}

$maintStartValue = jmMaintenanceParseDate($maintStartInput, 'início da manutenção', $errors);
$maintEndValue = jmMaintenanceParseDate($maintEndInput, 'fim previsto', $errors);

if ($maintStartValue !== null && $maintEndValue !== null && $maintEndValue <= $maintStartValue) {
    $errors[] = 'O fim previsto deve ser posterior ao início da manutenção.';
}

if ($errors) {
    jmMaintenanceJsonResponse(['success' => false, 'errors' => array_values(array_unique($errors))], 422);
}

$db = $GLOBALS['pdo'];
try {
    $db->beginTransaction();

    // Bloqueia a linha para impedir actualizações concorrentes e confirmar que existe.
    $currentStmt = $db->prepare(
        'SELECT status, maintenance_msg, maintenance_start, maintenance_end
         FROM _platform WHERE id_platform = ? FOR UPDATE'
    );
    $currentStmt->execute([1]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $db->rollBack();
        jmMaintenanceJsonResponse([
            'success' => false,
            'message' => 'O registo da plataforma não existe. Verifica a tabela _platform.'
        ], 404);
    }

    $update = $db->prepare(
        'UPDATE _platform
         SET status = ?, maintenance_msg = ?, maintenance_start = ?, maintenance_end = ?
         WHERE id_platform = ?'
    );
    $update->execute([$status, $maintenanceMsg, $maintStartValue, $maintEndValue, 1]);

    // logAudit usa a mesma conexão PDO; a gravação é tentada dentro da transacção.
    logAudit(
        $adminId,
        null,
        'settings.maintenance.update',
        '_platform',
        1,
        [
            'status' => $current['status'],
            'maintenance_start' => $current['maintenance_start'],
            'maintenance_end' => $current['maintenance_end'],
        ],
        [
            'status' => $status,
            'maintenance_start' => $maintStartValue,
            'maintenance_end' => $maintEndValue,
            'message_changed' => (string) ($current['maintenance_msg'] ?? '') !== $maintenanceMsg,
        ]
    );

    $db->commit();

    $message = match ($status) {
        'maintenance' => 'Modo de manutenção activado. O acesso público é afectado imediatamente.',
        'blocked' => 'Acesso público bloqueado com sucesso.',
        default => 'Site reactivado com sucesso.',
    };

    jmMaintenanceJsonResponse([
        'success' => true,
        'message' => $message,
        'status' => $status,
    ]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[SETTINGS MAINTENANCE] ' . $exception->getMessage());
    jmMaintenanceJsonResponse([
        'success' => false,
        'message' => 'Não foi possível actualizar o estado do site. Nenhuma alteração foi confirmada.',
    ], 500);
}