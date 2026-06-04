<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Upload de Media (AJAX)
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
$projectId = (int)($_POST['id_project'] ?? 0);
if ($projectId <= 0) {
    $jsonError(['ID de projecto inválido.'], 400);
}

// Verificar se o projecto existe
$stmt = $db->prepare("SELECT id_project FROM _projects WHERE id_project = ?");
$stmt->execute([$projectId]);
if (!$stmt->fetch()) {
    $jsonError(['Projecto não encontrado.'], 404);
}

// ── Validar ficheiro ──────────────────────────────────────────
$file = $_FILES['media'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $jsonError(['Seleciona um ficheiro para upload.']);
}

$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($mime, $allowedTypes)) {
    $jsonError(['Formato não permitido. Usa JPEG, PNG ou WebP.']);
}
if ($file['size'] > MAX_UPLOAD_SIZE) {
    $jsonError(['Ficheiro excede o tamanho máximo de ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.']);
}

// ── Upload ────────────────────────────────────────────────────
$ext      = $allowedTypes[$mime];
$fileName = 'media_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = ROOT_PATH . '/assets/img/projects/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $jsonError(['Falha ao guardar o ficheiro. Verifica as permissões.'], 500);
}

// ── Inserir na BD ─────────────────────────────────────────────
$caption = trim($_POST['caption_media'] ?? '');

try {
    $db->prepare("
        INSERT INTO _projects_media (id_project, url_media, caption_media)
        VALUES (?, ?, ?)
    ")->execute([$projectId, $fileName, $caption]);

    $newId = $db->lastInsertId();

    logAudit(
        (int)$_SESSION['admin_id'],
        null,
        'project_media.create',
        '_projects_media',
        (int)$newId,
        null,
        ['id_project' => $projectId, 'url' => $fileName]
    );

    echo json_encode(['success' => true, 'message' => 'Imagem adicionada com sucesso.']);
    exit;

} catch (PDOException $e) {
    // Remover ficheiro se a inserção falhar
    if (file_exists($dest)) unlink($dest);
    error_log('[MEDIA UPLOAD] ' . $e->getMessage());
    $jsonError(['Erro ao guardar na base de dados.'], 500);
}