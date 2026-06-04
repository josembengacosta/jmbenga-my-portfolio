<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações Gerais
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
$configStmt = $db->query("SELECT * FROM _site_config WHERE config_group IN ('general', 'contact') ORDER BY config_key");
$configs = [];
foreach ($configStmt->fetchAll() as $row) {
    $configs[$row['config_key']] = $row['config_value'];
}

// ── Estado da plataforma ────────────────────────────────────
$platform = $db->query("SELECT * FROM _platform WHERE id_platform = 1")->fetch();

// ── Recuperar feedback ─────────────────────────────────────
$formData    = $_SESSION['form_data']    ?? [];
$formErrors  = $_SESSION['form_errors']  ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['form_success']);

if (empty($formData)) {
    $formData = array_merge($configs, $platform ?: []);
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

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/settings">Configurações</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Gerais</span>
                    </div>
                    <h1 class="page-title">Configurações Gerais</h1>
                    <p class="page-sub">Nome do site, informações de contacto, experiência e outros dados essenciais.
                    </p>
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

            <form id="generalForm" novalidate enctype="multipart/form-data"
                data-api="<?= BASE_URL ?>/jm-panel/settings/general-process">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- SECÇÃO 1: Identidade do Site -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-globe"></i></span>
                        <div>
                            <h3>Identidade do Site</h3>
                            <p>Nome, versão e descrição principal.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="site_name">Nome do Site <span class="req">*</span></label>
                            <input type="text" id="site_name" name="site_name" class="form-control"
                                value="<?= e($formData['site_name'] ?? 'JMbenga') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="version">Versão</label>
                            <input type="text" id="version" name="version" class="form-control"
                                value="<?= e($formData['version'] ?? '1.0') ?>" placeholder="1.0">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label" for="hero_subtitle">Subtítulo do Hero</label>
                            <input type="text" id="hero_subtitle" name="hero_subtitle" class="form-control"
                                value="<?= e($formData['hero_subtitle'] ?? '') ?>"
                                placeholder="Transformando ideias em experiências digitais">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label" for="about_text">Texto "Sobre Mim"</label>
                            <textarea id="about_text" name="about_text" class="form-control textarea-lg" rows="4"
                                placeholder="Texto da secção About..."><?= e($formData['about_text'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECÇÃO 2: Experiência & Estatísticas -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-chart-bar"></i></span>
                        <div>
                            <h3>Experiência &amp; Estatísticas</h3>
                            <p>Anos de experiência, número de projectos e clientes.</p>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="years_experience">Anos de Experiência</label>
                            <input type="number" id="years_experience" name="years_experience" class="form-control"
                                value="<?= e($formData['years_experience'] ?? '3') ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="projects_count">Número de Projectos</label>
                            <input type="number" id="projects_count" name="projects_count" class="form-control"
                                value="<?= e($formData['projects_count'] ?? '50') ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="clients_count">Número de Clientes</label>
                            <input type="number" id="clients_count" name="clients_count" class="form-control"
                                value="<?= e($formData['clients_count'] ?? '100') ?>" min="0">
                        </div>
                    </div>
                </div>

                <!-- SECÇÃO 3: Contacto -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-address-book"></i></span>
                        <div>
                            <h3>Informações de Contacto</h3>
                            <p>Email, telefone, localização e link do CV.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="email_contact">Email de Contacto <span
                                    class="req">*</span></label>
                            <input type="email" id="email_contact" name="email_contact" class="form-control"
                                value="<?= e($formData['email_contact'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone_contact">Telefone</label>
                            <input type="text" id="phone_contact" name="phone_contact" class="form-control"
                                value="<?= e($formData['phone_contact'] ?? '') ?>" placeholder="+244 922 030 116">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="location">Localização</label>
                            <input type="text" id="location" name="location" class="form-control"
                                value="<?= e($formData['location'] ?? 'Luanda, Angola') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cv_file">Ficheiro CV (PDF)</label>
                            <input type="file" id="cv_file" name="cv_file" class="form-control" accept=".pdf">
                            <?php if (!empty($configs['cv_file'])): ?>
                            <small style="color:var(--text-muted)">
                                Actual: <strong><?= e($configs['cv_file']) ?></strong>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- SECÇÃO 4: Plataforma -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-server"></i></span>
                        <div>
                            <h3>Plataforma</h3>
                            <p>Controlo dos formulários de contacto e feedback.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Formulário de Contacto</label>
                            <label class="switch">
                                <input type="checkbox" name="allow_contact" value="1"
                                    <?= ($formData['allow_contact'] ?? 1) ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                                <span
                                    class="switch-label"><?= ($formData['allow_contact'] ?? 1) ? 'Aberto' : 'Fechado' ?></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Formulário de Feedback</label>
                            <label class="switch">
                                <input type="checkbox" name="allow_feedback" value="1"
                                    <?= ($formData['allow_feedback'] ?? 1) ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                                <span
                                    class="switch-label"><?= ($formData['allow_feedback'] ?? 1) ? 'Aberto' : 'Fechado' ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-save"></i>
                        Guardar Todas as Alterações</button>
                </div>
            </form>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

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

    .form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.2rem
    }

    .form-group.full-width {
        grid-column: 1/-1
    }

    @media(max-width:768px) {

        .form-grid,
        .form-grid-3 {
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

    .req {
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

    .textarea-lg {
        resize: vertical;
        min-height: 100px;
        line-height: 1.7
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

    <script>
    const form = document.getElementById('generalForm');
    const submitBtn = document.getElementById('submitBtn');

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

    // Labels dos switches
    document.querySelectorAll('.switch input').forEach(chk => {
        chk.addEventListener('change', function() {
            const label = this.parentElement.querySelector('.switch-label');
            label.textContent = this.checked ? 'Aberto' : 'Fechado';
        });
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        let valid = true;
        form.querySelectorAll('[required]').forEach(f => {
            if (!f.value.trim()) {
                f.classList.add('error');
                valid = false;
            } else f.classList.remove('error');
        });
        if (!valid) {
            showToast('Preenche os campos obrigatórios.', 'error');
            return;
        }

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
                showToast(data.message || 'Configurações guardadas!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                if (data.errors) data.errors.forEach(err => showToast(err, 'error'));
                else showToast(data.message || 'Erro.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Todas as Alterações';
        }
    });
    </script>
</body>

</html>