<?php
// JMbenga Portfolio — Editar FAQ (AJAX)
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$db = $GLOBALS['pdo'];

function faqJsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function faqPostString(string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : '';
}

function faqAuditSafely(...$args): void {
    try {
        logAudit(...$args);
    } catch (Throwable $auditError) {
        error_log('[FAQ AUDIT] ' . $auditError->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    faqJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !validateAdminCsrf($csrfToken)) {
    faqJsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
}

$idRaw = $_POST['id_faq'] ?? '';
$id = (is_string($idRaw) || is_int($idRaw)) ? filter_var((string)$idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($id === false) faqJsonResponse(['success' => false, 'message' => 'ID de FAQ inválido.'], 400);

$question = faqPostString('question');
$answer = faqPostString('answer');
$category = faqPostString('category_faq', 'Geral');
$orderRaw = $_POST['display_order'] ?? '0';
$orderValue = is_string($orderRaw) || is_int($orderRaw) ? filter_var((string)$orderRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) : false;
$statusRaw = $_POST['status_faq'] ?? null;
$status = $statusRaw === 'visible' ? 'visible' : 'hidden';

$errors = [];
if (mb_strlen($question) < 5) $errors[] = 'A pergunta deve ter pelo menos 5 caracteres.';
if (mb_strlen($question) > 500) $errors[] = 'A pergunta não pode ter mais de 500 caracteres.';
if (mb_strlen($answer) < 10) $errors[] = 'A resposta deve ter pelo menos 10 caracteres.';
if (mb_strlen($answer) > 5000) $errors[] = 'A resposta não pode ter mais de 5000 caracteres.';
if (mb_strlen($category) < 2 || mb_strlen($category) > 100) $errors[] = 'A categoria deve ter entre 2 e 100 caracteres.';
if ($orderValue === false) $errors[] = 'A ordem de exibição deve ser um número inteiro igual ou superior a zero.';
if ($statusRaw !== null && $statusRaw !== 'visible') $errors[] = 'Estado da FAQ inválido.';
if ($errors) faqJsonResponse(['success' => false, 'errors' => $errors], 422);
$displayOrder = (int)$orderValue;

try {
    $stmt = $db->prepare('SELECT * FROM _faq WHERE id_faq = ?');
    $stmt->execute([(int)$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) faqJsonResponse(['success' => false, 'message' => 'FAQ não encontrada.'], 404);

    $stmt = $db->prepare('SELECT COUNT(*) FROM _faq WHERE question = ? AND id_faq <> ?');
    $stmt->execute([$question, (int)$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        faqJsonResponse(['success' => false, 'errors' => ['Já existe uma FAQ com esta pergunta. Escolhe uma pergunta diferente.']], 422);
    }

    $stmt = $db->prepare('UPDATE _faq SET question = ?, answer = ?, category_faq = ?, display_order = ?, status_faq = ? WHERE id_faq = ?');
    $stmt->execute([$question, $answer, $category, $displayOrder, $status, (int)$id]);

    faqAuditSafely((int)$_SESSION['admin_id'], null, 'faq.update', '_faq', (int)$id, $old, [
        'question' => $question,
        'category' => $category,
        'display_order' => $displayOrder,
        'status' => $status
    ]);

    faqJsonResponse(['success' => true, 'message' => 'FAQ actualizada com sucesso.']);
} catch (PDOException $e) {
    error_log('[EDIT FAQ] ' . $e->getMessage());
    faqJsonResponse(['success' => false, 'message' => 'Erro interno ao actualizar a FAQ. Tenta novamente.'], 500);
}