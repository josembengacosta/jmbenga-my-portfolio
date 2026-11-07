<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador IP Whitelist (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = $GLOBALS['pdo'];

/**
 * Responde sempre com JSON e um código HTTP coerente.
 */
function jmSecurityJsonResponse(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/**
 * A auditoria é importante, mas a sua falha não deve transformar
 * uma operação de whitelist já confirmada numa falsa falha.
 */
function jmSecurityAuditSafely(
    int $adminId,
    string $action,
    int|string|null $entityId,
    ?array $oldValue,
    ?array $newValue
): void {
    try {
        logAudit(
            $adminId,
            null,
            $action,
            '_admin_ip_whitelist',
            $entityId,
            $oldValue,
            $newValue
        );
    } catch (Throwable $exception) {
        error_log('[SECURITY AUDIT] ' . $exception->getMessage());
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jmSecurityJsonResponse(false, 'Método não permitido.', 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    jmSecurityJsonResponse(false, 'Token de segurança inválido ou expirado.', 403);
}

$adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    jmSecurityJsonResponse(false, 'Sessão administrativa inválida. Inicia sessão novamente.', 401);
}

$action = $_GET['action'] ?? 'add';
if (!is_string($action)) {
    jmSecurityJsonResponse(false, 'Acção inválida.', 400);
}

try {
    switch ($action) {
        case 'add': {
            $ipInput = $_POST['ip_address'] ?? '';
            $labelInput = $_POST['label'] ?? '';

            $ip = is_string($ipInput) ? trim($ipInput) : '';
            $label = is_string($labelInput) ? trim($labelInput) : '';

            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                jmSecurityJsonResponse(false, 'Indica um endereço IPv4 ou IPv6 válido.', 422);
            }

            if (mb_strlen($label) > 100) {
                jmSecurityJsonResponse(false, 'O rótulo não pode ultrapassar 100 caracteres.', 422);
            }

            // Normaliza a representação do IP antes de comparar e guardar.
            $packedIp = inet_pton($ip);
            if ($packedIp === false) {
                jmSecurityJsonResponse(false, 'Não foi possível interpretar o endereço IP.', 422);
            }
            $normalizedIp = inet_ntop($packedIp);
            if ($normalizedIp === false) {
                jmSecurityJsonResponse(false, 'Endereço IP inválido.', 422);
            }

            $db->beginTransaction();

            $stmt = $db->prepare(
                'SELECT id_ip FROM _admin_ip_whitelist WHERE ip_address = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$normalizedIp]);

            if ($stmt->fetchColumn()) {
                $db->rollBack();
                jmSecurityJsonResponse(false, 'Este IP já está na whitelist.', 409);
            }

            $stmt = $db->prepare(
                'INSERT INTO _admin_ip_whitelist (ip_address, label, added_by) VALUES (?, ?, ?)'
            );
            $stmt->execute([$normalizedIp, $label !== '' ? $label : null, $adminId]);
            $newId = (int)$db->lastInsertId();

            $db->commit();

            jmSecurityAuditSafely(
                $adminId,
                'security.ip_whitelist.add',
                $newId > 0 ? $newId : null,
                null,
                ['ip' => $normalizedIp, 'label' => $label]
            );

            jmSecurityJsonResponse(true, 'IP adicionado com sucesso.', 201, ['id' => $newId]);
        }

        case 'toggle': {
            $rawId = $_GET['id'] ?? '';
            $id = is_string($rawId) || is_int($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;

            if ($id === false || (int)$id <= 0) {
                jmSecurityJsonResponse(false, 'ID de IP inválido.', 400);
            }
            $id = (int)$id;

            $db->beginTransaction();

            $stmt = $db->prepare(
                'SELECT id_ip, ip_address, active FROM _admin_ip_whitelist WHERE id_ip = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $db->rollBack();
                jmSecurityJsonResponse(false, 'IP não encontrado.', 404);
            }

            $currentActive = (int)$row['active'];
            $newActive = $currentActive === 1 ? 0 : 1;

            $stmt = $db->prepare('UPDATE _admin_ip_whitelist SET active = ? WHERE id_ip = ?');
            $stmt->execute([$newActive, $id]);

            $db->commit();

            jmSecurityAuditSafely(
                $adminId,
                'security.ip_whitelist.toggle',
                $id,
                ['ip' => $row['ip_address'], 'active' => $currentActive],
                ['ip' => $row['ip_address'], 'active' => $newActive]
            );

            jmSecurityJsonResponse(true, $newActive ? 'IP activado.' : 'IP desactivado.', 200, [
                'active' => $newActive
            ]);
        }

        case 'delete': {
            $rawId = $_GET['id'] ?? '';
            $id = is_string($rawId) || is_int($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;

            if ($id === false || (int)$id <= 0) {
                jmSecurityJsonResponse(false, 'ID de IP inválido.', 400);
            }
            $id = (int)$id;

            $db->beginTransaction();

            $stmt = $db->prepare(
                'SELECT id_ip, ip_address, label, active FROM _admin_ip_whitelist WHERE id_ip = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $db->rollBack();
                jmSecurityJsonResponse(false, 'IP não encontrado.', 404);
            }

            $stmt = $db->prepare('DELETE FROM _admin_ip_whitelist WHERE id_ip = ?');
            $stmt->execute([$id]);

            $db->commit();

            jmSecurityAuditSafely(
                $adminId,
                'security.ip_whitelist.delete',
                $id,
                [
                    'ip' => $row['ip_address'],
                    'label' => $row['label'],
                    'active' => (int)$row['active']
                ],
                null
            );

            jmSecurityJsonResponse(true, 'IP removido da whitelist.');
        }

        default:
            jmSecurityJsonResponse(false, 'Acção inválida.', 400);
    }
} catch (PDOException $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[IP WHITELIST] ' . $exception->getMessage());

    // SQLSTATE 23000 costuma indicar violação de unicidade/restrição.
    if ((string)$exception->getCode() === '23000') {
        jmSecurityJsonResponse(false, 'Este IP já existe na whitelist ou viola uma restrição da base de dados.', 409);
    }

    jmSecurityJsonResponse(false, 'Não foi possível concluir a operação. Tenta novamente.', 500);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[IP WHITELIST] ' . $exception->getMessage());
    jmSecurityJsonResponse(false, 'Ocorreu um erro interno ao processar a operação.', 500);
}