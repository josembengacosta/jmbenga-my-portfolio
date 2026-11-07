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

// Variáveis usadas pelo header/sidebar partilhados.
$adminName      = (string) ($_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin');
$adminInitial   = function_exists('mb_substr') ? strtoupper(mb_substr($adminName, 0, 1, 'UTF-8')) : strtoupper(substr($adminName, 0, 1));
$adminPhoto     = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime      = (int) ($_SESSION['admin_login_time'] ?? time());
$sessionMins    = max(0, (int) floor((time() - $loginTime) / 60));

// Ler apenas os campos necessários; não depender de SELECT *.
$platformStmt = $db->prepare(
    'SELECT id_platform, status, maintenance_msg, maintenance_start, maintenance_end,
            allow_contact, allow_feedback
     FROM _platform WHERE id_platform = ? LIMIT 1'
);
$platformStmt->execute([1]);
$platform = $platformStmt->fetch(PDO::FETCH_ASSOC);
$platformExists = is_array($platform);

$allowedStatuses = ['active', 'maintenance', 'blocked'];
$storedStatus = is_array($platform) && is_string($platform['status'] ?? null)
    ? $platform['status']
    : 'active';
$siteStatus = in_array($storedStatus, $allowedStatuses, true) ? $storedStatus : 'active';
$maintenanceMsg = is_array($platform) ? (string) ($platform['maintenance_msg'] ?? '') : '';
$maintStart = is_array($platform) ? ($platform['maintenance_start'] ?? '') : '';
$maintEnd = is_array($platform) ? ($platform['maintenance_end'] ?? '') : '';
$allowContact = is_array($platform) ? (int) ($platform['allow_contact'] ?? 1) : 1;
$allowFeedback = is_array($platform) ? (int) ($platform['allow_feedback'] ?? 1) : 1;

// O formulário datetime-local trabalha no horário local de Angola.
$toDateTimeLocal = static function ($value): string {
    if (!is_string($value) || trim($value) === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('Africa/Luanda')))->format('Y-m-d\\TH:i');
    } catch (Throwable $exception) {
        return '';
    }
};
$maintStartInput = $toDateTimeLocal($maintStart);
$maintEndInput = $toDateTimeLocal($maintEnd);

// A tabela contém pedidos de aviso ainda não processados.
$notifyCount = (int) $db->query('SELECT COUNT(*) FROM _maintenance_notify WHERE sent = 0')->fetchColumn();

$formSuccess = $_SESSION['form_success'] ?? '';
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_success'], $_SESSION['form_errors']);
$formSuccess = is_string($formSuccess) ? $formSuccess : '';
$formErrors = is_array($formErrors)
    ? array_values(array_filter($formErrors, static fn($error): bool => is_string($error) && $error !== ''))
    : [];
$csrfToken = is_string($_SESSION['admin_csrf_token'] ?? null) ? $_SESSION['admin_csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= e(BASE_URL) ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
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


.form-notice {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    padding: .85rem 1rem;
    border-radius: 10px;
    font-size: .78rem;
    line-height: 1.55;
    margin-bottom: 1rem;
}

.form-notice>i {
    margin-top: .15rem;
    flex: 0 0 auto;
}

.form-notice-warning {
    color: #fbbf24;
    background: rgba(245, 158, 11, .09);
    border: 1px solid rgba(245, 158, 11, .25);
}

.form-notice-neutral {
    color: var(--text-dim);
    background: var(--bg);
    border: 1px solid var(--border);
}

.status-card:focus-within {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}

.status-card input:focus-visible+.status-card-icon {
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.btn:disabled {
    opacity: .55;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

@media (max-width: 520px) {
    .form-section {
        padding: 1.15rem;
    }

    .form-actions {
        justify-content: stretch;
    }

    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
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

            <?php if (!$platformExists): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div><strong>Registo da plataforma não encontrado.</strong>
                    <p>Não é possível alterar o estado do site até existir o registo id_platform = 1 na tabela
                        _platform.</p>
                </div>
            </div>
            <?php endif; ?>

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

            <form id="maintenanceForm" novalidate
                data-api="<?= e(rtrim((string) BASE_URL, '/')) ?>/jm-panel/settings/maintenance-process"
                data-current-status="<?= e($siteStatus) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- Estado do Site -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-power-off"></i></span>
                        <div>
                            <h3>Estado do Site</h3>
                            <p>Controla a disponibilidade pública do portfólio. As alterações entram em vigor
                                imediatamente após guardar.</p>
                        </div>
                    </div>
                    <div class="form-notice form-notice-warning" role="note">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><strong>Atenção:</strong> “Em Manutenção” e “Bloqueado” afectam o acesso público assim que
                            guardares. A opção “Bloqueado” requer confirmação adicional.</span>
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
                            <p>Os visitantes são encaminhados para a página de manutenção. O painel administrativo
                                mantém-se separado.</p>
                        </label>
                        <label class="status-card <?= $siteStatus === 'blocked' ? 'selected' : '' ?>">
                            <input type="radio" name="status" value="blocked"
                                <?= $siteStatus === 'blocked' ? 'checked' : '' ?>>
                            <div class="status-card-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                                    class="fas fa-ban"></i></div>
                            <h4>Bloqueado</h4>
                            <p>Os visitantes são encaminhados para a página de bloqueio. Usa este estado apenas quando
                                pretendes suspender o acesso público.</p>
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
                            <p>Mensagem apresentada aos visitantes e datas informativas da intervenção.</p>
                        </div>
                    </div>
                    <div class="form-notice form-notice-neutral" role="note">
                        <i class="fas fa-clock"></i>
                        <span>As datas não agendam a activação automática. O estado seleccionado acima é aplicado
                            imediatamente quando guardas.</span>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Mensagem de Manutenção</label>
                            <textarea id="maintenance_msg" name="maintenance_msg" class="form-control textarea-sm"
                                rows="3" maxlength="2000"
                                placeholder="Estamos a realizar melhorias técnicas para lhe oferecer a melhor experiência."><?= e($maintenanceMsg) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Início da Manutenção</label>
                            <input type="datetime-local" id="maintenance_start" name="maintenance_start"
                                class="form-control" value="<?= e($maintStartInput) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fim Previsto</label>
                            <input type="datetime-local" id="maintenance_end" name="maintenance_end"
                                class="form-control" value="<?= e($maintEndInput) ?>">
                            <span class="form-hint">A data final é usada como previsão/countdown na página pública. Não
                                desactiva a manutenção automaticamente.</span>
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
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"
                        <?= !$platformExists ? 'disabled' : '' ?>><i class="fas fa-save"></i>
                        Guardar Estado</button>
                </div>
            </form>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    (() => {
        'use strict';

        const form = document.getElementById('maintenanceForm');
        const submitBtn = document.getElementById('submitBtn');
        const statusRadios = document.querySelectorAll('input[name="status"]');
        const maintDetails = document.getElementById('maintDetails');
        if (!form || !submitBtn || !maintDetails) return;

        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText =
                    'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:min(420px,calc(100vw - 2rem))';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            const icon = document.createElement('i');
            icon.className = `fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}`;
            const text = document.createElement('span');
            text.textContent = String(message);
            toast.append(icon, text);
            container.appendChild(toast);

            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                window.setTimeout(() => toast.remove(), 300);
            }, 4500);
        }

        function getSelectedStatus() {
            const selected = form.querySelector('input[name="status"]:checked');
            return selected ? selected.value : '';
        }

        function updateStatusUI() {
            const selectedStatus = getSelectedStatus();
            maintDetails.hidden = selectedStatus !== 'maintenance';
            maintDetails.style.display = selectedStatus === 'maintenance' ? 'block' : 'none';

            document.querySelectorAll('.status-card').forEach(card => {
                const radio = card.querySelector('input[name="status"]');
                card.classList.toggle('selected', Boolean(radio && radio.checked));
            });
        }

        async function confirmCriticalStatus(nextStatus) {
            const currentStatus = form.dataset.currentStatus || '';
            let title = '';
            let message = '';

            if (nextStatus === 'blocked') {
                title = 'Bloquear o acesso público?';
                message =
                    'Os visitantes serão encaminhados para a página de bloqueio assim que esta alteração for guardada.';
            } else if (nextStatus === 'maintenance' && currentStatus !== 'maintenance') {
                title = 'Activar o modo de manutenção?';
                message =
                    'O site público passará imediatamente a apresentar a página de manutenção. As datas não atrasam a activação.';
            } else {
                return true;
            }

            if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                const result = await Swal.fire({
                    icon: 'warning',
                    title,
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: nextStatus === 'blocked' ? 'Sim, bloquear' :
                        'Activar manutenção',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    focusCancel: true
                });
                return Boolean(result && result.isConfirmed);
            }

            return window.confirm(`${title}

${message}`);
        }

        statusRadios.forEach(radio => radio.addEventListener('change', updateStatusUI));
        updateStatusUI();

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (submitBtn.disabled) return;

            const status = getSelectedStatus();
            if (!['active', 'maintenance', 'blocked'].includes(status)) {
                showToast('Selecciona um estado válido para o site.', 'error');
                return;
            }

            const confirmed = await confirmCriticalStatus(status);
            if (!confirmed) return;

            const originalButtonHtml = '<i class="fas fa-save"></i> Guardar Estado';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';
            let saved = false;

            try {
                const csrfInput = form.querySelector('[name="csrf_token"]');
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfInput ? csrfInput.value : ''
                    },
                    credentials: 'same-origin'
                });

                let data;
                try {
                    data = await response.json();
                } catch (parseError) {
                    throw new Error('O servidor devolveu uma resposta inválida.');
                }

                if (!response.ok || !data || data.success !== true) {
                    const errors = data && Array.isArray(data.errors) ? data.errors : [];
                    if (errors.length) errors.forEach(error => showToast(error, 'error'));
                    else showToast((data && data.message) ||
                        `Não foi possível guardar (HTTP ${response.status}).`, 'error');
                    return;
                }

                saved = true;
                showToast(data.message || 'Estado do site actualizado com sucesso.', 'success');
                form.dataset.currentStatus = status;
                window.setTimeout(() => window.location.reload(), 1000);
            } catch (error) {
                showToast(error instanceof Error ? error.message :
                    'Erro de comunicação com o servidor.', 'error');
            } finally {
                if (!saved) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalButtonHtml;
                }
            }
        });
    })();
    </script>
</body>

</html>