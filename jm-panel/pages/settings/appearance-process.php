<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Configurações de Aparência
// Endpoint AJAX: POST /jm-panel/settings/appearance-process
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function appearanceJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function appearancePostString(string $key, string $default = ''): ?string
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }
    return is_string($_POST[$key]) ? trim($_POST[$key]) : null;
}

function appearanceValidAssetName(string $filename, array $allowedExtensions): bool
{
    if ($filename === '') {
        return true;
    }
    if (
        strlen($filename) > 255
        || basename($filename) !== $filename
        || $filename === '.'
        || $filename === '..'
        || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)
    ) {
        return false;
    }

    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowedExtensions, true);
}

$db = $GLOBALS['pdo'];
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    appearanceJsonResponse(['success' => false, 'message' => 'Sessão administrativa inválida. Inicia sessão novamente.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    appearanceJsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    appearanceJsonResponse(['success' => false, 'message' => 'Token de segurança inválido ou expirado.'], 403);
}

// Capturar apenas valores escalares; arrays injectados em campos de texto são rejeitados.
$customColorInput = appearancePostString('accent_color_custom');
$paletteColorInput = appearancePostString('accent_color');
$darkModeDefault = appearancePostString('dark_mode_default', '1');
$fontHeading = appearancePostString('font_heading', 'Syne');
$fontBody = appearancePostString('font_body', 'DM Sans');
$photoHero = appearancePostString('photo_hero');
$photoProfile = appearancePostString('photo_profile');
$favicon = appearancePostString('favicon');

$errors = [];
foreach ([
    'accent_color_custom' => $customColorInput,
    'accent_color' => $paletteColorInput,
    'dark_mode_default' => $darkModeDefault,
    'font_heading' => $fontHeading,
    'font_body' => $fontBody,
    'photo_hero' => $photoHero,
    'photo_profile' => $photoProfile,
    'favicon' => $favicon,
] as $field => $value) {
    if ($value === null) {
        $errors[] = 'O campo ' . $field . ' tem um formato inválido.';
    }
}

// A cor personalizada só prevalece quando existe um valor preenchido.
$accentColor = '';
if ($customColorInput !== null && $customColorInput !== '') {
    $accentColor = $customColorInput;
} elseif ($paletteColorInput !== null && $paletteColorInput !== '') {
    $accentColor = $paletteColorInput;
} else {
    $errors[] = 'Selecciona uma cor de destaque ou introduz uma cor personalizada.';
}

if ($accentColor !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
    $errors[] = 'A cor de destaque deve estar no formato hexadecimal (#RRGGBB).';
}

if ($darkModeDefault !== null && !in_array($darkModeDefault, ['0', '1'], true)) {
    $errors[] = 'Valor inválido para o tema padrão.';
}

$allowedHeadingFonts = ['Syne', 'Inter', 'Poppins', 'Space Grotesk', 'Outfit', 'Cabinet Grotesk'];
$allowedBodyFonts = ['DM Sans', 'Inter', 'Poppins', 'Nunito', 'Work Sans', 'Manrope'];

if ($fontHeading !== null && !in_array($fontHeading, $allowedHeadingFonts, true)) {
    $errors[] = 'Fonte de títulos inválida.';
}
if ($fontBody !== null && !in_array($fontBody, $allowedBodyFonts, true)) {
    $errors[] = 'Fonte de corpo inválida.';
}

if ($photoHero !== null && !appearanceValidAssetName($photoHero, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'])) {
    $errors[] = 'A foto do Hero deve ser apenas um nome de ficheiro de imagem válido, sem pastas ou URLs.';
}
if ($photoProfile !== null && !appearanceValidAssetName($photoProfile, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'])) {
    $errors[] = 'A foto do About deve ser apenas um nome de ficheiro de imagem válido, sem pastas ou URLs.';
}
if ($favicon !== null && !appearanceValidAssetName($favicon, ['ico', 'png', 'svg'])) {
    $errors[] = 'O favicon deve ser apenas um nome de ficheiro .ico, .png ou .svg, sem pastas ou URLs.';
}

if ($errors !== []) {
    appearanceJsonResponse(['success' => false, 'errors' => $errors], 422);
}

$configFields = [
    'accent_color' => $accentColor,
    'dark_mode_default' => $darkModeDefault,
    'font_heading' => $fontHeading,
    'font_body' => $fontBody,
    'photo_hero' => $photoHero,
    'photo_profile' => $photoProfile,
    'favicon' => $favicon,
];

try {
    // Todas as sete chaves são gravadas numa só transacção: sem gravações parciais.
    $db->beginTransaction();

    $findConfig = $db->prepare(
        'SELECT id_config FROM _site_config WHERE config_key = ? LIMIT 1'
    );
    $updateConfig = $db->prepare(
        "UPDATE _site_config
         SET config_value = ?, config_group = 'appearance', is_public = 1
         WHERE id_config = ?"
    );
    $insertConfig = $db->prepare(
        "INSERT INTO _site_config (config_key, config_value, config_group, is_public)
         VALUES (?, ?, 'appearance', 1)"
    );

    $updatedKeys = [];
    foreach ($configFields as $key => $value) {
        $findConfig->execute([$key]);
        $configId = $findConfig->fetchColumn();

        if ($configId !== false) {
            $updateConfig->execute([$value, (int) $configId]);
        } else {
            $insertConfig->execute([$key, $value]);
        }
        $updatedKeys[] = $key;
    }

    // logAudit() já trata internamente a sua própria falha; não expõe detalhes ao cliente.
    logAudit(
        $adminId,
        null,
        'settings.appearance.update',
        '_site_config',
        null,
        null,
        ['updated_keys' => $updatedKeys]
    );

    $db->commit();
    appearanceJsonResponse([
        'success' => true,
        'message' => 'Aparência do site actualizada com sucesso.',
        'updated_keys' => $updatedKeys,
    ]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[SETTINGS APPEARANCE] ' . $exception->getMessage());
    appearanceJsonResponse([
        'success' => false,
        'message' => 'Não foi possível guardar as configurações. Nenhuma alteração parcial foi mantida. Tenta novamente.',
    ], 500);
}