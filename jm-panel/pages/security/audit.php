<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Auditoria (Pesquisa Detalhada)
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

// ── Filtros ─────────────────────────────────────────────────
$filterAction = $_GET['action'] ?? 'all';
$filterEntity = $_GET['entity'] ?? 'all';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$search       = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterAction !== 'all') {
    $where[]  = 'al.action LIKE ?';
    $params[] = "%$filterAction%";
}
if ($filterEntity !== 'all') {
    $where[]  = 'al.entity = ?';
    $params[] = $filterEntity;
}
if (!empty($dateFrom)) {
    $where[]  = 'DATE(al.creat_log) >= ?';
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where[]  = 'DATE(al.creat_log) <= ?';
    $params[] = $dateTo;
}
if ($search !== '') {
    $where[]  = '(al.ip_address LIKE ? OR al.action LIKE ? OR al.entity LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT al.*, COALESCE(e.first_name, 'Sistema') AS actor
        FROM _audit_log al
        LEFT JOIN _employees e ON al.id_employees = e.id_employees";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY al.creat_log DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── Estatísticas ────────────────────────────────────────────
$totalAll = (int) $db->query("SELECT COUNT(*) FROM _audit_log")->fetchColumn();

// Listas para filtros
$actions = $db->query("SELECT DISTINCT action FROM _audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entities = $db->query("SELECT DISTINCT entity FROM _audit_log WHERE entity IS NOT NULL ORDER BY entity")->fetchAll(PDO::FETCH_COLUMN);
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
                        <span>Auditoria</span>
                    </div>
                    <h1 class="page-title">Auditoria Completa</h1>
                    <p class="page-sub"><?= $totalAll ?> eventos registados no log de auditoria.</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/security" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>

            <!-- Filtros -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar…" value="<?= e($search) ?>">
                    <?php if ($search): ?><a href="?action=all&entity=all" class="filter-clear"><i class="fas fa-times"></i></a><?php endif; ?>
                </div>
                <select name="action" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterAction === 'all' ? 'selected' : '' ?>>Todas acções</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="entity" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterEntity === 'all' ? 'selected' : '' ?>>Todas entidades</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?= e($e) ?>" <?= $filterEntity === $e ? 'selected' : '' ?>><?= e($e) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="filter-date" onchange="this.form.submit()" title="De">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="filter-date" onchange="this.form.submit()" title="Até">
                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem"><i class="fas fa-filter"></i> Filtrar</button>
                <?php if ($filterAction !== 'all' || $filterEntity !== 'all' || $dateFrom || $dateTo || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/security/audit" class="btn btn-secondary" style="padding:.45rem .9rem"><i class="fas fa-times"></i> Limpar</a>
                <?php endif; ?>
                <span class="filter-result"><i class="fas fa-info-circle"></i> <?= count($logs) ?> resultado(s)</span>
            </form>

            <!-- Tabela -->
            <div class="table-card">
                <?php if (empty($logs)): ?>
                <div class="empty-state"><div class="empty-icon"><i class="fas fa-search"></i></div><h3>Nenhum evento encontrado</h3></div>
                <?php else: ?>
                <table id="auditTable" class="display responsive nowrap" style="width:100%">
                    <thead><tr><th>Data</th><th>Acção</th><th>Entidade</th><th>ID</th><th>Utilizador</th><th>IP</th><th>Detalhes</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?></td>
                            <td><?= e(ucfirst(str_replace('_',' ',$log['action']))) ?></td>
                            <td><?= e($log['entity'] ?: '—') ?></td>
                            <td><?= $log['entity_id'] ?? '—' ?></td>
                            <td><?= e($log['actor']) ?></td>
                            <td><code><?= e($log['ip_address'] ?: '—') ?></code></td>
                            <td>
                                <button class="btn-icon view-audit-detail" data-old="<?= e($log['old_value'] ?? '') ?>" data-new="<?= e($log['new_value'] ?? '') ?>" title="Ver alterações">
                                    <i class="fas fa-search"></i>
                                </button>
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
    .page-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem}
    .breadcrumb{display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem}
    .breadcrumb a{color:var(--text-dim);text-decoration:none;transition:color .2s}.breadcrumb a:hover{color:var(--accent)}.breadcrumb i{font-size:.6rem}
    .page-title{font-family:var(--font-head);font-size:1.5rem;font-weight:800;letter-spacing:-.02em;margin-bottom:.2rem}
    .page-sub{font-size:.8rem;color:var(--text-muted)}
    .filter-bar{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:.85rem 1rem}
    .filter-search{display:flex;align-items:center;gap:.5rem;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.4rem .75rem;flex:1;min-width:160px}
    .filter-search input{background:none;border:none;outline:none;color:var(--text);font-size:.84rem;flex:1}
    .filter-clear{color:var(--text-muted);text-decoration:none;font-size:.8rem}
    .filter-select{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:.42rem .75rem;border-radius:8px;font-size:.82rem;outline:none}
    .filter-date{background:var(--bg);border:1px solid var(--border);color:var(--text);padding:.42rem .75rem;border-radius:8px;font-size:.82rem;outline:none}
    .filter-result{font-size:.78rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;margin-left:auto}
    .table-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem}
    table.dataTable{width:100%!important;border-collapse:collapse}
    table.dataTable th,table.dataTable td{padding:.7rem .8rem;border-bottom:1px solid var(--border);font-size:.85rem}
    table.dataTable th{color:var(--text-dim);font-weight:600;text-transform:uppercase;letter-spacing:.04em;font-size:.7rem}
    .btn-icon{width:30px;height:30px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;background:transparent;border:1px solid var(--border);color:var(--text-dim);cursor:pointer;transition:all .2s;font-size:.8rem}
    .btn-icon:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem 1.1rem;border-radius:8px;font-size:.83rem;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;border:1px solid var(--border);white-space:nowrap}
    .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn-primary:hover{background:#1d4ed8}
    .btn-secondary{background:transparent;border:1px solid var(--border);color:var(--text-dim)}.btn-secondary:hover{background:var(--bg-hover);border-color:var(--border-acc);color:var(--text)}
    .empty-state{padding:3rem 2rem;text-align:center}
    .empty-icon{font-size:2rem;color:var(--text-muted);margin-bottom:.5rem}
    </style>

    <script>
    $(document).ready(function() {
        if ($('#auditTable').length) {
            $('#auditTable').DataTable({
                responsive: true, pageLength: 25,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json' },
                order: [[0, 'desc']], dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{ orderable: false, targets: [6] }]
            });
        }
    });

    // Ver detalhes (old_value / new_value)
    document.querySelectorAll('.view-audit-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const oldVal = this.dataset.old ? JSON.stringify(JSON.parse(this.dataset.old), null, 2) : '(vazio)';
            const newVal = this.dataset.new ? JSON.stringify(JSON.parse(this.dataset.new), null, 2) : '(vazio)';
            Swal.fire({
                title: 'Detalhes da Alteração',
                html: `<div style="text-align:left;font-size:.82rem"><strong>Antes:</strong><pre style="background:var(--bg);padding:.5rem;border-radius:6px;max-height:150px;overflow:auto">${oldVal}</pre><strong>Depois:</strong><pre style="background:var(--bg);padding:.5rem;border-radius:6px;max-height:150px;overflow:auto">${newVal}</pre></div>`,
                width: '600px',
                background: 'var(--bg-card)',
                color: 'var(--text)',
                showCloseButton: true,
                confirmButtonText: 'Fechar',
                confirmButtonColor: 'var(--accent)'
            });
        });
    });
    </script>
</body>
</html>