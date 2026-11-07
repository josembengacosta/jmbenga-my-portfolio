<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — IP Whitelist
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

// ── Lista de IPs ────────────────────────────────────────────
$ips = $db->query("
    SELECT w.*, COALESCE(e.first_name, '—') AS added_by_name
    FROM _admin_ip_whitelist w
    LEFT JOIN _employees e ON w.added_by = e.id_employees
    ORDER BY w.active DESC, w.creat_ip DESC
")->fetchAll();

$totalActive = (int) $db->query("SELECT COUNT(*) FROM _admin_ip_whitelist WHERE active = 1")->fetchColumn();
$totalAll    = count($ips);

$csrfToken = $_SESSION['admin_csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-security.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-security.css') ? (int)filemtime(ROOT_PATH . '/assets/css/jmbenga-security.css') : time() ?>">
</head>

<body class="ip-whitelist-page">
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/security">Segurança</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>IP Whitelist</span>
                    </div>
                    <h1 class="page-title">IP Whitelist</h1>
                    <p class="page-sub"><?= $totalActive ?> IPs activos de <?= $totalAll ?> registados.</p>
                </div>
                <button class="btn btn-primary" id="addIpBtn" type="button" aria-expanded="false"
                    aria-controls="addIpForm"><i class="fas fa-plus"></i> Adicionar IP</button>
            </div>

            <div class="security-note" role="note">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <span>Antes de desactivar ou remover um IP, confirma que tens outra forma de acesso autorizada. A
                    aplicação efectiva da whitelist depende da validação do IP no fluxo de autenticação.</span>
            </div>

            <!-- Formulário rápido de adição (oculto por padrão) -->
            <div class="form-section" id="addIpForm" hidden>
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-plus-circle"></i></span>
                    <div>
                        <h3>Adicionar IP à Whitelist</h3>
                    </div>
                </div>
                <form id="ipForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/security/ip-whitelist-process">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Endereço IP <span class="req">*</span></label>
                            <input type="text" name="ip_address" class="form-control"
                                placeholder="192.168.1.1 ou 2001:db8::1" maxlength="45" autocomplete="off"
                                spellcheck="false" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rótulo</label>
                            <input type="text" name="label" class="form-control" placeholder="Ex: Casa, Escritório"
                                maxlength="100">
                        </div>
                        <div class="form-group" style="align-self:flex-end">
                            <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i>
                                Adicionar</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabela -->
            <div class="table-card">
                <?php if (empty($ips)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Nenhum IP na whitelist</h3>
                    <p>Adiciona IPs de confiança para aumentar a segurança.</p>
                </div>
                <?php else: ?>
                <table id="whitelistTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Rótulo</th>
                            <th>Adicionado por</th>
                            <th>Data</th>
                            <th>Estado</th>
                            <th style="width:80px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ips as $ip): ?>
                        <tr id="row-<?= $ip['id_ip'] ?>">
                            <td><code><?= e($ip['ip_address']) ?></code></td>
                            <td><?= e($ip['label'] ?: '—') ?></td>
                            <td><?= e($ip['added_by_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($ip['creat_ip'])) ?></td>
                            <td>
                                <span class="badge-status <?= $ip['active'] ? 'badge-published' : 'badge-archived' ?>">
                                    <?= $ip['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button class="btn-icon toggle-ip" data-id="<?= $ip['id_ip'] ?>"
                                        data-active="<?= $ip['active'] ?>"
                                        title="<?= $ip['active'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fas fa-<?= $ip['active'] ? 'toggle-on' : 'toggle-off' ?>"
                                            style="color:<?= $ip['active'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i>
                                    </button>
                                    <button class="btn-icon btn-icon-danger delete-ip" data-id="<?= $ip['id_ip'] ?>"
                                        title="Remover"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>



    <script>
    const SECURITY_BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const SECURITY_CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    async function readSecurityJson(response) {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (error) {
            throw new Error(`Resposta inválida do servidor (HTTP ${response.status}).`);
        }
    }

    function showSecurityToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || (() => {
            const element = document.createElement('div');
            element.id = 'toast-container';
            element.className = 'security-toast-container';
            document.body.appendChild(element);
            return element;
        })();

        const toast = document.createElement('div');
        const icon = document.createElement('i');
        const allowedTypes = ['success', 'error', 'warning'];
        const safeType = allowedTypes.includes(type) ? type : 'error';

        toast.className = `toast toast-${safeType}`;
        icon.className =
            `fas fa-${safeType === 'success' ? 'check-circle' : safeType === 'warning' ? 'exclamation-triangle' : 'times-circle'}`;
        toast.appendChild(icon);
        toast.appendChild(document.createTextNode(` ${String(message || 'Ocorreu um erro.')}`));
        container.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    $(document).ready(function() {
        if ($('#whitelistTable').length) {
            $('#whitelistTable').DataTable({
                responsive: true,
                pageLength: 20,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [3, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                    orderable: false,
                    targets: [5]
                }]
            });
        }
    });

    const addIpButton = document.getElementById('addIpBtn');
    const addIpPanel = document.getElementById('addIpForm');
    const ipForm = document.getElementById('ipForm');

    addIpButton?.addEventListener('click', () => {
        const willOpen = addIpPanel.hidden;
        addIpPanel.hidden = !willOpen;
        addIpButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) {
            ipForm?.querySelector('[name="ip_address"]')?.focus();
        }
    });

    ipForm?.addEventListener('submit', async function(event) {
        event.preventDefault();
        const submitButton = this.querySelector('button[type="submit"]');
        if (!submitButton || submitButton.disabled) return;

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A adicionar…';

        try {
            const response = await fetch(this.dataset.api, {
                method: 'POST',
                body: new FormData(this),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': SECURITY_CSRF
                }
            });
            const data = await readSecurityJson(response);

            if (!response.ok || !data.success) {
                showSecurityToast(data.message ||
                    `Não foi possível adicionar o IP (HTTP ${response.status}).`, 'error');
                return;
            }

            showSecurityToast(data.message || 'IP adicionado com sucesso.', 'success');
            window.setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            showSecurityToast(error.message || 'Erro de comunicação com o servidor.', 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-save"></i> Adicionar';
        }
    });

    async function sendWhitelistAction(action, id, button) {
        if (button) button.disabled = true;

        try {
            const params = new URLSearchParams({
                action,
                id: String(id)
            });
            const response = await fetch(
                `${SECURITY_BASE}/jm-panel/security/ip-whitelist-process?${params.toString()}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': SECURITY_CSRF
                    }
                });
            const data = await readSecurityJson(response);

            if (!response.ok || !data.success) {
                showSecurityToast(data.message || `Operação não concluída (HTTP ${response.status}).`, 'error');
                return;
            }

            showSecurityToast(data.message || 'Operação concluída.', 'success');
            // Actualiza a tabela e os indicadores para não deixar contadores desactualizados.
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            showSecurityToast(error.message || 'Erro de comunicação com o servidor.', 'error');
        } finally {
            if (button) button.disabled = false;
        }
    }

    document.querySelectorAll('.toggle-ip').forEach(button => {
        button.addEventListener('click', function() {
            sendWhitelistAction('toggle', this.dataset.id, this);
        });
    });

    document.querySelectorAll('.delete-ip').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            if (!window.confirm('Remover este IP da whitelist? Esta operação não pode ser desfeita.'))
                return;
            sendWhitelistAction('delete', id, this);
        });
    });
    </script>
</body>

</html>