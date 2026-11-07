<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar Visitantes em Massa (AJAX)
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

function visitorBulkJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

// Apenas POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    visitorBulkJson([
        'success' => false,
        'message' => 'Método não permitido.'
    ], 405);
}

// Protecção CSRF.
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !validateAdminCsrf($csrfToken)) {
    visitorBulkJson([
        'success' => false,
        'message' => 'Token de segurança inválido. Actualiza a página e tenta novamente.'
    ], 403);
}

// Receber e validar os IDs.
$rawIds = $_POST['ids'] ?? null;
if (!is_array($rawIds) || $rawIds === []) {
    visitorBulkJson([
        'success' => false,
        'message' => 'Selecciona pelo menos um visitante.'
    ], 422);
}

if (count($rawIds) > 500) {
    visitorBulkJson([
        'success' => false,
        'message' => 'Podes eliminar no máximo 500 visitantes por operação.'
    ], 422);
}

$ids = [];
foreach ($rawIds as $rawId) {
    if (!is_scalar($rawId) || !preg_match('/^[1-9][0-9]{0,18}$/', (string)$rawId)) {
        visitorBulkJson([
            'success' => false,
            'message' => 'A selecção contém um identificador inválido.'
        ], 422);
    }

    $validatedId = filter_var((string)$rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedId === false) {
        visitorBulkJson([
            'success' => false,
            'message' => 'A selecção contém um identificador fora do intervalo permitido.'
        ], 422);
    }

    $ids[] = (int)$validatedId;
}

$ids = array_values(array_unique($ids));
if ($ids === [] || count($ids) > 500) {
    visitorBulkJson([
        'success' => false,
        'message' => 'A selecção de visitantes é inválida.'
    ], 422);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$adminId = (int)($_SESSION['admin_id'] ?? 0);

try {
    $db->beginTransaction();

    // Bloquear as linhas enquanto se decide quais podem ser removidas.
    // Visitantes online são preservados para não interromper sessões activas.
    $select = $db->prepare("
        SELECT id_visitor, ip_address, is_online
        FROM _visitor
        WHERE id_visitor IN ($placeholders)
        FOR UPDATE
    ");
    $select->execute($ids);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $offlineIds = [];
    $onlineSkipped = 0;

    foreach ($rows as $row) {
        if ((int)$row['is_online'] === 1) {
            $onlineSkipped++;
        } else {
            $offlineIds[] = (int)$row['id_visitor'];
        }
    }

    if ($offlineIds === []) {
        $db->commit();

        visitorBulkJson([
            'success' => true,
            'deleted_count' => 0,
            'deleted_pageviews' => 0,
            'skipped_online' => $onlineSkipped,
            'message' => $onlineSkipped > 0
                ? 'Nenhum registo foi eliminado: os visitantes seleccionados continuam online.'
                : 'Os visitantes seleccionados já não existem.'
        ]);
    }

    $offlinePlaceholders = implode(',', array_fill(0, count($offlineIds), '?'));

    // Apagar explicitamente as pageviews para não deixar registos órfãos
    // mesmo em instalações sem a constraint ON DELETE CASCADE.
    $deletePageviews = $db->prepare("
        DELETE FROM _visitor_pageview
        WHERE id_visitor IN ($offlinePlaceholders)
    ");
    $deletePageviews->execute($offlineIds);
    $deletedPageviews = $deletePageviews->rowCount();

    // A condição is_online = 0 é uma segunda salvaguarda no próprio DELETE.
    $deleteVisitors = $db->prepare("
        DELETE FROM _visitor
        WHERE id_visitor IN ($offlinePlaceholders)
          AND is_online = 0
    ");
    $deleteVisitors->execute($offlineIds);
    $deletedCount = $deleteVisitors->rowCount();

    $db->commit();

    // A auditoria não deve transformar uma eliminação confirmada numa falsa falha.
    try {
        logAudit(
            $adminId,
            null,
            'visitor.bulk_delete',
            '_visitor',
            null,
            null,
            [
                'deleted_count' => $deletedCount,
                'deleted_pageviews' => $deletedPageviews,
                'ids_sample' => array_slice($offlineIds, 0, 50),
                'ids_sample_truncated' => count($offlineIds) > 50
            ]
        );
    } catch (Throwable $auditError) {
        error_log('[VISITOR BULK DELETE AUDIT] ' . $auditError->getMessage());
    }

    $message = $deletedCount . ' visitante(s) eliminado(s) com sucesso.';
    if ($onlineSkipped > 0) {
        $message .= ' ' . $onlineSkipped . ' visitante(s) online foram preservados.';
    }

    visitorBulkJson([
        'success' => true,
        'deleted_count' => $deletedCount,
        'deleted_pageviews' => $deletedPageviews,
        'skipped_online' => $onlineSkipped,
        'message' => $message
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[VISITOR BULK DELETE] ' . $error->getMessage());

    visitorBulkJson([
        'success' => false,
        'message' => 'Não foi possível eliminar os visitantes. Nenhum registo foi confirmado como eliminado.'
    ], 500);
}