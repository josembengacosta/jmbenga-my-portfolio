<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Segurança (Hub)
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

// ═══ ESTATÍSTICAS DE SEGURANÇA ════════════════════════════════
$totalAdmins      = (int) $db->query("SELECT COUNT(*) FROM _employees")->fetchColumn();
$activeAdmins     = (int) $db->query("SELECT COUNT(*) FROM _employees WHERE status_employees = 'active'")->fetchColumn();
$blockedToday     = (int) $db->query("SELECT COUNT(*) FROM _admin_access_log WHERE creat_log >= CURDATE() AND creat_log < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$whitelistedIPs   = (int) $db->query("SELECT COUNT(*) FROM _admin_ip_whitelist WHERE active = 1")->fetchColumn();
$failedLoginsToday = (int) $db->query("SELECT COUNT(*) FROM _audit_log WHERE action = 'auth.failed_login' AND creat_log >= CURDATE() AND creat_log < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$successLoginsToday = (int) $db->query("SELECT COUNT(*) FROM _audit_log WHERE action = 'auth.login' AND creat_log >= CURDATE() AND creat_log < DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

// ── Últimas entradas do audit log ───────────────────────────
$auditStmt = $db->query("
    SELECT al.*, COALESCE(e.first_name, 'Sistema') AS actor
    FROM _audit_log al
    LEFT JOIN _employees e ON al.id_employees = e.id_employees
    ORDER BY al.creat_log DESC
    LIMIT 15
");
$auditLogs = $auditStmt->fetchAll();

// ── Últimos acessos bloqueados ──────────────────────────────
$accessStmt = $db->query("
    SELECT * FROM _admin_access_log
    ORDER BY creat_log DESC
    LIMIT 10
");
$accessLogs = $accessStmt->fetchAll();

// ── Dados para gráfico (últimos 7 dias) ─────────────────────
$chartStmt = $db->query("
    SELECT DATE(creat_log) AS d,
           SUM(action = 'auth.failed_login') AS failed,
           SUM(action = 'auth.login') AS success
    FROM _audit_log
    WHERE creat_log >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(creat_log)
    ORDER BY d ASC
");
$chartData = $chartStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-security.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-security.css') ? (int)filemtime(ROOT_PATH . '/assets/css/jmbenga-security.css') : time() ?>">
</head>


<body class="security-hub-page">
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Segurança</span>
                    </div>
                    <h1 class="page-title">Centro de Segurança</h1>
                    <p class="page-sub">Monitoriza acessos, bloqueios e actividades sensíveis do painel.</p>
                </div>
                <div style="display:flex;gap:.6rem">
                    <a href="<?= BASE_URL ?>/jm-panel/security/ip-whitelist" class="btn btn-secondary"><i
                            class="fas fa-list"></i> IP Whitelist</a>
                    <a href="<?= BASE_URL ?>/jm-panel/security/access-log" class="btn btn-secondary"><i
                            class="fas fa-history"></i> Access Log</a>
                    <a href="<?= BASE_URL ?>/jm-panel/security/audit" class="btn btn-secondary"><i
                            class="fas fa-search"></i> Auditoria</a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-row">
                <div class="kpi-card"><span class="kpi-icon"
                        style="background:rgba(37,99,235,.12);color:var(--accent)"><i
                            class="fas fa-user-shield"></i></span><span class="kpi-val"><?= $totalAdmins ?></span><span
                        class="kpi-lbl">Admins</span></div>
                <div class="kpi-card"><span class="kpi-icon"
                        style="background:rgba(16,185,129,.12);color:var(--success)"><i
                            class="fas fa-check-circle"></i></span><span
                        class="kpi-val"><?= $activeAdmins ?></span><span class="kpi-lbl">Activos</span></div>
                <div class="kpi-card"><span class="kpi-icon"
                        style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                            class="fas fa-ban"></i></span><span class="kpi-val"><?= $blockedToday ?></span><span
                        class="kpi-lbl">Bloqueios Hoje</span></div>
                <div class="kpi-card"><span class="kpi-icon"
                        style="background:rgba(245,158,11,.12);color:var(--warning)"><i
                            class="fas fa-exclamation-triangle"></i></span><span
                        class="kpi-val"><?= $failedLoginsToday ?></span><span class="kpi-lbl">Falhas Hoje</span></div>
                <div class="kpi-card"><span class="kpi-icon"
                        style="background:rgba(139,92,246,.12);color:var(--purple)"><i
                            class="fas fa-list"></i></span><span class="kpi-val"><?= $whitelistedIPs ?></span><span
                        class="kpi-lbl">IPs Whitelist</span></div>
            </div>

            <!-- Gráfico + Access Log -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Actividade de Autenticação (7 dias)</h3>
                    </div>
                    <div class="chart-container"><canvas id="authChart"></canvas></div>
                </div>
                <div class="table-card">
                    <div class="card-header">
                        <h3>Últimos Acessos Bloqueados</h3>
                    </div>
                    <?php if (empty($accessLogs)): ?>
                    <p style="color:var(--text-muted);font-size:.85rem">Nenhum acesso bloqueado recentemente.</p>
                    <?php else: ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Motivo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessLogs as $al): ?>
                            <tr>
                                <td><code><?= e($al['ip_address']) ?></code></td>
                                <td><?= e($al['reason'] ?? '—') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($al['creat_log'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabela de Auditoria -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Últimas Actividades (Auditoria)</h3>
                </div>
                <table id="auditTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Acção</th>
                            <th>Entidade</th>
                            <th>ID</th>
                            <th>Utilizador</th>
                            <th>IP</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><?= e(ucfirst(str_replace('_',' ',$log['action']))) ?></td>
                            <td><?= e($log['entity'] ?? '—') ?></td>
                            <td><?= $log['entity_id'] ?? '—' ?></td>
                            <td><?= e($log['actor']) ?></td>
                            <td><code><?= e($log['ip_address'] ?? '—') ?></code></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>



    <script>
    $(document).ready(function() {
        if ($('#auditTable').length) {
            $('#auditTable').DataTable({
                responsive: true,
                pageLength: 10,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [5, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>'
            });
        }
    });

    // Gráfico de autenticação
    const chartData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    const chartCanvas = document.getElementById('authChart');

    if (chartCanvas && typeof Chart !== 'undefined') {
        const labels = chartData.map(d => d.d);
        const success = chartData.map(d => Number(d.success) || 0);
        const failed = chartData.map(d => Number(d.failed) || 0);

        new Chart(chartCanvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                    label: 'Login bem-sucedido',
                    data: success,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Login falhado',
                    data: failed,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        color: 'rgba(255,255,255,.06)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255,255,255,.06)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#94a3b8',
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
        });
    }
    </script>
</body>

</html>