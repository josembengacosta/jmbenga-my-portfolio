<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Eliminar Depoimento (AJAX)
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

// ── ID do depoimento ──────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de depoimento inválido.']);
    exit;
}

// Verificar se o depoimento existe
$stmt = $db->prepare("SELECT * FROM _testimonials WHERE id_testimonial = ?");
$stmt->execute([$id]);
$testimonial = $stmt->fetch();

if (!$testimonial) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Depoimento não encontrado.']);
    exit;
}

// ── Apagar foto associada (se existir) ────────────────────────
if (!empty($testimonial['photo_testimonial'])) {
    $photoPath = ROOT_PATH . '/assets/img/testimonials/' . $testimonial['photo_testimonial'];
    if (file_exists($photoPath)) {
        unlink($photoPath);
    }
}

// ── Eliminar registo ──────────────────────────────────────────
try {
    $db->prepare("DELETE FROM _testimonials WHERE id_testimonial = ?")->execute([$id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'testimonial.delete',
        '_testimonials',
        $id,
        ['name' => $testimonial['name_testimonial']],
        null
    );

    echo json_encode(['success' => true, 'message' => 'Depoimento eliminado com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[DELETE TESTIMONIAL] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar o depoimento.']);
    exit;
}