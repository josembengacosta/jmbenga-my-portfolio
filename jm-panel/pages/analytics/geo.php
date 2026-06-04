<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Analytics: Geografia (v2)
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

$countries = $db->query("
    SELECT country_code, country_name, COUNT(*) AS total
    FROM _visitor WHERE country_code IS NOT NULL AND is_bot = 0
    GROUP BY country_code, country_name ORDER BY total DESC LIMIT 15
")->fetchAll();

$cities = $db->query("
    SELECT city, country_name, country_code, COUNT(*) AS total
    FROM _visitor WHERE city IS NOT NULL AND is_bot = 0
    GROUP BY city, country_name, country_code ORDER BY total DESC LIMIT 15
")->fetchAll();

$totalCountryVisitors = !empty($countries) ? array_sum(array_column($countries, 'total')) : 1;
$maxCountry = !empty($countries) ? max(array_column($countries, 'total')) : 1;
$maxCity    = !empty($cities)    ? max(array_column($cities,    'total')) : 1;
$uniqueCountryCount = count($countries);

function flagEmoji(string $code): string {
    $code = strtoupper(trim($code));
    if (strlen($code) !== 2) return '🏳';
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

/* Stat row */
.stat-row {
    display: flex;
    gap: .7rem;
    flex-wrap: wrap;
    margin-bottom: 1.3rem
}

.stat-pill {
    flex: 1;
    min-width: 120px;
    background: var(--an-card);
    border: 1px solid var(--an-border);
    border-radius: var(--an-radius);
    padding: .9rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: .2rem;
    transition: border-color .2s, transform .2s
}

.stat-pill:hover {
    border-color: var(--an-border-h);
    transform: translateY(-2px)
}

.stat-pill-val {
    font-family: var(--fm);
    font-size: 1.5rem;
    font-weight: 500;
    color: var(--an-text);
    letter-spacing: -.03em
}

.stat-pill-lbl {
    font-size: .63rem;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: 700;
    color: var(--an-muted);
    font-family: var(--fb)
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

.card-hd .card-badge {
    font-family: var(--fm);
    font-size: .7rem;
    color: var(--an-muted)
}

.chart-wrap {
    position: relative;
    width: 100%
}

.g11 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem
}

@media(max-width:1200px) {
    .g11 {
        grid-template-columns: 1fr
    }
}

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
    padding: .58rem .5rem;
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
    min-width: 26px;
    text-align: right
}

.pg-pct {
    font-family: var(--fm);
    font-size: .65rem;
    color: var(--an-muted);
    min-width: 34px;
    text-align: right
}

.flag-cell {
    display: flex;
    align-items: center;
    gap: .55rem
}

.flag {
    font-size: 1.1rem;
    line-height: 1;
    flex-shrink: 0
}

.country-name {
    font-weight: 500
}

.country-code {
    font-size: .68rem;
    color: var(--an-muted);
    font-family: var(--fm)
}

.rank-num {
    font-family: var(--fm);
    font-size: .7rem;
    color: var(--an-muted);
    width: 22px
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
                        <span>Geografia</span>
                    </div>
                    <h1 class="page-title">Geografia</h1>
                    <p class="page-sub"><?= $uniqueCountryCount ?> países representados ·
                        <?= number_format($totalCountryVisitors) ?> visitantes</p>
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
                <a href="<?= BASE_URL ?>/jm-panel/analytics/pageviews" class="an-nav-item">
                    <i class="fas fa-eye"></i> Pageviews
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/devices" class="an-nav-item">
                    <i class="fas fa-mobile-alt"></i> Dispositivos
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/analytics/geo" class="an-nav-item active">
                    <i class="fas fa-globe"></i> Geografia
                </a>
            </nav>

            <!-- Stat pills: top 3 countries -->
            <?php if (!empty($countries)): ?>
            <div class="stat-row an-a d1">
                <?php foreach (array_slice($countries, 0, 4) as $i => $c): ?>
                <?php $medals = ['🥇','🥈','🥉','4️⃣']; ?>
                <div class="stat-pill">
                    <div style="font-size:1.4rem;line-height:1;margin-bottom:.2rem"><?= $medals[$i] ?? '' ?>
                        <?= flagEmoji($c['country_code']) ?></div>
                    <div class="stat-pill-val"><?= number_format($c['total']) ?></div>
                    <div class="stat-pill-lbl"><?= e($c['country_name'] ?: $c['country_code']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Countries chart + Cities table -->
            <div class="g11 an-a d2">
                <div class="chart-card">
                    <div class="card-hd">
                        <h3>Top Países</h3>
                        <span class="card-badge">Top <?= count($countries) ?></span>
                    </div>
                    <div class="chart-wrap"><canvas id="countryChart" height="360"></canvas></div>
                </div>
                <div class="table-card">
                    <div class="card-hd">
                        <h3>Top Cidades</h3>
                        <span class="card-badge">Top <?= count($cities) ?></span>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cidade</th>
                                <th>Visitantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $i => $c): ?>
                            <tr>
                                <td class="rank-num"><?= $i + 1 ?></td>
                                <td>
                                    <div class="flag-cell">
                                        <span class="flag"><?= flagEmoji($c['country_code'] ?? '') ?></span>
                                        <div>
                                            <div class="country-name"><?= e($c['city']) ?></div>
                                            <div class="country-code"><?= e($c['country_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="pg-wrap">
                                        <div class="pg-bar">
                                            <div class="pg-fill" style="--pf:var(--c-teal)"
                                                data-w="<?= round($c['total']/$maxCity*100) ?>"></div>
                                        </div>
                                        <span class="pg-num"><?= $c['total'] ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Full country table -->
            <div class="table-card an-a d3">
                <div class="card-hd">
                    <h3>Todos os Países</h3>
                    <span class="card-badge"><?= $uniqueCountryCount ?> países</span>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>País</th>
                            <th>Código</th>
                            <th>Visitantes</th>
                            <th>% do total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($countries as $i => $c): ?>
                        <?php $pct = $totalCountryVisitors > 0 ? round($c['total']/$totalCountryVisitors*100, 1) : 0; ?>
                        <tr>
                            <td class="rank-num"><?= $i + 1 ?></td>
                            <td>
                                <div class="flag-cell">
                                    <span class="flag"><?= flagEmoji($c['country_code']) ?></span>
                                    <span class="country-name"><?= e($c['country_name'] ?: '—') ?></span>
                                </div>
                            </td>
                            <td><span
                                    style="font-family:var(--fm);font-size:.73rem;color:var(--an-muted)"><?= e($c['country_code']) ?></span>
                            </td>
                            <td>
                                <div class="pg-wrap">
                                    <div class="pg-bar">
                                        <div class="pg-fill" style="--pf:var(--c-blue)"
                                            data-w="<?= round($c['total']/$maxCountry*100) ?>"></div>
                                    </div>
                                    <span class="pg-num"><?= number_format($c['total']) ?></span>
                                </div>
                            </td>
                            <td><span
                                    style="font-family:var(--fm);font-size:.73rem;color:var(--an-dim)"><?= $pct ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
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

    setTimeout(() => {
        document.querySelectorAll('.pg-fill[data-w]').forEach(el => el.style.width = el.dataset.w + '%');
    }, 300);

    const countryData = <?= json_encode($countries) ?>;
    const ctxC = document.getElementById('countryChart').getContext('2d');
    const barGrad = ctxC.createLinearGradient(400, 0, 0, 0);
    barGrad.addColorStop(0, '#5b8dee');
    barGrad.addColorStop(1, '#2dd4bf');

    new Chart(ctxC, {
        type: 'bar',
        data: {
            labels: countryData.map(c => (c.country_name || c.country_code)),
            datasets: [{
                data: countryData.map(c => c.total),
                backgroundColor: barGrad,
                borderRadius: 5,
                borderSkipped: false,
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
                tooltip: {
                    ...tooltipOpts,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255,255,255,.05)'
                    },
                    ticks: {
                        color: '#475569'
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
    </script>
</body>

</html>