<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações de Manutenção
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

// ── Estado actual da plataforma ─────────────────────────────
$platform = $db->query("SELECT * FROM _platform WHERE id_platform = 1")->fetch();
$siteStatus     = $platform['status'] ?? 'active';
$maintenanceMsg = $platform['maintenance_msg'] ?? '';
$maintStart     = $platform['maintenance_start'] ?? '';
$maintEnd       = $platform['maintenance_end'] ?? '';
$allowContact   = $platform['allow_contact'] ?? 1;
$allowFeedback  = $platform['allow_feedback'] ?? 1;

// ── Pessoas que pediram notificação ─────────────────────────
$notifyCount = (int) $db->query("SELECT COUNT(*) FROM _maintenance_notify WHERE sent = 0")->fetchColumn();

// ── Recuperar feedback ─────────────────────────────────────
$formSuccess = $_SESSION['form_success'] ?? '';
$formErrors  = $_SESSION['form_errors']  ?? [];
unset($_SESSION['form_success'], $_SESSION['form_errors']);
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
                        <span>Manutenção</span>
                    </div>
                    <h1 class="page-title">Modo de Manutenção</h1>
                    <p class="page-sub">
                        Estado actual:
                        <strong
                            style="color:<?= $siteStatus === 'active' ? 'var(--success)' : ($siteStatus === 'maintenance' ? 'var(--warning)' : 'var(--danger)') ?>">
                            <?= match($siteStatus){'active'=>'Site Activo','maintenance'=>'Em Manutenção','blocked'=>'Bloqueado',default=>ucfirst($siteStatus)} ?>
                        </strong>
                        <?php if ($siteStatus === 'maintenance'): ?>
                        &nbsp;·&nbsp; <?= $notifyCount ?> pessoa(s) aguardam notificação
                        <?php endif; ?>
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

            <form id="maintenanceForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/settings/maintenance-process">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- Estado do Site -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-power-off"></i></span>
                        <div>
                            <h3>Estado do Site</h3>
                            <p>Activa ou desactiva o modo de manutenção.</p>
                        </div>
                    </div>
                    <div class="status-cards">
                        <label class="status-card <?= $siteStatus === 'active' ? 'selected' : '' ?>">
                            <input type="radio" name="status" value="active"
                                <?= $siteStatus === 'active' ? 'checked' : '' ?>>
                            <div class="status-card-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Site Activo</h4>
                            <p>O site está acessível normalmente para todos os visitantes.</p>
                        </label>
                        <label class="status-card <?= $siteStatus === 'maintenance' ? 'selected' : '' ?>">
                            <input type="radio" name="status" value="maintenance"
                                <?= $siteStatus === 'maintenance' ? 'checked' : '' ?>>
                            <div class="status-card-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h4>Em Manutenção</h4>
                            <p>Visitantes veem a página de manutenção. Admins podem aceder ao painel.</p>
                        </label>
                        <label class="status-card <?= $siteStatus === 'blocked' ? 'selected' : '' ?>">
                            <input type="radio" name="status" value="blocked"
                                <?= $siteStatus === 'blocked' ? 'checked' : '' ?>>
                            <div class="status-card-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                                    class="fas fa-ban"></i></div>
                            <h4>Bloqueado</h4>
                            <p>Site completamente inacessível. Apenas o painel de admin funciona.</p>
                        </label>
                    </div>
                </div>

                <!-- Mensagem e Datas -->
                <div class="form-section" id="maintDetails"
                    style="display:<?= $siteStatus === 'maintenance' ? 'block' : 'none' ?>">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-edit"></i></span>
                        <div>
                            <h3>Detalhes da Manutenção</h3>
                            <p>Mensagem exibida e datas previstas.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Mensagem de Manutenção</label>
                            <textarea name="maintenance_msg" class="form-control textarea-sm" rows="3"
                                placeholder="Estamos a realizar melhorias técnicas para lhe oferecer a melhor experiência."><?= e($maintenanceMsg) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Início da Manutenção</label>
                            <input type="datetime-local" name="maintenance_start" class="form-control"
                                value="<?= e($maintStart ? date('Y-m-d\TH:i', strtotime($maintStart)) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fim Previsto</label>
                            <input type="datetime-local" name="maintenance_end" class="form-control"
                                value="<?= e($maintEnd ? date('Y-m-d\TH:i', strtotime($maintEnd)) : '') ?>">
                            <span class="form-hint">Se preenchido, a página mostrará um countdown regressivo.</span>
                        </div>
                    </div>
                </div>

                <!-- Notificações -->
                <?php if ($siteStatus === 'maintenance' && $notifyCount > 0): ?>
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-bell"></i></span>
                        <div>
                            <h3>Notificações Pendentes</h3>
                            <p>Pessoas que pediram para ser avisadas quando o site voltar.</p>
                        </div>
                    </div>
                    <div class="notify-info">
                        <div class="notify-count"><?= $notifyCount ?> pessoa(s) aguardam notificação</div>
                        <p style="font-size:.82rem;color:var(--text-dim)">Quando desactivares o modo de manutenção,
                            podes enviar notificações por email para estas pessoas. (Funcionalidade futura)</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-save"></i>
                        Guardar Estado</button>
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
        margin-bottom: 1.2rem
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

    .status-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem
    }

    @media(max-width:768px) {
        .status-cards {
            grid-template-columns: 1fr
        }
    }

    .status-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 1.5rem 1rem;
        background: var(--bg);
        border: 2px solid var(--border);
        border-radius: var(--radius);
        cursor: pointer;
        transition: all .2s;
        gap: .6rem
    }

    .status-card:hover {
        border-color: var(--border-acc);
        transform: translateY(-2px)
    }

    .status-card.selected {
        border-color: var(--accent);
        background: var(--accent-glow)
    }

    .status-card input {
        display: none
    }

    .status-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem
    }

    .status-card h4 {
        font-family: var(--font-head);
        font-size: .9rem
    }

    .status-card p {
        font-size: .75rem;
        color: var(--text-dim);
        line-height: 1.4
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
        width: 100%
    }

    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow)
    }

    .form-hint {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .15rem
    }

    .textarea-sm {
        resize: vertical;
        min-height: 70px;
        line-height: 1.6
    }

    .notify-info {
        text-align: center
    }

    .notify-count {
        font-size: 1.5rem;
        font-family: var(--font-head);
        font-weight: 800;
        color: var(--accent);
        margin-bottom: .3rem
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
    const form = document.getElementById('maintenanceForm');
    const submitBtn = document.getElementById('submitBtn');
    const statusRadios = document.querySelectorAll('input[name="status"]');
    const maintDetails = document.getElementById('maintDetails');

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

    // Mostrar/ocultar detalhes de manutenção
    statusRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            maintDetails.style.display = this.value === 'maintenance' ? 'block' : 'none';
            // Actualizar selecção visual
            document.querySelectorAll('.status-card').forEach(c => c.classList.remove('selected'));
            this.closest('.status-card').classList.add('selected');
        });
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
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
                showToast(data.message || 'Estado actualizado!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                if (data.errors) data.errors.forEach(err => showToast(err, 'error'));
                else showToast(data.message || 'Erro.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Estado';
        }
    });
    </script>
</body>

</html>