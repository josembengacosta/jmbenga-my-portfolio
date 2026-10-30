<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Novo Projecto
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

// Usa MAX_UPLOAD_SIZE quando configurado; caso contrário, respeita os limites PHP.
if (!defined('MAX_UPLOAD_SIZE')) {
    $jmbengaIniSizeToBytes = static function (string $value): int {
        $value = trim($value);
        if ($value === '' || !preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)B?$/i', $value, $matches)) {
            return 0;
        }
        $number = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');
        $factor = match ($unit) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };
        return (int) min(PHP_INT_MAX, round($number * $factor));
    };
    $uploadLimit = $jmbengaIniSizeToBytes((string) ini_get('upload_max_filesize'));
    $postLimit = $jmbengaIniSizeToBytes((string) ini_get('post_max_size'));
    // Reserva margem para os campos multipart do formulário.
    if ($postLimit > 262144) {
        $postLimit -= 262144;
    }
    $limits = array_values(array_filter([$uploadLimit, $postLimit], static fn (int $size): bool => $size > 0));
    define('MAX_UPLOAD_SIZE', $limits ? min($limits) : 5 * 1024 * 1024);
}

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = $_SESSION['admin_email'] ?? '';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;

// ── Contadores globais ─────────────────────────────────────
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();

// ── Sessão & cliente ───────────────────────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Categorias disponíveis ─────────────────────────────────
$categories = [
    'web'       => ['label' => 'Web',       'icon' => 'fa-globe',       'color' => '#2563eb'],
    'mobile'    => ['label' => 'Mobile',    'icon' => 'fa-mobile-alt',  'color' => '#10b981'],
    'api'       => ['label' => 'API',       'icon' => 'fa-plug',        'color' => '#f59e0b'],
    'desktop'   => ['label' => 'Desktop',   'icon' => 'fa-desktop',     'color' => '#8b5cf6'],
    'design'    => ['label' => 'Design',    'icon' => 'fa-paint-brush', 'color' => '#ec4899'],
    'other'     => ['label' => 'Outro',     'icon' => 'fa-ellipsis-h',  'color' => '#64748b'],
];

// ── Recuperar dados do formulário em caso de erro ───────────
$formData   = $_SESSION['form_data']   ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<body>

    <?php include __DIR__ . '/../../include/sidebar.php'; ?>

    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>

        <div class="content project-form-page">
            <!-- O restante do formulário mantém‑se exatamente como já está -->

            <!-- ═══ CABEÇALHO + BREADCRUMB ════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/projects">Projectos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Novo Projecto</span>
                    </div>
                    <h1 class="page-title">Criar Novo Projecto</h1>
                    <p class="page-sub">Preenche os campos abaixo para adicionar um novo projecto ao portfólio.</p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/projects" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="projectForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Projecto
                    </button>
                </div>
            </div>

            <!-- ═══ ALERTAS DE ERRO ═══════════════════════════════════════ -->
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Corrige os seguintes erros:</strong>
                    <ul style="margin:.3rem 0 0 1.2rem">
                        <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>

            <!-- ═══ FORMULÁRIO ════════════════════════════════════════════ -->
            <form id="projectForm" novalidate data-api="<?= e(BASE_URL) ?>/jm-panel/pages/projects/add-process.php">

                <!-- CSRF token escondido – o JS vai usá‑lo -->
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1 — INFORMAÇÕES BÁSICAS ════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-info-circle"></i></span>
                        <div>
                            <h3>Informação principal e publicação</h3>
                            <p>Título, slug, categoria e estado público do projecto.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Título -->
                        <div class="form-group">
                            <label class="form-label" for="title_project">
                                Título <span class="required">*</span>
                            </label>
                            <input type="text" id="title_project" name="title_project" class="form-control"
                                value="<?= e($formData['title_project'] ?? '') ?>" placeholder="Ex: E-commerce App"
                                maxlength="200" required autofocus>
                            <span class="form-hint">Máximo 200 caracteres.</span>
                        </div>

                        <!-- Slug -->
                        <div class="form-group">
                            <label class="form-label" for="slug_project">
                                Slug <span class="required">*</span>
                            </label>
                            <div class="input-with-icon">
                                <input type="text" id="slug_project" name="slug_project" class="form-control"
                                    value="<?= e($formData['slug_project'] ?? '') ?>" placeholder="e-commerce-app"
                                    maxlength="200" required>
                                <button type="button" class="input-icon-btn" id="generateSlugBtn"
                                    title="Gerar slug automaticamente">
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                            <span class="form-hint">Identificador único na URL. Apenas letras minúsculas, números e
                                hífens.</span>
                        </div>

                        <!-- Categoria -->
                        <div class="form-group">
                            <label class="form-label" for="category_project">
                                Categoria <span class="required">*</span>
                            </label>
                            <div class="category-select-grid">
                                <?php foreach ($categories as $key => $cat): ?>
                                <label
                                    class="category-option <?= ($formData['category_project'] ?? 'web') === $key ? 'selected' : '' ?>"
                                    style="--cat-color:<?= $cat['color'] ?>">
                                    <input type="radio" name="category_project" value="<?= $key ?>"
                                        <?= ($formData['category_project'] ?? 'web') === $key ? 'checked' : '' ?>>
                                    <i class="fas <?= $cat['icon'] ?>"></i>
                                    <span><?= $cat['label'] ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Estado + Destaque + Ordem -->
                        <div class="form-group">
                            <label class="form-label">Estado <span class="required">*</span></label>
                            <div class="radio-group">
                                <label class="radio-item">
                                    <input type="radio" name="status_project" value="published"
                                        <?= ($formData['status_project'] ?? 'draft') === 'published' ? 'checked' : '' ?>>
                                    <span class="radio-badge badge-published">
                                        <i class="fas fa-check-circle"></i> Publicado
                                    </span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="status_project" value="draft"
                                        <?= ($formData['status_project'] ?? 'draft') === 'draft' ? 'checked' : '' ?>>
                                    <span class="radio-badge badge-draft">
                                        <i class="fas fa-clock"></i> Rascunho
                                    </span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="status_project" value="archived">
                                    <span class="radio-badge badge-archived">
                                        <i class="fas fa-archive"></i> Arquivado
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2 — DESTAQUE & ORDENAÇÃO ═══════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-star"></i></span>
                        <div>
                            <h3>Destaque &amp; Ordenação</h3>
                            <p>Define se o projecto aparece em destaque e a sua ordem de exibição.</p>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <!-- Destaque -->
                        <div class="form-group">
                            <label class="form-label">Destaque</label>
                            <label class="switch">
                                <input type="checkbox" name="is_featured" value="1"
                                    <?= ($formData['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                                <span class="switch-label">
                                    <?= ($formData['is_featured'] ?? 0) ? 'Sim ⭐' : 'Não' ?>
                                </span>
                            </label>
                        </div>

                        <!-- Ordem -->
                        <div class="form-group">
                            <label class="form-label" for="display_order">Ordem de Exibição</label>
                            <div class="number-input">
                                <button type="button" class="num-btn" onclick="changeOrder(-1)">−</button>
                                <input type="number" id="display_order" name="display_order"
                                    class="form-control num-field" value="<?= $formData['display_order'] ?? 0 ?>"
                                    min="0">
                                <button type="button" class="num-btn" onclick="changeOrder(1)">+</button>
                            </div>
                            <span class="form-hint">Números menores aparecem primeiro.</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 3 — CONTEÚDO ═══════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-align-left"></i></span>
                        <div>
                            <h3>Estudo de caso</h3>
                            <p>Resumo, objectivos, funcionalidades, descrição e contributo técnico. Use texto simples.
                            </p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Resumo -->
                        <div class="form-group full-width">
                            <label class="form-label" for="summary_project">Resumo</label>
                            <input type="text" id="summary_project" name="summary_project" class="form-control"
                                value="<?= e($formData['summary_project'] ?? '') ?>"
                                placeholder="Breve descrição que aparece nos cards..." maxlength="300">
                            <span class="form-hint" id="summaryCount">0/300 caracteres</span>
                        </div>

                        <!-- Descrição completa -->
                        <div class="form-group full-width">
                            <label class="form-label" for="body_project">Descrição Completa</label>
                            <textarea id="body_project" name="body_project" class="form-control textarea-lg" rows="8"
                                maxlength="20000"
                                placeholder="Descrição detalhada do projecto, funcionalidades, tecnologias utilizadas..."><?= e($formData['body_project'] ?? '') ?></textarea>
                            <span class="form-hint">Texto simples, sem HTML. As quebras de linha são preservadas.</span>
                        </div>
                        <div class="form-group full-width"><label class="form-label"
                                for="objectives_project">Objectivos</label><textarea id="objectives_project"
                                name="objectives_project" class="form-control" rows="4" maxlength="10000"
                                placeholder="Que necessidade ou objectivo este projecto aborda?"><?= e($formData['objectives_project'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group full-width"><label class="form-label"
                                for="features_project">Funcionalidades</label><textarea id="features_project"
                                name="features_project" class="form-control" rows="4" maxlength="10000"
                                placeholder="Descreve as funcionalidades implementadas, uma por linha se preferires."><?= e($formData['features_project'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group full-width"><label class="form-label"
                                for="contribution_project">Contributo técnico</label><textarea id="contribution_project"
                                name="contribution_project" class="form-control" rows="4" maxlength="10000"
                                placeholder="Descreve o teu contributo técnico neste projecto."><?= e($formData['contribution_project'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 4 — TECH STACK ════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-code"></i></span>
                        <div>
                            <h3>Tecnologias</h3>
                            <p>Adiciona as tecnologias utilizadas no projecto.</p>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <div class="tags-wrap" id="tagsWrap">
                            <?php
                        $initialTags = $formData['tech_stack'] ?? '';
                        if (is_string($initialTags)):
                            $decoded = json_decode($initialTags, true) ?: [];
                        else:
                            $decoded = [];
                        endif;
                        foreach ($decoded as $tag):
                        ?>
                            <span class="tag-item">
                                <i class="fas fa-code"></i>
                                <span><?= e($tag) ?></span>
                                <button type="button" class="tag-remove"
                                    onclick="this.parentElement.remove();updateHiddenTech()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="tag-input-row">
                            <div class="input-with-icon" style="flex:1">
                                <i class="fas fa-plus input-icon-left"></i>
                                <input type="text" id="tagInput" class="form-control"
                                    placeholder="Ex: PHP, React, Node.js..." style="padding-left:2.5rem">
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addTag()">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                        <input type="hidden" name="tech_stack" id="techStackHidden"
                            value='<?= e($formData['tech_stack'] ?? '[]') ?>'>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 5 — URLS ═══════════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-link"></i></span>
                        <div>
                            <h3>Links independentes</h3>
                            <p>URLs externos da demonstração, do repositório e do site ao vivo.</p>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="url_demo">
                                <i class="fas fa-external-link-alt"></i> URL Demo
                            </label>
                            <input type="url" id="url_demo" name="url_demo" class="form-control"
                                value="<?= e($formData['url_demo'] ?? '') ?>" placeholder="https://demo.exemplo.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="url_github">
                                <i class="fab fa-github"></i> URL GitHub
                            </label>
                            <input type="url" id="url_github" name="url_github" class="form-control"
                                value="<?= e($formData['url_github'] ?? '') ?>"
                                placeholder="https://github.com/user/repo">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="url_live">
                                <i class="fas fa-globe"></i> URL Live
                            </label>
                            <input type="url" id="url_live" name="url_live" class="form-control"
                                value="<?= e($formData['url_live'] ?? '') ?>" placeholder="https://www.exemplo.com">
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 6 — IMAGEM DE CAPA ═════════════════════════ -->
                <div class="form-section">
                    <div class="section-header"><span class="section-icon"><i class="fas fa-desktop"></i></span>
                        <div>
                            <h3>Demonstração configurada</h3>
                            <p>Define o modo, a disponibilidade e o destino da demonstração gerida pelo preview.</p>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group"><label class="form-label" for="preview_mode">Modo</label><select
                                id="preview_mode" name="preview_mode" class="form-control">
                                <option value="disabled"
                                    <?= ($formData['preview_mode'] ?? 'disabled') === 'disabled' ? 'selected' : '' ?>>
                                    Desactivado</option>
                                <option value="external_embed"
                                    <?= ($formData['preview_mode'] ?? '') === 'external_embed' ? 'selected' : '' ?>>URL
                                    externa incorporada</option>
                                <option value="external_link"
                                    <?= ($formData['preview_mode'] ?? '') === 'external_link' ? 'selected' : '' ?>>URL
                                    externa, abrir separadamente</option>
                                <option value="internal"
                                    <?= ($formData['preview_mode'] ?? '') === 'internal' ? 'selected' : '' ?>>
                                    Demonstração estática interna</option>
                                <option value="gallery"
                                    <?= ($formData['preview_mode'] ?? '') === 'gallery' ? 'selected' : '' ?>>Galeria
                                    visual</option>
                            </select></div>
                        <div class="form-group"><label class="form-label" for="preview_url">URL / caminho</label><input
                                type="text" id="preview_url" name="preview_url" class="form-control"
                                value="<?= e($formData['preview_url'] ?? '') ?>"
                                placeholder="https://demo.example.com ou /demos/project/index.html"></div>
                        <div class="form-group"><label class="form-label"><input type="checkbox" name="preview_enabled"
                                    value="1" <?= !empty($formData['preview_enabled']) ? 'checked' : '' ?>>
                                Disponibilizar preview ao público</label></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-image"></i></span>
                        <div>
                            <h3>Imagem de Capa <span class="required">*</span></h3>
                            <p>Imagem principal que aparece nos cards e na página de detalhe. Formatos: JPEG, PNG ou
                                WebP. Máx: <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB.</p>
                        </div>
                    </div>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" id="cover_project" name="cover_project"
                            accept="image/jpeg,image/png,image/webp" required hidden>
                        <div class="upload-preview" id="coverPreview">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h4>Arrasta ou clica para fazer upload</h4>
                            <p>JPEG, PNG ou WebP</p>
                        </div>
                        <div class="upload-actions" id="uploadActions" style="display:none">
                            <button type="button" class="btn btn-secondary"
                                onclick="document.getElementById('cover_project').click()">
                                <i class="fas fa-sync-alt"></i> Trocar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="removeCover()">
                                <i class="fas fa-trash"></i> Remover
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header"><span class="section-icon"><i class="fas fa-images"></i></span>
                        <div>
                            <h3>Media</h3>
                            <p>Depois de criar o projecto, pode gerir a galeria e as legendas no módulo de media.</p>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/projects" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Criar Projecto
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>


    <script>
    const BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('projectForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');

    // ── Toast helper (adicionado) ─────────────────────────────────
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        const safeType = ['success', 'error'].includes(type) ? type : 'info';
        toast.className = `toast ${safeType}`;
        const icon = document.createElement('i');
        icon.className =
            `fas fa-${safeType === 'success' ? 'check-circle' : safeType === 'error' ? 'times-circle' : 'info-circle'}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = String(message ?? '');
        toast.append(icon, text);
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
        return container;
    }

    // ── Slug automático (existente) ──────────────────────────────
    const titleInput = document.getElementById('title_project');
    const slugInput = document.getElementById('slug_project');
    const genBtn = document.getElementById('generateSlugBtn');

    function slugify(text) {
        return text.toString().toLowerCase().trim()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    }

    titleInput.addEventListener('input', function() {
        if (!slugInput.dataset.manual || slugInput.dataset.manual === 'false') {
            slugInput.value = slugify(this.value);
        }
    });

    genBtn.addEventListener('click', function() {
        slugInput.value = slugify(titleInput.value);
        slugInput.dataset.manual = 'true';
    });

    slugInput.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });

    // ── Categoria select visual (existente) ──────────────────────
    document.querySelectorAll('.category-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input');
            radio.checked = true;
            document.querySelectorAll('.category-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    // ── Upload com preview (existente) ────────────────────────────
    const uploadArea = document.getElementById('uploadArea');
    const coverInput = document.getElementById('cover_project');
    const coverPreview = document.getElementById('coverPreview');
    const uploadActions = document.getElementById('uploadActions');

    uploadArea.addEventListener('click', function(e) {
        if (e.target === coverInput || (e.target.closest && e.target.closest('button'))) return;
        coverInput.click();
    });

    function previewCoverFile(file) {
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            showToast('Formato inválido. Usa JPEG, PNG ou WebP.', 'error');
            removeCover();
            return;
        }
        const maxBytes = <?= (int) MAX_UPLOAD_SIZE ?>;
        if (file.size > maxBytes) {
            showToast('A imagem excede o limite de ' + (maxBytes / 1024 / 1024).toLocaleString('pt-PT', {
                maximumFractionDigits: 2
            }) + ' MB.', 'error');
            removeCover();
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const image = document.createElement('img');
            image.src = String(e.target.result || '');
            image.alt = 'Pré-visualização da imagem de capa';
            coverPreview.replaceChildren(image);
            uploadArea.classList.add('has-image');
            uploadActions.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    coverInput.addEventListener('change', function() {
        previewCoverFile(this.files && this.files[0]);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, event => {
            event.preventDefault();
            event.stopPropagation();
            uploadArea.classList.add('drag-over');
        });
    });
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, event => {
            event.preventDefault();
            event.stopPropagation();
            uploadArea.classList.remove('drag-over');
        });
    });
    uploadArea.addEventListener('drop', event => {
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        coverInput.files = transfer.files;
        previewCoverFile(file);
    });

    function removeCover() {
        coverInput.value = '';
        coverPreview.innerHTML =
            '<i class="fas fa-cloud-upload-alt upload-icon"></i><h4>Arrasta ou clica para fazer upload</h4><p>JPEG, PNG ou WebP</p>';
        uploadArea.classList.remove('has-image');
        uploadActions.style.display = 'none';
    }

    // ── Tags (tech stack) (existente) ─────────────────────────────
    const tagInput = document.getElementById('tagInput');

    function addTag() {
        const val = tagInput.value.trim();
        if (val === '') return;
        const wrap = document.getElementById('tagsWrap');
        const span = document.createElement('span');
        span.className = 'tag-item';
        const codeIcon = document.createElement('i');
        codeIcon.className = 'fas fa-code';
        codeIcon.setAttribute('aria-hidden', 'true');
        const tagText = document.createElement('span');
        tagText.textContent = val;
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'tag-remove';
        removeButton.setAttribute('aria-label', 'Remover tecnologia ' + val);
        const removeIcon = document.createElement('i');
        removeIcon.className = 'fas fa-times';
        removeIcon.setAttribute('aria-hidden', 'true');
        removeButton.appendChild(removeIcon);
        removeButton.addEventListener('click', () => {
            span.remove();
            updateHiddenTech();
        });
        span.append(codeIcon, tagText, removeButton);
        wrap.appendChild(span);
        tagInput.value = '';
        updateHiddenTech();
    }

    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag();
        }
    });

    function updateHiddenTech() {
        const tags = [];
        document.querySelectorAll('#tagsWrap .tag-item span:first-of-type').forEach(el => {
            tags.push(el.textContent.trim());
        });
        document.getElementById('techStackHidden').value = JSON.stringify(tags);
    }

    // ── Ordem +/− (existente) ─────────────────────────────────────
    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        let val = parseInt(field.value) || 0;
        val = Math.max(0, val + delta);
        field.value = val;
    }

    // ── Contador de resumo (existente) ────────────────────────────
    const summaryInput = document.getElementById('summary_project');
    const summaryCount = document.getElementById('summaryCount');
    if (summaryInput && summaryCount) {
        summaryInput.addEventListener('input', function() {
            const len = this.value.length;
            summaryCount.textContent = len + '/300 caracteres';
            summaryCount.style.color = len > 280 ? 'var(--danger)' : 'var(--text-muted)';
        });
        summaryInput.dispatchEvent(new Event('input'));
    }

    // ── Submissão AJAX ─────────────────────────────────────────────
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        let clientValid = true;
        form.querySelectorAll('[required]').forEach(field => {
            const invalidFile = field.type === 'file' && (!field.files || field.files.length === 0);
            const emptyValue = field.type !== 'file' && !String(field.value || '').trim();
            if (invalidFile || emptyValue) {
                field.classList.add('error');
                clientValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        if (!clientValid) {
            showToast('Preenche todos os campos obrigatórios.', 'error');
            return;
        }

        const setBusy = (busy) => {
            submitBtns.forEach(btn => {
                btn.disabled = busy;
                btn.innerHTML = busy ?
                    '<i class="fas fa-spinner fa-spin"></i> A guardar…' :
                    '<i class="fas fa-save"></i> Guardar Projecto';
            });
        };
        setBusy(true);

        const endpoint = form.dataset.api;
        try {
            const formData = new FormData(form);
            const res = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token').value,
                    'Accept': 'application/json'
                }
            });

            const responseText = await res.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('[JMbenga add project] O endpoint não devolveu JSON.', {
                    status: res.status,
                    redirected: res.redirected,
                    responseUrl: res.url,
                    endpoint,
                    responsePreview: responseText.slice(0, 1200)
                });

                const finalUrl = String(res.url || '').toLowerCase();
                if (res.redirected && /(entrar|login)/.test(finalUrl)) {
                    showToast(
                        'A sessão administrativa expirou. Actualiza a página, inicia sessão novamente e tenta guardar.',
                        'error');
                } else if (res.redirected && /lockscreen/.test(finalUrl)) {
                    showToast('O painel está bloqueado. Desbloqueia a sessão e tenta novamente.', 'error');
                } else {
                    showToast(
                        `O servidor devolveu uma resposta inesperada (HTTP ${res.status}). Abre a consola do navegador (F12) para ver os detalhes.`,
                        'error');
                }
                setBusy(false);
                return;
            }

            if (!res.ok || !data.success) {
                if (Array.isArray(data.errors) && data.errors.length) {
                    data.errors.forEach(err => showToast(String(err), 'error'));
                } else {
                    showToast(String(data.message ||
                        `Não foi possível guardar o projecto (HTTP ${res.status}).`), 'error');
                }
                setBusy(false);
                return;
            }

            showToast(data.message || 'Projecto criado com sucesso!', 'success');
            setTimeout(() => {
                window.location.href = BASE + '/jm-panel/projects?msg=created';
            }, 800);
        } catch (err) {
            console.error('[JMbenga add project] Falha ao contactar o endpoint:', {
                endpoint,
                error: err
            });
            showToast(
                'Não foi possível contactar o processador do projecto. Confirma o servidor e a rota; consulta a consola (F12) para detalhes.',
                'error');
            setBusy(false);
        }
    });
    </script>
</body>

</html>