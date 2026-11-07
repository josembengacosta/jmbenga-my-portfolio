<?php
// JMbenga Portfolio — Processador de criação de depoimentos (AJAX)
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

function testimonialJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function testimonialPostString(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function testimonialStoredPhotoPath(?string $filename): ?string
{
    if ($filename === null || $filename === '' || basename($filename) !== $filename ||
        !preg_match('/^[A-Za-z0-9._-]+$/', $filename) || $filename === '.' || $filename === '..') {
        return null;
    }

    $directory = ROOT_PATH . '/assets/img/testimonials';
    $realDirectory = realpath($directory);
    $candidate = $directory . DIRECTORY_SEPARATOR . $filename;
    $realFile = realpath($candidate);

    if ($realDirectory === false || $realFile === false || !is_file($realFile) || dirname($realFile) !== $realDirectory) {
        return null;
    }

    return $realFile;
}

function testimonialAuditSafely(...$args): void
{
    try {
        logAudit(...$args);
    } catch (Throwable $auditError) {
        error_log('[TESTIMONIAL AUDIT] ' . $auditError->getMessage());
    }
}

$db = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    testimonialJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !validateAdminCsrf($csrf)) {
    testimonialJsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
}

$name = testimonialPostString('name_testimonial');
$role = testimonialPostString('role_testimonial');
$company = testimonialPostString('company_testimonial');
$body = testimonialPostString('body_testimonial');
$ratingRaw = $_POST['rating_testimonial'] ?? '5';
$orderRaw = $_POST['display_order'] ?? '0';
$status = (($_POST['status_testimonial'] ?? '') === 'visible') ? 'visible' : 'hidden';
$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
}
if (mb_strlen($role) > 100) {
    $errors[] = 'O cargo não pode ter mais de 100 caracteres.';
}
if (mb_strlen($company) > 100) {
    $errors[] = 'A empresa não pode ter mais de 100 caracteres.';
}
if (mb_strlen($body) < 10 || mb_strlen($body) > 2000) {
    $errors[] = 'O depoimento deve ter entre 10 e 2000 caracteres.';
}
$rating = filter_var($ratingRaw, FILTER_VALIDATE_INT);
if ($rating === false || $rating < 1 || $rating > 5) {
    $errors[] = 'A avaliação deve estar entre 1 e 5 estrelas.';
}
$order = filter_var($orderRaw, FILTER_VALIDATE_INT);
if ($order === false || $order < 0 || $order > 1000000) {
    $errors[] = 'A ordem deve ser um número inteiro entre 0 e 1000000.';
}

$upload = $_FILES['photo_testimonial'] ?? null;
$photoExtension = null;
if (is_array($upload)) {
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        if ($uploadError !== UPLOAD_ERR_OK) {
            $uploadMessages = [
                UPLOAD_ERR_INI_SIZE => 'A foto excede o limite de upload configurado no servidor.',
                UPLOAD_ERR_FORM_SIZE => 'A foto excede o tamanho máximo permitido.',
                UPLOAD_ERR_PARTIAL => 'A foto foi carregada apenas parcialmente. Tenta novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária de uploads não está disponível.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a foto.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload da foto.'
            ];
            $errors[] = $uploadMessages[$uploadError] ?? 'Não foi possível receber a foto.';
        } elseif (!isset($upload['tmp_name']) || !is_string($upload['tmp_name']) || $upload['tmp_name'] === '' || !is_uploaded_file($upload['tmp_name'])) {
            $errors[] = 'O ficheiro enviado não é um upload válido.';
        } elseif ((int)($upload['size'] ?? 0) <= 0 || (int)$upload['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'A foto está vazia ou excede o limite de ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
        } else {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!function_exists('finfo_open')) {
                $mime = false;
                $finfo = false;
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : false;
            }
            if ($finfo) finfo_close($finfo);
            if (!is_string($mime) || !isset($allowedTypes[$mime])) {
                $errors[] = 'Formato de foto não permitido. Usa JPEG, PNG ou WebP.';
            } else {
                $photoExtension = $allowedTypes[$mime];
            }
        }
    }
}

if ($errors) {
    testimonialJsonResponse(['success' => false, 'errors' => $errors], 422);
}

$photoName = null;
$photoPath = null;
try {
    if ($photoExtension !== null) {
        $uploadDirectory = ROOT_PATH . '/assets/img/testimonials';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
        }
        $photoName = 'testimonial_' . bin2hex(random_bytes(16)) . '.' . $photoExtension;
        $photoPath = $uploadDirectory . DIRECTORY_SEPARATOR . $photoName;
        if (!move_uploaded_file($upload['tmp_name'], $photoPath)) {
            throw new RuntimeException('Falha ao guardar a foto. Verifica as permissões da pasta.');
        }
    }

    $stmt = $db->prepare('INSERT INTO _testimonials
        (name_testimonial, role_testimonial, company_testimonial, body_testimonial,
         photo_testimonial, rating_testimonial, status_testimonial, display_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $role, $company,
        $body, $photoName, $rating, $status, $order]);
    $newId = (int)$db->lastInsertId();

    testimonialAuditSafely((int)$_SESSION['admin_id'], null, 'testimonial.create', '_testimonials',
        $newId, null, ['name' => $name, 'rating' => $rating, 'status' => $status]);

    testimonialJsonResponse(['success' => true, 'message' => 'Depoimento criado com sucesso.', 'id' => $newId], 201);
} catch (Throwable $error) {
    if ($photoPath && is_file($photoPath)) {
        @unlink($photoPath);
    }
    error_log('[ADD TESTIMONIAL] ' . $error->getMessage());
    testimonialJsonResponse(['success' => false, 'message' => 'Erro interno ao guardar o depoimento. Tenta novamente.'], 500);
}