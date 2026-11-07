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
                        <span>Geografia</span>
                    </div>
                    <h1 class="page-title">Geografia</h1>
                    <p class="page-sub"><?= $uniqueCountryCount ?> países representados ·
                        <?= number_format($totalCountryVisitors) ?> visitantes</p>
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

    setTimeout(() => {
        document.querySelectorAll('.pg-fill[data-w]').forEach(el => el.style.width = el.dataset.w + '%');
    }, 300);

    const countryData = <?= json_encode($countries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const ctxC = document.getElementById('countryChart').getContext('2d');
    const barGrad = ctxC.createLinearGradient(400, 0, 0, 0);
    barGrad.addColorStop(0, anTheme.accent);
    barGrad.addColorStop(1, anTheme.teal);

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
                        color: anTheme.dim,
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