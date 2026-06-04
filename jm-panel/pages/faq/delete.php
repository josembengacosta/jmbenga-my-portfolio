<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar FAQ (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── ID da FAQ ─────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de FAQ inválido.']);
    exit;
}

// Verificar se a FAQ existe
$stmt = $db->prepare("SELECT * FROM _faq WHERE id_faq = ?");
$stmt->execute([$id]);
$faq = $stmt->fetch();

if (!$faq) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'FAQ não encontrada.']);
    exit;
}

// ── Eliminar registo ──────────────────────────────────────────
try {
    $db->prepare("DELETE FROM _faq WHERE id_faq = ?")->execute([$id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'faq.delete',
        '_faq',
        $id,
        ['question' => $faq['question']],
        null
    );

    echo json_encode(['success' => true, 'message' => 'FAQ eliminada com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[DELETE FAQ] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar a FAQ.']);
    exit;
}