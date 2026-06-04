<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Configurações Gerais (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];
$adminId = (int)$_SESSION['admin_id'];

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

// ── Validações básicas ────────────────────────────────────────
$siteName     = trim($_POST['site_name'] ?? '');
$emailContact = trim($_POST['email_contact'] ?? '');

$errors = [];
if ($siteName === '') $errors[] = 'O nome do site é obrigatório.';
if ($emailContact !== '' && !filter_var($emailContact, FILTER_VALIDATE_EMAIL)) $errors[] = 'O email de contacto é inválido.';

// ── Upload do CV (PDF) ────────────────────────────────────────
$cvFileValue = null;  // se não houver ficheiro enviado, mantém o actual
$uploadDir   = ROOT_PATH . '/assets/downloads/';

// Garante que a pasta existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
    $tmpName      = $_FILES['cv_file']['tmp_name'];
    $originalName = basename($_FILES['cv_file']['name']);
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        $errors[] = 'O ficheiro do CV deve ser um PDF.';
    } elseif ($_FILES['cv_file']['size'] > 5 * 1024 * 1024) { // 5 MB
        $errors[] = 'O ficheiro PDF não pode exceder 5 MB.';
    } else {
        // Gera um nome seguro: cv_<timestamp>.pdf
        $newName  = 'cv_' . time() . '.pdf';
        $destPath = $uploadDir . $newName;

        if (move_uploaded_file($tmpName, $destPath)) {
            // Obtém o ficheiro antigo para remover
            $stmt = $db->prepare("SELECT config_value FROM _site_config WHERE config_key = 'cv_file'");
            $stmt->execute();
            $oldFile = $stmt->fetchColumn();

            if ($oldFile && file_exists($uploadDir . $oldFile)) {
                unlink($uploadDir . $oldFile);
            }

            $cvFileValue = $newName;
        } else {
            $errors[] = 'Erro ao mover o ficheiro. Verifica as permissões da pasta.';
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // ── Actualizar _site_config (grupos: general, contact) ──────
    $configFields = [
        // Grupo general
        'site_name'        => ['group' => 'general'],
        'hero_subtitle'    => ['group' => 'general'],
        'about_text'       => ['group' => 'general'],
        'years_experience' => ['group' => 'general'],
        'projects_count'   => ['group' => 'general'],
        'clients_count'    => ['group' => 'general'],
        'cv_file'          => ['group' => 'general', 'is_file' => true],
        // Grupo contact
        'email_contact'    => ['group' => 'contact'],
        'phone_contact'    => ['group' => 'contact'],
        'location'         => ['group' => 'contact'],
    ];

    $updatedKeys = [];

    foreach ($configFields as $key => $info) {
        // Se é o campo de ficheiro e temos um novo valor, usa-o; caso contrário ignora (mantém o anterior)
        if (!empty($info['is_file'])) {
            if ($cvFileValue !== null) {
                $value = $cvFileValue;
            } else {
                // Nenhum novo ficheiro enviado; não actualiza este campo
                continue;
            }
        } else {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
            } else {
                continue;
            }
        }

        // Verificar se já existe
        $stmt = $db->prepare("SELECT id_config FROM _site_config WHERE config_key = ?");
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            // Update
            $db->prepare("UPDATE _site_config SET config_value = ? WHERE config_key = ?")
               ->execute([$value, $key]);
        } else {
            // Insert
            $db->prepare("INSERT INTO _site_config (config_key, config_value, config_group, is_public) VALUES (?, ?, ?, 1)")
               ->execute([$key, $value, $info['group']]);
        }

        $updatedKeys[] = $key;
    }

    // ── Actualizar _platform ────────────────────────────────────
    $version       = trim($_POST['version'] ?? '1.0');
    $allowContact  = isset($_POST['allow_contact']) ? 1 : 0;
    $allowFeedback = isset($_POST['allow_feedback']) ? 1 : 0;

    $db->prepare("UPDATE _platform SET version = ?, allow_contact = ?, allow_feedback = ? WHERE id_platform = 1")
       ->execute([$version, $allowContact, $allowFeedback]);

    // ── Auditoria ──────────────────────────────────────────────
    logAudit(
        $adminId,
        null,
        'settings.general.update',
        '_site_config',
        null,
        null,
        ['updated_keys' => $updatedKeys, 'platform_version' => $version]
    );

    echo json_encode(['success' => true, 'message' => 'Configurações gerais actualizadas com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[SETTINGS GENERAL] ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Erro interno ao guardar as configurações. Tenta novamente.']]);
    exit;
}