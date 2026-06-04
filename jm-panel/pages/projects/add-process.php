<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Adicionar Projecto (AJAX)
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

// ── Unicidade do slug ─────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM _projects WHERE slug_project = ?");
$stmt->execute([$slug]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = 'Este slug já está em uso. Escolhe um diferente.';
}

// ── Validação da imagem de capa (obrigatória) ─────────────────
$coverFile = $_FILES['cover_project'] ?? null;
if (!$coverFile || $coverFile['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'A imagem de capa é obrigatória.';
} else {
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $coverFile['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowedTypes)) {
        $errors[] = 'Formato de imagem não permitido. Usa JPEG, PNG ou WebP.';
    }
    if ($coverFile['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'A imagem excede o tamanho máximo de ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
    }
}

// ── Validação do tech_stack (JSON válido) ────────────────────
$techArray = json_decode($techStack, true);
if (!is_array($techArray)) {
    $errors[] = 'Formato inválido para o Tech Stack.';
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Upload da imagem ──────────────────────────────────────────
$ext       = $allowedTypes[$mime];
$coverName = 'project_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest      = ROOT_PATH . '/assets/img/projects/' . $coverName;

if (!move_uploaded_file($coverFile['tmp_name'], $dest)) {
    $errors[] = 'Falha ao guardar a imagem. Verifica as permissões da pasta.';
    $jsonError($errors);
}

// ── Inserir na base de dados ──────────────────────────────────
try {
    $stmt = $db->prepare("
        INSERT INTO _projects
        (title_project, slug_project, summary_project, body_project,
         category_project, tech_stack, cover_project,
         url_demo, url_github, url_live,
         is_featured, display_order, status_project)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title, $slug, $summary, $body,
        $category, $techStack, $coverName,
        $urlDemo, $urlGithub, $urlLive,
        $isFeatured, $displayOrder, $status,
    ]);

    $newId = $db->lastInsertId();

    logAudit(
        (int)$_SESSION['admin_id'], null,
        'project.create', '_projects', (int)$newId,
        null,
        ['title' => $title, 'slug' => $slug, 'category' => $category, 'status' => $status]
    );

    echo json_encode(['success' => true, 'message' => 'Projecto criado com sucesso.']);
    exit;

} catch (PDOException $e) {
    // Remover imagem em caso de erro
    if (file_exists($dest)) unlink($dest);
    error_log('[ADD PROJECT] ' . $e->getMessage());
    $jsonError(['Erro interno ao guardar o projecto. Tenta novamente.'], 500);
}