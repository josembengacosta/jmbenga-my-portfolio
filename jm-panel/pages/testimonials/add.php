<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Novo Depoimento
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

// ── Recuperar dados do formulário em caso de erro ───────────
$formData   = $_SESSION['form_data']   ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-testimonials.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-testimonials.css') ? filemtime(ROOT_PATH . '/assets/css/jmbenga-testimonials.css') : '1.0.0' ?>">
</head>

<body class="testimonial-form-page">

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
                        <a href="<?= BASE_URL ?>/jm-panel/testimonials">Depoimentos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Novo Depoimento</span>
                    </div>
                    <h1 class="page-title">Adicionar Novo Depoimento</h1>
                    <p class="page-sub">Preenche os campos abaixo para adicionar um depoimento de cliente ao portfólio.
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/testimonials" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="testimonialForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Depoimento
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
            <form id="testimonialForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/testimonials/add-process"
                data-mode="add" data-max-upload-size="<?= (int) MAX_UPLOAD_SIZE ?>" enctype="multipart/form-data">

                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1 — INFORMAÇÕES DO CLIENTE ════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-user-circle"></i></span>
                        <div>
                            <h3>Informações do Cliente</h3>
                            <p>Nome, cargo e empresa do cliente que fez o depoimento.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Nome -->
                        <div class="form-group">
                            <label class="form-label" for="name_testimonial">
                                Nome <span class="required">*</span>
                            </label>
                            <input type="text" id="name_testimonial" name="name_testimonial" class="form-control"
                                value="<?= e($formData['name_testimonial'] ?? '') ?>" placeholder="Ex: Carlos Ribeiro"
                                maxlength="100" required autofocus>
                        </div>

                        <!-- Cargo -->
                        <div class="form-group">
                            <label class="form-label" for="role_testimonial">Cargo</label>
                            <input type="text" id="role_testimonial" name="role_testimonial" class="form-control"
                                value="<?= e($formData['role_testimonial'] ?? '') ?>"
                                placeholder="Ex: CEO, Product Manager" maxlength="100">
                        </div>

                        <!-- Empresa -->
                        <div class="form-group">
                            <label class="form-label" for="company_testimonial">Empresa</label>
                            <input type="text" id="company_testimonial" name="company_testimonial" class="form-control"
                                value="<?= e($formData['company_testimonial'] ?? '') ?>"
                                placeholder="Ex: TechSolutions Inc." maxlength="100">
                        </div>

                        <!-- Foto -->
                        <div class="form-group">
                            <label class="form-label" for="photo_testimonial">Foto do Cliente</label>
                            <input type="file" id="photo_testimonial" name="photo_testimonial" class="form-control"
                                accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">Formatos aceites: JPEG, PNG, WebP. Máx:
                                <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB. Deixa vazio para usar inicial.</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2 — DEPOIMENTO E AVALIAÇÃO ═══════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-star"></i></span>
                        <div>
                            <h3>Depoimento &amp; Avaliação</h3>
                            <p>Texto do depoimento e classificação por estrelas.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Avaliação (estrelas) -->
                        <div class="form-group">
                            <label class="form-label">Avaliação <span class="required">*</span></label>
                            <div class="star-rating">
                                <?php $currentRating = (int)($formData['rating_testimonial'] ?? 5); ?>
                                <input type="hidden" name="rating_testimonial" id="ratingValue"
                                    value="<?= $currentRating ?>">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <button type="button" class="star-btn <?= $s <= $currentRating ? 'active' : '' ?>"
                                    data-value="<?= $s ?>" title="<?= $s ?> estrela(s)">
                                    <i class="fas fa-star"></i>
                                </button>
                                <?php endfor; ?>
                                <span class="rating-text" id="ratingText"><?= $currentRating ?>/5</span>
                            </div>
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
                        </div>

                        <!-- Corpo do depoimento -->
                        <div class="form-group full-width">
                            <label class="form-label" for="body_testimonial">
                                Depoimento <span class="required">*</span>
                            </label>
                            <textarea id="body_testimonial" name="body_testimonial" class="form-control textarea-lg"
                                rows="5" maxlength="2000" maxlength="2000"
                                placeholder="Escreve o depoimento do cliente…"
                                required><?= e($formData['body_testimonial'] ?? '') ?></textarea>
                            <span class="form-hint" id="bodyCount">0/2000 caracteres</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 3 — ESTADO ═══════════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-eye"></i></span>
                        <div>
                            <h3>Estado</h3>
                            <p>Define se o depoimento está visível ou oculto no site.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="status_testimonial" value="visible"
                                <?= ($formData['status_testimonial'] ?? 'visible') === 'visible' ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                            <span class="switch-label">
                                <?= ($formData['status_testimonial'] ?? 'visible') === 'visible' ? 'Visível' : 'Oculto' ?>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/testimonials" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Criar Depoimento
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>


    <script>
    const BASE_URL =
        <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('testimonialForm');
    const submitBtns = Array.from(document.querySelectorAll('#submitBtn, #submitBtn2'));
    const photoInput = document.getElementById('photo_testimonial');
    const maxUploadSize = Number(form?.dataset.maxUploadSize || 0);

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icon = document.createElement('i');
        icon.className =
            `fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}`;
        const text = document.createElement('span');
        text.textContent = String(message);
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
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
        return container;
    }

    async function readJsonResponse(response) {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (_) {
            throw new Error('O servidor devolveu uma resposta inesperada. Verifica a sessão e tenta novamente.');
        }
    }

    function setSubmitting(isSubmitting) {
        submitBtns.forEach(button => {
            if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
            button.disabled = isSubmitting;
            button.innerHTML = isSubmitting ?
                '<i class="fas fa-spinner fa-spin"></i> A guardar…' :
                button.dataset.originalHtml;
        });
    }

    // Rating selector: synchronize hidden value, visual state and accessible state.
    const starBtns = document.querySelectorAll('.star-btn');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    starBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const value = Number.parseInt(btn.dataset.value, 10);
            if (!Number.isInteger(value) || value < 1 || value > 5) return;
            ratingValue.value = String(value);
            ratingText.textContent = `${value}/5`;
            starBtns.forEach((star, index) => {
                const active = index < value;
                star.classList.toggle('active', active);
                star.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });
    });

    // Character counter counts Unicode code points, matching PHP mb_strlen more closely.
    const bodyInput = document.getElementById('body_testimonial');
    const bodyCount = document.getElementById('bodyCount');

    function updateBodyCount() {
        if (!bodyInput || !bodyCount) return;
        const length = Array.from(bodyInput.value).length;
        bodyCount.textContent = `${length}/2000 caracteres`;
        bodyCount.style.color = length > 1900 ? 'var(--danger)' : 'var(--text-muted)';
    }
    if (bodyInput) {
        bodyInput.addEventListener('input', updateBodyCount);
        updateBodyCount();
    }

    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        if (!field) return;
        const value = Number.parseInt(field.value, 10) || 0;
        field.value = String(Math.max(0, value + delta));
    }
    window.changeOrder = changeOrder;

    const statusCheckbox = document.querySelector('input[name="status_testimonial"]');
    const switchLabel = document.querySelector('.switch-label');
    if (statusCheckbox && switchLabel) {
        statusCheckbox.addEventListener('change', () => {
            switchLabel.textContent = statusCheckbox.checked ? 'Visível' : 'Oculto';
        });
    }

    if (photoInput) {
        photoInput.addEventListener('change', () => {
            const file = photoInput.files?. [0];
            if (!file) return;
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                photoInput.value = '';
                showToast('Formato de foto não permitido. Usa JPEG, PNG ou WebP.', 'error');
            } else if (maxUploadSize > 0 && file.size > maxUploadSize) {
                photoInput.value = '';
                showToast(`A foto excede o limite de ${(maxUploadSize / 1024 / 1024).toFixed(1)} MB.`, 'error');
            }
        });
    }

    if (form) {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.submitting === '1') return;

            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                const empty = !String(field.value ?? '').trim();
                field.classList.toggle('error', empty);
                if (empty) valid = false;
            });
            if (!valid) {
                showToast('Preenche todos os campos obrigatórios.', 'error');
                return;
            }

            form.dataset.submitting = '1';
            setSubmitting(true);
            try {
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': document.getElementById('csrf_token')?.value || ''
                    }
                });
                const data = await readJsonResponse(response);
                if (!response.ok || !data.success) {
                    const errors = Array.isArray(data.errors) ? data.errors : [data.message ||
                        'Não foi possível guardar o depoimento.'
                    ];
                    errors.forEach(message => showToast(message, 'error'));
                    form.dataset.submitting = '0';
                    setSubmitting(false);
                    return;
                }
                showToast(data.message || 'Depoimento guardado com sucesso.', 'success');
                window.setTimeout(() => {
                    window.location.href = BASE_URL + '/jm-panel/testimonials?msg=' + (form.dataset
                        .mode === 'edit' ? 'updated' : 'created');
                }, 700);
            } catch (error) {
                showToast(error.message || 'Erro de rede. Tenta novamente.', 'error');
                form.dataset.submitting = '0';
                setSubmitting(false);
            }
        });
    }
    </script>
</body>

</html>