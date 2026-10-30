<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Upload de Media (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];

// Limite efectivo do upload: respeita a configuração da aplicação e os limites do PHP.
$iniBytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($unit) {
        case 'g': $number *= 1024; // fall through
        case 'm': $number *= 1024; // fall through
        case 'k': $number *= 1024;
    }
    return (int) $number;
};
$uploadLimits = [
    defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 0,
    $iniBytes((string) ini_get('upload_max_filesize')),
    $iniBytes((string) ini_get('post_max_size')),
];
$uploadLimits = array_values(array_filter($uploadLimits, static fn (int $bytes): bool => $bytes > 0));
$maxUploadSize = $uploadLimits ? min($uploadLimits) : 5 * 1024 * 1024;


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
if (!$file) {
    $jsonError(['Não foi recebido nenhum ficheiro. Selecciona uma imagem.']);
}
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $uploadError = (int) $file['error'];
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'A imagem excede o limite upload_max_filesize configurado no PHP.',
        UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL => 'O upload ficou incompleto. Tenta novamente.',
        UPLOAD_ERR_NO_FILE => 'Selecciona uma imagem para carregar.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor não tem uma pasta temporária de upload configurada.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu escrever o ficheiro no disco.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload.',
    ];
    $jsonError([$messages[$uploadError] ?? 'Falha desconhecida ao receber o ficheiro.'], 422);
}

if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    $jsonError(['O ficheiro recebido não é um upload válido.']);
}
if ((int) ($file['size'] ?? 0) > $maxUploadSize) {
    $jsonError(['Ficheiro excede o tamanho máximo de ' . number_format($maxUploadSize / 1024 / 1024, 1, ',', '') . ' MB.']);
}

$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
if ($finfo) finfo_close($finfo);
$imageInfo = @getimagesize($file['tmp_name']);
if (!is_string($mime) || !array_key_exists($mime, $allowedTypes) || !$imageInfo || !in_array($imageInfo['mime'] ?? '', array_keys($allowedTypes), true)) {
    $jsonError(['Formato de imagem não permitido ou ficheiro inválido. Usa JPEG, PNG ou WebP.']);
}

// ── Upload ────────────────────────────────────────────────────
$ext      = $allowedTypes[$mime];
$fileName = 'media_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = ROOT_PATH . '/assets/img/projects/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $jsonError(['Falha ao guardar o ficheiro. Verifica as permissões.'], 500);
}

// ── Inserir na base de dados ──────────────────────────────────
$caption = trim($_POST['caption_media'] ?? '');
if (mb_strlen($caption) > 255) {
    $jsonError(['A legenda não pode exceder 255 caracteres.']);
}

try {
    $db->prepare("
        INSERT INTO _projects_media (id_project, url_media, caption_media)
        VALUES (?, ?, ?)
    ")->execute([$projectId, $fileName, $caption]);
    $newId = (int) $db->lastInsertId();
} catch (Throwable $e) {
    if (isset($dest) && is_file($dest)) @unlink($dest);
    error_log('[MEDIA UPLOAD] ' . $e->getMessage());
    $jsonError(['Erro ao guardar na base de dados.'], 500);
}

// A falha do registo de auditoria não deve invalidar a imagem já guardada.
try {
    logAudit(
        (int) ($_SESSION['admin_id'] ?? 0),
        null,
        'project_media.create',
        '_projects_media',
        $newId,
        null,
        ['id_project' => $projectId, 'url' => $fileName]
    );
} catch (Throwable $e) {
    error_log('[MEDIA UPLOAD AUDIT] ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'Imagem adicionada com sucesso.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit;