<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Analytics Home (v2)
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

$totalVisitors   = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_bot = 0")->fetchColumn();
$totalPageviews  = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview")->fetchColumn();
$totalBots       = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_bot = 1")->fetchColumn();
$uniqueCountries = (int) $db->query("SELECT COUNT(DISTINCT country_code) FROM _visitor WHERE country_code IS NOT NULL")->fetchColumn();
$avgPages = $totalVisitors > 0 ? round($totalPageviews / $totalVisitors, 1) : 0;
$avgTime  = round((float) $db->query("SELECT AVG(time_on_page) FROM _visitor_pageview WHERE time_on_page IS NOT NULL")->fetchColumn(), 1);
$last24h  = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview WHERE creat_pageview >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

$dailyVisits = $db->query("
    SELECT DATE(creat_pageview) AS visit_date, COUNT(*) AS total
    FROM _visitor_pageview
    WHERE creat_pageview >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(creat_pageview) ORDER BY visit_date ASC
")->fetchAll();

$topPages = $db->query("
    SELECT page_url, page_title, COUNT(*) AS total
    FROM _visitor_pageview
    GROUP BY page_url, page_title ORDER BY total DESC LIMIT 10
")->fetchAll();

$devices = $db->query("
    SELECT device_type, COUNT(*) AS total FROM _visitor
    WHERE device_type IS NOT NULL AND is_bot = 0
    GROUP BY device_type ORDER BY total DESC
")->fetchAll();

$countries = $db->query("
    SELECT country_code, country_name, COUNT(*) AS total FROM _visitor
    WHERE country_code IS NOT NULL AND is_bot = 0
    GROUP BY country_code, country_name ORDER BY total DESC LIMIT 8
")->fetchAll();

$browsers = $db->query("
    SELECT browser, COUNT(*) AS total FROM _visitor
    WHERE browser IS NOT NULL AND is_bot = 0
    GROUP BY browser ORDER BY total DESC LIMIT 6
")->fetchAll();

$chartLabels = array_map(fn($d) => date('d/m', strtotime($d['visit_date'])), $dailyVisits);
$chartData   = array_map(fn($d) => (int) $d['total'], $dailyVisits);
$maxPage     = !empty($topPages)  ? max(array_column($topPages,  'total')) : 1;
$maxCountry  = !empty($countries) ? max(array_column($countries, 'total')) : 1;

function flagEmoji(string $code): string {
    $code = strtoupper(trim($code));
    if (strlen($code) !== 2) return '';
    return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8')
         . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
}
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
<?php include __DIR__ . '/an_shared.css.php';
/* see note below — CSS is inlined */
?>

/* ─── SHARED ANALYTICS STYLES (inlined) ─── */
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

/* Sub-nav */
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
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: .7rem;
    margin-bottom: 1.3rem
}

.kpi-card {
    background: var(--an-card);
    border: 1px solid var(--an-border);
    border-radius: var(--an-radius);
    padding: 1rem 1.1rem;
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
    margin-bottom: .6rem
}

.kpi-icon {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
    background: rgba(255, 255, 255, .06);
    color: var(--kc, var(--c-blue))
}

.kpi-val {
    font-family: var(--fm);
    font-size: 1.55rem;
    font-weight: 500;
    letter-spacing: -.03em;
    color: var(--an-text);
    line-height: 1;
    margin-bottom: 3px
}

.kpi-lbl {
    font-size: .65rem;
    color: var(--an-muted);
    font-family: var(--fb);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .07em
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

.chart-wrap {
    position: relative;
    width: 100%
}

/* Grids */
.g21 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem
}

.g111 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem
}

.g11 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem
}

@media(max-width:1200px) {

    .g21,
    .g111,
    .g11 {
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

/* Progress */
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
    min-width: 26px;
    text-align: right
}

/* Live */
.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--c-green);
    display: inline-block;
    animation: lpulse 1.8s infinite;
    box-shadow: 0 0 0 0 rgba(52, 211, 153, .5)
}

@keyframes lpulse {
    0% {
        box-shadow: 0 0 0 0 rgba(52, 211, 153, .5)
    }

    70% {
        box-shadow: 0 0 0 7px rgba(52, 211, 153, 0)
    }

    100% {
        box-shadow: 0 0 0 0 rgba(52, 211, 153, 0)
    }
}

/* Btn */
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

/* Fade-up */
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

.d5 {
    animation-delay: .2s
}

.d6 {
    animation-delay: .24s
}

.d7 {
    animation-delay: .28s
}

.d8 {
    animation-delay: .32s
}

/* Device color dots */
.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0
}

.url-cell {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: var(--fm);
    font-size: .73rem;
    color: var(--an-dim)
}
</style>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <!-- Page header -->
            <div class="page-top an-a">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <span class="sep"><i class="fas fa-chevron-right"></i></span>
                        <span>Analytics</span>
                    </div>
                    <h1 class="page-title">Analytics</h1>
                    <p class="page-sub">
                        <?php if ($onlineVisitors > 0): ?>
                        <span class="live-dot"></span>&nbsp;
                        <strong style="color:var(--c-green)"><?= $onlineVisitors ?></strong> online agora &nbsp;·&nbsp;
                        <?php endif; ?>
                        <?= $last24h ?> pageviews nas últimas 24h
                    </p>
                </div>
            </div>

            <!-- Sub-nav -->
            <nav class="an-nav an-a d1">
                <a href="<?= BASE_URL ?>/jm-panel/analytics" class="an-nav-item active">
                    <i class="fas fa-chart-line"></i> Visão Geral
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/pageviews" class="an-nav-item">
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
            <div class="kpi-row">
                <div class="kpi-card an-a d1" style="--kc:var(--c-blue)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-users"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalVisitors ?>"><?= number_format($totalVisitors) ?></div>
                    <div class="kpi-lbl">Visitantes</div>
                </div>
                <div class="kpi-card an-a d2" style="--kc:var(--c-teal)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalPageviews ?>"><?= number_format($totalPageviews) ?></div>
                    <div class="kpi-lbl">Pageviews</div>
                </div>
                <div class="kpi-card an-a d3" style="--kc:var(--c-amber)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-file-alt"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $avgPages ?>" data-decimals="1"><?= $avgPages ?></div>
                    <div class="kpi-lbl">Págs/Visitante</div>
                </div>
                <div class="kpi-card an-a d4" style="--kc:var(--c-purple)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-clock"></i></span>
                    </div>
                    <div class="kpi-val"><?= $avgTime ?>s</div>
                    <div class="kpi-lbl">Tempo Médio</div>
                </div>
                <div class="kpi-card an-a d5" style="--kc:var(--c-rose)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-globe"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $uniqueCountries ?>"><?= $uniqueCountries ?></div>
                    <div class="kpi-lbl">Países</div>
                </div>
                <div class="kpi-card an-a d6" style="--kc:var(--an-muted)">
                    <div class="kpi-top">
                        <span class="kpi-icon"><i class="fas fa-robot"></i></span>
                    </div>
                    <div class="kpi-val" data-count="<?= $totalBots ?>"><?= $totalBots ?></div>
                    <div class="kpi-lbl">Bots</div>
                </div>
            </div>

            <!-- Main chart + top pages -->
            <div class="g21 an-a d3">
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Visitas — Últimos 30 dias</h3>
                        <span
                            style="font-family:var(--fm);font-size:.72rem;color:var(--an-muted)"><?= array_sum($chartData) ?>
                            total</span>
                    </div>
                    <div class="chart-wrap"><canvas id="visitsChart" height="280"></canvas></div>
                </div>
                <div class="table-card">
                    <div class="card-hd">
                        <h3>Top Páginas</h3>
                        <a href="<?= BASE_URL ?>/jm-panel/analytics/pageviews"
                            style="font-size:.72rem;color:var(--an-dim);text-decoration:none">Ver todas →</a>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Página</th>
                                <th>Visitas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPages as $p): ?>
                            <tr>
                                <td>
                                    <div class="url-cell" title="<?= e($p['page_url']) ?>"><?= e($p['page_url']) ?>
                                    </div>
                                    <?php if (!empty($p['page_title'])): ?>
                                    <div style="font-size:.68rem;color:var(--an-muted);margin-top:1px">
                                        <?= e(mb_substr($p['page_title'], 0, 30)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" style="--pf:var(--c-blue)"
                                                data-w="<?= round($p['total'] / $maxPage * 100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $p['total'] ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Devices + Countries + Browsers -->
            <div class="g111 an-a d5">
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Dispositivos</h3>
                    </div>
                    <div class="chart-wrap"><canvas id="devicesChart" height="200"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Top Países</h3>
                        <a href="<?= BASE_URL ?>/jm-panel/analytics/geo"
                            style="font-size:.72rem;color:var(--an-dim);text-decoration:none">Ver →</a>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>País</th>
                                <th>Visitantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($countries as $c): ?>
                            <tr>
                                <td style="display:flex;align-items:center;gap:.5rem">
                                    <span
                                        style="font-size:1rem;line-height:1"><?= flagEmoji($c['country_code']) ?></span>
                                    <span><?= e($c['country_name'] ?: $c['country_code']) ?></span>
                                </td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" style="--pf:var(--c-teal)"
                                                data-w="<?= round($c['total'] / $maxCountry * 100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $c['total'] ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Navegadores</h3>
                    </div>
                    <div class="chart-wrap"><canvas id="browsersChart" height="200"></canvas></div>
                </div>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    // ── Count-up animation ────────────────────────────────────
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseFloat(el.dataset.count);
        const dec = parseInt(el.dataset.decimals || 0);
        const dur = 900;
        const step = 16;
        const steps = dur / step;
        let cur = 0;
        const inc = target / steps;
        const t = setInterval(() => {
            cur += inc;
            if (cur >= target) {
                cur = target;
                clearInterval(t);
            }
            el.textContent = dec ? cur.toFixed(dec) : Math.floor(cur).toLocaleString('pt');
        }, step);
    });

    // ── Progress bars ─────────────────────────────────────────
    setTimeout(() => {
        document.querySelectorAll('.pg-fill[data-w]').forEach(el => {
            el.style.width = el.dataset.w + '%';
        });
    }, 300);

    // ── Chart defaults ────────────────────────────────────────
    Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
    Chart.defaults.color = '#64748b';

    const tooltipOpts = {
        backgroundColor: '#0d1117',
        borderColor: 'rgba(148,163,184,.15)',
        borderWidth: 1,
        titleColor: '#e2e8f0',
        bodyColor: '#94a3b8',
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
    };

    // ── Main visits chart (area) ──────────────────────────────
    const visCtx = document.getElementById('visitsChart').getContext('2d');
    const grad = visCtx.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(91,141,238,.28)');
    grad.addColorStop(.7, 'rgba(91,141,238,.05)');
    grad.addColorStop(1, 'rgba(91,141,238,0)');

    new Chart(visCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                data: <?= json_encode($chartData) ?>,
                borderColor: '#5b8dee',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: .35,
                pointBackgroundColor: '#5b8dee',
                pointBorderColor: '#0d1117',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: tooltipOpts
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
                        color: 'rgba(255,255,255,.04)'
                    },
                    ticks: {
                        color: '#475569',
                        maxRotation: 0,
                        maxTicksLimit: 8
                    }
                }
            }
        }
    });

    // ── Devices doughnut ──────────────────────────────────────
    const devData = <?= json_encode($devices) ?>;
    const devColors = ['#5b8dee', '#2dd4bf', '#fbbf24', '#a78bfa', '#f87171'];
    new Chart(document.getElementById('devicesChart'), {
        type: 'doughnut',
        data: {
            labels: devData.map(d => d.device_type.charAt(0).toUpperCase() + d.device_type.slice(1)),
            datasets: [{
                data: devData.map(d => d.total),
                backgroundColor: devColors,
                borderWidth: 0,
                hoverOffset: 6
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
                        color: '#94a3b8',
                        padding: 14,
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

    // ── Browsers bar ──────────────────────────────────────────
    const brData = <?= json_encode($browsers) ?>;
    const brColors = ['#5b8dee', '#2dd4bf', '#fbbf24', '#a78bfa', '#f87171', '#fb923c'];
    new Chart(document.getElementById('browsersChart'), {
        type: 'bar',
        data: {
            labels: brData.map(b => b.browser),
            datasets: [{
                data: brData.map(b => b.total),
                backgroundColor: brColors,
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: tooltipOpts
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
                        color: '#475569'
                    }
                }
            }
        }
    });
    </script>
</body>

</html>