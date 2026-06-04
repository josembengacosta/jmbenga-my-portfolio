<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Editar Depoimento
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Verificar se o ID foi passado e o depoimento existe ──────
$editId = (int)($_GET['id'] ?? 0);
if ($editId <= 0) {
    redirect('/jm-panel/testimonials');
}

$stmt = $db->prepare("SELECT * FROM _testimonials WHERE id_testimonial = ?");
$stmt->execute([$editId]);
$testimonial = $stmt->fetch();

if (!$testimonial) {
    redirect('/jm-panel/testimonials?msg=notfound');
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

// ── Recuperar dados do formulário em caso de erro ───────────
$formData   = $_SESSION['form_data']   ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

// Se não houve erro, usar dados do depoimento
if (empty($formData)) {
    $formData = $testimonial;
}
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
                        <a href="<?= BASE_URL ?>/jm-panel/testimonials">Depoimentos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Editar Depoimento</span>
                    </div>
                    <h1 class="page-title">Editar Depoimento</h1>
                    <p class="page-sub">Altera os campos abaixo para actualizar o depoimento de
                        <strong><?= e($testimonial['name_testimonial']) ?></strong>.</p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/testimonials" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="testimonialForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Actualizar Depoimento
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
            <form id="testimonialForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/testimonials/edit-process"
                enctype="multipart/form-data">

                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                <input type="hidden" name="id_testimonial" value="<?= $editId ?>">

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
                            <span class="form-hint">Deixa vazio para manter a foto actual. Formatos: JPEG, PNG, WebP.
                                Máx: <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB.</span>
                            <?php if (!empty($testimonial['photo_testimonial'])): ?>
                            <div class="current-photo">
                                <img src="<?= BASE_URL ?>/assets/img/testimonials/<?= e($testimonial['photo_testimonial']) ?>"
                                    alt="Foto actual">
                                <span>Foto actual</span>
                            </div>
                            <?php endif; ?>
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
                                rows="5" placeholder="Escreve o depoimento do cliente…"
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
                        <i class="fas fa-save"></i> Actualizar Depoimento
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

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

    .form-group.full-width {
        grid-column: 1/-1
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

    .textarea-lg {
        resize: vertical;
        min-height: 120px;
        line-height: 1.7
    }

    .current-photo {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-top: .5rem;
        padding: .5rem;
        background: var(--bg);
        border-radius: 8px;
        border: 1px solid var(--border)
    }

    .current-photo img {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-acc)
    }

    .current-photo span {
        font-size: .78rem;
        color: var(--text-dim)
    }

    .star-rating {
        display: flex;
        align-items: center;
        gap: .3rem
    }

    .star-btn {
        background: none;
        border: none;
        font-size: 1.3rem;
        color: var(--border);
        cursor: pointer;
        transition: color .15s;
        padding: 0
    }

    .star-btn.active,
    .star-btn:hover {
        color: var(--warning)
    }

    .rating-text {
        font-family: var(--font-mono);
        font-size: .85rem;
        color: var(--text-dim);
        margin-left: .5rem
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
    const form = document.getElementById('testimonialForm');
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

    // ── Avaliação por estrelas ───────────────────────────────────
    const starBtns = document.querySelectorAll('.star-btn');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    starBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const val = parseInt(this.dataset.value);
            ratingValue.value = val;
            ratingText.textContent = val + '/5';
            starBtns.forEach((b, i) => b.classList.toggle('active', i < val));
        });
    });

    // ── Contador de caracteres ──────────────────────────────────
    const bodyInput = document.getElementById('body_testimonial');
    const bodyCount = document.getElementById('bodyCount');
    if (bodyInput && bodyCount) {
        bodyInput.addEventListener('input', function() {
            const len = this.value.length;
            bodyCount.textContent = len + '/2000 caracteres';
            bodyCount.style.color = len > 1900 ? 'var(--danger)' : 'var(--text-muted)';
        });
        bodyInput.dispatchEvent(new Event('input'));
    }

    // ── Ordem +/− ──────────────────────────────────────────────
    function changeOrder(delta) {
        const field = document.getElementById('display_order');
        let val = parseInt(field.value) || 0;
        val = Math.max(0, val + delta);
        field.value = val;
    }

    // ── Switch label ─────────────────────────────────────────────
    const statusCheckbox = document.querySelector('input[name="status_testimonial"]');
    const switchLabel = document.querySelector('.switch-label');
    statusCheckbox.addEventListener('change', function() {
        switchLabel.textContent = this.checked ? 'Visível' : 'Oculto';
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
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A actualizar…';
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
                    showToast(data.message || 'Erro ao actualizar depoimento.', 'error');
                }
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Depoimento';
                });
                return;
            }

            showToast('Depoimento actualizado com sucesso!', 'success');
            setTimeout(() => {
                window.location.href = BASE_URL + '/jm-panel/testimonials?msg=updated';
            }, 800);

        } catch (err) {
            showToast('Erro de rede. Tenta novamente.', 'error');
            submitBtns.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Depoimento';
            });
        }
    });
    </script>
</body>

</html>