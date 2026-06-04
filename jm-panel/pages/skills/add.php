<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Nova Skill
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
    'frontend' => ['label' => 'Frontend', 'icon' => 'fa-desktop', 'color' => '#2563eb'],
    'backend'  => ['label' => 'Backend',  'icon' => 'fa-server',  'color' => '#10b981'],
    'devops'   => ['label' => 'DevOps',   'icon' => 'fa-infinity','color' => '#f59e0b'],
    'design'   => ['label' => 'Design',   'icon' => 'fa-paint-brush','color' => '#ec4899'],
    'other'    => ['label' => 'Outro',    'icon' => 'fa-ellipsis-h','color' => '#64748b'],
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

            <!-- ═══ CABEÇALHO + BREADCRUMB ════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/skills">Skills</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Nova Skill</span>
                    </div>
                    <h1 class="page-title">Adicionar Nova Skill</h1>
                    <p class="page-sub">Preenche os campos abaixo para adicionar uma nova competência ao portfólio.</p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/skills" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="skillForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Skill
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
            <form id="skillForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/skills/add-process">

                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1 — INFORMAÇÕES BÁSICAS ════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-info-circle"></i></span>
                        <div>
                            <h3>Informações da Skill</h3>
                            <p>Nome, percentagem e categoria da competência.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Nome -->
                        <div class="form-group">
                            <label class="form-label" for="name_skill">
                                Nome <span class="required">*</span>
                            </label>
                            <input type="text" id="name_skill" name="name_skill" class="form-control"
                                value="<?= e($formData['name_skill'] ?? '') ?>" placeholder="Ex: PHP, React, MySQL"
                                maxlength="100" required autofocus>
                        </div>

                        <!-- Percentagem -->
                        <div class="form-group">
                            <label class="form-label" for="percentage_skill">
                                Percentagem <span class="required">*</span>
                            </label>
                            <div class="percentage-input">
                                <input type="range" id="percentage_skill" name="percentage_skill" min="0" max="100"
                                    value="<?= $formData['percentage_skill'] ?? 80 ?>" class="range-slider"
                                    oninput="document.getElementById('pctValue').textContent=this.value+'%'">
                                <div class="percentage-display">
                                    <span id="pctValue"><?= $formData['percentage_skill'] ?? 80 ?>%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Categoria -->
                        <div class="form-group">
                            <label class="form-label" for="category_skill">
                                Categoria <span class="required">*</span>
                            </label>
                            <div class="category-select-grid">
                                <?php foreach ($categories as $key => $cat): ?>
                                <label
                                    class="category-option <?= ($formData['category_skill'] ?? 'frontend') === $key ? 'selected' : '' ?>"
                                    style="--cat-color:<?= $cat['color'] ?>">
                                    <input type="radio" name="category_skill" value="<?= $key ?>"
                                        <?= ($formData['category_skill'] ?? 'frontend') === $key ? 'checked' : '' ?>>
                                    <i class="fas <?= $cat['icon'] ?>"></i>
                                    <span><?= $cat['label'] ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Estado (visível/oculta) -->
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <label class="switch">
                                <input type="checkbox" name="is_visible" value="1"
                                    <?= ($formData['is_visible'] ?? 1) ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                                <span class="switch-label">
                                    <?= ($formData['is_visible'] ?? 1) ? 'Visível' : 'Oculta' ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2 — ÍCONE & ORDENAÇÃO ═══════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-icons"></i></span>
                        <div>
                            <h3>Ícone &amp; Ordenação</h3>
                            <p>Define o ícone Font Awesome e a ordem de exibição.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Ícone -->
                        <div class="form-group">
                            <label class="form-label" for="icon_skill">
                                Ícone (classe Font Awesome)
                            </label>
                            <div class="input-with-icon">
                                <input type="text" id="icon_skill" name="icon_skill" class="form-control"
                                    value="<?= e($formData['icon_skill'] ?? 'fas fa-code') ?>"
                                    placeholder="Ex: fab fa-php, fas fa-database" maxlength="100">
                                <span class="icon-preview" id="iconPreview">
                                    <i class="<?= e($formData['icon_skill'] ?? 'fas fa-code') ?>"></i>
                                </span>
                            </div>
                            <span class="form-hint">
                                Usa classes do <a href="https://fontawesome.com/icons" target="_blank"
                                    style="color:var(--accent)">Font Awesome</a> (ex: fab fa-php).
                            </span>
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

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/skills" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Criar Skill
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <!-- ═══ ESTILOS ═══════════════════════════════════════════════ -->
    <style>
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

    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem
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
        margin-bottom: 1.2rem;
        transition: border-color .25s
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

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem
    }

    @media(max-width:768px) {
        .form-grid {
            grid-template-columns: 1fr
        }

        .category-select-grid {
            grid-template-columns: repeat(3, 1fr)
        }
    }

    @media(max-width:480px) {
        .category-select-grid {
            grid-template-columns: repeat(2, 1fr)
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

    .required {
        color: var(--danger)
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

    .form-control::placeholder {
        color: var(--text-muted)
    }

    .form-hint {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .15rem
    }

    .input-with-icon {
        position: relative;
        display: flex;
        align-items: center
    }

    .input-with-icon .form-control {
        padding-right: 2.5rem
    }

    .icon-preview {
        position: absolute;
        right: .7rem;
        color: var(--accent);
        font-size: 1rem;
        pointer-events: none
    }

    .percentage-input {
        display: flex;
        align-items: center;
        gap: 1rem
    }

    .range-slider {
        flex: 1;
        -webkit-appearance: none;
        appearance: none;
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        outline: none
    }

    .range-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--accent);
        cursor: pointer
    }

    .percentage-display {
        min-width: 45px;
        text-align: right;
        font-family: var(--font-mono);
        font-size: .9rem;
        color: var(--accent);
        font-weight: 600
    }

    .category-select-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .5rem
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
        position: relative
    }

    .category-option:hover {
        border-color: var(--border-acc)
    }

    .category-option.selected {
        border-color: var(--cat-color);
        background: color-mix(in srgb, var(--cat-color) 10%, transparent)
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

    .switch {
        display: flex;
        align-items: center;
        gap: .75rem;
        cursor: pointer
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
        flex-shrink: 0
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
        transition: transform .25s
    }

    .switch input:checked+.switch-slider {
        background: var(--accent)
    }

    .switch input:checked+.switch-slider::after {
        transform: translateX(18px)
    }

    .switch-label {
        font-size: .82rem;
        font-weight: 500
    }

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
        justify-content: center
    }

    .num-btn:hover {
        border-color: var(--accent);
        color: var(--accent)
    }

    .num-field {
        width: 70px;
        text-align: center;
        -moz-appearance: textfield
    }

    .num-field::-webkit-outer-spin-button,
    .num-field::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0
    }

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

    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const form = document.getElementById('skillForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');

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

    // ── Categoria select visual ──────────────────────────────────
    document.querySelectorAll('.category-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input');
            radio.checked = true;
            document.querySelectorAll('.category-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    // ── Preview do ícone ────────────────────────────────────────
    const iconInput = document.getElementById('icon_skill');
    const iconPreview = document.getElementById('iconPreview');
    iconInput.addEventListener('input', function() {
        const icon = iconPreview.querySelector('i');
        icon.className = this.value || 'fas fa-code';
    });

    // ── Range slider ─────────────────────────────────────────────
    const rangeSlider = document.getElementById('percentage_skill');
    const pctValue = document.getElementById('pctValue');
    rangeSlider.addEventListener('input', function() {
        pctValue.textContent = this.value + '%';
    });

    // ── Ordem +/− ──────────────────────────────────────────────
    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        let val = parseInt(field.value) || 0;
        val = Math.max(0, val + delta);
        field.value = val;
    }

    // ── Switch label ─────────────────────────────────────────────
    const visibilityCheckbox = document.querySelector('input[name="is_visible"]');
    const switchLabel = document.querySelector('.switch-label');
    visibilityCheckbox.addEventListener('change', function() {
        switchLabel.textContent = this.checked ? 'Visível' : 'Oculta';
    });

    // ── Submissão AJAX ───────────────────────────────────────────
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

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
                if (data.errors) {
                    data.errors.forEach(err => showToast(err, 'error'));
                } else {
                    showToast(data.message || 'Erro ao criar skill.', 'error');
                }
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Guardar Skill';
                });
                return;
            }

            showToast('Skill criada com sucesso!', 'success');
            setTimeout(() => {
                window.location.href = BASE_URL + '/jm-panel/skills?msg=created';
            }, 800);

        } catch (err) {
            showToast('Erro de rede. Tenta novamente.', 'error');
            submitBtns.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar Skill';
            });
        }
    });
    </script>
</body>

</html>