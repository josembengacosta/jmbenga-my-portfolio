<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Editar Projecto (AJAX)
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

// ── ID do projecto ────────────────────────────────────────────
$id = (int)($_POST['id_project'] ?? 0);
if ($id <= 0) {
    $jsonError(['ID de projecto inválido.'], 400);
}

// Verificar se o projecto existe
$stmt = $db->prepare("SELECT * FROM _projects WHERE id_project = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();
if (!$old) {
    $jsonError(['Projecto não encontrado.'], 404);
}

// ── Capturar e sanitizar campos ───────────────────────────────
$title        = trim($_POST['title_project']    ?? '');
$slug         = trim($_POST['slug_project']     ?? '');
$category     = $_POST['category_project']      ?? 'web';
$status       = $_POST['status_project']        ?? 'draft';
$summary      = trim($_POST['summary_project']  ?? '');
$body         = trim($_POST['body_project']     ?? '');
$techStack    = $_POST['tech_stack']            ?? '[]';
$urlDemo      = trim($_POST['url_demo']         ?? '');
$urlGithub    = trim($_POST['url_github']       ?? '');
$urlLive      = trim($_POST['url_live']         ?? '');
$isFeatured   = isset($_POST['is_featured']) ? 1 : 0;
$displayOrder = (int)($_POST['display_order']   ?? 0);

// ── Validações básicas ────────────────────────────────────────
$errors = [];

if (mb_strlen($title) < 2) {
    $errors[] = 'O título deve ter pelo menos 2 caracteres.';
}
if (mb_strlen($slug) < 2) {
    $errors[] = 'O slug deve ter pelo menos 2 caracteres.';
}
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    $errors[] = 'O slug só pode conter letras minúsculas, números e hífens.';
}
if (!in_array($category, ['web','mobile','api','desktop','design','other'])) {
    $errors[] = 'Categoria inválida.';
}
if (!in_array($status, ['published','draft','archived'])) {
    $errors[] = 'Estado inválido.';
}

// ── Unicidade do slug (excluindo o próprio registo) ───────────
$stmt = $db->prepare("SELECT COUNT(*) FROM _projects WHERE slug_project = ? AND id_project != ?");
$stmt->execute([$slug, $id]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = 'Este slug já está em uso por outro projecto. Escolhe um diferente.';
}

// ── Validação do tech_stack (JSON válido) ────────────────────
$techArray = json_decode($techStack, true);
if (!is_array($techArray)) {
    $errors[] = 'Formato inválido para o Tech Stack.';
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Tratar upload de nova capa (se enviada) ──────────────────
$newCoverName = $old['cover_project']; // mantém a actual por padrão
$coverFile    = $_FILES['cover_project'] ?? null;

if ($coverFile && $coverFile['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $coverFile['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowedTypes)) {
        $errors[] = 'Formato de imagem não permitido. Usa JPEG, PNG ou WebP.';
    } elseif ($coverFile['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'A imagem excede o tamanho máximo de ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
    } else {
        $ext         = $allowedTypes[$mime];
        $newCoverName = 'project_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest         = ROOT_PATH . '/assets/img/projects/' . $newCoverName;

        if (!move_uploaded_file($coverFile['tmp_name'], $dest)) {
            $errors[] = 'Falha ao guardar a nova imagem. Verifica as permissões da pasta.';
        } else {
            // Apagar imagem antiga (se existir)
            $oldPath = ROOT_PATH . '/assets/img/projects/' . $old['cover_project'];
            if ($old['cover_project'] && file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
    }
}

// Se houve erro no upload, devolver
if (!empty($errors)) {
    $jsonError($errors);
}

// ── Actualizar na base de dados ───────────────────────────────
try {
    $stmt = $db->prepare("
        UPDATE _projects SET
            title_project   = ?,
            slug_project    = ?,
            summary_project = ?,
            body_project    = ?,
            category_project = ?,
            tech_stack      = ?,
            cover_project   = ?,
            url_demo        = ?,
            url_github      = ?,
            url_live        = ?,
            is_featured     = ?,
            display_order   = ?,
            status_project  = ?
        WHERE id_project   = ?
    ");
    $stmt->execute([
        $title,
        $slug,
        $summary,
        $body,
        $category,
        $techStack,
        $newCoverName,
        $urlDemo,
        $urlGithub,
        $urlLive,
        $isFeatured,
        $displayOrder,
        $status,
        $id,
    ]);

    // Auditoria
    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'project.update',
        '_projects',
        $id,
        $old,
        ['title' => $title, 'slug' => $slug, 'category' => $category, 'status' => $status]
    );

    echo json_encode(['success' => true, 'message' => 'Projecto actualizado com sucesso.']);
    exit;

} catch (PDOException $e) {
    // Remover nova imagem se houve erro (rollback manual)
    if ($newCoverName !== $old['cover_project']) {
        $newPath = ROOT_PATH . '/assets/img/projects/' . $newCoverName;
        if (file_exists($newPath)) unlink($newPath);
    }
    error_log('[EDIT PROJECT] ' . $e->getMessage());
    $jsonError(['Erro interno ao actualizar o projecto. Tenta novamente.'], 500);
}