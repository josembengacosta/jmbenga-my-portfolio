<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Registo de Acessos Bloqueados
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

// ── Filtros ──────────────────────────────────────────────────
$filterReason = $_GET['reason'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterReason !== 'all') {
    $where[]  = 'reason = ?';
    $params[] = $filterReason;
}
if ($search !== '') {
    $where[]  = '(ip_address LIKE ? OR path_tried LIKE ? OR user_agent LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _admin_access_log';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY creat_log DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── Estatísticas ────────────────────────────────────────────
$totalToday = (int) $db->query("SELECT COUNT(*) FROM _admin_access_log WHERE DATE(creat_log) = CURDATE()")->fetchColumn();
$totalAll   = (int) $db->query("SELECT COUNT(*) FROM _admin_access_log")->fetchColumn();

// Razões distintas para o filtro
$reasons = $db->query("SELECT DISTINCT reason FROM _admin_access_log WHERE reason IS NOT NULL ORDER BY reason")->fetchAll(PDO::FETCH_COLUMN);
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
                        <span>Access Log</span>
                    </div>
                    <h1 class="page-title">Registo de Acessos Bloqueados</h1>
                    <p class="page-sub"><?= $totalToday ?> bloqueios hoje &nbsp;·&nbsp; <?= $totalAll ?> total</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/security" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <!-- Filtros -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar IP, caminho, user agent…"
                        value="<?= e($search) ?>">
                    <?php if ($search): ?><a href="?reason=<?= e($filterReason) ?>" class="filter-clear"><i
                            class="fas fa-times"></i></a><?php endif; ?>
                </div>
                <select name="reason" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterReason === 'all' ? 'selected' : '' ?>>Todos os motivos</option>
                    <?php foreach ($reasons as $r): ?>
                    <option value="<?= e($r) ?>" <?= $filterReason === $r ? 'selected' : '' ?>>
                        <?= e(ucfirst(str_replace('_',' ',$r))) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem"><i class="fas fa-filter"></i>
                    Filtrar</button>
                <?php if ($filterReason !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/security/access-log" class="btn btn-secondary"
                    style="padding:.45rem .9rem"><i class="fas fa-times"></i> Limpar</a>
                <?php endif; ?>
                <span class="filter-result"><i class="fas fa-info-circle"></i> <?= count($logs) ?> resultado(s)</span>
            </form>

            <!-- Tabela -->
            <div class="table-card">
                <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-shield-check"></i></div>
                    <h3>Nenhum acesso bloqueado</h3>
                    <p>Não foram registados acessos bloqueados com os filtros actuais.</p>
                </div>
                <?php else: ?>
                <table id="accessLogTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Caminho Tentado</th>
                            <th>Motivo</th>
                            <th>User Agent</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><code><?= e($log['ip_address']) ?></code></td>
                            <td><?= e($log['path_tried'] ?: '—') ?></td>
                            <td><span
                                    class="badge-status badge-archived"><?= e(ucfirst(str_replace('_',' ',$log['reason'] ?? '—'))) ?></span>
                            </td>
                            <td style="font-size:.75rem;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                title="<?= e($log['user_agent']) ?>"><?= e($log['user_agent'] ?: '—') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?></td>
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

    .filter-bar {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .85rem 1rem
    }

    .filter-search {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: .4rem .75rem;
        flex: 1;
        min-width: 180px
    }

    .filter-search input {
        background: none;
        border: none;
        outline: none;
        color: var(--text);
        font-size: .84rem;
        flex: 1
    }

    .filter-clear {
        color: var(--text-muted);
        text-decoration: none;
        font-size: .8rem
    }

    .filter-select {
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        padding: .42rem .75rem;
        border-radius: 8px;
        font-size: .82rem;
        outline: none
    }

    .filter-result {
        font-size: .78rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-left: auto
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

    .badge-archived {
        background: rgba(239, 68, 68, .1);
        color: var(--danger)
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
    $(document).ready(function() {
        if ($('#accessLogTable').length) {
            $('#accessLogTable').DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [4, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>'
            });
        }
    });
    </script>
</body>

</html>