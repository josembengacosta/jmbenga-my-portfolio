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
                        <a href="<?= BASE_URL ?>/jm-panel/security">Segurança</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>IP Whitelist</span>
                    </div>
                    <h1 class="page-title">IP Whitelist</h1>
                    <p class="page-sub"><?= $totalActive ?> IPs activos de <?= $totalAll ?> registados.</p>
                </div>
                <button class="btn btn-primary" id="addIpBtn"><i class="fas fa-plus"></i> Adicionar IP</button>
            </div>

            <!-- Formulário rápido de adição (oculto por padrão) -->
            <div class="form-section" id="addIpForm" style="display:none;margin-bottom:1.5rem">
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-plus-circle"></i></span>
                    <div>
                        <h3>Adicionar IP à Whitelist</h3>
                    </div>
                </div>
                <form id="ipForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/security/ip-whitelist-process">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Endereço IP <span class="req">*</span></label>
                            <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.1"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rótulo</label>
                            <input type="text" name="label" class="form-control" placeholder="Ex: Casa, Escritório">
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

    .form-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.8rem
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1rem
    }

    .section-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--accent-glow);
        border: 1px solid var(--border-acc);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-size: .85rem;
        flex-shrink: 0
    }

    .section-header h3 {
        font-family: var(--font-head);
        font-size: .95rem;
        font-weight: 700
    }

    .form-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 1rem;
        align-items: end
    }

    @media(max-width:768px) {
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

    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.5rem
    }

    table.dataTable {
        width: 100% !important;
        border-collapse: collapse
    }

    table.dataTable th,
    table.dataTable td {
        padding: .7rem .8rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        font-size: .85rem
    }

    table.dataTable th {
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: .7rem
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 600
    }

    .badge-published {
        background: rgba(16, 185, 129, .1);
        color: var(--success)
    }

    .badge-archived {
        background: rgba(148, 163, 184, .1);
        color: var(--text-muted)
    }

    .row-actions {
        display: flex;
        gap: .3rem
    }

    .btn-icon {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim);
        cursor: pointer;
        transition: all .2s;
        font-size: .8rem
    }

    .btn-icon:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent)
    }

    .btn-icon-danger:hover {
        background: var(--danger);
        border-color: var(--danger)
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
        background: #1d4ed8
    }

    .empty-state {
        padding: 3rem 2rem;
        text-align: center
    }

    .empty-icon {
        font-size: 2rem;
        color: var(--text-muted);
        margin-bottom: .5rem
    }
    </style>

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

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

    // Mostrar / esconder formulário
    document.getElementById('addIpBtn').addEventListener('click', () => {
        const form = document.getElementById('addIpForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // Submeter formulário
    document.getElementById('ipForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A adicionar…';
        try {
            const fd = new FormData(this);
            const res = await fetch(this.dataset.api, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                }
            });
            const data = await res.json();
            if (data.success) {
                showToast('IP adicionado!', 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(data.message || 'Erro', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Adicionar';
        }
    });

    // Toggle activo/inactivo
    document.querySelectorAll('.toggle-ip').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            try {
                const res = await fetch(BASE +
                    '/jm-panel/security/ip-whitelist-process?action=toggle&id=' + id, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': CSRF
                        }
                    });
                const data = await res.json();
                if (data.success) {
                    showToast(data.active ? 'IP activado' : 'IP desactivado', 'success');
                    setTimeout(() => location.reload(), 400);
                } else {
                    showToast(data.message || 'Erro', 'error');
                }
            } catch (err) {
                showToast('Erro de rede.', 'error');
            }
        });
    });

    // Remover IP
    document.querySelectorAll('.delete-ip').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            if (!confirm('Remover este IP da whitelist?')) return;
            try {
                const res = await fetch(BASE +
                    '/jm-panel/security/ip-whitelist-process?action=delete&id=' + id, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': CSRF
                        }
                    });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('row-' + id)?.remove();
                    showToast('IP removido.', 'success');
                } else {
                    showToast(data.message || 'Erro', 'error');
                }
            } catch (err) {
                showToast('Erro de rede.', 'error');
            }
        });
    });
    </script>
</body>

</html>