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
<link rel="stylesheet"
    href="<?= e(BASE_URL) ?>/assets/css/jmbenga-skills.css?v=<?= file_exists(ROOT_PATH . '/assets/css/jmbenga-skills.css') ? (int) filemtime(ROOT_PATH . '/assets/css/jmbenga-skills.css') : 1 ?>">
</head>

<body class="skill-form-page">

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

                <input type="hidden" name="csrf_token" id="csrf_token"
                    value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">

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
                                    value="<?= (int) ($formData['percentage_skill'] ?? 80) ?>" class="range-slider">
                                <div class="percentage-display">
                                    <span id="pctValue"><?= (int) ($formData['percentage_skill'] ?? 80) ?>%</span>
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
                                    class="form-control num-field"
                                    value="<?= max(0, (int) ($formData['display_order'] ?? 0)) ?>" min="0">
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


    <script>
    const BASE_URL =
        <?= json_encode(rtrim(BASE_URL, '/'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('skillForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');
    const csrfInput = document.getElementById('csrf_token');

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icon = document.createElement('i');
        icon.className =
            `fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-times-circle' : 'fa-info-circle'}`;
        const text = document.createElement('span');
        text.textContent = String(message ?? '');
        toast.append(icon, text);
        container.appendChild(toast);
        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            window.setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
        return container;
    }

    async function readJsonResponse(response) {
        const body = await response.text();
        try {
            return JSON.parse(body);
        } catch (_error) {
            if (response.redirected) {
                throw new Error('A sessão expirou ou o servidor redireccionou o pedido. Inicia sessão novamente.');
            }
            throw new Error('O servidor devolveu uma resposta inválida. Verifica a sessão e o endpoint de Skills.');
        }
    }

    // Categoria: mantém o cartão seleccionado também quando se navega com teclado.
    document.querySelectorAll('.category-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.category-option').forEach(option => option.classList.remove(
                'selected'));
            radio.closest('.category-option')?.classList.add('selected');
        });
    });

    // Pré-visualização do ícone Font Awesome.
    const iconInput = document.getElementById('icon_skill');
    const iconPreview = document.getElementById('iconPreview');
    iconInput?.addEventListener('input', function() {
        const icon = iconPreview?.querySelector('i');
        if (!icon) return;
        const candidate = this.value.trim();
        icon.className = /^(?:fas|far|fab|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*$/i
            .test(candidate) ?
            candidate : 'fas fa-code';
    });

    // Percentagem.
    const rangeSlider = document.getElementById('percentage_skill');
    const pctValue = document.getElementById('pctValue');
    rangeSlider?.addEventListener('input', function() {
        if (pctValue) pctValue.textContent = `${this.value}%`;
    });

    // Ordenação +/−.
    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        if (!field) return;
        field.value = Math.max(0, (parseInt(field.value, 10) || 0) + delta);
    }
    window.changeOrder = changeOrder;

    // Legenda do estado visível/oculto.
    const visibilityCheckbox = document.querySelector('input[name="is_visible"]');
    const switchLabel = document.querySelector('.switch-label');
    visibilityCheckbox?.addEventListener('change', function() {
        if (switchLabel) switchLabel.textContent = this.checked ? 'Visível' : 'Oculta';
    });

    form?.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (!form.reportValidity()) {
            showToast('Verifica os campos obrigatórios.', 'error');
            return;
        }
        const parsedOrder = Number.parseInt(document.getElementById('display_order')?.value ?? '0', 10);
        if (!Number.isInteger(parsedOrder) || parsedOrder < 0) {
            showToast('A ordem de exibição não pode ser negativa.', 'error');
            return;
        }

        submitBtns.forEach(button => {
            button.disabled = true;
            button.dataset.originalHtml ||= button.innerHTML;
            button.innerHTML = '<i$CURSOR$ class="fas fa-spinner fa-spin"></i> A guardar…';
        });

        try {
            const response = await fetch(form.dataset.api, {
                method: 'POST',
                body: new window.FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfInput?.value ?? ''
                },
                credentials: 'same-origin'
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.success) {
                (Array.isArray(data.errors) ? data.errors : [data.message ||
                    'Não foi possível criar a skill.'
                ])
                .forEach(message => showToast(message, 'error'));
                return;
            }
            showToast(data.message || 'Skill criada com sucesso!', 'success');
            window.setTimeout(() => {
                window.location.href = BASE_URL + '/jm-panel/skills?msg=created';
            }, 650);
        } catch (error) {
            showToast(error instanceof Error ? error.message : 'Falha de comunicação com o servidor.',
                'error');
        } finally {
            submitBtns.forEach(button => {
                button.disabled = false;
                if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
            });
        }
    });
    </script>
</body>

</html>