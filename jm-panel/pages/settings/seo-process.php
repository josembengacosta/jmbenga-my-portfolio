<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador SEO (AJAX)
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

// ── Capturar campos ───────────────────────────────────────────
$siteName        = trim($_POST['site_name']        ?? '');
$metaDescription = trim($_POST['meta_description'] ?? '');
$metaKeywords    = trim($_POST['meta_keywords']    ?? '');
$ogImage         = trim($_POST['og_image']         ?? '');
$canonicalUrl    = trim($_POST['canonical_url']    ?? BASE_URL);
$googleAnalytics = trim($_POST['google_analytics'] ?? '');
$robotsTxt       = trim($_POST['robots_txt']       ?? '');

// ── Validações ─────────────────────────────────────────────────
$errors = [];

if ($siteName !== '' && mb_strlen($siteName) < 2) {
    $errors[] = 'O título do site deve ter pelo menos 2 caracteres.';
}
if (!empty($canonicalUrl) && !filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'A URL canónica é inválida.';
}
if (!empty($googleAnalytics) && !preg_match('/^(G|UA|AW)-\w+$/', $googleAnalytics)) {
    $errors[] = 'O ID do Google Analytics é inválido. Deve começar com G-, UA- ou AW-.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Actualizar _site_config (grupo: seo) ──────────────────────
try {
    $configFields = [
        'site_name'        => ['value' => $siteName,        'group' => 'seo'],
        'meta_description' => ['value' => $metaDescription, 'group' => 'seo'],
        'meta_keywords'    => ['value' => $metaKeywords,    'group' => 'seo'],
        'og_image'         => ['value' => $ogImage,         'group' => 'seo'],
        'canonical_url'    => ['value' => $canonicalUrl,    'group' => 'seo'],
        'google_analytics' => ['value' => $googleAnalytics, 'group' => 'seo'],
        'robots_txt'       => ['value' => $robotsTxt,       'group' => 'seo'],
    ];

    $updatedKeys = [];

    foreach ($configFields as $key => $info) {
        // Verificar se já existe
        $stmt = $db->prepare("SELECT id_config FROM _site_config WHERE config_key = ?");
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            // Update
            $db->prepare("UPDATE _site_config SET config_value = ? WHERE config_key = ?")
               ->execute([$info['value'], $key]);
        } else {
            // Insert
            $db->prepare("INSERT INTO _site_config (config_key, config_value, config_group, is_public) VALUES (?, ?, ?, 1)")
               ->execute([$key, $info['value'], $info['group']]);
        }

        $updatedKeys[] = $key;
    }

    // ── Auditoria ──────────────────────────────────────────────
    logAudit(
        $adminId,
        null,
        'settings.seo.update',
        '_site_config',
        null,
        null,
        ['updated_keys' => $updatedKeys]
    );

    echo json_encode(['success' => true, 'message' => 'Configurações de SEO actualizadas com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[SETTINGS SEO] ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Erro interno ao guardar as configurações. Tenta novamente.']]);
    exit;
}