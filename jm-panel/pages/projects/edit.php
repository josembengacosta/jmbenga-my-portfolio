<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Editar Projecto
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

// ── Verificar se o ID foi passado e o projecto existe ────────
$editId = (int)($_GET['id'] ?? 0);
if ($editId <= 0) {
    redirect('/jm-panel/projects');
}

$stmt = $db->prepare("SELECT * FROM _projects WHERE id_project = ?");
$stmt->execute([$editId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/jm-panel/projects?msg=notfound');
}

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
    'web'     => ['label' => 'Web',      'icon' => 'fa-globe',       'color' => '#2563eb'],
    'mobile'  => ['label' => 'Mobile',   'icon' => 'fa-mobile-alt',  'color' => '#10b981'],
    'api'     => ['label' => 'API',      'icon' => 'fa-plug',        'color' => '#f59e0b'],
    'desktop' => ['label' => 'Desktop',  'icon' => 'fa-desktop',     'color' => '#8b5cf6'],
    'design'  => ['label' => 'Design',   'icon' => 'fa-paint-brush', 'color' => '#ec4899'],
    'other'   => ['label' => 'Outro',    'icon' => 'fa-ellipsis-h',  'color' => '#64748b'],
];

// ── Recuperar dados do formulário em caso de erro ───────────
$formData   = $_SESSION['form_data']   ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

// Se não houve erro, usar dados do projecto
if (empty($formData)) {
    $formData = $project;
}

// Tech stack: aceita JSON armazenado na base de dados ou um array recuperado da sessão.
$techArray = [];
$rawTechStack = $formData['tech_stack'] ?? '';
if (is_array($rawTechStack)) {
    $techArray = array_values(array_filter($rawTechStack, static fn ($item): bool => is_string($item)));
} elseif (is_string($rawTechStack) && $rawTechStack !== '') {
    $decoded = json_decode($rawTechStack, true);
    if (is_array($decoded)) {
        $techArray = array_values(array_filter($decoded, static fn ($item): bool => is_string($item)));
    }
}
$techStackJson = json_encode($techArray, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($techStackJson)) {
    $techStackJson = '[]';
}
$currentCover = basename((string) ($project['cover_project'] ?? ''));
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

            <!-- ═══ CABEÇALHO + BREADCRUMB ════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/projects">Projectos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Editar Projecto</span>
                    </div>
                    <h1 class="page-title">Editar Projecto</h1>
                    <p class="page-sub">Altera os campos abaixo para actualizar o projecto.</p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/projects" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="projectForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Actualizar Projecto
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
            <form id="projectForm" novalidate data-api="<?= e(BASE_URL) ?>/jm-panel/pages/projects/edit-process.php">

                <input type="hidden" name="csrf_token" id="csrf_token"
                    value="<?= e((string) ($_SESSION['admin_csrf_token'] ?? '')) ?>">
                <input type="hidden" name="id_project" value="<?= $editId ?>">

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
                        <div class="form-group">
                            <label class="form-label" for="title_project">
                                Título <span class="required">*</span>
                            </label>
                            <input type="text" id="title_project" name="title_project" class="form-control"
                                value="<?= e($formData['title_project'] ?? '') ?>" placeholder="Ex: E-commerce App"
                                maxlength="200" required autofocus>
                        </div>

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
                        </div>

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
                                    <input type="radio" name="status_project" value="archived"
                                        <?= ($formData['status_project'] ?? '') === 'archived' ? 'checked' : '' ?>>
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

                        <div class="form-group">
                            <label class="form-label" for="display_order">Ordem de Exibição</label>
                            <div class="number-input">
                                <button type="button" class="num-btn" id="orderDownBtn"
                                    aria-label="Diminuir ordem">−</button>
                                <input type="number" id="display_order" name="display_order"
                                    class="form-control num-field" value="<?= $formData['display_order'] ?? 0 ?>"
                                    min="0">
                                <button type="button" class="num-btn" id="orderUpBtn"
                                    aria-label="Aumentar ordem">+</button>
                            </div>
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
                        <div class="form-group full-width">
                            <label class="form-label" for="summary_project">Resumo</label>
                            <input type="text" id="summary_project" name="summary_project" class="form-control"
                                value="<?= e($formData['summary_project'] ?? '') ?>"
                                placeholder="Breve descrição que aparece nos cards..." maxlength="300">
                            <span class="form-hint" id="summaryCount">0/300 caracteres</span>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="body_project">Descrição Completa</label>
                            <textarea id="body_project" name="body_project" class="form-control textarea-lg" rows="8"
                                maxlength="20000"
                                placeholder="Descrição detalhada do projecto..."><?= e($formData['body_project'] ?? '') ?></textarea>
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
                            <?php foreach ($techArray as $tag): ?>
                            <span class="tag-item">
                                <i class="fas fa-code"></i>
                                <span class="tag-text"><?= e($tag) ?></span>
                                <button type="button" class="tag-remove" data-remove-tech
                                    aria-label="Remover tecnologia <?= e((string) $tag) ?>">
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
                            <button type="button" class="btn btn-secondary" id="addTagBtn">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                        <input type="hidden" name="tech_stack" id="techStackHidden" value='<?= e($techStackJson) ?>'>
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
                            <h3>Imagem de Capa</h3>
                            <p>Imagem principal do projecto. Formatos: JPEG, PNG ou WebP. Máx:
                                <?= number_format(MAX_UPLOAD_SIZE / 1024 / 1024, 2, ',', '.') ?> MB. Deixa vazio para
                                manter a actual.</p>
                        </div>
                    </div>
                    <div class="upload-area<?= $currentCover !== '' ? ' has-image' : '' ?>" id="uploadArea"
                        data-current-cover="<?= e($currentCover) ?>" tabindex="0" role="button"
                        aria-label="Seleccionar ou substituir imagem de capa">
                        <input type="file" id="cover_project" name="cover_project"
                            accept="image/jpeg,image/png,image/webp" hidden>
                        <div class="upload-preview" id="coverPreview">
                            <?php if ($currentCover !== ''): ?>
                            <img src="<?= e(BASE_URL) ?>/assets/img/projects/<?= e(rawurlencode($currentCover)) ?>"
                                alt="Capa actual">
                            <?php else: ?>
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h4>Arrasta ou clica para seleccionar a capa</h4>
                            <p>JPEG, PNG ou WebP</p>
                            <?php endif; ?>
                        </div>
                        <div class="upload-actions<?= $currentCover !== '' ? ' is-visible' : '' ?>" id="uploadActions">
                            <button type="button" class="btn btn-secondary" id="changeCoverBtn">
                                <i class="fas fa-sync-alt"></i> Trocar
                            </button>
                            <button type="button" class="btn btn-secondary" id="restoreCoverBtn">
                                <i class="fas fa-undo"></i> Restaurar capa actual
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 7 — IMAGENS DO PROJECTO ═══════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-images"></i></span>
                        <div>
                            <h3>Media do projecto</h3>
                            <p>Gere as screenshots e imagens associadas.</p>
                        </div>
                        <a href="<?= BASE_URL ?>/jm-panel/projects/media?id=<?= $editId ?>" class="btn btn-secondary"
                            style="margin-left:auto">
                            <i class="fas fa-external-link-alt"></i> Gerir Todas
                        </a>
                    </div>
                    <div class="media-preview-grid">
                        <?php
        $mediaStmt = $db->prepare("SELECT * FROM _projects_media WHERE id_project = ? ORDER BY display_order ASC LIMIT 4");
        $mediaStmt->execute([$editId]);
        $mediaItems = $mediaStmt->fetchAll();
        ?>
                        <?php if (empty($mediaItems)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Nenhuma imagem adicional.</p>
                        <?php else: ?>
                        <?php foreach ($mediaItems as $img): ?>
                        <div class="media-thumb-small">
                            <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($img['url_media']) ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($mediaItems) >= 4): ?>
                        <a href="<?= BASE_URL ?>/jm-panel/projects/media?id=<?= $editId ?>" class="media-more">
                            + ver todas
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/projects" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Actualizar Projecto
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>



    <script>
    'use strict';
    const BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const MAX_UPLOAD_SIZE_BYTES = <?= (int) MAX_UPLOAD_SIZE ?>;
    const form = document.getElementById('projectForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');
    const coverInput = document.getElementById('cover_project');
    const uploadArea = document.getElementById('uploadArea');
    const coverPreview = document.getElementById('coverPreview');
    const uploadActions = document.getElementById('uploadActions');
    const currentCoverName = uploadArea.dataset.currentCover || '';

    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'project-form-toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'error' : 'success'}`;
        const icon = document.createElement('i');
        icon.className = `fas ${type === 'error' ? 'fa-times-circle' : 'fa-check-circle'}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = String(message);
        toast.append(icon, text);
        container.appendChild(toast);
        window.setTimeout(() => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    function setBusy(busy) {
        submitBtns.forEach(btn => {
            btn.disabled = busy;
            btn.innerHTML = busy ?
                '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> A actualizar…' :
                '<i class="fas fa-save" aria-hidden="true"></i> Actualizar Projecto';
        });
    }

    // Slug automático: deixa de actualizar quando o utilizador edita o slug manualmente.
    const titleInput = document.getElementById('title_project');
    const slugInput = document.getElementById('slug_project');
    const generateSlugBtn = document.getElementById('generateSlugBtn');
    slugInput.dataset.manual = 'false';

    function slugify(value) {
        return String(value || '').toLowerCase().trim()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    }
    titleInput.addEventListener('input', function() {
        if (slugInput.dataset.manual !== 'true') slugInput.value = slugify(this.value);
    });
    generateSlugBtn.addEventListener('click', function() {
        slugInput.value = slugify(titleInput.value);
        slugInput.dataset.manual = 'true';
        slugInput.dispatchEvent(new Event('input', {
            bubbles: true
        }));
    });
    slugInput.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });

    // Categoria e estado: actualiza o cartão seleccionado.
    document.querySelectorAll('.category-option input').forEach(input => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.category-option').forEach(option => {
                option.classList.toggle('selected', option.querySelector('input').checked);
            });
        });
    });
    document.querySelectorAll('.radio-item input').forEach(input => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.radio-item').forEach(item => {
                item.classList.toggle('selected', item.querySelector('input').checked);
            });
        });
    });

    // Actualiza o texto do interruptor de destaque.
    const featuredInput = form.querySelector('input[name="is_featured"]');
    const featuredLabel = form.querySelector('.switch-label');

    function syncFeaturedLabel() {
        if (featuredLabel && featuredInput) featuredLabel.textContent = featuredInput.checked ? 'Sim ⭐' : 'Não';
    }
    if (featuredInput) featuredInput.addEventListener('change', syncFeaturedLabel);
    syncFeaturedLabel();

    // Capa: num formulário de edição, restaurar significa voltar à capa guardada no servidor.
    function renderPlaceholder() {
        coverPreview.replaceChildren();
        const icon = document.createElement('i');
        icon.className = 'fas fa-cloud-upload-alt upload-icon';
        icon.setAttribute('aria-hidden', 'true');
        const heading = document.createElement('h4');
        heading.textContent = 'Arrasta ou clica para seleccionar a capa';
        const detail = document.createElement('p');
        detail.textContent = 'JPEG, PNG ou WebP';
        coverPreview.append(icon, heading, detail);
    }

    function renderCurrentCover() {
        coverInput.value = '';
        if (currentCoverName) {
            coverPreview.replaceChildren();
            const image = document.createElement('img');
            image.src = `${BASE}/assets/img/projects/${encodeURIComponent(currentCoverName)}`;
            image.alt = 'Capa actual';
            coverPreview.appendChild(image);
            uploadArea.classList.add('has-image');
            uploadActions.classList.add('is-visible');
        } else {
            renderPlaceholder();
            uploadArea.classList.remove('has-image');
            uploadActions.classList.remove('is-visible');
        }
    }

    function previewCoverFile(file) {
        if (!file) return;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Formato inválido. Usa JPEG, PNG ou WebP.', 'error');
            renderCurrentCover();
            return;
        }
        if (file.size <= 0 || file.size > MAX_UPLOAD_SIZE_BYTES) {
            showToast(
                `A imagem deve ter entre 1 byte e ${(MAX_UPLOAD_SIZE_BYTES / 1024 / 1024).toLocaleString('pt-PT', { maximumFractionDigits: 2 })} MB.`,
                'error');
            renderCurrentCover();
            return;
        }
        const reader = new FileReader();
        reader.onload = event => {
            const image = document.createElement('img');
            image.src = String(event.target && event.target.result || '');
            image.alt = 'Pré-visualização da nova capa';
            coverPreview.replaceChildren(image);
            uploadArea.classList.add('has-image');
            uploadActions.classList.add('is-visible');
        };
        reader.onerror = () => {
            showToast('Não foi possível pré-visualizar esta imagem.', 'error');
            renderCurrentCover();
        };
        reader.readAsDataURL(file);
    }
    coverInput.addEventListener('change', () => previewCoverFile(coverInput.files && coverInput.files[0]));
    uploadArea.addEventListener('click', event => {
        if (event.target.closest('button') || event.target.closest('a')) return;
        if (event.target === coverInput) return;
        coverInput.click();
    });
    uploadArea.addEventListener('keydown', event => {
        if (event.target !== uploadArea) return;
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            coverInput.click();
        }
    });
    document.getElementById('changeCoverBtn').addEventListener('click', () => coverInput.click());
    document.getElementById('restoreCoverBtn').addEventListener('click', renderCurrentCover);
    ['dragenter', 'dragover'].forEach(name => uploadArea.addEventListener(name, event => {
        event.preventDefault();
        uploadArea.classList.add('drag-over');
    }));
    ['dragleave', 'drop'].forEach(name => uploadArea.addEventListener(name, event => {
        event.preventDefault();
        uploadArea.classList.remove('drag-over');
    }));
    uploadArea.addEventListener('drop', event => {
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file) return;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            coverInput.files = transfer.files;
            previewCoverFile(file);
        } catch (error) {
            showToast('Não foi possível receber a imagem arrastada. Usa o botão Trocar.', 'error');
        }
    });

    // Tecnologias: cria elementos via DOM, sem inserir o texto do utilizador em HTML.
    const tagInput = document.getElementById('tagInput');
    const tagsWrap = document.getElementById('tagsWrap');
    const techStackHidden = document.getElementById('techStackHidden');

    function updateHiddenTech() {
        const tags = Array.from(tagsWrap.querySelectorAll('.tag-item .tag-text'))
            .map(item => item.textContent.trim()).filter(Boolean);
        techStackHidden.value = JSON.stringify(tags);
    }

    function addTag() {
        const value = tagInput.value.trim();
        if (!value) return;
        if (value.length > 80) {
            showToast('Cada tecnologia deve ter no máximo 80 caracteres.', 'error');
            return;
        }
        const existing = Array.from(tagsWrap.querySelectorAll('.tag-text')).some(node => node.textContent.trim()
            .toLowerCase() === value.toLowerCase());
        if (existing) {
            showToast('Esta tecnologia já foi adicionada.', 'error');
            return;
        }
        if (tagsWrap.querySelectorAll('.tag-item').length >= 100) {
            showToast('O máximo é de 100 tecnologias por projecto.', 'error');
            return;
        }
        const item = document.createElement('span');
        item.className = 'tag-item';
        const icon = document.createElement('i');
        icon.className = 'fas fa-code';
        icon.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'tag-text';
        label.textContent = value;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'tag-remove';
        remove.dataset.removeTech = '1';
        remove.setAttribute('aria-label', `Remover tecnologia ${value}`);
        const removeIcon = document.createElement('i');
        removeIcon.className = 'fas fa-times';
        removeIcon.setAttribute('aria-hidden', 'true');
        remove.appendChild(removeIcon);
        item.append(icon, label, remove);
        tagsWrap.appendChild(item);
        tagInput.value = '';
        updateHiddenTech();
    }
    document.getElementById('addTagBtn').addEventListener('click', addTag);
    tagInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addTag();
        }
    });
    tagsWrap.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-tech]');
        if (remove) {
            remove.closest('.tag-item').remove();
            updateHiddenTech();
        }
    });
    // Sincroniza o JSON inicial com os chips renderizados, preservando o conteúdo actual.
    updateHiddenTech();

    // Ordem de apresentação.
    const orderField = document.getElementById('display_order');

    function changeOrder(delta) {
        const value = Number.parseInt(orderField.value, 10) || 0;
        orderField.value = String(Math.max(0, value + delta));
    }
    document.getElementById('orderDownBtn').addEventListener('click', () => changeOrder(-1));
    document.getElementById('orderUpBtn').addEventListener('click', () => changeOrder(1));

    // Contador do resumo.
    const summaryInput = document.getElementById('summary_project');
    const summaryCount = document.getElementById('summaryCount');
    if (summaryInput && summaryCount) {
        const updateSummaryCount = () => {
            summaryCount.textContent = `${summaryInput.value.length}/300 caracteres`;
            summaryCount.classList.toggle('is-near-limit', summaryInput.value.length > 280);
        };
        summaryInput.addEventListener('input', updateSummaryCount);
        updateSummaryCount();
    }

    // Submissão AJAX. A resposta é lida como texto para diagnosticar redirects/erros PHP sem os mascarar como rede.
    form.addEventListener('submit', async event => {
        event.preventDefault();
        let valid = true;
        form.querySelectorAll('[required]').forEach(field => {
            if (!String(field.value || '').trim()) {
                field.classList.add('error');
                valid = false;
            } else {
                field.classList.remove('error');
            }
        });
        if (!/^[a-z0-9-]{2,200}$/.test(slugInput.value)) {
            slugInput.classList.add('error');
            valid = false;
            showToast('O slug só pode conter letras minúsculas, números e hífens.', 'error');
        }
        if (!form.querySelector('input[name="category_project"]:checked')) {
            valid = false;
            showToast('Selecciona uma categoria para o projecto.', 'error');
        }
        if (!form.querySelector('input[name="status_project"]:checked')) {
            valid = false;
            showToast('Selecciona o estado do projecto.', 'error');
        }
        if (!valid) {
            if (!slugInput.classList.contains('error')) showToast('Corrige os campos obrigatórios.',
                'error');
            return;
        }

        setBusy(true);
        const endpoint = form.dataset.api;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token').value,
                    'Accept': 'application/json'
                }
            });
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('[JMbenga edit project] O endpoint não devolveu JSON.', {
                    status: response.status,
                    redirected: response.redirected,
                    responseUrl: response.url,
                    endpoint,
                    responsePreview: responseText.slice(0, 1200)
                });
                const finalUrl = String(response.url || '').toLowerCase();
                if (response.redirected && /(entrar|login)/.test(finalUrl)) {
                    showToast(
                        'A sessão administrativa expirou. Actualiza a página, inicia sessão novamente e tenta guardar.',
                        'error');
                } else if (response.redirected && /lockscreen/.test(finalUrl)) {
                    showToast('O painel está bloqueado. Desbloqueia a sessão e tenta novamente.', 'error');
                } else {
                    showToast(
                        `O servidor devolveu uma resposta inesperada (HTTP ${response.status}). Consulta a consola (F12).`,
                        'error');
                }
                setBusy(false);
                return;
            }
            if (!response.ok || !data.success) {
                if (Array.isArray(data.errors) && data.errors.length) {
                    data.errors.forEach(error => showToast(String(error), 'error'));
                } else {
                    showToast(String(data.message ||
                            `Não foi possível actualizar o projecto (HTTP ${response.status}).`),
                        'error');
                }
                setBusy(false);
                return;
            }
            showToast(data.message || 'Projecto actualizado com sucesso!', 'success');
            window.setTimeout(() => {
                window.location.href = `${BASE}/jm-panel/projects?msg=updated`;
            }, 800);
        } catch (error) {
            console.error('[JMbenga edit project] Falha ao contactar o endpoint:', {
                endpoint,
                error
            });
            showToast(
                'Não foi possível contactar o processador do projecto. Confirma o servidor e a rota; consulta a consola (F12).',
                'error');
            setBusy(false);
        }
    });
    </script>

</body>

</html>