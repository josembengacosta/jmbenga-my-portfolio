<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações de Aparência
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);

// ── Carregar configurações actuais ──────────────────────────
$configStmt = $db->query("SELECT * FROM _site_config WHERE config_group = 'appearance' ORDER BY config_key");
$configs = [];
foreach ($configStmt->fetchAll() as $row) {
    $configs[$row['config_key']] = $row['config_value'];
}

// ── Recuperar feedback ─────────────────────────────────────
$formData    = $_SESSION['form_data']    ?? [];
$formErrors  = $_SESSION['form_errors']  ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['form_success']);

if (empty($formData)) $formData = $configs;

// Paleta organizada por famílias: 48 opções no total.
$colorGroups = [
    'Azuis' => [
        ['name' => 'Azul Royal', 'color' => '#2563eb'],
        ['name' => 'Azul Céu', 'color' => '#0ea5e9'],
        ['name' => 'Azul Marinho', 'color' => '#1e3a8a'],
        ['name' => 'Azul Petróleo', 'color' => '#0891b2'],
        ['name' => 'Índigo', 'color' => '#4f46e5'],
        ['name' => 'Cobalto', 'color' => '#1d4ed8'],
        ['name' => 'Azul Eléctrico', 'color' => '#3b82f6'],
        ['name' => 'Azul Aço', 'color' => '#475569'],
        ['name' => 'Azul Bebé', 'color' => '#7dd3fc'],
    ],
    'Verdes' => [
        ['name' => 'Verde Esmeralda', 'color' => '#10b981'],
        ['name' => 'Verde Lima', 'color' => '#84cc16'],
        ['name' => 'Verde Floresta', 'color' => '#166534'],
        ['name' => 'Menta', 'color' => '#34d399'],
        ['name' => 'Verde Água', 'color' => '#14b8a6'],
        ['name' => 'Oliva', 'color' => '#65a30d'],
        ['name' => 'Verde Sálvia', 'color' => '#84a98c'],
        ['name' => 'Verde Néon', 'color' => '#a3e635'],
    ],
    'Roxos e violetas' => [
        ['name' => 'Roxo Violeta', 'color' => '#8b5cf6'],
        ['name' => 'Lavanda', 'color' => '#a78bfa'],
        ['name' => 'Roxo Escuro', 'color' => '#6d28d9'],
        ['name' => 'Magenta', 'color' => '#d946ef'],
        ['name' => 'Ametista', 'color' => '#7c3aed'],
        ['name' => 'Ameixa', 'color' => '#7e22ce'],
        ['name' => 'Orquídea', 'color' => '#c084fc'],
    ],
    'Laranjas e amarelos' => [
        ['name' => 'Laranja Âmbar', 'color' => '#f59e0b'],
        ['name' => 'Laranja Fogo', 'color' => '#f97316'],
        ['name' => 'Amarelo Ouro', 'color' => '#eab308'],
        ['name' => 'Pêssego', 'color' => '#fb923c'],
        ['name' => 'Terracota', 'color' => '#c2410c'],
        ['name' => 'Cobre', 'color' => '#b45309'],
        ['name' => 'Dourado', 'color' => '#ca8a04'],
    ],
    'Rosas e vermelhos' => [
        ['name' => 'Rosa Pink', 'color' => '#ec4899'],
        ['name' => 'Vermelho Coral', 'color' => '#ef4444'],
        ['name' => 'Vermelho Escuro', 'color' => '#b91c1c'],
        ['name' => 'Rosa Claro', 'color' => '#f472b6'],
        ['name' => 'Bordô', 'color' => '#881337'],
        ['name' => 'Vinho', 'color' => '#9f1239'],
        ['name' => 'Salmão', 'color' => '#fb7185'],
    ],
    'Cianos e turquesas' => [
        ['name' => 'Ciano', 'color' => '#06b6d4'],
        ['name' => 'Turquesa', 'color' => '#2dd4bf'],
        ['name' => 'Azul Piscina', 'color' => '#22d3ee'],
    ],
    'Neutros e escuros' => [
        ['name' => 'Cinza Slate', 'color' => '#64748b'],
        ['name' => 'Cinza Escuro', 'color' => '#334155'],
        ['name' => 'Branco Gelo', 'color' => '#f1f5f9'],
        ['name' => 'Preto', 'color' => '#000000'],
        ['name' => 'Grafite', 'color' => '#1f2937'],
        ['name' => 'Prata', 'color' => '#94a3b8'],
        ['name' => 'Marfim', 'color' => '#fef3c7'],
    ],
];

$colorPalette = [];
foreach ($colorGroups as $groupColors) {
    foreach ($groupColors as $paletteColor) {
        $colorPalette[] = $paletteColor;
    }
}

// Fontes disponíveis
$headingFonts = ['Syne', 'Inter', 'Poppins', 'Space Grotesk', 'Outfit', 'Cabinet Grotesk'];
$bodyFonts    = ['DM Sans', 'Inter', 'Poppins', 'Nunito', 'Work Sans', 'Manrope'];

// Normalizar valores vindos da sessão/base de dados antes de os usar no HTML/CSS.
$formData = is_array($formData) ? $formData : [];
$readConfigString = static function (array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    return is_string($value) || is_numeric($value) ? (string) $value : $default;
};

$accentColor = $readConfigString($formData, 'accent_color', '#2563eb');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
    $accentColor = '#2563eb';
}

$darkModeDefault = $readConfigString($formData, 'dark_mode_default', '1');
if (!in_array($darkModeDefault, ['0', '1'], true)) {
    $darkModeDefault = '1';
}

$fontHeading = $readConfigString($formData, 'font_heading', 'Syne');
if (!in_array($fontHeading, $headingFonts, true)) {
    $fontHeading = 'Syne';
}
$fontBody = $readConfigString($formData, 'font_body', 'DM Sans');
if (!in_array($fontBody, $bodyFonts, true)) {
    $fontBody = 'DM Sans';
}

// Só aceitar nomes simples de ficheiro; nunca caminhos ou URLs nas pré-visualizações.
$isSafeAssetName = static function (string $value, array $extensions): bool {
    if ($value === '') {
        return true;
    }
    if (strlen($value) > 255 || basename($value) !== $value || $value === '.' || $value === '..') {
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
        return false;
    }
    return in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), $extensions, true);
};

$heroPhoto = $readConfigString($formData, 'photo_hero');
if (!$isSafeAssetName($heroPhoto, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'])) {
    $heroPhoto = '';
}
$aboutPhoto = $readConfigString($formData, 'photo_profile');
if (!$isSafeAssetName($aboutPhoto, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'])) {
    $aboutPhoto = '';
}
$faviconFile = $readConfigString($formData, 'favicon');
if (!$isSafeAssetName($faviconFile, ['ico', 'png', 'svg'])) {
    $faviconFile = '';
}

// Resolver a existência física antes de mostrar uma pré-visualização.
$resolveAppearanceAsset = static function (string $filename, string $folder, array $extensions): ?array {
    if (
        $filename === ''
        || basename($filename) !== $filename
        || !preg_match('/\A[A-Za-z0-9._-]+\z/', $filename)
        || !in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $extensions, true)
        || !defined('ROOT_PATH')
        || !defined('BASE_URL')
    ) {
        return null;
    }

    $relativeFolder = $folder === 'icons' ? 'icons' : 'profile';
    $path = rtrim((string) ROOT_PATH, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img'
        . DIRECTORY_SEPARATOR . $relativeFolder . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || is_link($path) || !is_readable($path)) {
        return null;
    }

    $url = rtrim((string) BASE_URL, '/') . '/assets/img/' . $relativeFolder . '/' . rawurlencode($filename);
    return ['file' => $filename, 'path' => $path, 'url' => $url . '?v=' . (string) filemtime($path)];
};

$heroPreview = $resolveAppearanceAsset($heroPhoto, 'profile', ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif']);
$aboutFallbackPreview = $resolveAppearanceAsset($aboutPhoto, 'profile', ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif']);
$faviconPreview = $resolveAppearanceAsset($faviconFile, 'icons', ['ico', 'png', 'svg']);

// A homepage escolhe primeiro a foto do administrador activo; photo_profile é fallback.
$aboutPublicPreview = null;
$aboutPublicSource = 'Nenhuma imagem pública encontrada';
try {
    $publicAdminPhotoStmt = $db->query(
        "SELECT photo_employees
         FROM _employees
         WHERE photo_employees IS NOT NULL
           AND photo_employees <> ''
           AND status_employees = 'active'
         ORDER BY (role = 'super_admin') DESC, id_employees ASC
         LIMIT 1"
    );
    $publicAdminPhoto = trim((string) ($publicAdminPhotoStmt->fetchColumn() ?: ''));
    $publicAdminPhoto = basename(str_replace('\\', '/', $publicAdminPhoto));
    $aboutPublicPreview = $resolveAppearanceAsset($publicAdminPhoto, 'profile', ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif']);
    if ($aboutPublicPreview !== null) {
        $aboutPublicSource = 'Foto do perfil do administrador activo';
    }
} catch (Throwable $photoLookupError) {
    error_log('[APPEARANCE PREVIEW] Não foi possível verificar a foto do administrador.');
}
if ($aboutPublicPreview === null && $aboutFallbackPreview !== null) {
    $aboutPublicPreview = $aboutFallbackPreview;
    $aboutPublicSource = 'Foto do About (fallback de Aparência)';
}

$isPresetAccent = false;
foreach ($colorPalette as $paletteColor) {
    if (strcasecmp($accentColor, $paletteColor['color']) === 0) {
        $isPresetAccent = true;
        break;
    }
}
$customAccentValue = $isPresetAccent ? '' : $accentColor;


?>
<?php $pageTitle = 'Aparência'; ?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= e(BASE_URL) ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<style>
.page-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .78rem;
    color: var(--text-muted);
    margin-bottom: .5rem
}

.breadcrumb a {
    color: var(--text-dim);
    text-decoration: none;
    transition: color .2s
}

.breadcrumb a:hover {
    color: var(--accent)
}

.breadcrumb i {
    font-size: .6rem
}

.page-title {
    font-family: var(--font-head);
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -.02em;
    margin-bottom: .2rem
}

.page-sub {
    font-size: .8rem;
    color: var(--text-muted)
}

.alert {
    padding: 1rem 1.2rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    font-size: .85rem
}

.alert-success {
    background: rgba(16, 185, 129, .1);
    border: 1px solid rgba(16, 185, 129, .3);
    color: #34d399
}

.alert-danger {
    background: rgba(239, 68, 68, .1);
    border: 1px solid rgba(239, 68, 68, .3);
    color: #f87171
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    opacity: .6;
    font-size: .9rem
}

.alert-close:hover {
    opacity: 1
}

.form-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.8rem;
    margin-bottom: 1.2rem
}

.section-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.2rem;
    border-bottom: 1px solid var(--border)
}

.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: .95rem;
    flex-shrink: 0
}

.section-header h3 {
    font-family: var(--font-head);
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: .15rem
}

.section-header p {
    font-size: .78rem;
    color: var(--text-muted)
}

/* Paleta de cores */
.color-group {
    margin-bottom: 1.25rem;
}

.color-group-title {
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: .65rem;
}

.color-palette {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(108px, 1fr));
    gap: .6rem;
}

.color-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: 1rem .8rem;
    background: var(--bg);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all .2s;
    text-align: center;
    position: relative;
}

.color-option:focus-within {
    outline: 2px solid var(--color);
    outline-offset: 3px;
}

.color-option:hover {
    border-color: var(--color);
    transform: translateY(-2px)
}

.color-option.selected {
    border-color: var(--color);
    background: color-mix(in srgb, var(--color) 10%, var(--bg))
}

.color-option input[type="radio"] {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.color-swatch {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--color);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .3)
}

.color-name {
    font-size: .75rem;
    font-weight: 600;
    color: var(--text-dim);
}

.color-hex {
    color: var(--text-muted);
    font-size: .65rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .02em;
}

.custom-color-help {
    display: block;
    color: var(--text-muted);
    font-size: .7rem;
    margin-top: .45rem;
}

/* Cor personalizada */
.custom-color-row {
    margin-top: .5rem
}

.custom-color-input {
    display: flex;
    align-items: center;
    gap: .8rem;
    margin-top: .5rem
}

.color-picker-native {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    background: none;
    padding: 2px
}

.custom-color-input .form-control {
    flex: 1
}

/* Preview de cor */
.color-preview {
    margin-top: 1.5rem;
    padding: 1.2rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius)
}

.color-preview h4 {
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
    margin-bottom: .8rem
}

.preview-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap
}

.preview-badge {
    padding: 4px 12px;
    border-radius: 99px;
    color: #fff;
    font-size: .72rem;
    font-weight: 600
}

.preview-link {
    font-weight: 600;
    font-size: .85rem;
    text-decoration: underline
}

.preview-btn {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: .82rem;
    font-weight: 600;
    cursor: default
}

.preview-bar {
    flex: 1;
    min-width: 100px;
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden
}

.preview-bar-fill {
    height: 100%;
    border-radius: 3px
}

/* Tema */
.theme-options {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap
}

.theme-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .8rem;
    cursor: pointer;
    transition: all .2s
}

.theme-card input {
    display: none
}

.theme-card.selected .theme-preview {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow)
}

.theme-preview {
    width: 160px;
    border-radius: var(--radius);
    overflow: hidden;
    border: 2px solid var(--border);
    transition: all .2s
}

.dark-preview .theme-header {
    height: 24px;
    background: #1e293b
}

.dark-preview .theme-body {
    background: #0f172a;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px
}

.dark-preview .theme-line {
    height: 8px;
    background: #334155;
    border-radius: 2px
}

.dark-preview .theme-line.short {
    width: 60%
}

.light-preview .theme-header {
    height: 24px;
    background: #e2e8f0
}

.light-preview .theme-body {
    background: #f8fafc;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px
}

.light-preview .theme-line {
    height: 8px;
    background: #cbd5e1;
    border-radius: 2px
}

.light-preview .theme-line.short {
    width: 60%
}

.theme-card span {
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-dim)
}

.theme-card.selected span {
    color: var(--accent)
}

@media(max-width:480px) {
    .form-section {
        padding: 1.1rem;
    }

    .custom-color-input {
        flex-wrap: wrap;
    }

    .custom-color-input .form-control {
        min-width: 0;
    }

    .theme-preview {
        width: min(160px, 42vw);
    }
}

/* Tipografia */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem
}

@media(max-width:768px) {
    .form-grid {
        grid-template-columns: 1fr
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: .35rem
}

.form-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text-dim)
}

.form-control {
    padding: .65rem .85rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font-body);
    font-size: .88rem;
    outline: none;
    transition: border-color .2s, box-shadow .2s
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow)
}

.form-hint {
    font-size: .7rem;
    color: var(--text-muted);
    margin-top: .15rem
}

.font-select {
    font-size: .9rem;
    padding: .75rem
}

.font-preview {
    margin-top: .8rem;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 1.1rem;
    line-height: 1.6;
    color: var(--text)
}

#bodyPreview {
    font-size: .9rem
}

/* Imagens */
.img-preview {
    margin-top: .65rem;
    width: 100%;
    max-width: 240px;
    min-height: 90px;
    padding: .55rem;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--bg);
}

.img-preview img {
    width: 100%;
    max-height: 260px;
    display: block;
    object-fit: contain;
    border-radius: 6px;
}

.img-preview.icon-preview {
    width: 88px;
    min-height: 88px;
    display: grid;
    place-items: center;
}

.img-preview.icon-preview img {
    width: 64px;
    height: 64px;
    object-fit: contain;
}

.asset-status {
    display: flex;
    align-items: flex-start;
    gap: .45rem;
    margin-top: .55rem;
    color: var(--text-muted);
    font-size: .72rem;
    line-height: 1.55;
}

.asset-status.is-ok {
    color: var(--success);
}

.asset-status.is-warning {
    color: var(--warning);
}

.asset-status.is-error {
    color: var(--danger);
}

.asset-note {
    margin-top: .65rem;
    padding: .7rem .8rem;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--bg);
    color: var(--text-dim);
    font-size: .72rem;
    line-height: 1.6;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem 1.1rem;
    border-radius: 8px;
    font-size: .83rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    border: 1px solid var(--border);
    white-space: nowrap
}

.btn-primary {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent)
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px var(--accent-glow)
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-dim)
}

.btn-secondary:hover {
    background: var(--bg-hover);
    border-color: var(--border-acc);
    color: var(--text)
}

.btn-lg {
    padding: .7rem 1.5rem;
    font-size: .9rem
}

.form-actions {
    display: flex;
    gap: .75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border)
}
</style>
</head>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/settings">Configurações</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Aparência</span>
                    </div>
                    <h1 class="page-title">Aparência do Site</h1>
                    <p class="page-sub">Personaliza cores, tipografia, imagens e outros elementos visuais.</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/settings" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <!-- Feedback -->
            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($formSuccess) ?><button
                    class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>
                <div><strong>Corrige:</strong>
                    <ul style="margin:.3rem 0 0 1.2rem"><?php foreach ($formErrors as $err): ?><li><?= e($err) ?></li>
                        <?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <form id="appearanceForm" novalidate data-api="<?= e(BASE_URL . '/jm-panel/settings/appearance-process') ?>"
                enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">

                <!-- ═══ SECÇÃO 1: COR DE DESTAQUE ════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-palette"></i></span>
                        <div>
                            <h3>Cor de Destaque</h3>
                            <p>Define a cor principal do site (links, botões, badges).</p>
                        </div>
                    </div>
                    <?php foreach ($colorGroups as $groupName => $groupColors): ?>
                    <div class="color-group">
                        <h4 class="color-group-title"><?= e($groupName) ?></h4>
                        <div class="color-palette">
                            <?php foreach ($groupColors as $palette): ?>
                            <?php $isSelectedColor = strcasecmp($accentColor, $palette['color']) === 0; ?>
                            <label class="color-option <?= $isSelectedColor ? 'selected' : '' ?>"
                                style="--color:<?= e($palette['color']) ?>"
                                title="<?= e($palette['name'] . ' ' . $palette['color']) ?>">
                                <input type="radio" name="accent_color" value="<?= e($palette['color']) ?>"
                                    <?= $isSelectedColor ? 'checked' : '' ?> aria-label="<?= e($palette['name']) ?>">
                                <span class="color-swatch" aria-hidden="true"></span>
                                <span class="color-name"><?= e($palette['name']) ?></span>
                                <span class="color-hex"><?= e($palette['color']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="custom-color-row">
                        <label class="form-label" for="customColorHex">Ou escolhe uma cor personalizada</label>
                        <div class="custom-color-input">
                            <input type="color" id="customColorPicker" value="<?= e($accentColor) ?>"
                                class="color-picker-native" aria-label="Selector de cor personalizada">
                            <input type="text" id="customColorHex" class="form-control" value="<?= e($accentColor) ?>"
                                placeholder="#2563eb" maxlength="7" autocomplete="off" spellcheck="false"
                                aria-describedby="customColorHelp">
                            <input type="hidden" name="accent_color_custom" id="accentColorCustomValue"
                                value="<?= e($customAccentValue) ?>">
                            <button type="button" class="btn btn-secondary" id="applyCustomColor">Aplicar</button>
                        </div>
                        <span class="custom-color-help" id="customColorHelp">Formato hexadecimal: #RRGGBB. A
                            pré-visualização actualiza-se ao escolher ou aplicar a cor.</span>
                    </div>

                    <!-- Preview visual da cor -->
                    <div class="color-preview">
                        <h4>Pré-visualização</h4>
                        <div class="preview-row">
                            <span class="preview-badge" style="background:<?= e($accentColor) ?>">Badge</span>
                            <span class="preview-link" style="color:<?= e($accentColor) ?>">Link de exemplo</span>
                            <button class="preview-btn" style="background:<?= e($accentColor) ?>">Botão</button>
                            <div class="preview-bar">
                                <div class="preview-bar-fill" style="background:<?= e($accentColor) ?>;width:75%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2: TEMA PADRÃO ════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-moon"></i></span>
                        <div>
                            <h3>Tema Padrão</h3>
                            <p>Define se o site abre em modo escuro ou claro por padrão.</p>
                        </div>
                    </div>
                    <div class="theme-options">
                        <label class="theme-card <?= $darkModeDefault === '1' ? 'selected' : '' ?>">
                            <input type="radio" name="dark_mode_default" value="1"
                                <?= $darkModeDefault === '1' ? 'checked' : '' ?>>
                            <div class="theme-preview dark-preview">
                                <div class="theme-header"></div>
                                <div class="theme-body">
                                    <div class="theme-line"></div>
                                    <div class="theme-line short"></div>
                                    <div class="theme-line"></div>
                                </div>
                            </div>
                            <span>Modo Escuro</span>
                        </label>
                        <label class="theme-card <?= $darkModeDefault === '0' ? 'selected' : '' ?>">
                            <input type="radio" name="dark_mode_default" value="0"
                                <?= $darkModeDefault === '0' ? 'checked' : '' ?>>
                            <div class="theme-preview light-preview">
                                <div class="theme-header"></div>
                                <div class="theme-body">
                                    <div class="theme-line"></div>
                                    <div class="theme-line short"></div>
                                    <div class="theme-line"></div>
                                </div>
                            </div>
                            <span>Modo Claro</span>
                        </label>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 3: TIPOGRAFIA ════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-font"></i></span>
                        <div>
                            <h3>Tipografia</h3>
                            <p>Escolhe as fontes para títulos e corpo de texto.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Fonte para Títulos</label>
                            <select name="font_heading" class="form-control font-select" id="fontHeadingSelect">
                                <?php foreach ($headingFonts as $font): ?>
                                <option value="<?= e($font) ?>" <?= $fontHeading === $font ? 'selected' : '' ?>
                                    style="font-family:'<?= e($font) ?>',sans-serif"><?= $font ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview" style="font-family:'<?= e($fontHeading) ?>',sans-serif"
                                id="headingPreview">
                                Título Grande e Impactante
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fonte para Corpo de Texto</label>
                            <select name="font_body" class="form-control font-select" id="fontBodySelect">
                                <?php foreach ($bodyFonts as $font): ?>
                                <option value="<?= e($font) ?>" <?= $fontBody === $font ? 'selected' : '' ?>
                                    style="font-family:'<?= e($font) ?>',sans-serif"><?= $font ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview" style="font-family:'<?= e($fontBody) ?>',sans-serif"
                                id="bodyPreview">
                                Este é um parágrafo de exemplo para demonstrar a aparência da fonte seleccionada no
                                corpo de texto do site.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 4: IMAGENS ═══════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-image"></i></span>
                        <div>
                            <h3>Imagens do Site</h3>
                            <p>Pré-visualiza os ficheiros e identifica onde cada configuração é utilizada.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="photoHeroInput">Foto do Hero (nome do ficheiro)</label>
                            <input id="photoHeroInput" type="text" name="photo_hero" class="form-control"
                                value="<?= e($heroPhoto) ?>" placeholder="hero.jpg" autocomplete="off">
                            <span class="form-hint">Ficheiro em <code>/assets/img/profile/</code>.</span>
                            <?php if ($heroPhoto !== '' && $heroPreview): ?>
                            <div class="img-preview">
                                <img src="<?= e($heroPreview['url']) ?>"
                                    alt="Pré-visualização do ficheiro reservado para Hero" loading="lazy">
                            </div>
                            <span class="asset-status is-ok"><i class="fas fa-check-circle"></i> Ficheiro encontrado:
                                <?= e($heroPreview['file']) ?>. Está guardado, mas não é utilizado pelo Hero
                                actual.</span>
                            <?php elseif ($heroPhoto !== ''): ?>
                            <span class="asset-status is-error"><i class="fas fa-exclamation-circle"></i> O nome antigo
                                continua guardado, mas o ficheiro não existe em <code>/assets/img/profile/</code>. Limpa
                                este campo para manter a apresentação actual.</span>
                            <?php else: ?>
                            <span class="asset-status"><i class="fas fa-info-circle"></i> Configuração correcta: o Hero
                                mantém o editor de código e não tem uma fotografia associada.</span>
                            <?php endif; ?>
                            <div class="asset-note">
                                <strong>Estado na homepage:</strong> a versão actual de <code>home.php</code> mantém o
                                editor de código na área Hero. O campo <code>photo_hero</code> fica reservado e não
                                altera essa apresentação. Deixa-o vazio para preservar o desenho actual.
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="photoProfileInput">Foto do About (fallback)</label>
                            <input id="photoProfileInput" type="text" name="photo_profile" class="form-control"
                                value="<?= e($aboutPhoto) ?>" placeholder="about.jpg" autocomplete="off">
                            <span class="form-hint">Ficheiro de reserva em <code>/assets/img/profile/</code>.</span>
                            <?php if ($aboutFallbackPreview): ?>
                            <div class="img-preview">
                                <img src="<?= e($aboutFallbackPreview['url']) ?>"
                                    alt="Imagem alternativa configurada para About" loading="lazy">
                            </div>
                            <span class="asset-status is-ok"><i class="fas fa-check-circle"></i> Fallback encontrado:
                                <?= e($aboutFallbackPreview['file']) ?></span>
                            <?php elseif ($aboutPhoto !== '' && $aboutPublicPreview !== null): ?>
                            <span class="asset-status is-warning"><i class="fas fa-info-circle"></i> O fallback antigo
                                não foi encontrado. A imagem pública está funcional através do administrador activo.
                                Para manter uma cópia de reserva, actualiza este campo para
                                <code><?= e($aboutPublicPreview['file']) ?></code>.</span>
                            <?php elseif ($aboutPhoto !== ''): ?>
                            <span class="asset-status is-error"><i class="fas fa-exclamation-circle"></i> O fallback
                                está guardado, mas o ficheiro não existe em assets/img/profile/ e não há uma fotografia
                                activa válida para o substituir.</span>
                            <?php else: ?>
                            <span class="asset-status"><i class="fas fa-info-circle"></i> Nenhuma imagem de fallback
                                definida.</span>
                            <?php endif; ?>
                            <div class="asset-note">
                                <strong>Imagem actualmente usada no About:</strong>
                                <?php if ($aboutPublicPreview): ?>
                                <div class="img-preview">
                                    <img src="<?= e($aboutPublicPreview['url']) ?>"
                                        alt="Imagem actualmente apresentada no About" loading="lazy">
                                </div>
                                <span class="asset-status is-ok"><i class="fas fa-check-circle"></i> Origem:
                                    <?= e($aboutPublicSource) ?>.</span>
                                <?php else: ?>
                                <span class="asset-status is-warning"><i class="fas fa-exclamation-triangle"></i> Não
                                    foi encontrada uma foto de perfil activa nem um fallback válido; o site apresenta o
                                    ícone de substituição.</span>
                                <?php endif; ?>
                                A lógica pública dá prioridade à foto do administrador activo em
                                <code>_employees.photo_employees</code>. Só usa <code>photo_profile</code> quando essa
                                foto não está disponível.
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="faviconInput">Favicon (nome do ficheiro)</label>
                            <input id="faviconInput" type="text" name="favicon" class="form-control"
                                value="<?= e($faviconFile) ?>" placeholder="favicon.ico" autocomplete="off">
                            <span class="form-hint">Ficheiro em <code>/assets/img/icons/</code>. Formatos: ICO, PNG ou
                                SVG.</span>
                            <?php if ($faviconPreview): ?>
                            <div class="img-preview icon-preview">
                                <img src="<?= e($faviconPreview['url']) ?>" alt="Pré-visualização do favicon">
                            </div>
                            <span class="asset-status is-ok"><i class="fas fa-check-circle"></i> Ficheiro encontrado:
                                <?= e($faviconPreview['file']) ?>. Será usado no site e no painel.</span>
                            <?php elseif ($faviconFile !== ''): ?>
                            <span class="asset-status is-error"><i class="fas fa-exclamation-circle"></i> O nome está
                                guardado, mas o ficheiro não foi encontrado em assets/img/icons/.</span>
                            <?php else: ?>
                            <span class="asset-status"><i class="fas fa-info-circle"></i> Sem favicon personalizado;
                                serão usados os ícones padrão do JMbenga.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-save"></i>
                        Guardar Aparência</button>
                </div>
            </form>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    (() => {
        const form = document.getElementById('appearanceForm');
        const submitBtn = document.getElementById('submitBtn');
        const accentInputs = [...document.querySelectorAll('input[name="accent_color"]')];
        const customPicker = document.getElementById('customColorPicker');
        const customHex = document.getElementById('customColorHex');
        const customValue = document.getElementById('accentColorCustomValue');
        const applyBtn = document.getElementById('applyCustomColor');
        const csrfInput = form.querySelector('[name="csrf_token"]');
        const hexPattern = /^#[0-9A-F]{6}$/i;

        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText =
                    'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icon = document.createElement('i');
            icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}`;
            icon.setAttribute('aria-hidden', 'true');
            toast.append(icon, document.createTextNode(String(message ?? '')));
            container.appendChild(toast);

            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                window.setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function clearPaletteSelection() {
            accentInputs.forEach(input => {
                input.checked = false;
            });
            document.querySelectorAll('.color-option').forEach(option => option.classList.remove('selected'));
        }

        function updateColorPreview(color) {
            const badge = document.querySelector('.preview-badge');
            const link = document.querySelector('.preview-link');
            const button = document.querySelector('.preview-btn');
            const bar = document.querySelector('.preview-bar-fill');
            if (badge) badge.style.backgroundColor = color;
            if (link) link.style.color = color;
            if (button) button.style.backgroundColor = color;
            if (bar) bar.style.backgroundColor = color;
        }

        function applyCustomColor(color) {
            const normalized = color.toUpperCase();
            customValue.value = normalized;
            customHex.value = normalized;
            customPicker.value = normalized;
            clearPaletteSelection();
            updateColorPreview(normalized);
        }

        // Uma cor escolhida na paleta limpa o valor personalizado oculto.
        accentInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (!input.checked) return;
                customValue.value = '';
                customPicker.value = input.value;
                customHex.value = input.value;
                clearPaletteSelection();
                input.checked = true;
                const option = input.closest('.color-option');
                if (option) option.classList.add('selected');
                updateColorPreview(input.value);
            });
        });

        // O selector nativo aplica imediatamente a cor escolhida.
        customPicker.addEventListener('input', () => {
            applyCustomColor(customPicker.value);
        });

        // O campo hexadecimal actualiza o valor submetido; valores inválidos
        // permanecem visíveis para que o servidor os rejeite em vez de guardar outra cor.
        customHex.addEventListener('input', () => {
            const value = customHex.value.trim();
            customValue.value = value;
            clearPaletteSelection();
            if (hexPattern.test(value)) {
                customPicker.value = value;
                updateColorPreview(value);
                customHex.setCustomValidity('');
            } else {
                customHex.setCustomValidity('Introduz uma cor no formato #RRGGBB.');
            }
        });

        applyBtn.addEventListener('click', () => {
            const color = customHex.value.trim();
            if (!hexPattern.test(color)) {
                customHex.setCustomValidity('Introduz uma cor no formato #RRGGBB.');
                customHex.reportValidity();
                showToast('Formato de cor inválido. Usa #RRGGBB.', 'error');
                return;
            }
            customHex.setCustomValidity('');
            applyCustomColor(color);
            showToast('Cor personalizada aplicada à pré-visualização.', 'success');
        });

        document.getElementById('fontHeadingSelect').addEventListener('change', function() {
            document.getElementById('headingPreview').style.fontFamily = `'${this.value}', sans-serif`;
        });
        document.getElementById('fontBodySelect').addEventListener('change', function() {
            document.getElementById('bodyPreview').style.fontFamily = `'${this.value}', sans-serif`;
        });

        document.querySelectorAll('.theme-card input[name="dark_mode_default"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.theme-card').forEach(card => card.classList.remove(
                    'selected'));
                const card = this.closest('.theme-card');
                if (card) card.classList.add('selected');
            });
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            const selectedPreset = accentInputs.some(input => input.checked);
            if (!selectedPreset && !hexPattern.test(customValue.value.trim())) {
                showToast('Selecciona uma cor da paleta ou introduz uma cor hexadecimal válida.',
                    'error');
                customHex.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML =
            '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> A guardar…';

            try {
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfInput.value
                    }
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (_) {
                    throw new Error(
                        'O servidor devolveu uma resposta inesperada. Verifica a sessão e tenta novamente.'
                        );
                }

                if (!response.ok || !data.success) {
                    const messages = Array.isArray(data.errors) ? data.errors : [data.message ||
                        'Não foi possível guardar as configurações.'
                    ];
                    messages.forEach(message => showToast(message, 'error'));
                    return;
                }

                showToast(data.message || 'Aparência actualizada com sucesso.', 'success');
                window.setTimeout(() => window.location.reload(), 1000);
            } catch (error) {
                showToast(error instanceof Error ? error.message :
                    'Erro de rede ao guardar as configurações.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML =
                '<i class="fas fa-save" aria-hidden="true"></i> Guardar Aparência';
            }
        });
    })();
    </script>
</body>

</html>