<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alterar Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);

$formErrors = $_SESSION['form_errors'] ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_errors'], $_SESSION['form_success']);
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
                        <a href="<?= BASE_URL ?>/jm-panel/profile">Perfil</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Alterar Senha</span>
                    </div>
                    <h1 class="page-title">Alterar Senha</h1>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/profile" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($formSuccess) ?><button
                    class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>
                <div><strong>Erro:</strong>
                    <ul><?php foreach ($formErrors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-section" style="max-width:500px">
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-lock"></i></span>
                    <div>
                        <h3>Nova Senha</h3>
                        <p>Escolhe uma senha forte e segura.</p>
                    </div>
                </div>
                <form id="passwordForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/profile/change-password-process">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Senha Actual <span
                                class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control"
                            placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Nova Senha <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control"
                            placeholder="Mínimo 8 caracteres" required minlength="8">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Força da senha</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nova Senha <span
                                class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" style="width:100%"><i
                            class="fas fa-save"></i> Alterar Senha</button>
                </form>
            </div>
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
        padding: 1.8rem
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

    .form-group {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        margin-bottom: 1rem
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
        font-size: .88rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s
    }

    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow)
    }

    .strength-bar {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        overflow: hidden;
        margin-top: .5rem
    }

    .strength-fill {
        height: 100%;
        width: 0;
        border-radius: 2px;
        transition: width .3s, background .3s
    }

    .strength-text {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .2rem;
        text-align: right
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
    </style>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const form = document.getElementById('passwordForm');
    const submitBtn = document.getElementById('submitBtn');

    // Força da senha
    document.getElementById('new_password').addEventListener('input', function() {
        const v = this.value;
        let s = 0;
        if (v.length >= 8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[a-z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        const pct = (s / 5) * 100;
        const colors = ['#ef4444', '#f59e0b', '#f59e0b', '#10b981', '#10b981'];
        const labels = ['Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];
        document.getElementById('strengthFill').style.width = pct + '%';
        document.getElementById('strengthFill').style.background = colors[Math.min(s, 4)];
        document.getElementById('strengthText').textContent = labels[Math.min(s, 4)];
    });

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

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (document.getElementById('new_password').value !== document.getElementById('confirm_password')
            .value) {
            showToast('As senhas não coincidem.', 'error');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A alterar…';
        try {
            const fd = new FormData(form);
            const res = await fetch(form.dataset.api, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                }
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                form.reset();
            } else {
                showToast(data.message || 'Erro', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Alterar Senha';
        }
    });
    </script>
</body>

</html>