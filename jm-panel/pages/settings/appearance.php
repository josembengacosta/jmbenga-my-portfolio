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

// Valores actuais
$accentColor     = $formData['accent_color']      ?? '#2563eb';
$darkModeDefault = $formData['dark_mode_default'] ?? '1';
$fontHeading     = $formData['font_heading']      ?? 'Syne';
$fontBody        = $formData['font_body']         ?? 'DM Sans';
$heroPhoto       = $formData['photo_hero']        ?? '';
$aboutPhoto      = $formData['photo_profile']     ?? '';
$faviconFile     = $formData['favicon']           ?? '';

// Paleta de cores alargada (30 cores)
$colorPalette = [
    // ── Azuis ─────────────────────────────
    ['name' => 'Azul Royal',      'color' => '#2563eb', 'class' => 'royal'],
    ['name' => 'Azul Céu',        'color' => '#0ea5e9', 'class' => 'sky'],
    ['name' => 'Azul Marinho',    'color' => '#1e3a8a', 'class' => 'navy'],
    ['name' => 'Azul Petróleo',   'color' => '#0891b2', 'class' => 'teal'],
    ['name' => 'Índigo',          'color' => '#4f46e5', 'class' => 'indigo'],

    // ── Verdes ────────────────────────────
    ['name' => 'Verde Esmeralda', 'color' => '#10b981', 'class' => 'emerald'],
    ['name' => 'Verde Lima',      'color' => '#84cc16', 'class' => 'lime'],
    ['name' => 'Verde Floresta',  'color' => '#166534', 'class' => 'forest'],
    ['name' => 'Menta',           'color' => '#34d399', 'class' => 'mint'],
    ['name' => 'Verde Água',      'color' => '#14b8a6', 'class' => 'aqua'],

    // ── Roxos / Violetas ──────────────────
    ['name' => 'Roxo Violeta',    'color' => '#8b5cf6', 'class' => 'violet'],
    ['name' => 'Lavanda',         'color' => '#a78bfa', 'class' => 'lavender'],
    ['name' => 'Roxo Escuro',     'color' => '#6d28d9', 'class' => 'darkviolet'],
    ['name' => 'Magenta',         'color' => '#d946ef', 'class' => 'magenta'],

    // ── Laranjas / Amarelos ───────────────
    ['name' => 'Laranja Âmbar',   'color' => '#f59e0b', 'class' => 'amber'],
    ['name' => 'Laranja Fogo',    'color' => '#f97316', 'class' => 'orange'],
    ['name' => 'Amarelo Ouro',    'color' => '#eab308', 'class' => 'yellow'],
    ['name' => 'Pêssego',         'color' => '#fb923c', 'class' => 'peach'],

    // ── Rosas / Vermelhos ────────────────
    ['name' => 'Rosa Pink',       'color' => '#ec4899', 'class' => 'pink'],
    ['name' => 'Vermelho Coral',  'color' => '#ef4444', 'class' => 'coral'],
    ['name' => 'Vermelho Escuro', 'color' => '#b91c1c', 'class' => 'darkred'],
    ['name' => 'Rosa Claro',      'color' => '#f472b6', 'class' => 'lightpink'],

    // ── Cianos / Turquesas ────────────────
    ['name' => 'Ciano',           'color' => '#06b6d4', 'class' => 'cyan'],
    ['name' => 'Turquesa',        'color' => '#2dd4bf', 'class' => 'turquoise'],

    // ── Neutros / Escuros ────────────────
    ['name' => 'Cinza Slate',     'color' => '#64748b', 'class' => 'slate'],
    ['name' => 'Cinza Escuro',    'color' => '#334155', 'class' => 'darkgray'],
    ['name' => 'Branco Gelo',     'color' => '#f1f5f9', 'class' => 'ice'],
    ['name' => 'Preto',           'color' => '#000000', 'class' => 'black'],
];

// Fontes disponíveis
$headingFonts = ['Syne', 'Inter', 'Poppins', 'Space Grotesk', 'Outfit', 'Cabinet Grotesk'];
$bodyFonts    = ['DM Sans', 'Inter', 'Poppins', 'Nunito', 'Work Sans', 'Manrope'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>
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
.color-palette {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: .65rem;
    margin-bottom: 1.5rem;
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
    text-align: center
}

.color-option:hover {
    border-color: var(--color);
    transform: translateY(-2px)
}

.color-option.selected {
    border-color: var(--color);
    background: color-mix(in srgb, var(--color) 10%, var(--bg))
}

.color-option input {
    display: none
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
    color: var(--text-dim)
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
    margin-top: .5rem;
    width: 100%;
    max-width: 200px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border)
}

.img-preview img {
    width: 100%;
    display: block
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

            <form id="appearanceForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/settings/appearance-process"
                enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1: COR DE DESTAQUE ════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-palette"></i></span>
                        <div>
                            <h3>Cor de Destaque</h3>
                            <p>Define a cor principal do site (links, botões, badges).</p>
                        </div>
                    </div>
                    <div class="color-palette">
                        <?php foreach ($colorPalette as $palette): ?>
                        <label class="color-option <?= $accentColor === $palette['color'] ? 'selected' : '' ?>"
                            style="--color:<?= $palette['color'] ?>">
                            <input type="radio" name="accent_color" value="<?= $palette['color'] ?>"
                                <?= $accentColor === $palette['color'] ? 'checked' : '' ?>>
                            <span class="color-swatch"></span>
                            <span class="color-name"><?= $palette['name'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="custom-color-row">
                        <label class="form-label">Ou escolhe uma cor personalizada</label>
                        <div class="custom-color-input">
                            <input type="color" id="customColorPicker" value="<?= e($accentColor) ?>"
                                class="color-picker-native">
                            <input type="text" id="customColorHex" name="accent_color_custom" class="form-control"
                                value="<?= e($accentColor) ?>" placeholder="#2563eb" maxlength="7">
                            <button type="button" class="btn btn-secondary" id="applyCustomColor">Aplicar</button>
                        </div>
                    </div>

                    <!-- Preview visual da cor -->
                    <div class="color-preview">
                        <h4>Pré-visualização</h4>
                        <div class="preview-row">
                            <span class="preview-badge" style="background:<?= $accentColor ?>">Badge</span>
                            <span class="preview-link" style="color:<?= $accentColor ?>">Link de exemplo</span>
                            <button class="preview-btn" style="background:<?= $accentColor ?>">Botão</button>
                            <div class="preview-bar">
                                <div class="preview-bar-fill" style="background:<?= $accentColor ?>;width:75%"></div>
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
                                <option value="<?= $font ?>" <?= $fontHeading === $font ? 'selected' : '' ?>
                                    style="font-family:'<?= $font ?>',sans-serif"><?= $font ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview" style="font-family:'<?= $fontHeading ?>',sans-serif"
                                id="headingPreview">
                                Título Grande e Impactante
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fonte para Corpo de Texto</label>
                            <select name="font_body" class="form-control font-select" id="fontBodySelect">
                                <?php foreach ($bodyFonts as $font): ?>
                                <option value="<?= $font ?>" <?= $fontBody === $font ? 'selected' : '' ?>
                                    style="font-family:'<?= $font ?>',sans-serif"><?= $font ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview" style="font-family:'<?= $fontBody ?>',sans-serif"
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
                            <p>Foto do Hero, foto do About e Favicon.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Foto do Hero (nome do ficheiro)</label>
                            <input type="text" name="photo_hero" class="form-control" value="<?= e($heroPhoto) ?>"
                                placeholder="hero.jpg">
                            <span class="form-hint">Coloca o ficheiro em /assets/img/profile/</span>
                            <?php if ($heroPhoto): ?>
                            <div class="img-preview"><img src="<?= BASE_URL ?>/assets/img/profile/<?= e($heroPhoto) ?>"
                                    alt="Hero"></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto do About (nome do ficheiro)</label>
                            <input type="text" name="photo_profile" class="form-control" value="<?= e($aboutPhoto) ?>"
                                placeholder="about.jpg">
                            <span class="form-hint">Coloca o ficheiro em /assets/img/profile/</span>
                            <?php if ($aboutPhoto): ?>
                            <div class="img-preview"><img src="<?= BASE_URL ?>/assets/img/profile/<?= e($aboutPhoto) ?>"
                                    alt="About"></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Favicon (nome do ficheiro)</label>
                            <input type="text" name="favicon" class="form-control" value="<?= e($faviconFile) ?>"
                                placeholder="favicon.ico">
                            <span class="form-hint">Coloca o ficheiro em /assets/img/icons/</span>
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
    const form = document.getElementById('appearanceForm');
    const submitBtn = document.getElementById('submitBtn');
    const accentInputs = document.querySelectorAll('input[name="accent_color"]');
    const customPicker = document.getElementById('customColorPicker');
    const customHex = document.getElementById('customColorHex');
    const applyBtn = document.getElementById('applyCustomColor');

    function showToast(m, t = 'success') {
        const c = document.getElementById('toast-container') || (() => {
            const d = document.createElement('div');
            d.id = 'toast-container';
            d.style.cssText =
                'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
            document.body.appendChild(d);
            return d;
        })();
        const toast = document.createElement('div');
        toast.className = `toast toast-${t}`;
        toast.innerHTML = `<i class="fas fa-${t === 'success' ? 'check-circle' : 'times-circle'}"></i> ${m}`;
        c.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ── Actualizar previews de cor ──────────────────────────────
    function updateColorPreview(color) {
        document.querySelector('.preview-badge').style.background = color;
        document.querySelector('.preview-link').style.color = color;
        document.querySelector('.preview-btn').style.background = color;
        document.querySelector('.preview-bar-fill').style.background = color;
    }

    // ── Selecção de cor da paleta ──────────────────────────────
    accentInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.checked) {
                updateColorPreview(this.value);
                customPicker.value = this.value;
                customHex.value = this.value;
                // Actualizar selecção visual
                document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
                this.closest('.color-option').classList.add('selected');
            }
        });
    });

    // ── Cor personalizada ──────────────────────────────────────
    customPicker.addEventListener('input', function() {
        customHex.value = this.value;
    });

    applyBtn.addEventListener('click', function() {
        const color = customHex.value;
        if (/^#[0-9A-F]{6}$/i.test(color)) {
            updateColorPreview(color);
            // Desmarcar paletas
            accentInputs.forEach(i => i.checked = false);
            document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
            // Actualizar campo hidden para submissão
            let customInput = document.querySelector('input[name="accent_color_custom"]');
            customInput.value = color;
        } else {
            showToast('Formato de cor inválido. Usa #RRGGBB', 'error');
        }
    });

    // ── Preview de fontes ──────────────────────────────────────
    document.getElementById('fontHeadingSelect').addEventListener('change', function() {
        document.getElementById('headingPreview').style.fontFamily = this.value + ',sans-serif';
    });
    document.getElementById('fontBodySelect').addEventListener('change', function() {
        document.getElementById('bodyPreview').style.fontFamily = this.value + ',sans-serif';
    });

    // ── Selecção visual do tema ──────────────────────────────────
    document.querySelectorAll('.theme-card input[name="dark_mode_default"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('selected'));
            this.closest('.theme-card').classList.add('selected');
        });
    });

    // ── Submissão ──────────────────────────────────────────────
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';
        try {
            const fd = new FormData(form);
            const res = await fetch(form.dataset.api, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Aparência actualizada!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                if (data.errors) data.errors.forEach(err => showToast(err, 'error'));
                else showToast(data.message || 'Erro.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Aparência';
        }
    });
    </script>
</body>

</html>