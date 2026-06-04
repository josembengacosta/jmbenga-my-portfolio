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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link
    href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
    rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<style>
:root {
    --an-card: #0d1117;
    --an-border: rgba(148, 163, 184, .09);
    --an-border-h: rgba(148, 163, 184, .2);
    --an-radius: 13px;
    --an-text: #e2e8f0;
    --an-dim: #94a3b8;
    --an-muted: #475569;
    --c-blue: #5b8dee;
    --c-teal: #2dd4bf;
    --c-amber: #fbbf24;
    --c-purple: #a78bfa;
    --c-rose: #f87171;
    --c-green: #34d399;
    --fh: 'Syne', var(--font-head, sans-serif);
    --fb: 'IBM Plex Sans', var(--font-body, sans-serif);
    --fm: 'IBM Plex Mono', monospace;
}

.page-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.2rem
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .73rem;
    color: var(--an-muted);
    margin-bottom: .4rem;
    font-family: var(--fb)
}

.breadcrumb a {
    color: var(--an-dim);
    text-decoration: none;
    transition: color .18s
}

.breadcrumb a:hover {
    color: var(--c-blue)
}

.breadcrumb .sep {
    font-size: .5rem;
    opacity: .45
}

.page-title {
    font-family: var(--fh);
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -.03em;
    color: var(--an-text);
    margin-bottom: .2rem;
    line-height: 1.1
}

.page-sub {
    font-size: .77rem;
    color: var(--an-muted);
    font-family: var(--fb)
}

.an-nav {
    display: flex;
    gap: .2rem;
    padding: .3rem;
    background: var(--an-card);
    border: 1px solid var(--an-border);
    border-radius: 11px;
    margin-bottom: 1.5rem;
    width: fit-content;
    flex-wrap: wrap
}

.an-nav-item {
    display: inline-flex;
    align-items: center;
    gap: .38rem;
    padding: .42rem .9rem;
    border-radius: 8px;
    font-size: .76rem;
    font-weight: 600;
    color: var(--an-dim);
    text-decoration: none;
    transition: all .18s;
    font-family: var(--fb);
    white-space: nowrap
}

.an-nav-item:hover {
    color: var(--an-text);
    background: rgba(255, 255, 255, .05)
}

.an-nav-item.active {
    background: var(--c-blue);
    color: #fff;
    box-shadow: 0 2px 14px rgba(91, 141, 238, .35)
}

/* KPI */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: .7rem;
    margin-bottom: 1.3rem
}

.kpi-card {
    background: var(--an-card);
    border: 1px solid var(--an-border);
    border-radius: var(--an-radius);
    padding: 1.1rem 1.2rem;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s, transform .2s;
    cursor: default
}

.kpi-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--kc, var(--c-blue));
    opacity: 0;
    transition: opacity .2s
}

.kpi-card:hover {
    border-color: var(--an-border-h);
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(0, 0, 0, .3)
}

.kpi-card:hover::after {
    opacity: 1
}

.kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .7rem
}

.kpi-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    background: rgba(255, 255, 255, .06);
    color: var(--kc, var(--c-blue))
}

.kpi-val {
    font-family: var(--fm);
    font-size: 1.65rem;
    font-weight: 500;
    letter-spacing: -.03em;
    color: var(--an-text);
    line-height: 1;
    margin-bottom: 3px
}

.kpi-lbl {
    font-size: .63rem;
    color: var(--an-muted);
    font-family: var(--fb);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em
}

.kpi-delta {
    font-size: .68rem;
    font-family: var(--fb);
    margin-top: .3rem
}

.kpi-delta.up {
    color: var(--c-green)
}

.kpi-delta.down {
    color: var(--c-rose)
}

/* Cards */
.chart-card,
.table-card {
    background: var(--an-card);
    border: 1px solid var(--an-border);
    border-radius: var(--an-radius);
    padding: 1.4rem 1.5rem;
    transition: border-color .2s
}

.chart-card:hover,
.table-card:hover {
    border-color: var(--an-border-h)
}

.card-hd {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.1rem
}

.card-hd h3 {
    font-family: var(--fh);
    font-size: .9rem;
    font-weight: 700;
    color: var(--an-text);
    letter-spacing: -.01em
}

.card-hd .card-meta {
    font-family: var(--fm);
    font-size: .7rem;
    color: var(--an-muted)
}

.chart-wrap {
    position: relative;
    width: 100%
}

.g21 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem
}

@media(max-width:1200px) {
    .g21 {
        grid-template-columns: 1fr
    }
}

/* Table */
.mini-table {
    width: 100%;
    border-collapse: collapse
}

.mini-table th {
    padding: .4rem .5rem;
    text-align: left;
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--an-muted);
    border-bottom: 1px solid var(--an-border);
    font-family: var(--fb);
    white-space: nowrap
}

.mini-table td {
    padding: .55rem .5rem;
    font-size: .79rem;
    border-bottom: 1px solid var(--an-border);
    color: var(--an-text);
    font-family: var(--fb)
}

.mini-table tbody tr:last-child td {
    border-bottom: none
}

.mini-table tbody tr:hover td {
    background: rgba(255, 255, 255, .025)
}

.pg-wrap {
    display: flex;
    align-items: center;
    gap: .5rem
}

.pg-bar {
    flex: 1;
    height: 3px;
    background: rgba(255, 255, 255, .07);
    border-radius: 4px;
    overflow: hidden
}

.pg-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--pf, var(--c-blue));
    width: 0;
    transition: width .7s cubic-bezier(.4, 0, .2, 1)
}

.pg-num {
    font-family: var(--fm);
    font-size: .72rem;
    color: var(--an-dim);
    min-width: 28px;
    text-align: right
}

.url-cell {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: var(--fm);
    font-size: .73rem;
    color: var(--an-dim)
}

/* Avg line marker */
.avg-line-label {
    font-family: var(--fm);
    font-size: .68rem;
    color: var(--c-amber)
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1rem;
    border-radius: 9px;
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    border: 1px solid var(--an-border);
    font-family: var(--fb);
    white-space: nowrap
}

.btn-ghost {
    background: transparent;
    color: var(--an-dim)
}

.btn-ghost:hover {
    background: rgba(255, 255, 255, .055);
    border-color: var(--an-border-h);
    color: var(--an-text)
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(10px)
    }

    to {
        opacity: 1;
        transform: translateY(0)
    }
}

.an-a {
    animation: fadeUp .38s ease both
}

.d1 {
    animation-delay: .04s
}

.d2 {
    animation-delay: .08s
}

.d3 {
    animation-delay: .12s
}

.d4 {
    animation-delay: .16s
}
</style>

<body>
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
                <a href="<?= BASE_URL ?>/jm-panel/analytics" class="btn btn-ghost">
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

    Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
    const tooltipOpts = {
        backgroundColor: '#0d1117',
        borderColor: 'rgba(148,163,184,.15)',
        borderWidth: 1,
        titleColor: '#e2e8f0',
        bodyColor: '#94a3b8',
        padding: 10,
        cornerRadius: 8,
        displayColors: false
    };

    // Daily chart with gradient
    const ctx = document.getElementById('dailyChart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(91,141,238,.3)');
    grad.addColorStop(.65, 'rgba(91,141,238,.06)');
    grad.addColorStop(1, 'rgba(91,141,238,0)');

    const avgVal = <?= $avgChartVal ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                    type: 'bar',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: <?= json_encode($chartData) ?>.map(v => v >= avgVal ? '#5b8dee' :
                        '#1e2a3a'),
                    borderRadius: 4,
                    borderSkipped: false,
                    order: 2,
                },
                {
                    type: 'line',
                    data: <?= json_encode(array_fill(0, count($chartData), $avgChartVal)) ?>,
                    borderColor: 'rgba(251,191,36,.6)',
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
                        color: 'rgba(255,255,255,.05)'
                    },
                    ticks: {
                        color: '#475569'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#475569',
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