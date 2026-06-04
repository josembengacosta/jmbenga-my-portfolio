<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Aparência (AJAX)
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
$accentColor     = trim($_POST['accent_color_custom'] ?? $_POST['accent_color'] ?? '#2563eb');
$darkModeDefault = $_POST['dark_mode_default'] ?? '1';
$fontHeading     = trim($_POST['font_heading'] ?? 'Syne');
$fontBody        = trim($_POST['font_body'] ?? 'DM Sans');
$photoHero       = trim($_POST['photo_hero'] ?? '');
$photoProfile    = trim($_POST['photo_profile'] ?? '');
$favicon         = trim($_POST['favicon'] ?? '');

// ── Validações ─────────────────────────────────────────────────
$errors = [];

// Validar cor hexadecimal
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
    $errors[] = 'A cor de destaque deve estar no formato hexadecimal (#RRGGBB).';
}

// Validar modo escuro
if (!in_array($darkModeDefault, ['0', '1'])) {
    $errors[] = 'Valor inválido para o tema padrão.';
}

// Validar fontes
$allowedHeadingFonts = ['Syne', 'Inter', 'Poppins', 'Space Grotesk', 'Outfit', 'Cabinet Grotesk'];
$allowedBodyFonts    = ['DM Sans', 'Inter', 'Poppins', 'Nunito', 'Work Sans', 'Manrope'];

if (!in_array($fontHeading, $allowedHeadingFonts)) {
    $errors[] = 'Fonte de títulos inválida.';
}
if (!in_array($fontBody, $allowedBodyFonts)) {
    $errors[] = 'Fonte de corpo inválida.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Actualizar _site_config (grupo: appearance) ────────────────
try {
    $configFields = [
        'accent_color'      => $accentColor,
        'dark_mode_default' => $darkModeDefault,
        'font_heading'      => $fontHeading,
        'font_body'         => $fontBody,
        'photo_hero'        => $photoHero,
        'photo_profile'     => $photoProfile,
        'favicon'           => $favicon,
    ];

    $updatedKeys = [];

    foreach ($configFields as $key => $value) {
        // Verificar se já existe
        $stmt = $db->prepare("SELECT id_config FROM _site_config WHERE config_key = ?");
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            // Update
            $db->prepare("UPDATE _site_config SET config_value = ? WHERE config_key = ?")
               ->execute([$value, $key]);
        } else {
            // Insert
            $db->prepare("INSERT INTO _site_config (config_key, config_value, config_group, is_public) VALUES (?, ?, 'appearance', 1)")
               ->execute([$key, $value]);
        }

        $updatedKeys[] = $key;
    }

    // ── Auditoria ──────────────────────────────────────────────
    logAudit(
        $adminId,
        null,
        'settings.appearance.update',
        '_site_config',
        null,
        null,
        ['updated_keys' => $updatedKeys]
    );

    echo json_encode(['success' => true, 'message' => 'Aparência do site actualizada com sucesso.']);
    exit;

} catch (PDOException $e) {
    error_log('[SETTINGS APPEARANCE] ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['Erro interno ao guardar as configurações. Tenta novamente.']]);
    exit;
}