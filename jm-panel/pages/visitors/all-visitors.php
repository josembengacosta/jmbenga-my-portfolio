<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Visitantes
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = $_SESSION['admin_email'] ?? '';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;

// ── Contadores globais ─────────────────────────────────────
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();

// ── Sessão & cliente ─────────────────────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Filtros via GET ─────────────────────────────────────────
$filterDevice  = isset($_GET['device']) && is_string($_GET['device']) ? $_GET['device'] : 'all';
$filterCountry = isset($_GET['country']) && is_string($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'all';
$filterStatus  = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : 'all';
$search        = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';

if (!in_array($filterStatus, ['all', 'online', 'blocked', 'bot'], true)) {
    $filterStatus = 'all';
}
if (strlen($filterDevice) > 30) {
    $filterDevice = 'all';
}
if (strlen($filterCountry) > 10) {
    $filterCountry = 'all';
}
$search = mb_substr($search, 0, 120);

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterDevice !== 'all') {
    $where[]  = 'device_type = ?';
    $params[] = $filterDevice;
}
if ($filterCountry !== 'all') {
    $where[]  = 'country_code = ?';
    $params[] = $filterCountry;
}
if ($filterStatus === 'online') {
    $where[]  = 'is_online = 1';
} elseif ($filterStatus === 'blocked') {
    $where[]  = "status_visitor = 'blocked'";
} elseif ($filterStatus === 'bot') {
    $where[]  = 'is_bot = 1';
}
if ($search !== '') {
    $where[]  = '(ip_address LIKE ? OR country_name LIKE ? OR city LIKE ? OR browser LIKE ? OR os LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _visitor';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY last_seen DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visitors = $stmt->fetchAll();

// ── Estatísticas para os KPI cards ─────────────────────────
$totalAll       = (int) $db->query("SELECT COUNT(*) FROM _visitor")->fetchColumn();
$totalOnline    = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$totalToday     = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE DATE(last_seen) = CURDATE()")->fetchColumn();
$totalBots      = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_bot = 1")->fetchColumn();
$totalBlocked   = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE status_visitor = 'blocked'")->fetchColumn();
$totalPageviews = (int) $db->query("SELECT COUNT(*) FROM _visitor_pageview")->fetchColumn();

// Dispositivos e países para filtros
$devices = $db->query("SELECT DISTINCT device_type FROM _visitor WHERE device_type IS NOT NULL ORDER BY device_type")->fetchAll(PDO::FETCH_COLUMN);
$countries = $db->query("SELECT DISTINCT country_code, country_name FROM _visitor WHERE country_code IS NOT NULL ORDER BY country_name")->fetchAll();

// ── CSRF token para AJAX ────────────────────────────────────
$csrfToken = $_SESSION['admin_csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-visitors.css?v=<?= file_exists(ROOT_PATH . '/assets/css/jmbenga-visitors.css') ? filemtime(ROOT_PATH . '/assets/css/jmbenga-visitors.css') : '1' ?>">
</head>

<body class="visitors-list-page">

    <?php include __DIR__ . '/../../include/sidebar.php'; ?>

    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>

        <div class="content">

            <!-- ═══ CABEÇALHO DA PÁGINA ════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Visitantes</span>
                    </div>
                    <h1 class="page-title">Visitantes</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> registados &nbsp;·&nbsp;
                        <?= $totalOnline ?> online agora &nbsp;·&nbsp;
                        <?= $totalToday ?> hoje &nbsp;·&nbsp;
                        <?= $totalPageviews ?> pageviews
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/analytics" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                </div>
            </div>

            <!-- ═══ KPI MINI-CARDS ══════════════════════════════════════ -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.15);color:#94a3b8"><i
                            class="fas fa-users"></i></span>
                    <span class="kpi-val"><?= $totalAll ?></span>
                    <span class="kpi-lbl">Total</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)"><i
                            class="fas fa-wifi"></i></span>
                    <span class="kpi-val"><?= $totalOnline ?></span>
                    <span class="kpi-lbl">Online</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(37,99,235,.12);color:var(--accent)"><i
                            class="fas fa-calendar-day"></i></span>
                    <span class="kpi-val"><?= $totalToday ?></span>
                    <span class="kpi-lbl">Hoje</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)"><i
                            class="fas fa-robot"></i></span>
                    <span class="kpi-val"><?= $totalBots ?></span>
                    <span class="kpi-lbl">Bots</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                            class="fas fa-ban"></i></span>
                    <span class="kpi-val"><?= $totalBlocked ?></span>
                    <span class="kpi-lbl">Bloqueados</span>
                </div>
            </div>

            <!-- ═══ BARRA DE FILTROS ═════════════════════════════════════ -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar IP, país, cidade, browser…"
                        value="<?= e($search) ?>" id="filterSearch">
                    <?php if ($search): ?>
                    <button type="button" class="filter-clear-search"
                        onclick="document.getElementById('filterSearch').value='';this.form.submit()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <select name="device" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterDevice === 'all' ? 'selected' : '' ?>>Todos dispositivos</option>
                    <?php foreach ($devices as $dev): ?>
                    <option value="<?= e($dev) ?>" <?= $filterDevice === $dev ? 'selected' : '' ?>>
                        <?= e(ucfirst($dev)) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="country" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterCountry === 'all' ? 'selected' : '' ?>>Todos países</option>
                    <?php foreach ($countries as $c): ?>
                    <option value="<?= e($c['country_code']) ?>"
                        <?= $filterCountry === $c['country_code'] ? 'selected' : '' ?>><?= e($c['country_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Todos estados</option>
                    <option value="online" <?= $filterStatus === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="blocked" <?= $filterStatus === 'blocked' ? 'selected' : '' ?>>Bloqueados</option>
                    <option value="bot" <?= $filterStatus === 'bot' ? 'selected' : '' ?>>Bots</option>
                </select>

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterDevice !== 'all' || $filterCountry !== 'all' || $filterStatus !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/visitors" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <?php endif; ?>

                <span class="filter-result">
                    <i class="fas fa-info-circle"></i>
                    <?= count($visitors) ?> resultado<?= count($visitors) !== 1 ? 's' : '' ?>
                </span>
            </form>

            <!-- ═══ TABELA DE VISITANTES ═══════════════════════════════════ -->
            <div class="table-card">
                <?php if (empty($visitors)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-users-slash"></i></div>
                    <h3>Nenhum visitante encontrado</h3>
                    <p>Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/visitors">ver todos</a>.</p>
                </div>
                <?php else: ?>
                <div class="bulk-selection-toolbar" role="region" aria-label="Acções em massa">
                    <div class="bulk-selection-copy">
                        <strong id="bulkSelectedCount">0 seleccionados</strong>
                        <span>Selecciona visitantes offline. Os visitantes online ficam protegidos contra
                            eliminação.</span>
                    </div>
                    <button type="button" class="btn bulk-delete-btn" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash-alt"></i>
                        <span>Eliminar seleccionados</span>
                    </button>
                </div>
                <table id="visitorsTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th class="visitor-select-col">
                                <input type="checkbox" id="selectPageVisitors"
                                    aria-label="Seleccionar visitantes desta página"
                                    title="Seleccionar visitantes offline desta página">
                            </th>
                            <th>Visitante</th>
                            <th>País</th>
                            <th>Dispositivo</th>
                            <th>Navegador / SO</th>
                            <th>Páginas</th>
                            <th>Duração</th>
                            <th style="text-align:center">Estado</th>
                            <th>Última vez</th>
                            <th style="width:80px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitors as $v): ?>
                        <tr id="row-<?= (int)$v['id_visitor'] ?>"
                            class="<?= !empty($v['is_online']) ? 'row-online' : '' ?>">
                            <td class="visitor-select-col">
                                <input type="checkbox" class="visitor-select" value="<?= (int)$v['id_visitor'] ?>"
                                    aria-label="Seleccionar visitante <?= e($v['ip_address']) ?>"
                                    <?= !empty($v['is_online']) ? 'disabled title="Visitantes online não podem ser eliminados."' : '' ?>>
                            </td>
                            <td>
                                <div class="visitor-ip">
                                    <strong><?= e($v['ip_address']) ?></strong>
                                    <?php if ($v['is_bot']): ?><span class="badge-bot">BOT</span><?php endif; ?>
                                </div>
                                <div class="visitor-detail">
                                    <?= e($v['city'] ?? '—') ?><?= $v['region'] ? ', ' . e($v['region']) : '' ?>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="country-flag"><?= $v['country_code'] ? strtoupper(e($v['country_code'])) : '—' ?></span>
                                <span
                                    style="font-size:.72rem;color:var(--text-muted);margin-left:4px"><?= e($v['country_name'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="cat-tag"
                                    style="--cat-color:<?= match($v['device_type']){'desktop'=>'#2563eb','mobile'=>'#10b981','tablet'=>'#f59e0b',default=>'#64748b'} ?>">
                                    <i
                                        class="fas fa-<?= match($v['device_type']){'desktop'=>'desktop','mobile'=>'mobile-alt','tablet'=>'tablet-alt',default=>'question-circle'} ?>"></i>
                                    <?= e(ucfirst($v['device_type'] ?? '—')) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size:.8rem"><?= e($v['browser'] ?? '—') ?>
                                    <?= e($v['browser_version'] ?? '') ?></div>
                                <div style="font-size:.7rem;color:var(--text-muted)"><?= e($v['os'] ?? '—') ?></div>
                            </td>
                            <td style="text-align:center">
                                <span class="order-badge"><?= (int)$v['pages_viewed'] ?></span>
                            </td>
                            <td>
                                <?= $v['session_duration'] ? floor($v['session_duration'] / 60) . 'm ' . ($v['session_duration'] % 60) . 's' : '—' ?>
                            </td>
                            <td style="text-align:center">
                                <?php if ($v['is_bot']): ?>
                                <span class="badge-status badge-bot">Bot</span>
                                <?php elseif ($v['status_visitor'] === 'blocked'): ?>
                                <span class="badge-status badge-archived"><i class="fas fa-ban"></i> Bloqueado</span>
                                <?php elseif ($v['is_online']): ?>
                                <span class="badge-status badge-published"><i class="fas fa-circle"
                                        style="font-size:.45rem"></i> Online</span>
                                <?php else: ?>
                                <span class="badge-status badge-draft">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?= date('d/m/Y H:i', strtotime($v['last_seen'])) ?>">
                                    <?= date('d/m/Y H:i', strtotime($v['last_seen'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="<?= BASE_URL ?>/jm-panel/visitors/view?id=<?= $v['id_visitor'] ?>"
                                        class="btn-icon" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($v['status_visitor'] !== 'blocked'): ?>
                                    <button class="btn-icon btn-icon-danger block-visitor"
                                        data-id="<?= $v['id_visitor'] ?>" title="Bloquear IP">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-icon unblock-visitor" data-id="<?= $v['id_visitor'] ?>"
                                        title="Desbloquear IP" style="color:var(--success);border-color:var(--success)">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <style>
    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: .5rem
    }

    .breadcrumb a {
        color: var(--text-dim);
        text-decoration: none;
        transition: color .2s
    }

    .breadcrumb a:hover {
        color: var(--accent)
    }

    .breadcrumb i {
        font-size: .6rem
    }

    .page-title {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -.02em;
        margin-bottom: .2rem
    }

    .page-sub {
        font-size: .8rem;
        color: var(--text-muted)
    }

    .kpi-row {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem
    }

    .kpi-card {
        flex: 1;
        min-width: 120px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .85rem 1rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        text-decoration: none;
        color: var(--text);
        transition: all .22s;
        position: relative;
        overflow: hidden
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--border-acc);
        box-shadow: 0 6px 24px rgba(0, 0, 0, .25)
    }

    .kpi-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex-shrink: 0
    }

    .kpi-val {
        font-family: var(--font-head);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -.02em;
        line-height: 1
    }

    .kpi-lbl {
        font-size: .68rem;
        color: var(--text-muted);
        margin-top: 2px;
        white-space: nowrap
    }

    .filter-bar {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .85rem 1rem
    }

    .filter-search {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: .4rem .75rem;
        flex: 1;
        min-width: 180px
    }

    .filter-search:focus-within {
        border-color: var(--accent)
    }

    .filter-search i {
        color: var(--text-muted);
        font-size: .85rem;
        flex-shrink: 0
    }

    .filter-search input {
        background: none;
        border: none;
        outline: none;
        color: var(--text);
        font-size: .84rem;
        flex: 1;
        min-width: 0
    }

    .filter-search input::placeholder {
        color: var(--text-muted)
    }

    .filter-clear-search {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: .8rem;
        padding: 0;
        line-height: 1;
        transition: color .2s
    }

    .filter-clear-search:hover {
        color: var(--danger)
    }

    .filter-select {
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        padding: .42rem .75rem;
        border-radius: 8px;
        font-size: .82rem;
        outline: none;
        cursor: pointer;
        transition: border-color .2s
    }

    .filter-select:focus {
        border-color: var(--accent)
    }

    .filter-result {
        font-size: .78rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-left: auto
    }

    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.5rem
    }

    table.dataTable {
        width: 100% !important;
        border-collapse: collapse
    }

    table.dataTable th,
    table.dataTable td {
        padding: .7rem .8rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        font-size: .85rem
    }

    table.dataTable th {
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: .7rem
    }

    table.dataTable tbody tr:hover {
        background: rgba(255, 255, 255, .02)
    }

    .row-online {
        background: rgba(16, 185, 129, .02)
    }

    .visitor-ip {
        display: flex;
        align-items: center;
        gap: .4rem
    }

    .visitor-detail {
        font-size: .72rem;
        color: var(--text-muted);
        margin-top: 2px
    }

    .badge-bot {
        display: inline-block;
        background: rgba(245, 158, 11, .15);
        color: var(--warning);
        font-size: .6rem;
        padding: 1px 6px;
        border-radius: 4px;
        font-weight: 700
    }

    .country-flag {
        font-family: var(--font-mono);
        font-size: .78rem;
        font-weight: 700;
        background: var(--bg);
        padding: 2px 6px;
        border-radius: 4px
    }

    .cat-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: .68rem;
        font-weight: 700;
        background: color-mix(in srgb, var(--cat-color) 12%, transparent);
        color: var(--cat-color);
        border: 1px solid color-mix(in srgb, var(--cat-color) 25%, transparent)
    }

    .order-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 24px;
        border-radius: 6px;
        background: var(--bg);
        border: 1px solid var(--border);
        font-size: .72rem;
        font-family: var(--font-mono);
        color: var(--text-dim)
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 600
    }

    .badge-published {
        background: rgba(16, 185, 129, .1);
        color: var(--success)
    }

    .badge-draft {
        background: rgba(148, 163, 184, .1);
        color: var(--text-muted)
    }

    .badge-archived {
        background: rgba(239, 68, 68, .1);
        color: var(--danger)
    }

    .badge-bot {
        background: rgba(245, 158, 11, .1);
        color: var(--warning)
    }

    .row-actions {
        display: flex;
        gap: .3rem;
        align-items: center
    }

    .btn-icon {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim);
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        font-size: .8rem
    }

    .btn-icon:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent)
    }

    .btn-icon-danger:hover {
        background: var(--danger);
        border-color: var(--danger)
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1.1rem;
        border-radius: 8px;
        font-size: .83rem;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        border: 1px solid var(--border);
        white-space: nowrap
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent)
    }

    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px var(--accent-glow)
    }

    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim)
    }

    .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--border-acc);
        color: var(--text)
    }

    .empty-state {
        padding: 3.5rem 2rem;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .6rem
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: var(--bg);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--text-muted);
        margin-bottom: .5rem
    }

    .empty-state h3 {
        font-family: var(--font-head);
        font-size: 1.1rem;
        font-weight: 700
    }

    .empty-state p {
        font-size: .85rem;
        color: var(--text-muted);
        max-width: 360px
    }

    .empty-state a {
        color: var(--accent)
    }
    </style>

    <script>
    const BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // ── DataTable ─────────────────────────────────────────────────
    $(document).ready(function() {
        if ($('#visitorsTable').length) {
            visitorsDataTable = $('#visitorsTable').DataTable({
                responsive: true,
                pageLength: 20,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [8, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [0, 4, 7, 9]
                    },
                    {
                        searchable: false,
                        targets: [0, 5, 6, 9]
                    }
                ]
            });
            visitorsDataTable.on('draw', syncBulkSelectionUI);
            syncBulkSelectionUI();
        }
    });

    // ═══ SELECÇÃO E ELIMINAÇÃO EM MASSA ═══
    const selectedVisitorIds = new Set();
    const selectPageCheckbox = document.getElementById('selectPageVisitors');
    const bulkDeleteButton = document.getElementById('bulkDeleteBtn');
    const selectedCountLabel = document.getElementById('bulkSelectedCount');
    let visitorsDataTable = null;

    function getCurrentPageCheckboxes() {
        if (visitorsDataTable) {
            const nodes = visitorsDataTable.rows({
                page: 'current'
            }).nodes();
            return $(nodes).find('.visitor-select:not(:disabled)').toArray();
        }
        return Array.from(document.querySelectorAll('.visitor-select:not(:disabled)'));
    }

    function syncBulkSelectionUI() {
        document.querySelectorAll('.visitor-select').forEach(input => {
            input.checked = selectedVisitorIds.has(input.value);
        });

        const selectableOnPage = getCurrentPageCheckboxes();
        const checkedOnPage = selectableOnPage.filter(input => selectedVisitorIds.has(input.value)).length;

        if (selectPageCheckbox) {
            selectPageCheckbox.checked = selectableOnPage.length > 0 && checkedOnPage === selectableOnPage.length;
            selectPageCheckbox.indeterminate = checkedOnPage > 0 && checkedOnPage < selectableOnPage.length;
            selectPageCheckbox.disabled = selectableOnPage.length === 0;
        }

        if (selectedCountLabel) {
            selectedCountLabel.textContent =
                `${selectedVisitorIds.size} seleccionado${selectedVisitorIds.size === 1 ? '' : 's'}`;
        }

        if (bulkDeleteButton) {
            bulkDeleteButton.disabled = selectedVisitorIds.size === 0 || selectedVisitorIds.size > 500;
            const label = bulkDeleteButton.querySelector('span');
            if (label) {
                label.textContent = selectedVisitorIds.size > 500 ?
                    'Máximo de 500 por operação' :
                    'Eliminar seleccionados';
            }
        }
    }

    document.addEventListener('change', event => {
        const input = event.target;
        if (input.matches('.visitor-select')) {
            if (input.checked) selectedVisitorIds.add(input.value);
            else selectedVisitorIds.delete(input.value);
            syncBulkSelectionUI();
        }
    });

    if (selectPageCheckbox) {
        selectPageCheckbox.addEventListener('change', function() {
            getCurrentPageCheckboxes().forEach(input => {
                input.checked = this.checked;
                if (this.checked) selectedVisitorIds.add(input.value);
                else selectedVisitorIds.delete(input.value);
            });
            syncBulkSelectionUI();
        });
    }

    if (visitorsDataTable) {
        visitorsDataTable.on('draw', syncBulkSelectionUI);
    }
    syncBulkSelectionUI();

    async function deleteSelectedVisitors() {
        if (!selectedVisitorIds.size || selectedVisitorIds.size > 500) return;

        const count = selectedVisitorIds.size;
        let confirmed = false;

        if (window.Swal) {
            const result = await Swal.fire({
                title: 'Eliminar visitantes seleccionados?',
                text: `Serão eliminados ${count} registo(s) e as respectivas pageviews. Esta acção não pode ser anulada.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                reverseButtons: true,
                focusCancel: true
            });
            confirmed = result.isConfirmed;
        } else {
            confirmed = window.confirm(
                `Eliminar ${count} visitante(s) seleccionado(s) e as respectivas pageviews? Esta acção não pode ser anulada.`
                );
        }

        if (!confirmed) return;

        const formData = new FormData();
        selectedVisitorIds.forEach(id => formData.append('ids[]', id));

        bulkDeleteButton.disabled = true;
        const originalLabel = bulkDeleteButton.innerHTML;
        bulkDeleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>A eliminar…</span>';

        try {
            const response = await fetch(BASE + '/jm-panel/visitors/bulk-delete', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                },
                body: formData
            });

            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch {
                throw new Error('O servidor devolveu uma resposta inválida.');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível eliminar os visitantes seleccionados.');
            }

            selectedVisitorIds.clear();
            showToast(data.message || `${data.deleted_count || 0} visitante(s) eliminado(s).`, 'success');
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            showToast(error.message || 'Erro de rede ao eliminar visitantes.', 'error');
            bulkDeleteButton.innerHTML = originalLabel;
            syncBulkSelectionUI();
        }
    }

    if (bulkDeleteButton) {
        bulkDeleteButton.addEventListener('click', deleteSelectedVisitors);
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        const icon = document.createElement('i');
        const text = document.createElement('span');
        const iconClass = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' :
            'fa-info-circle');

        toast.className = `toast toast-${type}`;
        icon.className = `fas ${iconClass}`;
        text.textContent = String(message ?? '');
        toast.append(icon, text);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
        return container;
    }

    // Bloquear / Desbloquear visitante (AJAX + recarregar)
    document.querySelectorAll('.block-visitor, .unblock-visitor').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const isBlocking = this.classList.contains('block-visitor');
            const action = isBlocking ? 'block' : 'unblock';
            try {
                const res = await fetch(BASE + '/jm-panel/visitors/' + action + '?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    showToast(data.message || 'Erro', 'error');
                }
            } catch (err) {
                showToast('Erro de rede.', 'error');
            }
        });
    });

    // ═══ ACTUALIZAÇÃO DINÂMICA DOS KPI CARDS (NOVO) ═══
    async function refreshKPIs() {
        try {
            const res = await fetch(BASE + '/jm-panel/visitors/api?action=summary');
            const data = await res.json();
            if (data.success) {
                // Actualizar os valores nos KPI cards
                const kpiCards = document.querySelectorAll('.kpi-card .kpi-val');
                if (kpiCards.length >= 5) {
                    kpiCards[0].textContent = data.total;
                    kpiCards[1].textContent = data.online;
                    kpiCards[2].textContent = data.today;
                    kpiCards[3].textContent = data.bots || 0;
                    kpiCards[4].textContent = data.blocked || 0;
                }
            }
        } catch (err) {
            /* silencioso */
        }
    }

    // Primeira actualização após 30 segundos, depois a cada 60 segundos
    setTimeout(() => {
        refreshKPIs();
        setInterval(refreshKPIs, 60000);
    }, 30000);
    </script>
</body>

</html>