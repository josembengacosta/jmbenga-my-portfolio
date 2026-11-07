<?php
// JMbenga Portfolio — Processador de edição de depoimentos (AJAX)
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

$id = filter_var($_POST['id_testimonial'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    testimonialJsonResponse(['success' => false, 'errors' => ['ID de depoimento inválido.']], 400);
}

try {
    $stmt = $db->prepare('SELECT * FROM _testimonials WHERE id_testimonial = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('[EDIT TESTIMONIAL READ] ' . $error->getMessage());
    testimonialJsonResponse(['success' => false, 'message' => 'Não foi possível carregar o depoimento.'], 500);
}
if (!$old) {
    testimonialJsonResponse(['success' => false, 'errors' => ['Depoimento não encontrado.']], 404);
}

$name = testimonialPostString('name_testimonial');
$role = testimonialPostString('role_testimonial');
$company = testimonialPostString('company_testimonial');
$body = testimonialPostString('body_testimonial');
$ratingRaw = $_POST['rating_testimonial'] ?? '5';
$orderRaw = $_POST['display_order'] ?? '0';
$status = (($_POST['status_testimonial'] ?? '') === 'visible') ? 'visible' : 'hidden';
$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
if (mb_strlen($role) > 100) $errors[] = 'O cargo não pode ter mais de 100 caracteres.';
if (mb_strlen($company) > 100) $errors[] = 'A empresa não pode ter mais de 100 caracteres.';
if (mb_strlen($body) < 10 || mb_strlen($body) > 2000) $errors[] = 'O depoimento deve ter entre 10 e 2000 caracteres.';
$rating = filter_var($ratingRaw, FILTER_VALIDATE_INT);
if ($rating === false || $rating < 1 || $rating > 5) $errors[] = 'A avaliação deve estar entre 1 e 5 estrelas.';
$order = filter_var($orderRaw, FILTER_VALIDATE_INT);
if ($order === false || $order < 0 || $order > 1000000) $errors[] = 'A ordem deve ser um número inteiro entre 0 e 1000000.';

$upload = $_FILES['photo_testimonial'] ?? null;
$photoExtension = null;
if (is_array($upload)) {
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'O upload da nova foto não foi concluído. Verifica o tamanho do ficheiro e tenta novamente.';
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
            if (!is_string($mime) || !isset($allowedTypes[$mime])) $errors[] = 'Formato de foto não permitido. Usa JPEG, PNG ou WebP.';
            else $photoExtension = $allowedTypes[$mime];
        }
    }
}

if ($errors) testimonialJsonResponse(['success' => false, 'errors' => $errors], 422);

$newPhotoName = $old['photo_testimonial'] ?: null;
$newPhotoPath = null;
try {
    if ($photoExtension !== null) {
        $uploadDirectory = ROOT_PATH . '/assets/img/testimonials';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
        }
        $newPhotoName = 'testimonial_' . bin2hex(random_bytes(16)) . '.' . $photoExtension;
        $newPhotoPath = $uploadDirectory . DIRECTORY_SEPARATOR . $newPhotoName;
        if (!move_uploaded_file($upload['tmp_name'], $newPhotoPath)) {
            throw new RuntimeException('Falha ao guardar a nova foto.');
        }
    }

    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE _testimonials SET
        name_testimonial = ?, role_testimonial = ?, company_testimonial = ?, body_testimonial = ?,
        photo_testimonial = ?, rating_testimonial = ?, status_testimonial = ?, display_order = ?
        WHERE id_testimonial = ?');
    $stmt->execute([$name, $role, $company,
        $body, $newPhotoName, $rating, $status, $order, $id]);
    $db->commit();

    // Só apagar a foto anterior depois de a alteração na base de dados estar confirmada.
    if ($newPhotoPath && !empty($old['photo_testimonial'])) {
        $oldPhotoPath = testimonialStoredPhotoPath((string)$old['photo_testimonial']);
        if ($oldPhotoPath && !@unlink($oldPhotoPath)) {
            error_log('[EDIT TESTIMONIAL] Não foi possível remover a foto anterior: ' . $oldPhotoPath);
        }
    }

    testimonialAuditSafely((int)$_SESSION['admin_id'], null, 'testimonial.update', '_testimonials',
        (int)$id, $old, ['name' => $name, 'rating' => $rating, 'status' => $status, 'photo' => $newPhotoName]);

    testimonialJsonResponse(['success' => true, 'message' => 'Depoimento actualizado com sucesso.']);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    if ($newPhotoPath && is_file($newPhotoPath)) @unlink($newPhotoPath);
    error_log('[EDIT TESTIMONIAL] ' . $error->getMessage());
    testimonialJsonResponse(['success' => false, 'message' => 'Erro interno ao actualizar o depoimento. Tenta novamente.'], 500);
}