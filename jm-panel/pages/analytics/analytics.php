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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-analytics.css?v=20261030">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="analytics-page">
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
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'sans-serif';
    Chart.defaults.color = anTheme.muted;

    const tooltipOpts = {
        backgroundColor: anTheme.card,
        borderColor: anTheme.border,
        borderWidth: 1,
        titleColor: anTheme.text,
        bodyColor: anTheme.dim,
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
    };

    // ── Main visits chart (area) ──────────────────────────────
    const visCtx = document.getElementById('visitsChart').getContext('2d');
    const grad = visCtx.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, anRgba(anTheme.accent, .28));
    grad.addColorStop(.7, anRgba(anTheme.accent, .05));
    grad.addColorStop(1, anRgba(anTheme.accent, 0));

    new Chart(visCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            datasets: [{
                data: <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                borderColor: anTheme.accent,
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: .35,
                pointBackgroundColor: anTheme.accent,
                pointBorderColor: anTheme.card,
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
                        color: anTheme.border
                    },
                    ticks: {
                        color: anTheme.muted
                    }
                },
                x: {
                    grid: {
                        color: anTheme.border
                    },
                    ticks: {
                        color: anTheme.muted,
                        maxRotation: 0,
                        maxTicksLimit: 8
                    }
                }
            }
        }
    });

    // ── Devices doughnut ──────────────────────────────────────
    const devData = <?= json_encode($devices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const devColors = [anTheme.accent, anTheme.teal, anTheme.warning, anTheme.purple, anTheme.danger];
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
                        color: anTheme.dim,
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
    const brData = <?= json_encode($browsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const brColors = [anTheme.accent, anTheme.teal, anTheme.warning, anTheme.purple, anTheme.danger, anTheme.warning];
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
                        color: anTheme.muted
                    }
                }
            }
        }
    });
    </script>
</body>

</html>