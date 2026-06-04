<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Editar Depoimento (AJAX)
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

// ── ID do depoimento ──────────────────────────────────────────
$id = (int)($_POST['id_testimonial'] ?? 0);
if ($id <= 0) {
    $jsonError(['ID de depoimento inválido.'], 400);
}

// Verificar se o depoimento existe
$stmt = $db->prepare("SELECT * FROM _testimonials WHERE id_testimonial = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();
if (!$old) {
    $jsonError(['Depoimento não encontrado.'], 404);
}

// ── Capturar e sanitizar campos ───────────────────────────────
$name      = trim($_POST['name_testimonial']    ?? '');
$role      = trim($_POST['role_testimonial']    ?? '');
$company   = trim($_POST['company_testimonial'] ?? '');
$body      = trim($_POST['body_testimonial']    ?? '');
$rating    = (int)($_POST['rating_testimonial'] ?? 5);
$order     = (int)($_POST['display_order']      ?? 0);
$status    = ($_POST['status_testimonial'] ?? 'visible') === 'visible' ? 'visible' : 'hidden';

// ── Validações ─────────────────────────────────────────────────
$errors = [];

if (mb_strlen($name) < 2) {
    $errors[] = 'O nome deve ter pelo menos 2 caracteres.';
}
if (mb_strlen($body) < 10) {
    $errors[] = 'O depoimento deve ter pelo menos 10 caracteres.';
}
if ($rating < 1 || $rating > 5) {
    $errors[] = 'A avaliação deve estar entre 1 e 5 estrelas.';
}

// ── Upload opcional de nova foto ───────────────────────────────
$newPhotoName = $old['photo_testimonial']; // mantém a atual por padrão
$photoFile    = $_FILES['photo_testimonial'] ?? null;

if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $photoFile['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowedTypes)) {
        $errors[] = 'Formato de foto não permitido. Usa JPEG, PNG ou WebP.';
    } elseif ($photoFile['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'A foto excede o tamanho máximo de ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
    } else {
        $ext          = $allowedTypes[$mime];
        $newPhotoName = 'testimonial_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest         = ROOT_PATH . '/assets/img/testimonials/' . $newPhotoName;

        if (!move_uploaded_file($photoFile['tmp_name'], $dest)) {
            $errors[] = 'Falha ao guardar a nova foto.';
        } else {
            // Apagar foto antiga (se existir)
            $oldPath = ROOT_PATH . '/assets/img/testimonials/' . $old['photo_testimonial'];
            if ($old['photo_testimonial'] && file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
    }
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Actualizar na base de dados ───────────────────────────────
try {
    $stmt = $db->prepare("
        UPDATE _testimonials SET
            name_testimonial    = ?,
            role_testimonial    = ?,
            company_testimonial = ?,
            body_testimonial    = ?,
            photo_testimonial   = ?,
            rating_testimonial  = ?,
            status_testimonial  = ?,
            display_order       = ?
        WHERE id_testimonial    = ?
    ");
    $stmt->execute([$name, $role, $company, $body, $newPhotoName, $rating, $status, $order, $id]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'testimonial.update',
        '_testimonials',
        $id,
        $old,
        ['name' => $name, 'rating' => $rating, 'status' => $status]
    );

    echo json_encode(['success' => true, 'message' => 'Depoimento actualizado com sucesso.']);
    exit;

} catch (PDOException $e) {
    // Remover nova foto se houve erro
    if ($newPhotoName !== $old['photo_testimonial']) {
        $newPath = ROOT_PATH . '/assets/img/testimonials/' . $newPhotoName;
        if (file_exists($newPath)) unlink($newPath);
    }
    error_log('[EDIT TESTIMONIAL] ' . $e->getMessage());
    $jsonError(['Erro interno ao actualizar o depoimento. Tenta novamente.'], 500);
}