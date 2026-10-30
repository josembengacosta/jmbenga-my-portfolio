<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Editar Skill
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Verificar se o ID foi passado e a skill existe ──────────
$rawEditId = $_GET['id'] ?? null;
$editId = is_string($rawEditId) && ctype_digit($rawEditId) ? (int) $rawEditId : 0;
if ($editId <= 0) {
    redirect('/jm-panel/skills');
}

$stmt = $db->prepare("SELECT * FROM _skills WHERE id_skill = ?");
$stmt->execute([$editId]);
$skill = $stmt->fetch();

if (!$skill) {
    redirect('/jm-panel/skills?msg=notfound');
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

// Se não houve dados de recuperação, usar os dados actuais da skill.
if (!is_array($formData) || empty($formData)) {
    $formData = $skill;
}

// Normalizar valores antes de os inserir no HTML.
$nameValue = isset($formData['name_skill']) && is_string($formData['name_skill'])
    ? $formData['name_skill'] : '';
$percentageValue = filter_var($formData['percentage_skill'] ?? 80, FILTER_VALIDATE_INT);
$percentageValue = ($percentageValue === false) ? 80 : max(0, min(100, $percentageValue));
$categoryValue = $formData['category_skill'] ?? 'frontend';
if (!is_string($categoryValue) || !array_key_exists($categoryValue, $categories)) {
    $categoryValue = 'frontend';
}
$iconValue = isset($formData['icon_skill']) && is_string($formData['icon_skill'])
    ? $formData['icon_skill'] : 'fas fa-code';
$displayOrderValue = filter_var($formData['display_order'] ?? 0, FILTER_VALIDATE_INT);
$displayOrderValue = ($displayOrderValue === false) ? 0 : max(0, $displayOrderValue);
$isVisibleValue = (int) ($formData['is_visible'] ?? 1) === 1;
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
                        <span>Editar Skill</span>
                    </div>
                    <h1 class="page-title">Editar Skill</h1>
                    <p class="page-sub">Altera os campos abaixo para actualizar a skill
                        <strong><?= e($skill['name_skill']) ?></strong>.
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/skills" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="skillForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Actualizar Skill
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
            <form id="skillForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/skills/edit-process">

                <input type="hidden" name="csrf_token" id="csrf_token"
                    value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">
                <input type="hidden" name="id_skill" value="<?= $editId ?>">

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
                                value="<?= e($nameValue) ?>" placeholder="Ex: PHP, React, MySQL" maxlength="100"
                                required autofocus>
                        </div>

                        <!-- Percentagem -->
                        <div class="form-group">
                            <label class="form-label" for="percentage_skill">
                                Percentagem <span class="required">*</span>
                            </label>
                            <div class="percentage-input">
                                <input type="range" id="percentage_skill" name="percentage_skill" min="0" max="100"
                                    value="<?= $percentageValue ?>" required class="range-slider">
                                <div class="percentage-display">
                                    <span id="pctValue"><?= $percentageValue ?>%</span>
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
                                <label class="category-option <?= $categoryValue === $key ? 'selected' : '' ?>"
                                    style="--cat-color:<?= $cat['color'] ?>">
                                    <input type="radio" name="category_skill" value="<?= $key ?>"
                                        <?= $key === 'frontend' ? 'required' : '' ?>
                                        <?= $categoryValue === $key ? 'checked' : '' ?>>
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
                                    <?= $isVisibleValue ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                                <span class="switch-label">
                                    <?= $isVisibleValue ? 'Visível' : 'Oculta' ?>
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
                                    value="<?= e($iconValue) ?>" required placeholder="Ex: fab fa-php, fas fa-database"
                                    maxlength="100">
                                <span class="icon-preview" id="iconPreview">
                                    <i class="<?= e($iconValue) ?>"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Ordem -->
                        <div class="form-group">
                            <label class="form-label" for="display_order">Ordem de Exibição</label>
                            <div class="number-input">
                                <button type="button" class="num-btn" onclick="changeOrder(-1)">−</button>
                                <input type="number" id="display_order" name="display_order"
                                    class="form-control num-field" value="<?= $displayOrderValue ?>" min="0">
                                <button type="button" class="num-btn" onclick="changeOrder(1)">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/skills" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Actualizar Skill
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>
    <!-- Os estilos do formulário são carregados pelo módulo CSS partilhado. -->

    <script>
    const BASE_URL = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('skillForm');
    const submitBtns = document.querySelectorAll('#submitBtn, #submitBtn2');
    const defaultButtonMarkup = '<i class="fas fa-save"></i> Actualizar Skill';

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        const icon = document.createElement('i');
        const text = document.createElement('span');
        const iconName = type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle';
        toast.className = `toast toast-${type}`;
        icon.className = `fas fa-${iconName}`;
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
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (_) {
            throw new Error(
                `O servidor devolveu uma resposta inválida (HTTP ${response.status}). Actualiza a página e tenta novamente.`
            );
        }
    }

    function setSaving(isSaving) {
        submitBtns.forEach(btn => {
            btn.disabled = isSaving;
            btn.innerHTML = isSaving ?
                '<i class="fas fa-spinner fa-spin"></i> A actualizar…' :
                defaultButtonMarkup;
        });
    }

    // Selecção acessível da categoria.
    document.querySelectorAll('.category-option').forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (!radio) return;
            radio.checked = true;
            document.querySelectorAll('.category-option').forEach(item => item.classList.remove(
                'selected'));
            this.classList.add('selected');
        });
        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.category-option').forEach(item => item.classList.remove(
                    'selected'));
                option.classList.add('selected');
            });
        }
    });

    // Preview do ícone Font Awesome.
    const iconInput = document.getElementById('icon_skill');
    const iconPreview = document.getElementById('iconPreview');
    iconInput?.addEventListener('input', function() {
        const icon = iconPreview?.querySelector('i');
        if (icon) icon.className = this.value.trim() || 'fas fa-code';
    });

    // Percentagem.
    const rangeSlider = document.getElementById('percentage_skill');
    const pctValue = document.getElementById('pctValue');
    rangeSlider?.addEventListener('input', function() {
        if (pctValue) pctValue.textContent = `${this.value}%`;
    });

    // Ordem de apresentação.
    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        if (!field) return;
        const current = Number.parseInt(field.value, 10);
        field.value = String(Math.max(0, (Number.isFinite(current) ? current : 0) + delta));
    }

    // Estado visível/oculto.
    const visibilityCheckbox = document.querySelector('input[name="is_visible"]');
    const switchLabel = document.querySelector('.switch-label');
    visibilityCheckbox?.addEventListener('change', function() {
        if (switchLabel) switchLabel.textContent = this.checked ? 'Visível' : 'Oculta';
    });

    form?.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            showToast('Verifica os campos obrigatórios e os valores indicados.', 'error');
            return;
        }

        setSaving(true);
        try {
            const formData = new FormData(form);
            const response = await fetch(form.dataset.api, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token')?.value ?? ''
                }
            });
            const data = await readJsonResponse(response);

            if (!response.ok || !data.success) {
                const errors = Array.isArray(data.errors) ? data.errors : [];
                if (errors.length) errors.forEach(error => showToast(error, 'error'));
                else showToast(data.message || 'Erro ao actualizar a skill.', 'error');
                setSaving(false);
                return;
            }

            showToast(data.message || 'Skill actualizada com sucesso!', 'success');
            window.setTimeout(() => {
                window.location.href = `${BASE_URL}/jm-panel/skills?msg=updated`;
            }, 800);
        } catch (error) {
            showToast(error instanceof Error ? error.message : 'Falha de comunicação com o servidor.',
                'error');
            setSaving(false);
        }
    });
    </script>
</body>

</html>