<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Analytics: Pageviews (v2)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

$adminName      = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial   = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto     = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime      = $_SESSION['admin_login_time'] ?? time();
$sessionMins    = floor((time() - $loginTime) / 60);

$totalPageviews  = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview")->fetchColumn();
$totalToday      = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview WHERE DATE(creat_pageview) = CURDATE()")->fetchColumn();
$totalThisWeek   = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview WHERE YEARWEEK(creat_pageview, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();
$totalThisMonth  = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview WHERE MONTH(creat_pageview) = MONTH(CURDATE()) AND YEAR(creat_pageview) = YEAR(CURDATE())")->fetchColumn();
$totalYesterday  = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview WHERE DATE(creat_pageview) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();

$daily = $db->query("
    SELECT DATE(creat_pageview) AS visit_date, COUNT(*) AS total
    FROM _visitor_pageview
    WHERE creat_pageview >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(creat_pageview) ORDER BY visit_date ASC
")->fetchAll();

$topPages = $db->query("
    SELECT page_url, page_title, COUNT(*) AS total, AVG(time_on_page) AS avg_time
    FROM _visitor_pageview
    GROUP BY page_url, page_title ORDER BY total DESC LIMIT 20
")->fetchAll();

$chartLabels = array_map(fn($d) => date('d/m', strtotime($d['visit_date'])), $daily);
$chartData   = array_map(fn($d) => (int) $d['total'], $daily);
$maxPage     = !empty($topPages) ? max(array_column($topPages, 'total')) : 1;
$avgChartVal = count($chartData) > 0 ? round(array_sum($chartData) / count($chartData)) : 0;

// Yesterday trend
$todayDelta = $totalYesterday > 0 ? round(($totalToday - $totalYesterday) / $totalYesterday * 100) : 0;
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
                        <span>Pageviews</span>
                    </div>
                    <h1 class="page-title">Pageviews</h1>
                    <p class="page-sub">
                        <?= number_format($totalPageviews) ?> no total &nbsp;·&nbsp;
                        Média diária: <strong style="color:var(--an-text)"><?= $avgChartVal ?></strong> (últimos 30
                        dias)
                    </p>
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
                <a href="<?= BASE_URL ?>/jm-panel/analytics/pageviews" class="an-nav-item active">
                    <i class="fas fa-eye"></i> Pageviews
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/devices" class="an-nav-item">
                    <i class="fas fa-mobile-alt"></i> Dispositivos
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/geo" class="an-nav-item">
                    <i class="fas fa-globe"></i> Geografia
                </a>
            </nav>

            <!-- KPI row -->
            <div class="kpi-row an-a d1">
                <div class="kpi-card" style="--kc:var(--c-blue)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalPageviews ?>"><?= number_format($totalPageviews) ?></div>
                    <div class="kpi-lbl">Total</div>
                </div>
                <div class="kpi-card" style="--kc:var(--c-green)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-calendar-day"></i></span>
                        <?php if ($totalYesterday > 0): ?>
                        <span class="kpi-delta <?= $todayDelta >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $todayDelta >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($todayDelta) ?>% vs ontem
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalToday ?>"><?= number_format($totalToday) ?></div>
                    <div class="kpi-lbl">Hoje</div>
                    <?php if ($totalYesterday > 0): ?>
                    <div style="font-family:var(--fm);font-size:.65rem;color:var(--an-muted);margin-top:.3rem">
                        <?= number_format($totalYesterday) ?> ontem</div>
                    <?php endif; ?>
                </div>
                <div class="kpi-card" style="--kc:var(--c-amber)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-calendar-week"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalThisWeek ?>"><?= number_format($totalThisWeek) ?></div>
                    <div class="kpi-lbl">Esta Semana</div>
                </div>
                <div class="kpi-card" style="--kc:var(--c-purple)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalThisMonth ?>"><?= number_format($totalThisMonth) ?></div>
                    <div class="kpi-lbl">Este Mês</div>
                </div>
            </div>

            <!-- Daily chart + top pages -->
            <div class="g21 an-a d2">
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Evolução Diária — 30 dias</h3>
                        <div>
                            <span class="avg-line-label"><i class="fas fa-minus" style="font-size:.55rem"></i> Média:
                                <?= $avgChartVal ?>/dia</span>
                        </div>
                    </div>
                    <div class="chart-wrap"><canvas id="dailyChart" height="300"></canvas></div>
                </div>
                <div class="table-card">
                    <div class="card-hd">
                        <h3>Top Páginas</h3>
                        <span class="card-meta">Top <?= count($topPages) ?></span>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Página</th>
                                <th>Visitas</th>
                                <th>T. Méd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPages as $p): ?>
                            <tr>
                                <td>
                                    <div class="url-cell" title="<?= e($p['page_url']) ?>"><?= e($p['page_url']) ?>
                                    </div>
                                    <?php if (!empty($p['page_title'])): ?>
                                    <div style="font-size:.67rem;color:var(--an-muted);margin-top:1px">
                                        <?= e(mb_substr($p['page_title'], 0, 28)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" data-w="<?= round($p['total']/$maxPage*100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $p['total'] ?></span>
                                    </div>
                                </td>
                                <td
                                    style="font-family:var(--fm);font-size:.72rem;color:var(--an-dim);white-space:nowrap">
                                    <?= $p['avg_time'] > 0 ? round((float)$p['avg_time'], 1).'s' : '—' ?>
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


    // Count-up
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseFloat(el.dataset.count);
        const dur = 900,
            step = 16,
            steps = dur / step;
        let cur = 0;
        const inc = target / steps;
        const t = setInterval(() => {
            cur += inc;
            if (cur >= target) {
                cur = target;
                clearInterval(t);
            }
            el.textContent = Math.floor(cur).toLocaleString('pt');
        }, step);
    });

    // Progress bars
    setTimeout(() => {
        document.querySelectorAll('.pg-fill[data-w]').forEach(el => el.style.width = el.dataset.w + '%');
    }, 300);

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

    // Daily chart with gradient
    const ctx = document.getElementById('dailyChart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, anRgba(anTheme.accent, .3));
    grad.addColorStop(.65, anRgba(anTheme.accent, .06));
    grad.addColorStop(1, anRgba(anTheme.accent, 0));

    const avgVal = <?= $avgChartVal ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            datasets: [{
                    type: 'bar',
                    data: <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                    backgroundColor: <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
                        .map(v => v >= avgVal ? anTheme.accent :
                            anTheme.border),
                    borderRadius: 4,
                    borderSkipped: false,
                    order: 2,
                },
                {
                    type: 'line',
                    data: <?= json_encode(array_fill(0, count($chartData), $avgChartVal), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                    borderColor: anRgba(anTheme.warning, .6),
                    borderWidth: 1.5,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    ...tooltipOpts,
                    callbacks: {
                        label: (item) => item.datasetIndex === 0 ?
                            ` ${item.raw.toLocaleString('pt')} pageviews` : ` Média: ${item.raw}`,
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: anTheme.border
                    },
                    ticks: {
                        color: anTheme.muted
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: anTheme.muted,
                        maxRotation: 0,
                        maxTicksLimit: 10
                    }
                }
            }
        }
    });
    </script>
</body>

</html>