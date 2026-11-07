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

// ── Valores disponíveis e filtros validados ─────────────────
$reasons = $db->query("SELECT DISTINCT reason FROM _admin_access_log WHERE reason IS NOT NULL ORDER BY reason")
    ->fetchAll(PDO::FETCH_COLUMN);

$rawReason = $_GET['reason'] ?? 'all';
$filterReason = is_string($rawReason) ? trim($rawReason) : 'all';
if ($filterReason !== 'all' && !in_array($filterReason, $reasons, true)) {
    $filterReason = 'all';
}

$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 120) : '';

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterReason !== 'all') {
    $where[]  = 'reason = ?';
    $params[] = $filterReason;
}
if ($search !== '') {
    $where[]  = '(ip_address LIKE ? OR path_tried LIKE ? OR user_agent LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT * FROM _admin_access_log';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY creat_log DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── Estatísticas ────────────────────────────────────────────
// Intervalo semiaberto: permite ao MySQL usar um índice em creat_log.
$totalToday = (int) $db->query(
    "SELECT COUNT(*) FROM _admin_access_log
     WHERE creat_log >= CURDATE()
       AND creat_log < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
)->fetchColumn();
$totalAll = (int) $db->query("SELECT COUNT(*) FROM _admin_access_log")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-security.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-security.css') ? (int)filemtime(ROOT_PATH . '/assets/css/jmbenga-security.css') : time() ?>">
</head>

<body class="access-log-page">
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
                        value="<?= e($search) ?>" maxlength="120">
                    <?php if ($search): ?><a href="?reason=<?= rawurlencode($filterReason) ?>" class="filter-clear"><i
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