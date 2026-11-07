<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Analytics: Dispositivos (v2)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

$adminName      = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial   = strtoupper(mb_substr($adminName, 0, 1));
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();

$devices     = $db->query("SELECT device_type, COUNT(*) AS total FROM _visitor WHERE device_type IS NOT NULL AND is_bot = 0 GROUP BY device_type ORDER BY total DESC")->fetchAll();
$brands      = $db->query("SELECT device_brand, COUNT(*) AS total FROM _visitor WHERE device_brand IS NOT NULL AND is_bot = 0 GROUP BY device_brand ORDER BY total DESC LIMIT 10")->fetchAll();
$osData      = $db->query("SELECT os, COUNT(*) AS total FROM _visitor WHERE os IS NOT NULL AND is_bot = 0 GROUP BY os ORDER BY total DESC LIMIT 10")->fetchAll();
$resolutions = $db->query("SELECT screen_resolution, COUNT(*) AS total FROM _visitor WHERE screen_resolution IS NOT NULL AND is_bot = 0 GROUP BY screen_resolution ORDER BY total DESC LIMIT 10")->fetchAll();

$maxBrand = !empty($brands)      ? max(array_column($brands,      'total')) : 1;
$maxRes   = !empty($resolutions) ? max(array_column($resolutions, 'total')) : 1;
$maxOs    = !empty($osData)      ? max(array_column($osData,      'total')) : 1;

$totalDevices = array_sum(array_column($devices, 'total'));
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-analytics.css?v=20261030">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="analytics-page">
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top an-a">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <span class="sep"><i class="fas fa-chevron-right"></i></span>
                        <a href="<?= BASE_URL ?>/jm-panel/analytics">Analytics</a>
                        <span class="sep"><i class="fas fa-chevron-right"></i></span>
                        <span>Dispositivos</span>
                    </div>
                    <h1 class="page-title">Dispositivos</h1>
                    <p class="page-sub"><?= number_format($totalDevices) ?> visitantes analisados</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/analytics" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Analytics
                </a>
            </div>

            <!-- Sub-nav -->
            <nav class="an-nav an-a d1">
                <a href="<?= BASE_URL ?>/jm-panel/analytics" class="an-nav-item">
                    <i class="fas fa-chart-line"></i> Visão Geral
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/pageviews" class="an-nav-item">
                    <i class="fas fa-eye"></i> Pageviews
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/devices" class="an-nav-item active">
                    <i class="fas fa-mobile-alt"></i> Dispositivos
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/geo" class="an-nav-item">
                    <i class="fas fa-globe"></i> Geografia
                </a>
            </nav>

            <!-- Device type summary pills -->
            <?php
    $devColors = ['desktop'=>'var(--c-blue)','mobile'=>'var(--c-teal)','tablet'=>'var(--c-amber)','bot'=>'var(--c-rose)','unknown'=>'var(--an-muted)'];
    $devIcons  = ['desktop'=>'fa-desktop','mobile'=>'fa-mobile-alt','tablet'=>'fa-tablet-alt','bot'=>'fa-robot','unknown'=>'fa-question-circle'];
    ?>
            <div class="dev-pills an-a d1">
                <?php foreach ($devices as $d): ?>
                <?php $clr = $devColors[$d['device_type']] ?? 'var(--an-muted)'; $pct = $totalDevices > 0 ? round($d['total']/$totalDevices*100) : 0; ?>
                <div class="dev-pill">
                    <div class="dev-pill-dot" style="background:<?= $clr ?>"></div>
                    <i class="fas <?= $devIcons[$d['device_type']] ?? 'fa-question' ?>"
                        style="font-size:.8rem;color:<?= $clr ?>"></i>
                    <span class="dev-pill-name"><?= ucfirst($d['device_type']) ?></span>
                    <span class="dev-pill-val"><?= number_format($d['total']) ?></span>
                    <span class="dev-pill-pct"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Donut + OS bar -->
            <div class="g11 an-a d2">
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Tipo de Dispositivo</h3>
                        <span class="card-badge"><?= $totalDevices ?> visitantes</span>
                    </div>
                    <div class="chart-wrap"><canvas id="deviceChart" height="280"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Sistema Operativo</h3>
                        <span class="card-badge">Top <?= count($osData) ?></span>
                    </div>
                    <div class="chart-wrap"><canvas id="osChart" height="280"></canvas></div>
                </div>
            </div>

            <!-- Brands + Resolutions -->
            <div class="g11 an-a d3">
                <div class="table-card">
                    <div class="card-hd">
                        <h3>Marcas</h3>
                        <span class="card-badge">Top <?= count($brands) ?></span>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Marca</th>
                                <th>Visitantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $i => $b): ?>
                            <tr>
                                <td style="color:var(--an-muted);font-family:var(--fm);font-size:.7rem;width:28px">
                                    <?= $i + 1 ?></td>
                                <td><?= e($b['device_brand'] ?: 'Desconhecida') ?></td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" style="--pf:var(--c-amber)"
                                                data-w="<?= round($b['total']/$maxBrand*100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $b['total'] ?></span>
                                        <span
                                            class="pg-pct"><?= $totalDevices > 0 ? round($b['total']/$totalDevices*100) : 0 ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-card">
                    <div class="card-hd">
                        <h3>Resoluções de Ecrã</h3>
                        <span class="card-badge">Top <?= count($resolutions) ?></span>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Resolução</th>
                                <th>Visitantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resolutions as $i => $r): ?>
                            <tr>
                                <td style="color:var(--an-muted);font-family:var(--fm);font-size:.7rem;width:28px">
                                    <?= $i + 1 ?></td>
                                <td style="font-family:var(--fm);font-size:.77rem">
                                    <?= e($r['screen_resolution'] ?: '—') ?></td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" style="--pf:var(--c-purple)"
                                                data-w="<?= round($r['total']/$maxRes*100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $r['total'] ?></span>
                                        <span
                                            class="pg-pct"><?= $totalDevices > 0 ? round($r['total']/$totalDevices*100) : 0 ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    const anRoot = getComputedStyle(document.documentElement);
    const anVar = (name, fallback) => anRoot.getPropertyValue(name).trim() || fallback;
    const anTheme = {
        accent: anVar('--accent', '#2563eb'),
        teal: anVar('--teal', '#14b8a6'),
        warning: anVar('--warning', '#f59e0b'),
        purple: anVar('--purple', '#8b5cf6'),
        danger: anVar('--danger', '#ef4444'),
        success: anVar('--success', '#10b981'),
        text: anVar('--text', '#e2e8f4'),
        dim: anVar('--text-dim', '#8896aa'),
        muted: anVar('--text-muted', '#4a5568'),
        card: anVar('--bg-card', '#101624'),
        border: anVar('--border', 'rgba(255,255,255,.07)')
    };
    const anRgba = (color, alpha) => {
        let hex = String(color).trim().replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(ch => ch + ch).join('');
        if (!/^[0-9a-f]{6}$/i.test(hex)) return `rgba(37,99,235,${alpha})`;
        const value = parseInt(hex, 16);
        return `rgba(${(value >> 16) & 255},${(value >> 8) & 255},${value & 255},${alpha})`;
    };
    if (window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'sans-serif';
        Chart.defaults.color = anTheme.muted;
    }


    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'sans-serif';
    const tooltipOpts = {
        backgroundColor: anTheme.card,
        borderColor: anTheme.border,
        borderWidth: 1,
        titleColor: anTheme.text,
        bodyColor: anTheme.dim,
        padding: 10,
        cornerRadius: 8,
        displayColors: false
    };

    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.pg-fill[data-w]').forEach(el => el.style.width = el.dataset.w + '%');
    }, 300);

    // Doughnut
    const devData = <?= json_encode($devices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    new Chart(document.getElementById('deviceChart'), {
        type: 'doughnut',
        data: {
            labels: devData.map(d => d.device_type.charAt(0).toUpperCase() + d.device_type.slice(1)),
            datasets: [{
                data: devData.map(d => d.total),
                backgroundColor: [anTheme.accent, anTheme.teal, anTheme.warning, anTheme.purple, anTheme
                    .danger
                ],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: anTheme.dim,
                        padding: 16,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: tooltipOpts
            }
        }
    });

    // OS bar
    const osData = <?= json_encode($osData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const osCtx = document.getElementById('osChart').getContext('2d');
    const osGrad = osCtx.createLinearGradient(0, 0, 400, 0);
    osGrad.addColorStop(0, anTheme.teal);
    osGrad.addColorStop(1, anTheme.accent);
    new Chart(osCtx, {
        type: 'bar',
        data: {
            labels: osData.map(o => o.os),
            datasets: [{
                data: osData.map(o => o.total),
                backgroundColor: osGrad,
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: tooltipOpts
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: anTheme.border
                    },
                    ticks: {
                        color: anTheme.muted
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: anTheme.dim
                    }
                }
            }
        }
    });
    </script>
</body>

</html>