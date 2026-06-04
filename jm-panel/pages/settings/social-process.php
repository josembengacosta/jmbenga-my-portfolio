<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Redes Sociais (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['pdo'];
$adminId = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// Todas as redes sociais suportadas
$socialFields = [
    'github_url',
    'linkedin_url',
    'whatsapp_url',
    'twitter_url',
    'instagram_url',
    'youtube_url',
    'behance_url',
    'dribbble_url',
    'medium_url',
    'devto_url',
    'facebook_url',
    'telegram_url',
    'tiktok_url',
    'discord_url',
    'stackoverflow_url',
    'codepen_url',
];

$errors = [];

// Validar URLs
foreach ($socialFields as $field) {
    $value = trim($_POST[$field] ?? '');
    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
        $label = ucfirst(str_replace('_url', '', $field));
        $errors[] = "A URL do campo \"{$label}\" é inválida.";
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Actualizar _site_config
try {
    $updatedKeys = [];

    foreach ($socialFields as $key) {
        $value = trim($_POST[$key] ?? '');

        $stmt = $db->prepare("SELECT id_config FROM _site_config WHERE config_key = ?");
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            $db->prepare("UPDATE _site_config SET config_value = ? WHERE config_key = ?")
               ->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO _site_config (config_key, config_value, config_group, is_public) VALUES (?, ?, 'social', 1)")
               ->execute([$key, $value]);
        }

        $updatedKeys[] = $key;
    }

    logAudit($adminId, null, 'settings.social.update', '_site_config', null, null, ['updated_keys' => $updatedKeys]);

    echo json_encode(['success' => true, 'message' => 'Redes sociais actualizadas com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[SETTINGS SOCIAL] ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Erro interno ao guardar as configurações.']]);
    exit;
}