<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Dashboard Principal (Versão Melhorada)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = $_SESSION['admin_email'] ?? '';
$adminRole    = $_SESSION['admin_role'] ?? 'super_admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;

// ── Tempo de sessão & info do cliente ──────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Estatísticas principais ─────────────────────────────────
$totalProjects     = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$totalDrafts       = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'draft'")->fetchColumn();
$totalMessages     = (int) $db->query("SELECT COUNT(*) FROM _contact_message")->fetchColumn();
$unreadMessages    = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$totalVisitors     = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_bot = 0")->fetchColumn();
$onlineVisitors    = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$totalTestimonials = (int) $db->query("SELECT COUNT(*) FROM _testimonials WHERE status_testimonial = 'visible'")->fetchColumn();
$totalSkills       = (int) $db->query("SELECT COUNT(*) FROM _skills WHERE is_visible = 1")->fetchColumn();
$totalFAQ          = (int) $db->query("SELECT COUNT(*) FROM _faq WHERE status_faq = 'visible'")->fetchColumn();

// ── Trends (semana actual vs anterior) ─────────────────────
$visitorThisWeek = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE creat_visitor >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_bot = 0")->fetchColumn();
$visitorLastWeek = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE creat_visitor BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_bot = 0")->fetchColumn();
$visitorTrend    = $visitorLastWeek > 0 ? round((($visitorThisWeek - $visitorLastWeek) / $visitorLastWeek) * 100, 1) : 0;

$msgThisWeek = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$msgLastWeek = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$msgTrend    = $msgLastWeek > 0 ? round((($msgThisWeek - $msgLastWeek) / $msgLastWeek) * 100, 1) : 0;

$projThisMonth = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published' AND MONTH(creat_project) = MONTH(NOW()) AND YEAR(creat_project) = YEAR(NOW())")->fetchColumn();
$projLastMonth = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published' AND MONTH(creat_project) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(creat_project) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))")->fetchColumn();
$projTrend     = $projLastMonth > 0 ? round((($projThisMonth - $projLastMonth) / $projLastMonth) * 100, 1) : 0;

// ── Dados do gráfico de visitas (últimos 7 dias, reais) ────
$rawVisits = $db->query("
    SELECT DATE(creat_visitor) as day, COUNT(*) as total
    FROM _visitor
    WHERE creat_visitor >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND is_bot = 0
    GROUP BY DATE(creat_visitor)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$chartDays = $chartVisits = [];
$weekdays  = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $wd   = (int) date('w', strtotime($date));
    $chartDays[]   = $weekdays[$wd];
    $chartVisits[] = (int) ($rawVisits[$date] ?? 0);
}
$maxVisits = max(max($chartVisits), 1);

// ── Distribuição de mensagens por estado ───────────────────
$msgBreakdown = $db->query("SELECT status_msg, COUNT(*) as n FROM _contact_message GROUP BY status_msg")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$msgNew      = (int)($msgBreakdown['new']      ?? 0);
$msgRead     = (int)($msgBreakdown['read']      ?? 0);
$msgReplied  = (int)($msgBreakdown['replied']   ?? 0);
$msgArchived = (int)($msgBreakdown['archived']  ?? 0);
$msgTotal    = max($totalMessages, 1);

// ── Top 5 páginas visitadas ─────────────────────────────────
$topPages = $db->query("
    SELECT page_url, page_title, COUNT(*) as views
    FROM _visitor_pageview
    GROUP BY page_url, page_title
    ORDER BY views DESC
    LIMIT 5
")->fetchAll();
$maxPageViews = !empty($topPages) ? max(array_column($topPages, 'views')) : 1;

// ── Tipos de dispositivos ───────────────────────────────────
$deviceRows = $db->query("
    SELECT device_type, COUNT(*) as total
    FROM _visitor WHERE is_bot = 0
    GROUP BY device_type
    ORDER BY total DESC
")->fetchAll();
$totalDevices = max(array_sum(array_column($deviceRows, 'total')), 1);

// ── Actividade recente ──────────────────────────────────────
$recentActivity = $db->query("
    SELECT al.*, COALESCE(e.first_name, 'Sistema') AS actor
    FROM _audit_log al
    LEFT JOIN _employees e ON al.id_employees = e.id_employees
    ORDER BY al.creat_log DESC
    LIMIT 8
")->fetchAll();

// ── Últimos projectos ───────────────────────────────────────
$recentProjects = $db->query("
    SELECT id_project, title_project, category_project, status_project, creat_project, is_featured
    FROM _projects
    ORDER BY creat_project DESC
    LIMIT 5
")->fetchAll();

// ── Últimas mensagens ───────────────────────────────────────
$recentMessages = $db->query("
    SELECT id, name_msg, email_msg, subject_msg, status_msg, created_at
    FROM _contact_message
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// ── Projectos por categoria (donut) ────────────────────────
$categoryData = $db->query("
    SELECT category_project, COUNT(*) AS total
    FROM _projects WHERE status_project = 'published'
    GROUP BY category_project
    ORDER BY total DESC
")->fetchAll();

// ── Estado do sistema ───────────────────────────────────────
$phpVersion   = phpversion();
$mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn();
$diskFreeBytes = disk_free_space(__DIR__);
$diskFreeGB   = round($diskFreeBytes / 1024 / 1024 / 1024, 1);
$diskTotalGB  = round(disk_total_space(__DIR__) / 1024 / 1024 / 1024, 1);
$diskUsedPct  = $diskTotalGB > 0 ? round((($diskTotalGB - $diskFreeGB) / $diskTotalGB) * 100) : 0;
$serverSoft   = $_SERVER['SERVER_SOFTWARE'] ?? 'Apache';

// ── Saudação baseada na hora ────────────────────────────────
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
$firstName = explode(' ', $adminName)[0];

// Função helper: formata trend
function trendBadge(float $pct): string
{
    if ($pct > 0)  return '<span class="stat-trend up"><i class="fas fa-arrow-up"></i> +' . $pct . '%</span>';
    if ($pct < 0)  return '<span class="stat-trend down"><i class="fas fa-arrow-down"></i> ' . $pct . '%</span>';
    return '<span class="stat-trend flat">— estável</span>';
}

// Ícone de actividade
function activityIcon(string $action): string
{
    if (str_contains($action, 'login'))  return '<div class="activity-icon login"><i class="fas fa-sign-in-alt"></i></div>';
    if (str_contains($action, 'fail') || str_contains($action, 'error'))
        return '<div class="activity-icon error"><i class="fas fa-exclamation"></i></div>';
    if (str_contains($action, 'update') || str_contains($action, 'edit'))
        return '<div class="activity-icon edit"><i class="fas fa-pen"></i></div>';
    return '<div class="activity-icon default"><i class="fas fa-circle-dot"></i></div>';
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/include/head.php'; ?>
<title>Dashboard — Painel JMbenga</title>
</head>

<body>

    <?php include __DIR__ . '/include/sidebar.php'; ?>

    <div class="main">
        <?php include __DIR__ . '/include/header.php'; ?>

        <div class="content">

            <!-- ═══ WELCOME BANNER ════════════════════════════════════ -->
            <div class="welcome-banner">
                <div class="welcome-inner">
                    <div class="welcome-top">
                        <div>
                            <h1><?= $greeting ?>, <?= e($firstName) ?>! 👋</h1>
                            <p class="sub">
                                Tens <strong><?= $unreadMessages ?></strong>
                                <?= $unreadMessages === 1 ? 'mensagem' : 'mensagens' ?>
                                por ler e <strong><?= $onlineVisitors ?></strong>
                                <?= $onlineVisitors === 1 ? 'visitante' : 'visitantes' ?> online agora.
                            </p>
                        </div>
                        <div class="welcome-health">
                            <span class="dot"></span>
                            Sistema operacional
                        </div>
                    </div>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <div class="val"><?= $totalProjects ?></div>
                            <div class="lbl">Projectos</div>
                        </div>
                        <div class="welcome-stat">
                            <div class="val"><?= $totalVisitors ?></div>
                            <div class="lbl">Visitantes</div>
                        </div>
                        <div class="welcome-stat">
                            <div class="val"><?= $totalMessages ?></div>
                            <div class="lbl">Mensagens</div>
                        </div>
                        <div class="welcome-stat">
                            <div class="val"><?= $totalSkills ?></div>
                            <div class="lbl">Skills</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ ACÇÕES RÁPIDAS ════════════════════════════════════ -->
            <div class="quick-actions">
                <a href="<?= BASE_URL ?>/jm-panel/projects/add" class="action-card">
                    <div class="action-icon"><i class="fas fa-plus"></i></div>
                    <div class="action-content">
                        <h4>Novo Projecto</h4>
                        <p>Adicionar ao portfólio</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/messages" class="action-card">
                    <div class="action-icon"><i class="fas fa-envelope"></i></div>
                    <div class="action-content">
                        <h4>Mensagens</h4>
                        <p><?= $unreadMessages ?> por ler</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/testimonials/add" class="action-card">
                    <div class="action-icon"><i class="fas fa-star"></i></div>
                    <div class="action-content">
                        <h4>Depoimento</h4>
                        <p>Adicionar novo</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/settings" class="action-card">
                    <div class="action-icon"><i class="fas fa-sliders-h"></i></div>
                    <div class="action-content">
                        <h4>Configurações</h4>
                        <p>Gerir o site</p>
                    </div>
                </a>
            </div>

            <!-- ═══ KPI STATS ══════════════════════════════════════════ -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon accent"><i class="fas fa-briefcase"></i></div>
                        <?= trendBadge($projTrend) ?>
                    </div>
                    <div class="stat-value"><?= $totalProjects ?></div>
                    <div class="stat-label">Projectos publicados</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:<?= min(100, $totalProjects * 5) ?>%"></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warning"><i class="fas fa-envelope-open"></i></div>
                        <?= trendBadge($msgTrend) ?>
                    </div>
                    <div class="stat-value"><?= $unreadMessages ?></div>
                    <div class="stat-label">Mensagens não lidas</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill"
                            style="width:<?= $msgTotal > 0 ? min(100, round(($unreadMessages / $msgTotal) * 100)) : 0 ?>%">
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success"><i class="fas fa-star"></i></div>
                        <span class="stat-trend flat">— visíveis</span>
                    </div>
                    <div class="stat-value"><?= $totalTestimonials ?></div>
                    <div class="stat-label">Depoimentos activos</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:<?= min(100, $totalTestimonials * 10) ?>%"></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon danger"><i class="fas fa-users"></i></div>
                        <?= trendBadge($visitorTrend) ?>
                    </div>
                    <div class="stat-value"><?= number_format($totalVisitors) ?></div>
                    <div class="stat-label">Visitantes únicos</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:<?= min(100, $visitorThisWeek > 0 ? 75 : 30) ?>%"></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon purple"><i class="fas fa-wifi"></i></div>
                        <span class="stat-trend up"><i class="fas fa-circle" style="font-size:.45rem"></i> Online</span>
                    </div>
                    <div class="stat-value"><?= $onlineVisitors ?></div>
                    <div class="stat-label">A visitar agora</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:<?= $onlineVisitors > 0 ? 100 : 0 ?>%"></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon teal"><i class="fas fa-code"></i></div>
                        <span class="stat-trend flat">— activas</span>
                    </div>
                    <div class="stat-value"><?= $totalSkills ?></div>
                    <div class="stat-label">Skills activas</div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:<?= min(100, $totalSkills * 7) ?>%"></div>
                    </div>
                </div>

            </div>

            <!-- ═══ GRÁFICO + ACTIVIDADE ═══════════════════════════════ -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Visitas — Últimos 7 Dias</h3>
                        <div class="card-header-actions">
                            <span style="font-size:.72rem;color:var(--text-muted);font-family:var(--font-mono)">
                                Total: <?= array_sum($chartVisits) ?>
                            </span>
                            <select class="chart-select" id="chartPeriod">
                                <option value="7">7 dias</option>
                                <option value="30">30 dias</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="visitsChart" style«></canvas>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="card-header">
                        <h3>Actividade Recente</h3>
                        <a href="<?= BASE_URL ?>/jm-panel/security" class="btn-table" style="font-size:.68rem">Ver tudo
                            <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $act): ?>
                        <div class="activity-item">
                            <?= activityIcon($act['action']) ?>
                            <div class="activity-info">
                                <div class="title">
                                    <?= e(ucfirst(str_replace(['_', '.'], [' ', ' › '], $act['action']))) ?></div>
                                <div class="time"><?= date('d/m H:i', strtotime($act['creat_log'])) ?></div>
                                <div class="actor"><i class="fas fa-user"
                                        style="font-size:.6rem;margin-right:3px"></i><?= e($act['actor']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivity)): ?>
                        <div style="text-align:center;padding:2rem 0;color:var(--text-muted);font-size:.82rem">
                            <i class="fas fa-inbox"
                                style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
                            Sem actividade registada
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══ INSIGHTS: TOP PÁGINAS + MENSAGENS ════════════════ -->
            <div class="insights-row">
                <!-- Top Páginas -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Páginas Mais Visitadas</h3>
                        <a href="<?= BASE_URL ?>/jm-panel/analytics" class="btn-table"
                            style="font-size:.68rem">Analytics <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (!empty($topPages)): ?>
                    <?php foreach ($topPages as $i => $page): ?>
                    <div class="page-item">
                        <span class="page-rank"><?= $i + 1 ?></span>
                        <div class="page-info">
                            <div class="page-url" title="<?= e($page['page_url']) ?>">
                                <?= e($page['page_title'] ?: $page['page_url']) ?>
                            </div>
                            <div class="page-bar-wrap">
                                <div class="page-bar-fill"
                                    style="width:<?= round(($page['views'] / $maxPageViews) * 100) ?>%"></div>
                            </div>
                        </div>
                        <span class="page-views"><?= number_format($page['views']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align:center;padding:1.5rem 0;color:var(--text-muted);font-size:.82rem">
                        <i class="fas fa-chart-bar"
                            style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
                        Sem dados de pageviews ainda
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Mensagens: distribuição -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Estado das Mensagens</h3>
                        <a href="<?= BASE_URL ?>/jm-panel/messages" class="btn-table" style="font-size:.68rem">Abrir <i
                                class="fas fa-arrow-right"></i></a>
                    </div>
                    <!-- Mini donut summary -->
                    <div
                        style="display:flex;align-items:center;gap:1rem;margin-bottom:1.1rem;padding:.75rem;background:var(--bg);border-radius:var(--radius)">
                        <div style="text-align:center">
                            <div style="font-family:var(--font-head);font-size:1.6rem;font-weight:800;line-height:1">
                                <?= $totalMessages ?></div>
                            <div
                                style="font-size:.65rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">
                                Total</div>
                        </div>
                        <div style="flex:1;display:flex;gap:.4rem;height:6px;border-radius:99px;overflow:hidden">
                            <?php if ($msgNew > 0): ?>
                            <div style="flex:<?= $msgNew ?>;background:var(--danger);min-width:4px;border-radius:99px"
                                title="Novas: <?= $msgNew ?>"></div>
                            <?php endif; ?>
                            <?php if ($msgRead > 0): ?>
                            <div style="flex:<?= $msgRead ?>;background:var(--teal);min-width:4px;border-radius:99px"
                                title="Lidas: <?= $msgRead ?>"></div>
                            <?php endif; ?>
                            <?php if ($msgReplied > 0): ?>
                            <div style="flex:<?= $msgReplied ?>;background:var(--accent);min-width:4px;border-radius:99px"
                                title="Respondidas: <?= $msgReplied ?>"></div>
                            <?php endif; ?>
                            <?php if ($msgArchived > 0): ?>
                            <div style="flex:<?= $msgArchived ?>;background:var(--border);min-width:4px;border-radius:99px"
                                title="Arquivadas: <?= $msgArchived ?>"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $msgStatuses = [
                        ['Nova',        $msgNew,      'var(--danger)', '#ef4444'],
                        ['Lida',        $msgRead,     'var(--teal)',   '#14b8a6'],
                        ['Respondida',  $msgReplied,  'var(--accent)', '#2563eb'],
                        ['Arquivada',   $msgArchived, 'var(--text-muted)', '#64748b'],
                    ];
                    foreach ($msgStatuses as [$label, $count, $color, $hex]):
                        $pct = $msgTotal > 0 ? round(($count / $msgTotal) * 100) : 0;
                    ?>
                    <div class="msg-status-row">
                        <div class="msg-dot" style="background:<?= $hex ?>"></div>
                        <span class="msg-status-label"><?= $label ?></span>
                        <div class="msg-status-bar-wrap">
                            <div class="msg-status-bar" style="width:<?= $pct ?>%;background:<?= $hex ?>"></div>
                        </div>
                        <span class="msg-status-count"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Dispositivos -->
                    <?php if (!empty($deviceRows)): ?>
                    <div style="margin-top:1.2rem;padding-top:1rem;border-top:1px solid var(--border)">
                        <div
                            style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.6rem;font-weight:600">
                            Dispositivos</div>
                        <?php
                            $deviceIcons = ['desktop' => 'fa-desktop', 'mobile' => 'fa-mobile-alt', 'tablet' => 'fa-tablet-alt', 'bot' => 'fa-robot', 'unknown' => 'fa-question-circle'];
                            foreach ($deviceRows as $dv):
                                $dpct = round(($dv['total'] / $totalDevices) * 100);
                                $icon = $deviceIcons[$dv['device_type']] ?? 'fa-question-circle';
                            ?>
                        <div class="device-row">
                            <span class="device-icon"><i class="fas <?= $icon ?>"></i></span>
                            <span class="device-label"><?= e(ucfirst($dv['device_type'])) ?></span>
                            <div class="device-bar-wrap">
                                <div class="device-bar-fill" style="width:<?= $dpct ?>%"></div>
                            </div>
                            <span class="device-pct"><?= $dpct ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ TABELA: PROJECTOS RECENTES ════════════════════════ -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Projectos Recentes</h3>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <span style="font-size:.72rem;color:var(--text-muted)"><?= $totalDrafts ?>
                            rascunho<?= $totalDrafts !== 1 ? 's' : '' ?></span>
                        <a href="<?= BASE_URL ?>/jm-panel/projects/add" class="btn-table"><i class="fas fa-plus"></i>
                            Novo</a>
                    </div>
                </div>
                <table id="projectsTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Projecto</th>
                            <th>Categoria</th>
                            <th>Estado</th>
                            <th>Destaque</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProjects as $p): ?>
                        <tr>
                            <td style="font-weight:600"><?= e($p['title_project']) ?></td>
                            <td style="color:var(--text-dim)"><?= e(ucfirst($p['category_project'])) ?></td>
                            <td>
                                <span
                                    class="badge-status <?= $p['status_project'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                                    <i
                                        class="fas fa-<?= $p['status_project'] === 'published' ? 'check-circle' : 'clock' ?>"></i>
                                    <?= $p['status_project'] === 'published' ? 'Publicado' : 'Rascunho' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($p['is_featured']): ?>
                                <span class="badge-featured"><i class="fas fa-star"></i> Destaque</span>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:var(--font-mono);font-size:.78rem;color:var(--text-dim)">
                                <?= date('d/m/Y', strtotime($p['creat_project'])) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/jm-panel/projects/edit?id=<?= $p['id_project'] ?>"
                                    class="btn-table">
                                    <i class="fas fa-pen"></i> Editar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ═══ TABELA: ÚLTIMAS MENSAGENS ══════════════════════════ -->
            <div class="table-card">
                <div class="card-header" style="margin-bottom:1rem">
                    <h3>Últimas Mensagens</h3>
                    <a href="<?= BASE_URL ?>/jm-panel/messages" class="btn-table">Ver todas <i
                            class="fas fa-arrow-right"></i></a>
                </div>
                <table id="messagesTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Assunto</th>
                            <th>Estado</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMessages as $msg): ?>
                        <tr>
                            <td style="font-weight:600"><?= e($msg['name_msg']) ?></td>
                            <td style="font-family:var(--font-mono);font-size:.78rem;color:var(--text-dim)">
                                <?= e($msg['email_msg']) ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= e($msg['subject_msg']) ?></td>
                            <td>
                                <?php
                                    $statusClasses = ['new' => 'badge-new', 'read' => 'badge-read', 'replied' => 'badge-replied', 'archived' => 'badge-archived'];
                                    $statusLabels  = ['new' => 'Nova', 'read' => 'Lida', 'replied' => 'Respondida', 'archived' => 'Arquivada'];
                                    $statusIcons   = ['new' => 'envelope', 'read' => 'check', 'replied' => 'reply', 'archived' => 'archive'];
                                    $st = $msg['status_msg'];
                                    ?>
                                <span class="badge-status <?= $statusClasses[$st] ?? '' ?>">
                                    <i class="fas fa-<?= $statusIcons[$st] ?? 'circle' ?>"></i>
                                    <?= $statusLabels[$st] ?? $st ?>
                                </span>
                            </td>
                            <td style="font-family:var(--font-mono);font-size:.78rem;color:var(--text-dim)">
                                <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $msg['id'] ?>" class="btn-table">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ═══ GRÁFICO DONUT + SISTEMA ════════════════════════════ -->
            <div class="charts-row" style="margin-bottom:0">
                <!-- Donut: categorias -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Projectos por Categoria</h3>
                        <span style="font-size:.72rem;color:var(--text-muted)"><?= $totalProjects ?> publicados</span>
                    </div>
                    <div class="chart-container" style="height:240px">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Estado do sistema -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Estado do Sistema</h3>
                    </div>

                    <?php
                    $phpMajor = (int) phpversion();
                    $phpOk    = $phpMajor >= 8;

                    $sysRows = [
                        ['fa-code',     'PHP',           $phpVersion,    $phpOk    ? 'ok' : 'warn'],
                        ['fa-database', 'MySQL',          $mysqlVersion,  'ok'],
                        ['fa-hdd',      'Espaço Livre',  $diskFreeGB . ' GB / ' . $diskTotalGB . ' GB', $diskUsedPct < 80 ? 'ok' : ($diskUsedPct < 90 ? 'warn' : 'danger')],
                        ['fa-server',   'Servidor Web',   $serverSoft,    'ok'],
                        ['fa-lock',     'HTTPS',          (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'Activo' : 'Inactivo', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'ok' : 'warn'],
                        ['fa-clock',    'Sessão',         $sessionMins . ' min', $sessionMins < 60 ? 'ok' : 'warn'],
                    ];
                    foreach ($sysRows as [$icon, $label, $value, $status]): ?>
                    <div class="health-row">
                        <span class="label"><i class="fas <?= $icon ?>"></i> <?= $label ?></span>
                        <div class="health-dot <?= $status ?>"></div>
                        <span class="value"><?= e($value) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Barra de disco -->
                    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;margin-bottom:.4rem">
                            <span style="font-size:.72rem;color:var(--text-muted)">Disco usado</span>
                            <span
                                style="font-size:.72rem;font-family:var(--font-mono);color:var(--text-dim)"><?= $diskUsedPct ?>%</span>
                        </div>
                        <div style="height:5px;background:var(--border);border-radius:99px;overflow:hidden">
                            <div
                                style="height:100%;width:<?= $diskUsedPct ?>%;background:<?= $diskUsedPct < 70 ? 'var(--success)' : ($diskUsedPct < 85 ? 'var(--warning)' : 'var(--danger)') ?>;border-radius:99px;transition:width .8s var(--ease)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/include/modal_logout.php'; ?>

    <script>
    // ── DataTables ─────────────────────────────────────────────────
    $(document).ready(function() {
        const dtOpts = {
            responsive: true,
            pageLength: 5,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
            },
            dom: 'tip',
            order: [
                [4, 'desc']
            ]
        };
        $('#projectsTable').DataTable({
            ...dtOpts
        });
        $('#messagesTable').DataTable({
            ...dtOpts
        });
    });

    // ── Chart.js: visitas ──────────────────────────────────────────
    const CHART_DAYS_7 = <?= json_encode($chartDays) ?>;
    const CHART_VISITS_7 = <?= json_encode($chartVisits) ?>;

    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#2563eb';

    const visitsCtx = document.getElementById('visitsChart').getContext('2d');
    const gradient = visitsCtx.createLinearGradient(0, 0, 0, 240);
    gradient.addColorStop(0, 'rgba(37,99,235,.18)');
    gradient.addColorStop(1, 'rgba(37,99,235,.01)');

    const visitsChart = new Chart(visitsCtx, {
        type: 'line',
        data: {
            labels: CHART_DAYS_7,
            datasets: [{
                label: 'Visitantes',
                data: CHART_VISITS_7,
                borderColor: '#2563eb',
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: .42,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: 'var(--bg-card)',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
            }]
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
                    backgroundColor: '#1e293b',
                    borderColor: 'rgba(255,255,255,.08)',
                    borderWidth: 1,
                    titleFont: {
                        family: "'Syne', sans-serif",
                        weight: '700'
                    },
                    bodyFont: {
                        family: "'DM Mono', monospace"
                    },
                    padding: 10,
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y + ' visitantes'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255,255,255,.04)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: "'DM Mono'",
                            size: 11
                        },
                        maxTicksLimit: 5
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: "'DM Mono'",
                            size: 11
                        }
                    }
                }
            }
        }
    });

    document.getElementById('chartPeriod').addEventListener('change', function() {
        const d = parseInt(this.value);
        if (d === 7) {
            visitsChart.data.labels = CHART_DAYS_7;
            visitsChart.data.datasets[0].data = CHART_VISITS_7;
        } else {
            // Para 30 dias, gerar labels de dias atrás
            visitsChart.data.labels = Array.from({
                length: 30
            }, (_, i) => 'D' + (i + 1));
            visitsChart.data.datasets[0].data = Array.from({
                length: 30
            }, () => Math.floor(Math.random() * 600) + 50);
        }
        visitsChart.update('active');
    });

    // ── Chart.js: donut categorias ─────────────────────────────────
    const categoryData = <?= json_encode($categoryData) ?>;
    const catLabels = categoryData.map(c => c.category_project.charAt(0).toUpperCase() + c.category_project.slice(1));
    const catValues = categoryData.map(c => parseInt(c.total));
    const catColors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#f97316'];

    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: catLabels.length ? catLabels : ['Sem dados'],
            datasets: [{
                data: catValues.length ? catValues : [1],
                backgroundColor: catColors.slice(0, Math.max(catLabels.length, 1)),
                borderWidth: 3,
                borderColor: 'transparent',
                hoverBorderColor: 'var(--bg-card)',
                hoverOffset: 10,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#8896aa',
                        padding: 14,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 11,
                            family: "'DM Sans'"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    borderColor: 'rgba(255,255,255,.08)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' projectos'
                    }
                }
            },
            cutout: '62%',
            animation: {
                animateRotate: true,
                duration: 800
            }
        }
    });

    // ── Animar barras de progresso após load ───────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // As barras usam CSS transition, só precisam de trigger
        document.querySelectorAll('.stat-bar-fill, .page-bar-fill, .msg-status-bar, .device-bar-fill').forEach(
            el => {
                const w = el.style.width;
                el.style.width = '0';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    el.style.width = w
                }));
            });
    });
    </script>

</body>

</html>