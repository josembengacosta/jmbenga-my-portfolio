<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Editar FAQ (AJAX)
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
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── Função auxiliar para retornar erros ───────────────────────
$jsonError = function (array $errors, int $status = 422) {
    http_response_code($status);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
};

// ── ID da FAQ ─────────────────────────────────────────────────
$id = (int)($_POST['id_faq'] ?? 0);
if ($id <= 0) {
    $jsonError(['ID de FAQ inválido.'], 400);
}

// Verificar se a FAQ existe
$stmt = $db->prepare("SELECT * FROM _faq WHERE id_faq = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();
if (!$old) {
    $jsonError(['FAQ não encontrada.'], 404);
}

// ── Capturar e sanitizar campos ───────────────────────────────
$question     = trim($_POST['question']      ?? '');
$answer       = trim($_POST['answer']        ?? '');
$category     = trim($_POST['category_faq']  ?? 'Geral');
$displayOrder = (int)($_POST['display_order'] ?? 0);
$status       = ($_POST['status_faq'] ?? 'visible') === 'visible' ? 'visible' : 'hidden';

// ── Validações ─────────────────────────────────────────────────
$errors = [];

if (mb_strlen($question) < 5) {
    $errors[] = 'A pergunta deve ter pelo menos 5 caracteres.';
}
if (mb_strlen($question) > 500) {
    $errors[] = 'A pergunta não pode ter mais de 500 caracteres.';
}
if (mb_strlen($answer) < 10) {
    $errors[] = 'A resposta deve ter pelo menos 10 caracteres.';
}
if (mb_strlen($category) < 2) {
    $errors[] = 'A categoria deve ter pelo menos 2 caracteres.';
}

// ── Verificar duplicados (mesma pergunta, excluindo a própria) ──
$stmt = $db->prepare("SELECT COUNT(*) FROM _faq WHERE question = ? AND id_faq != ?");
$stmt->execute([$question, $id]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = 'Já existe uma FAQ com esta pergunta. Escolhe uma pergunta diferente.';
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Actualizar na base de dados ───────────────────────────────
try {
    $stmt = $db->prepare("
        UPDATE _faq SET
            question      = ?,
            answer        = ?,
            category_faq  = ?,
            display_order = ?,
            status_faq    = ?
        WHERE id_faq      = ?
    ");
    $stmt->execute([$question, $answer, $category, $displayOrder, $status, $id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'faq.update',
        '_faq',
        $id,
        $old,
        ['question' => $question, 'category' => $category, 'status' => $status]
    );

    echo json_encode(['success' => true, 'message' => 'FAQ actualizada com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[EDIT FAQ] ' . $e->getMessage());
    $jsonError(['Erro interno ao actualizar a FAQ. Tenta novamente.'], 500);
}