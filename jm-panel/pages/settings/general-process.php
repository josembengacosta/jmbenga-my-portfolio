<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Configurações Gerais (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = $GLOBALS['pdo'];
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

function generalJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function generalPostString(
    string $key,
    array &$errors,
    bool $mustExist = false,
    bool $mustNotBeEmpty = false
): string {
    if (!array_key_exists($key, $_POST)) {
        if ($mustExist) {
            $errors[] = "O campo {$key} não foi enviado. Actualiza a página e tenta novamente.";
        }
        return '';
    }

    if (!is_string($_POST[$key])) {
        $errors[] = "O campo {$key} tem um formato inválido.";
        return '';
    }

    $value = trim($_POST[$key]);
    if ($mustNotBeEmpty && $value === '') {
        $errors[] = "O campo {$key} é obrigatório.";
    }
    return $value;
}

function generalSafeStoredCvPath(string $filename, string $directory): ?string
{
    if (
        $filename === '' || basename($filename) !== $filename ||
        !preg_match('/^cv_[A-Za-z0-9_-]+\.pdf$/i', $filename)
    ) {
        return null;
    }

    $candidate = $directory . DIRECTORY_SEPARATOR . $filename;
    if (is_link($candidate)) {
        return null;
    }

    $realFile = realpath($candidate);
    if ($realFile === false || !is_file($realFile) || dirname($realFile) !== $directory) {
        return null;
    }

    return $realFile;
}

function generalRemoveNewUpload(?string $path): void
{
    if ($path !== null && is_file($path) && !is_link($path)) {
        @unlink($path);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    generalJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    generalJsonResponse(['success' => false, 'message' => 'Token de segurança inválido ou expirado.'], 403);
}

if ($adminId <= 0) {
    generalJsonResponse(['success' => false, 'message' => 'Sessão administrativa inválida. Inicia sessão novamente.'], 401);
}

// ── Recolha e validação dos campos ────────────────────────────
$errors = [];
$siteName = generalPostString('site_name', $errors, true, true);
$emailContact = generalPostString('email_contact', $errors, true, true);
$heroSubtitle = generalPostString('hero_subtitle', $errors, true);
$aboutText = generalPostString('about_text', $errors, true);
$phoneContact = generalPostString('phone_contact', $errors, true);
$location = generalPostString('location', $errors, true);
$version = generalPostString('version', $errors, true, true);
$yearsExperience = generalPostString('years_experience', $errors, true, true);
$projectsCount = generalPostString('projects_count', $errors, true, true);
$clientsCount = generalPostString('clients_count', $errors, true, true);

if ($siteName !== '' && mb_strlen($siteName, 'UTF-8') > 120) {
    $errors[] = 'O nome do site não pode ultrapassar 120 caracteres.';
}
if ($emailContact !== '' && (mb_strlen($emailContact, 'UTF-8') > 254 || !filter_var($emailContact, FILTER_VALIDATE_EMAIL))) {
    $errors[] = 'O email de contacto é inválido.';
}
if (mb_strlen($heroSubtitle, 'UTF-8') > 300) {
    $errors[] = 'O subtítulo do Hero não pode ultrapassar 300 caracteres.';
}
if (mb_strlen($aboutText, 'UTF-8') > 10000) {
    $errors[] = 'O texto Sobre Mim não pode ultrapassar 10.000 caracteres.';
}
if (mb_strlen($phoneContact, 'UTF-8') > 30) {
    $errors[] = 'O telefone não pode ultrapassar 30 caracteres.';
}
if (mb_strlen($location, 'UTF-8') > 120) {
    $errors[] = 'A localização não pode ultrapassar 120 caracteres.';
}
if ($version !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,9}$/D', $version)) {
    $errors[] = 'A versão deve ter até 10 caracteres e usar apenas letras, números, ponto, hífen ou sinal +.';
}

foreach ([
    'Anos de experiência' => $yearsExperience,
    'Número de projectos' => $projectsCount,
    'Número de clientes' => $clientsCount,
] as $label => $number) {
    if (!preg_match('/^\d{1,6}$/D', $number)) {
        $errors[] = "{$label}: introduz um número inteiro entre 0 e 999999.";
    }
}

$allowContact = 0;
$allowFeedback = 0;
foreach (['allow_contact' => 'allowContact', 'allow_feedback' => 'allowFeedback'] as $field => $target) {
    if (array_key_exists($field, $_POST)) {
        if (!is_string($_POST[$field]) || $_POST[$field] !== '1') {
            $errors[] = "O campo {$field} tem um valor inválido.";
        } else {
            if ($target === 'allowContact') $allowContact = 1;
            if ($target === 'allowFeedback') $allowFeedback = 1;
        }
    }
}

// ── Validar o upload sem mover nem apagar ficheiros ainda ──────
$hasNewCv = false;
$uploadTmp = null;
if (array_key_exists('cv_file', $_FILES)) {
    $file = $_FILES['cv_file'];
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
        $errors[] = 'O envio do CV contém dados inválidos.';
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'O CV excede o limite de upload configurado no servidor.',
                UPLOAD_ERR_FORM_SIZE => 'O CV excede o limite permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'O CV foi enviado apenas parcialmente. Tenta novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'O servidor não tem uma pasta temporária disponível.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu escrever o ficheiro enviado.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o envio do CV.',
            ];
            $errors[] = $uploadErrors[$file['error']] ?? 'Falha desconhecida durante o envio do CV.';
        } else {
            $tmpName = $file['tmp_name'] ?? '';
            $originalName = $file['name'] ?? '';
            $fileSize = $file['size'] ?? null;
            $extension = is_string($originalName) ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';

            if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
                $errors[] = 'O ficheiro temporário do CV não é válido.';
            } elseif ($extension !== 'pdf') {
                $errors[] = 'O ficheiro do CV deve ter a extensão PDF.';
            } elseif (!is_int($fileSize) || $fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                $errors[] = 'O ficheiro PDF deve ter tamanho superior a zero e não exceder 5 MB.';
            } else {
                $signature = @file_get_contents($tmpName, false, null, 0, 8);
                $mimeType = null;
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($tmpName);
                }

                if (!is_string($signature) || strpos($signature, '%PDF-') !== 0) {
                    $errors[] = 'O conteúdo do ficheiro não parece ser um PDF válido.';
                } elseif (!in_array($mimeType, ['application/pdf', 'application/x-pdf'], true)) {
                    $errors[] = 'O tipo MIME do ficheiro enviado não é PDF.';
                } else {
                    $hasNewCv = true;
                    $uploadTmp = $tmpName;
                }
            }
        }
    }
}

if (!empty($errors)) {
    generalJsonResponse(['success' => false, 'errors' => $errors], 422);
}

// ── Preparar destino seguro do novo CV (se existir) ────────────
$uploadDirectory = null;
$newCvName = null;
$newCvPath = null;
if ($hasNewCv) {
    $rootReal = realpath(ROOT_PATH);
    if ($rootReal === false) {
        generalJsonResponse(['success' => false, 'message' => 'A pasta raiz do site não está disponível.'], 500);
    }

    $uploadDirectoryCandidate = $rootReal . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'downloads';
    if (is_link($uploadDirectoryCandidate)) {
        generalJsonResponse(['success' => false, 'message' => 'A pasta de CV não pode ser um link simbólico.'], 500);
    }
    if (!is_dir($uploadDirectoryCandidate) && !@mkdir($uploadDirectoryCandidate, 0755, true) && !is_dir($uploadDirectoryCandidate)) {
        generalJsonResponse(['success' => false, 'message' => 'Não foi possível preparar a pasta de CV. Verifica as permissões.'], 500);
    }

    $uploadDirectory = realpath($uploadDirectoryCandidate);
    if ($uploadDirectory === false || !is_dir($uploadDirectory)) {
        generalJsonResponse(['success' => false, 'message' => 'A pasta de CV não está acessível.'], 500);
    }

    try {
        $newCvName = 'cv_' . bin2hex(random_bytes(16)) . '.pdf';
    } catch (Throwable $randomError) {
        generalJsonResponse(['success' => false, 'message' => 'Não foi possível gerar um nome seguro para o CV.'], 500);
    }
    $newCvPath = $uploadDirectory . DIRECTORY_SEPARATOR . $newCvName;
    if (!is_string($uploadTmp) || !move_uploaded_file($uploadTmp, $newCvPath)) {
        generalJsonResponse(['success' => false, 'message' => 'Não foi possível guardar o novo CV. Verifica as permissões da pasta.'], 500);
    }
}

// ── Gravação transaccional ────────────────────────────────────
$oldCvName = null;
$updatedKeys = [];
try {
    $db->beginTransaction();

    if ($hasNewCv) {
        $oldCvStmt = $db->prepare("SELECT config_value FROM _site_config WHERE config_key = 'cv_file' FOR UPDATE");
        $oldCvStmt->execute();
        $oldValue = $oldCvStmt->fetchColumn();
        $oldCvName = is_string($oldValue) ? $oldValue : null;
    }

    $configFields = [
        'site_name'        => ['value' => $siteName, 'group' => 'general'],
        'hero_subtitle'    => ['value' => $heroSubtitle, 'group' => 'general'],
        'about_text'       => ['value' => $aboutText, 'group' => 'general'],
        'years_experience' => ['value' => $yearsExperience, 'group' => 'general'],
        'projects_count'   => ['value' => $projectsCount, 'group' => 'general'],
        'clients_count'    => ['value' => $clientsCount, 'group' => 'general'],
        'email_contact'    => ['value' => $emailContact, 'group' => 'contact'],
        'phone_contact'    => ['value' => $phoneContact, 'group' => 'contact'],
        'location'         => ['value' => $location, 'group' => 'contact'],
    ];

    if ($hasNewCv && $newCvName !== null) {
        $configFields['cv_file'] = ['value' => $newCvName, 'group' => 'general'];
    }

    $upsert = $db->prepare(
        "INSERT INTO _site_config (config_key, config_value, config_group, is_public)\n" .
        "VALUES (?, ?, ?, 1)\n" .
        "ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), config_group = VALUES(config_group), is_public = 1"
    );

    foreach ($configFields as $key => $info) {
        $upsert->execute([$key, $info['value'], $info['group']]);
        $updatedKeys[] = $key;
    }

    $platformCheck = $db->query('SELECT id_platform FROM _platform WHERE id_platform = 1 FOR UPDATE')->fetchColumn();
    if ($platformCheck === false) {
        throw new RuntimeException('A linha principal de _platform não existe.');
    }

    $platformUpdate = $db->prepare(
        'UPDATE _platform SET version = ?, allow_contact = ?, allow_feedback = ? WHERE id_platform = 1'
    );
    $platformUpdate->execute([$version, $allowContact, $allowFeedback]);

    logAudit(
        $adminId,
        null,
        'settings.general.update',
        '_site_config',
        null,
        null,
        [
            'updated_keys' => $updatedKeys,
            'platform_version' => $version,
            'allow_contact' => $allowContact,
            'allow_feedback' => $allowFeedback,
            'cv_replaced' => $hasNewCv,
        ]
    );

    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    generalRemoveNewUpload($newCvPath);
    error_log('[SETTINGS GENERAL] ' . $error->getMessage());
    generalJsonResponse(
        ['success' => false, 'message' => 'Não foi possível guardar as configurações. Nenhum CV anterior foi removido. Tenta novamente.'],
        500
    );
}

// Só depois do COMMIT tentamos remover o CV anterior, e apenas se o nome
// corresponder ao padrão interno e resolver dentro da pasta de downloads.
if ($hasNewCv && $oldCvName !== null && $oldCvName !== $newCvName && $uploadDirectory !== null) {
    $oldCvPath = generalSafeStoredCvPath($oldCvName, $uploadDirectory);
    if ($oldCvPath !== null && !@unlink($oldCvPath)) {
        error_log('[SETTINGS GENERAL] Não foi possível remover o CV antigo após a substituição confirmada.');
    }
}

generalJsonResponse([
    'success' => true,
    'message' => 'Configurações gerais actualizadas com sucesso.',
    'updated_keys' => $updatedKeys,
]);