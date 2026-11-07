<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Bloquear Visitante (AJAX)
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

function visitorStatusJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    visitorStatusJson(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    visitorStatusJson(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    visitorStatusJson(['success' => false, 'message' => 'ID de visitante inválido.'], 400);
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT id_visitor, status_visitor
        FROM _visitor
        WHERE id_visitor = ?
        FOR UPDATE
    ");
    $stmt->execute([$id]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visitor) {
        $db->rollBack();
        visitorStatusJson(['success' => false, 'message' => 'Visitante não encontrado.'], 404);
    }

    if ($visitor['status_visitor'] === 'blocked') {
        $db->commit();
        visitorStatusJson([
            'success' => true,
            'message' => 'O visitante já se encontra bloqueado.',
            'status_visitor' => 'blocked'
        ]);
    }

    $update = $db->prepare("UPDATE _visitor
            SET status_visitor = 'blocked',
                is_online = 0,
                block_type = 'permanent',
                block_reason = 'other',
                blocked_by = ?,
                blocked_at = NOW()
            WHERE id_visitor = ?");
    $update->execute([(int)$_SESSION['admin_id'], $id]);

    $db->commit();

    try {
        logAudit(
            (int)$_SESSION['admin_id'],
            null,
            'visitor.block',
            '_visitor',
            (int)$id,
            ['status' => $visitor['status_visitor']],
            ['status' => 'blocked']
        );
    } catch (Throwable $auditError) {
        error_log('[VISITOR STATUS AUDIT] ' . $auditError->getMessage());
    }

    visitorStatusJson([
        'success' => true,
        'message' => 'Visitante bloqueado com sucesso.',
        'status_visitor' => 'blocked'
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[VISITOR STATUS] ' . $error->getMessage());
    visitorStatusJson([
        'success' => false,
        'message' => 'Não foi possível actualizar o estado do visitante.'
    ], 500);
}