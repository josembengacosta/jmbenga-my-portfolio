<?php
declare(strict_types=1);
// JMbenga Portfolio — Processador do Perfil do Administrador
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$db = $GLOBALS['pdo'];
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) jsonError('Sessão administrativa inválida. Inicia sessão novamente.', 401);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// Distinguir payload que excede post_max_size de token CSRF ausente.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMax = iniBytes((string) ini_get('post_max_size'));
if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax && empty($_POST)) {
    jsonError('O pedido excede o limite configurado em post_max_size no PHP.', 413);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateAdminCsrf($csrfToken)) {
    jsonError('Token de segurança inválido. Actualiza a página e tenta novamente.', 403);
}

$action = trim((string) ($_POST['action'] ?? 'update_profile'));
try {
    $roleStmt = $db->prepare('SELECT role FROM _employees WHERE id_employees = ? LIMIT 1');
    $roleStmt->execute([$adminId]);
    $currentRole = (string) ($roleStmt->fetchColumn() ?: '');

    switch ($action) {
        case 'update_profile':
            handleUpdateProfile($db, $adminId);
        case 'toggle_lockscreen':
            handleToggleLockscreen($db, $adminId);
        case 'change_pin':
            handleChangePin($db, $adminId);
        case 'reset_attempts':
            handleResetAttempts($db, $adminId, $currentRole);
        default:
            jsonError('Acção desconhecida.', 400);
    }
} catch (Throwable $e) {
    error_log('[PROFILE PROCESS] ' . $e->getMessage());
    jsonError('Erro interno ao processar o pedido. Tenta novamente.', 500);
}

function handleUpdateProfile(PDO $db, int $adminId): never
{
    $firstName  = trim((string) ($_POST['first_name'] ?? ''));
    $secondName = trim((string) ($_POST['second_name'] ?? ''));
    $email      = strtolower(trim((string) ($_POST['email_employees'] ?? '')));
    $username   = trim((string) ($_POST['user_employees'] ?? ''));
    $tel        = trim((string) ($_POST['tel_employees'] ?? ''));
    $gender     = (string) ($_POST['gender'] ?? 'M');
    $country    = strtoupper(trim((string) ($_POST['country_employees'] ?? 'AO')));
    $city       = trim((string) ($_POST['city_employees'] ?? 'Luanda'));
    $about      = trim((string) ($_POST['about_employees'] ?? ''));
    $errors = [];

    if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    if ($secondName !== '' && mb_strlen($secondName) > 100) $errors[] = 'O apelido não pode exceder 100 caracteres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors[] = 'Endereço de email inválido.';
    if (!in_array($gender, ['M', 'F'], true)) $errors[] = 'Género inválido.';
    if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) $errors[] = 'O código do país deve ter exactamente 2 letras (ex.: AO, PT).';
    if ($username !== '' && !preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) $errors[] = 'O username só pode conter letras, números, _, . e - (3 a 60 caracteres).';
    if ($tel !== '' && mb_strlen($tel) > 40) $errors[] = 'O telefone não pode exceder 40 caracteres.';
    if (mb_strlen($city) > 80) $errors[] = 'A cidade não pode exceder 80 caracteres.';
    if (mb_strlen($about) > 5000) $errors[] = 'A descrição não pode exceder 5000 caracteres.';

    $stmt = $db->prepare('SELECT COUNT(*) FROM _employees WHERE email_employees = ? AND id_employees != ?');
    $stmt->execute([$email, $adminId]);
    if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Este email já está em uso por outro administrador.';

    if ($username !== '') {
        $stmt = $db->prepare('SELECT COUNT(*) FROM _employees WHERE user_employees = ? AND id_employees != ?');
        $stmt->execute([$username, $adminId]);
        if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Este username já está em uso.';
    }
    if ($errors) jsonErrors($errors, 422);

    $photoFile = $_FILES['photo_employees'] ?? null;
    $newPhotoName = null;
    $oldPhotoName = null;
    $uploadDir = rtrim(ROOT_PATH, '/\\') . '/assets/img/profile/';

    if (is_array($photoFile) && (int) ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadError = (int) ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'A foto excede upload_max_filesize configurado no PHP.',
                UPLOAD_ERR_FORM_SIZE => 'A foto excede o tamanho permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'A foto foi recebida incompleta. Tenta novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária de uploads não está disponível.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu escrever o ficheiro de upload.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP bloqueou o upload.',
            ];
            jsonError($messages[$uploadError] ?? 'Não foi possível receber a foto.', 422);
        }

        $tmp = (string) ($photoFile['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) jsonError('O ficheiro recebido não é um upload válido.', 422);

        $maxSize = defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 2 * 1024 * 1024;
        if ($maxSize < 1) $maxSize = 2 * 1024 * 1024;
        $fileSize = (int) ($photoFile['size'] ?? 0);
        if ($fileSize <= 0) jsonError('O ficheiro da foto está vazio ou inválido.', 422);
        if ($fileSize > $maxSize) jsonError('A foto excede o tamanho máximo de ' . round($maxSize / 1024 / 1024, 1) . 'MB.' , 422);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) jsonError('Não foi possível validar o tipo da imagem no servidor.', 500);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $imageInfo = @getimagesize($tmp);
        if (!is_string($mime) || !isset($allowed[$mime]) || !is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== $mime) {
            jsonError('Formato não suportado. Usa uma imagem JPEG, PNG ou WebP válida.', 422);
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            jsonError('Não foi possível criar a pasta de fotos do perfil.', 500);
        }
        $newPhotoName = 'admin_' . $adminId . '_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $uploadDir . $newPhotoName)) {
            jsonError('Falha ao guardar a foto. Verifica as permissões da pasta.', 500);
        }
        $oldStmt = $db->prepare('SELECT photo_employees FROM _employees WHERE id_employees = ?');
        $oldStmt->execute([$adminId]);
        $oldPhotoName = $oldStmt->fetchColumn() ?: null;
    }

    try {
        $db->beginTransaction();
        $sql = 'UPDATE _employees SET first_name = ?, second_name = ?, email_employees = ?, user_employees = ?, tel_employees = ?, gender = ?, country_employees = ?, city_employees = ?, about_employees = ?';
        $params = [$firstName, $secondName !== '' ? $secondName : null, $email, $username !== '' ? $username : null, $tel !== '' ? $tel : null, $gender, $country, $city, $about !== '' ? $about : null];
        if ($newPhotoName !== null) {
            $sql .= ', photo_employees = ?';
            $params[] = $newPhotoName;
        }
        $sql .= ' WHERE id_employees = ?';
        $params[] = $adminId;
        $db->prepare($sql)->execute($params);

        logAudit($adminId, null, 'profile.update', '_employees', $adminId, null, [
            'first_name' => $firstName,
            'email' => $email,
            'country' => $country,
            'city' => $city,
            'photo_changed' => $newPhotoName !== null,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($newPhotoName !== null && is_file($uploadDir . $newPhotoName)) @unlink($uploadDir . $newPhotoName);
        error_log('[PROFILE UPDATE] ' . $e->getMessage());
        jsonError('Erro interno ao actualizar o perfil. Tenta novamente.', 500);
    }

    $_SESSION['admin_name'] = $firstName;
    $_SESSION['admin_full_name'] = trim($firstName . ' ' . $secondName);
    $_SESSION['admin_email'] = $email;
    if ($newPhotoName !== null) $_SESSION['admin_photo'] = $newPhotoName;

    // Só remover a fotografia anterior depois da confirmação da transacção.
    if ($newPhotoName !== null && is_string($oldPhotoName) && $oldPhotoName !== '' && $oldPhotoName !== $newPhotoName && basename($oldPhotoName) === $oldPhotoName) {
        $oldPath = $uploadDir . $oldPhotoName;
        if (is_file($oldPath) && !@unlink($oldPath)) error_log('[PROFILE UPDATE] Não foi possível remover a foto antiga: ' . $oldPhotoName);
    }

    jsonSuccess('Perfil actualizado com sucesso.');
}

function handleToggleLockscreen(PDO $db, int $adminId): never
{
    $raw = $_POST['enabled'] ?? '0';
    if (!is_string($raw) || !in_array($raw, ['0', '1'], true)) jsonError('Valor inválido para lockscreen.', 422);
    $enabled = (int) $raw;
    $check = $db->prepare('SELECT access_code FROM _employees_security WHERE id_employees = ? LIMIT 1');
    $check->execute([$adminId]);
    $storedPin = $check->fetchColumn();
    if ($storedPin === false) jsonError('Não existe registo de segurança para esta conta.', 409);
    $storedPin = is_string($storedPin) ? $storedPin : '';
    $pinConfigured = preg_match('/\A[0-9]{6}\z/', $storedPin) === 1 || isAdminLockscreenPinHash($storedPin);
    if ($enabled === 1 && !$pinConfigured) {
        jsonError('Define primeiro um PIN de seis dígitos antes de activar a lockscreen.', 409);
    }

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE _employees_security SET lockscreen = ? WHERE id_employees = ?')->execute([$enabled, $adminId]);
        logAudit($adminId, null, 'security.lockscreen_' . ($enabled ? 'on' : 'off'), '_employees_security', $adminId, null, ['lockscreen' => $enabled]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[LOCKSCREEN TOGGLE] ' . $e->getMessage());
        jsonError('Erro interno ao alterar o lockscreen.', 500);
    }
    jsonSuccess($enabled ? 'Lockscreen activado com sucesso.' : 'Lockscreen desactivado.');
}

function handleChangePin(PDO $db, int $adminId): never
{
    $pinInput = $_POST['new_pin'] ?? null;
    $newPin = is_string($pinInput) ? trim($pinInput) : '';
    if (!preg_match('/\A[0-9]{6}\z/', $newPin)) jsonError('O PIN deve conter exactamente 6 dígitos numéricos.', 422);
    $check = $db->prepare('SELECT access_code FROM _employees_security WHERE id_employees = ? LIMIT 1');
    $check->execute([$adminId]);
    if ($check->fetchColumn() === false) jsonError('Não existe registo de segurança para esta conta.', 409);

    try {
        $pinHash = hashAdminLockscreenPin($newPin);
        $db->beginTransaction();
        $db->prepare('UPDATE _employees_security SET access_code = ?, lockscreen = 1 WHERE id_employees = ?')->execute([$pinHash, $adminId]);
        logAudit($adminId, null, 'security.pin_changed', '_employees_security', $adminId, null, ['pin_length' => strlen($newPin)]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CHANGE PIN] ' . $e->getMessage());
        jsonError('Erro interno ao guardar o PIN.', 500);
    }
    jsonSuccess('PIN actualizado e lockscreen activado.');
}

function handleResetAttempts(PDO $db, int $adminId, string $currentRole): never
{
    if ($currentRole !== 'super_admin') jsonError('Apenas um Super Admin pode limpar as tentativas de login.', 403);
    $check = $db->prepare('SELECT login_attempts FROM _employees_security WHERE id_employees = ? LIMIT 1');
    $check->execute([$adminId]);
    if ($check->fetchColumn() === false) jsonError('Não existe registo de segurança para esta conta.', 409);

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE _employees_security SET login_attempts = 0, block_level = 0, block_until = NULL WHERE id_employees = ?')->execute([$adminId]);
        logAudit($adminId, null, 'security.reset_attempts', '_employees_security', $adminId, null, ['reset_by' => $adminId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[RESET ATTEMPTS] ' . $e->getMessage());
        jsonError('Erro interno ao limpar tentativas.', 500);
    }
    jsonSuccess('Tentativas de login limpas.');
}

function jsonSuccess(string $message, array $extra = []): never
{
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function jsonErrors(array $errors, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'errors' => array_values($errors)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function jsonError(string $message, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function iniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    return (int) match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}