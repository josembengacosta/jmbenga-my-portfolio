<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Novo Projecto
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

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

        <div class="content">
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
            <form id="projectForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/projects/add-process">

                <!-- CSRF token escondido – o JS vai usá‑lo -->
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1 — INFORMAÇÕES BÁSICAS ════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-info-circle"></i></span>
                        <div>
                            <h3>Informações Básicas</h3>
                            <p>Título, slug, categoria e estado do projecto.</p>
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
                            <h3>Conteúdo</h3>
                            <p>Resumo curto e descrição completa do projecto.</p>
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
                                placeholder="Descrição detalhada do projecto, funcionalidades, tecnologias utilizadas..."><?= e($formData['body_project'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 4 — TECH STACK ════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-code"></i></span>
                        <div>
                            <h3>Tech Stack</h3>
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
                            <h3>Links do Projecto</h3>
                            <p>URLs para demonstração, repositório e site ao vivo.</p>
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

    <!-- ═══ ESTILOS ESPECÍFICOS DESTA PÁGINA ══════════════════════ -->
    <style>
    /* ── Breadcrumb ─────────────────────────────────────────────── */
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: .5rem;
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

    /* ── Cabeçalho ──────────────────────────────────────────────── */
    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -.02em;
        margin-bottom: .2rem;
    }

    .page-sub {
        font-size: .8rem;
        color: var(--text-muted)
    }

    /* ── Alertas ────────────────────────────────────────────────── */
    .alert {
        padding: 1rem 1.2rem;
        border-radius: var(--radius);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        font-size: .85rem;
    }

    .alert-danger {
        background: rgba(239, 68, 68, .1);
        border: 1px solid rgba(239, 68, 68, .3);
        color: #f87171;
    }

    .alert-close {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        opacity: .6;
        font-size: .9rem;
    }

    .alert-close:hover {
        opacity: 1
    }

    /* ── Secções do formulário ──────────────────────────────────── */
    .form-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.8rem;
        margin-bottom: 1.2rem;
        transition: border-color .25s;
    }

    .form-section:focus-within {
        border-color: var(--border-acc)
    }

    .section-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.2rem;
        border-bottom: 1px solid var(--border);
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
        flex-shrink: 0;
    }

    .section-header h3 {
        font-family: var(--font-head);
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: .15rem;
    }

    .section-header p {
        font-size: .78rem;
        color: var(--text-muted)
    }

    /* ── Grid do formulário ─────────────────────────────────────── */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }

    .form-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }

    @media (max-width: 768px) {

        .form-grid,
        .form-grid-3 {
            grid-template-columns: 1fr
        }

        .category-select-grid {
            grid-template-columns: repeat(3, 1fr)
        }
    }

    @media (max-width: 480px) {
        .category-select-grid {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    /* ── Grupos de campo ────────────────────────────────────────── */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: .35rem
    }

    .form-group.full-width {
        grid-column: 1 / -1
    }

    .form-label {
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--text-dim);
    }

    .required {
        color: var(--danger)
    }

    /* ── Campos de formulário ───────────────────────────────────── */
    .form-control {
        padding: .65rem .85rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-family: var(--font-body);
        font-size: .88rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow)
    }

    .form-control::placeholder {
        color: var(--text-muted)
    }

    .form-hint {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .15rem
    }

    .textarea-lg {
        resize: vertical;
        min-height: 160px;
        line-height: 1.7
    }

    /* Input com ícone */
    .input-with-icon {
        position: relative;
        display: flex;
        align-items: center
    }

    .input-with-icon .form-control {
        flex: 1
    }

    .input-icon-btn {
        position: absolute;
        right: .5rem;
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: .85rem;
        padding: .3rem;
        transition: color .2s;
    }

    .input-icon-btn:hover {
        color: var(--accent)
    }

    .input-icon-left {
        position: absolute;
        left: .7rem;
        color: var(--text-muted);
        font-size: .8rem;
        pointer-events: none;
        z-index: 1;
    }

    /* ── Categoria select grid ──────────────────────────────────── */
    .category-select-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .5rem;
    }

    .category-option {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .6rem .8rem;
        border-radius: 8px;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: all .2s;
        position: relative;
    }

    .category-option:hover {
        border-color: var(--border-acc)
    }

    .category-option.selected {
        border-color: var(--cat-color);
        background: color-mix(in srgb, var(--cat-color) 10%, transparent);
    }

    .category-option input {
        display: none
    }

    .category-option i {
        color: var(--cat-color);
        font-size: .85rem;
        width: 16px;
        text-align: center
    }

    .category-option span {
        font-size: .82rem;
        font-weight: 500
    }

    /* ── Radio badges (estado) ──────────────────────────────────── */
    .radio-group {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap
    }

    .radio-item input {
        display: none
    }

    .radio-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .5rem .85rem;
        border-radius: 8px;
        border: 1px solid var(--border);
        font-size: .8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
    }

    .radio-item input:checked+.radio-badge {
        border-color: var(--accent);
        background: var(--accent-glow);
    }

    .radio-badge:hover {
        border-color: var(--border-acc)
    }

    /* ── Switch toggle ──────────────────────────────────────────── */
    .switch {
        display: flex;
        align-items: center;
        gap: .75rem;
        cursor: pointer;
    }

    .switch input {
        display: none
    }

    .switch-slider {
        width: 42px;
        height: 24px;
        border-radius: 12px;
        background: var(--border);
        transition: background .25s;
        position: relative;
        flex-shrink: 0;
    }

    .switch-slider::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        transition: transform .25s;
    }

    .switch input:checked+.switch-slider {
        background: var(--accent);
    }

    .switch input:checked+.switch-slider::after {
        transform: translateX(18px);
    }

    .switch-label {
        font-size: .82rem;
        font-weight: 500
    }

    /* ── Number input ───────────────────────────────────────────── */
    .number-input {
        display: flex;
        align-items: center;
        gap: .3rem
    }

    .num-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text-dim);
        cursor: pointer;
        font-size: .9rem;
        transition: all .15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .num-btn:hover {
        border-color: var(--accent);
        color: var(--accent)
    }

    .num-field {
        width: 70px;
        text-align: center;
        appearance: textfield;
    }

    .num-field::-webkit-outer-spin-button,
    .num-field::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0
    }

    /* ── Tags (tech stack) ──────────────────────────────────────── */
    .tags-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-bottom: .75rem
    }

    .tag-item {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .7rem;
        background: rgba(37, 99, 235, .1);
        border: 1px solid rgba(37, 99, 235, .2);
        border-radius: 99px;
        font-size: .78rem;
        color: var(--accent);
        font-family: var(--font-mono);
    }

    .tag-item i {
        font-size: .65rem;
        opacity: .6
    }

    .tag-remove {
        background: none;
        border: none;
        color: var(--accent);
        cursor: pointer;
        font-size: .65rem;
        padding: 0 0 0 .2rem;
        opacity: .5;
    }

    .tag-remove:hover {
        opacity: 1
    }

    .tag-input-row {
        display: flex;
        gap: .5rem
    }

    /* ── Upload de imagem ───────────────────────────────────────── */
    .upload-area {
        border: 2px dashed var(--border);
        border-radius: var(--radius-lg);
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all .25s;
    }

    .upload-area:hover {
        border-color: var(--accent);
        background: rgba(37, 99, 235, .03)
    }

    .upload-area.has-image {
        border-style: solid;
        padding: 0;
        border-radius: var(--radius-lg);
        overflow: hidden
    }

    .upload-preview {
        pointer-events: none
    }

    .upload-preview img {
        width: 100%;
        max-height: 300px;
        object-fit: cover;
        border-radius: var(--radius-lg);
        display: block;
    }

    .upload-icon {
        font-size: 2.5rem;
        color: var(--text-muted);
        margin-bottom: .8rem
    }

    .upload-preview h4 {
        font-family: var(--font-head);
        font-size: 1rem;
        margin-bottom: .2rem
    }

    .upload-preview p {
        font-size: .8rem;
        color: var(--text-muted)
    }

    .upload-actions {
        display: flex;
        gap: .5rem;
        margin-top: 1rem;
        justify-content: center
    }

    /* ── Botões ─────────────────────────────────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1.1rem;
        border-radius: 8px;
        font-family: var(--font-body);
        font-size: .83rem;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        border: 1px solid var(--border);
        white-space: nowrap;
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
        border-top: 1px solid var(--border);
    }

    /* ── Responsividade extra ───────────────────────────────────── */
    @media (max-width: 600px) {
        .page-top {
            flex-direction: column
        }

        .form-section {
            padding: 1.2rem
        }

        .radio-group {
            flex-direction: column
        }
    }
    </style>

    <script>
    const BASE = '<?= BASE_URL ?>'; // já existia
    const form = document.getElementById('projectForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');

    // ── Toast helper (adicionado) ─────────────────────────────────
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML =
            `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i> ${message}`;
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
        if (e.target === coverInput) return;
        coverInput.click();
    });

    coverInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            coverPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            uploadArea.classList.add('has-image');
            uploadActions.style.display = 'flex';
        };
        reader.readAsDataURL(file);
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
        span.innerHTML = '<i class="fas fa-code"></i><span>' + val + '</span>' +
            '<button type="button" class="tag-remove" onclick="this.parentElement.remove();updateHiddenTech()"><i class="fas fa-times"></i></button>';
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

    // ── Submissão AJAX (NOVO, substitui o evento submit antigo) ──
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validação rápida no cliente
        let clientValid = true;
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
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

        // Desactivar botões
        submitBtns.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';
        });

        try {
            const formData = new FormData(form);
            const res = await fetch(form.dataset.api, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                }
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                // Exibir erros
                if (data.errors) {
                    data.errors.forEach(err => showToast(err, 'error'));
                } else {
                    showToast(data.message || 'Erro ao criar projecto.', 'error');
                }
                // Reactivar botões
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Guardar Projecto';
                });
                return;
            }

            // Sucesso
            showToast('Projecto criado com sucesso!', 'success');
            setTimeout(() => {
                window.location.href = BASE + '/jm-panel/projects?msg=created';
            }, 800);

        } catch (err) {
            showToast('Erro de rede. Tenta novamente.', 'error');
            submitBtns.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar Projecto';
            });
        }
    });
    </script>
</body>

</html>