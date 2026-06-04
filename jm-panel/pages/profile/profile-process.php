<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Perfil (v2 — Completo)
// Acções: update_profile | toggle_lockscreen | change_pin | reset_attempts
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db      = $GLOBALS['pdo'];
$adminId = (int) $_SESSION['admin_id'];

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── Dispatch de acção ─────────────────────────────────────────
$action = trim($_POST['action'] ?? 'update_profile');

match ($action) {
    'update_profile'   => handleUpdateProfile($db, $adminId),
    'toggle_lockscreen'=> handleToggleLockscreen($db, $adminId),
    'change_pin'       => handleChangePin($db, $adminId),
    'reset_attempts'   => handleResetAttempts($db, $adminId),
    default            => jsonError('Acção desconhecida.', 400),
};

// ══════════════════════════════════════════════════════════════
// ACÇÃO 1 — Actualizar Dados Pessoais
// ══════════════════════════════════════════════════════════════
function handleUpdateProfile(PDO $db, int $adminId): never
{
    $firstName  = trim($_POST['first_name']        ?? '');
    $secondName = trim($_POST['second_name']        ?? '');
    $email      = strtolower(trim($_POST['email_employees'] ?? ''));
    $username   = trim($_POST['user_employees']     ?? '');
    $tel        = trim($_POST['tel_employees']      ?? '');
    $gender     = $_POST['gender']                  ?? 'M';
    $country    = strtoupper(trim($_POST['country_employees'] ?? 'AO'));
    $city       = trim($_POST['city_employees']     ?? 'Luanda');
    $about      = trim($_POST['about_employees']    ?? '');

    $errors = [];

    // Validações básicas
    if (mb_strlen($firstName) < 2) {
        $errors[] = 'O nome deve ter pelo menos 2 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Endereço de email inválido.';
    }
    if (!in_array($gender, ['M', 'F'])) {
        $errors[] = 'Género inválido.';
    }
    if (!empty($country) && strlen($country) !== 2) {
        $errors[] = 'O código do país deve ter exactamente 2 caracteres (ex: AO, PT).';
    }
    if (!empty($username) && !preg_match('/^[a-zA-Z0-9_.\-]{3,60}$/', $username)) {
        $errors[] = 'O username só pode conter letras, números, _, . e - (3 a 60 caracteres).';
    }

    // Unicidade email
    $stmt = $db->prepare("SELECT COUNT(*) FROM _employees WHERE email_employees = ? AND id_employees != ?");
    $stmt->execute([$email, $adminId]);
    if ((int) $stmt->fetchColumn() > 0) {
        $errors[] = 'Este email já está em uso por outro administrador.';
    }

    // Unicidade username
    if (!empty($username)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM _employees WHERE user_employees = ? AND id_employees != ?");
        $stmt->execute([$username, $adminId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Este username já está em uso.';
        }
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // ── Upload de foto ────────────────────────────────────────
    $newPhotoName = null;
    $photoFile    = $_FILES['photo_employees'] ?? null;

    if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $photoFile['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mime, $allowed)) {
            echo json_encode(['success' => false, 'errors' => ['Formato não suportado. Usa JPEG, PNG ou WebP.']]);
            exit;
        }
        $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 2 * 1024 * 1024; // 2 MB default
        if ($photoFile['size'] > $maxSize) {
            echo json_encode(['success' => false, 'errors' => ['A foto excede o tamanho máximo de ' . round($maxSize / 1024 / 1024, 1) . ' MB.']]);
            exit;
        }

        $ext          = $allowed[$mime];
        $newPhotoName = 'admin_' . $adminId . '_' . time() . '.' . $ext;
        $uploadDir    = ROOT_PATH . '/assets/img/profile/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($photoFile['tmp_name'], $uploadDir . $newPhotoName)) {
            echo json_encode(['success' => false, 'errors' => ['Falha ao guardar a foto. Verifica as permissões da pasta.']]);
            exit;
        }

        // Apagar foto antiga
        $oldStmt = $db->prepare("SELECT photo_employees FROM _employees WHERE id_employees = ?");
        $oldStmt->execute([$adminId]);
        $oldPhoto = $oldStmt->fetchColumn();
        if ($oldPhoto && $oldPhoto !== $newPhotoName && file_exists($uploadDir . $oldPhoto)) {
            @unlink($uploadDir . $oldPhoto);
        }
    }

    // ── Guardar na BD ─────────────────────────────────────────
    try {
        $sql = "UPDATE _employees SET
            first_name        = ?,
            second_name       = ?,
            email_employees   = ?,
            user_employees    = ?,
            tel_employees     = ?,
            gender            = ?,
            country_employees = ?,
            city_employees    = ?,
            about_employees   = ?";

        $params = [$firstName, $secondName ?: null, $email, $username ?: null, $tel ?: null, $gender, $country, $city, $about ?: null];

        if ($newPhotoName) {
            $sql      .= ', photo_employees = ?';
            $params[]  = $newPhotoName;
        }

        $sql      .= ' WHERE id_employees = ?';
        $params[]  = $adminId;

        $db->prepare($sql)->execute($params);

        // Actualizar sessão
        $_SESSION['admin_name']      = $firstName;
        $_SESSION['admin_full_name'] = trim($firstName . ' ' . $secondName);
        $_SESSION['admin_email']     = $email;
        if ($newPhotoName) $_SESSION['admin_photo'] = $newPhotoName;

        // Auditoria
        logAudit(
            $adminId, null, 'profile.update', '_employees', $adminId, null,
            ['first_name' => $firstName, 'email' => $email, 'country' => $country, 'city' => $city]
        );

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado com sucesso.']);
        exit;

    } catch (PDOException $e) {
        if ($newPhotoName) @unlink(ROOT_PATH . '/assets/img/profile/' . $newPhotoName);
        error_log('[PROFILE UPDATE] ' . $e->getMessage());
        echo json_encode(['success' => false, 'errors' => ['Erro interno ao actualizar o perfil. Tenta novamente.']]);
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
// ACÇÃO 2 — Activar / Desactivar Lockscreen
// ══════════════════════════════════════════════════════════════
function handleToggleLockscreen(PDO $db, int $adminId): never
{
    $enabled = (int) ($_POST['enabled'] ?? 0);

    if (!in_array($enabled, [0, 1])) {
        jsonError('Valor inválido para lockscreen.');
    }

    try {
        // Se desactivar, não apagamos o PIN (por segurança; mantemos para reactivação)
        $db->prepare("
            UPDATE _employees_security
            SET lockscreen = ?
            WHERE id_employees = ?
        ")->execute([$enabled, $adminId]);

        logAudit(
            $adminId, null, 'security.lockscreen_' . ($enabled ? 'on' : 'off'),
            '_employees_security', $adminId, null,
            ['lockscreen' => $enabled]
        );

        echo json_encode([
            'success' => true,
            'message' => $enabled ? 'Lockscreen activado com sucesso.' : 'Lockscreen desactivado.',
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('[LOCKSCREEN TOGGLE] ' . $e->getMessage());
        jsonError('Erro interno ao alterar o lockscreen.');
    }
}

// ══════════════════════════════════════════════════════════════
// ACÇÃO 3 — Alterar PIN de Lockscreen
// ══════════════════════════════════════════════════════════════
function handleChangePin(PDO $db, int $adminId): never
{
    $newPin = trim($_POST['new_pin'] ?? '');

    if (!preg_match('/^\d{6}$/', $newPin)) {
        echo json_encode(['success' => false, 'message' => 'O PIN deve ter entre 6 dígitos numéricos.']);
        exit;
    }

    try {
        $db->prepare("
            UPDATE _employees_security
            SET access_code = ?, lockscreen = 1
            WHERE id_employees = ?
        ")->execute([$newPin, $adminId]);

        logAudit(
            $adminId, null, 'security.pin_changed',
            '_employees_security', $adminId, null,
            ['pin_length' => strlen($newPin)]
        );

        echo json_encode(['success' => true, 'message' => 'PIN actualizado e lockscreen activado.']);
        exit;

    } catch (PDOException $e) {
        error_log('[CHANGE PIN] ' . $e->getMessage());
        jsonError('Erro interno ao guardar o PIN.');
    }
}

// ══════════════════════════════════════════════════════════════
// ACÇÃO 4 — Limpar Tentativas de Login Falhadas
// ══════════════════════════════════════════════════════════════
function handleResetAttempts(PDO $db, int $adminId): never
{
    // Apenas super_admin pode limpar os próprios bloqueios
    // (podes adicionar restrição de role aqui se necessário)
    try {
        $db->prepare("
            UPDATE _employees_security
            SET login_attempts = 0, block_level = 0, block_until = NULL
            WHERE id_employees = ?
        ")->execute([$adminId]);

        logAudit(
            $adminId, null, 'security.reset_attempts',
            '_employees_security', $adminId, null,
            ['reset_by' => $adminId]
        );

        echo json_encode(['success' => true, 'message' => 'Tentativas de login limpas.']);
        exit;

    } catch (PDOException $e) {
        error_log('[RESET ATTEMPTS] ' . $e->getMessage());
        jsonError('Erro interno ao limpar tentativas.');
    }
}

// ── Helper ────────────────────────────────────────────────────
function jsonError(string $msg, int $code = 422): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}