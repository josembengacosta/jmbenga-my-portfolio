<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Depoimentos
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

// ── Tempo de sessão & cliente ─────────────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Filtros via GET ─────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterStatus === 'visible') {
    $where[]  = 'status_testimonial = ?';
    $params[] = 'visible';
} elseif ($filterStatus === 'hidden') {
    $where[]  = 'status_testimonial = ?';
    $params[] = 'hidden';
}

if ($search !== '') {
    $where[]  = '(name_testimonial LIKE ? OR company_testimonial LIKE ? OR body_testimonial LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _testimonials';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY display_order ASC, creat_testimonial DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

// ── Estatísticas para os KPI cards ─────────────────────────
$totalAll      = (int) $db->query("SELECT COUNT(*) FROM _testimonials")->fetchColumn();
$totalVisible  = (int) $db->query("SELECT COUNT(*) FROM _testimonials WHERE status_testimonial = 'visible'")->fetchColumn();
$totalHidden   = $totalAll - $totalVisible;
$avgRating     = round((float) $db->query("SELECT AVG(rating_testimonial) FROM _testimonials")->fetchColumn(), 1);

// ── CSRF token para AJAX ────────────────────────────────────
$csrfToken = $_SESSION['admin_csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<body>

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
                        <span>Depoimentos</span>
                    </div>
                    <h1 class="page-title">Gestão de Depoimentos</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> depoimentos no total &nbsp;·&nbsp;
                        <?= $totalVisible ?> visíveis &nbsp;·&nbsp;
                        <?= $totalHidden ?> ocultos &nbsp;·&nbsp;
                        Média <?= $avgRating ?> ⭐
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary" title="Ver portfólio">
                        <i class="fas fa-external-link-alt"></i> Ver site
                    </a>
                    <a href="<?= BASE_URL ?>/jm-panel/testimonials/add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Depoimento
                    </a>
                </div>
            </div>

            <!-- ═══ KPI MINI-CARDS ══════════════════════════════════════ -->
            <div class="kpi-row">
                <a href="?status=all" class="kpi-card <?= $filterStatus === 'all' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.15);color:#94a3b8">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <span class="kpi-val"><?= $totalAll ?></span>
                    <span class="kpi-lbl">Total</span>
                </a>
                <a href="?status=visible" class="kpi-card <?= $filterStatus === 'visible' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
                        <i class="fas fa-eye"></i>
                    </span>
                    <span class="kpi-val"><?= $totalVisible ?></span>
                    <span class="kpi-lbl">Visíveis</span>
                </a>
                <a href="?status=hidden" class="kpi-card <?= $filterStatus === 'hidden' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
                        <i class="fas fa-eye-slash"></i>
                    </span>
                    <span class="kpi-val"><?= $totalHidden ?></span>
                    <span class="kpi-lbl">Ocultos</span>
                </a>
                <div class="kpi-card" style="cursor:default">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
                        <i class="fas fa-star"></i>
                    </span>
                    <span class="kpi-val"><?= $avgRating ?></span>
                    <span class="kpi-lbl">Média</span>
                </div>
            </div>

            <!-- ═══ BARRA DE FILTROS ═════════════════════════════════════ -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar depoimentos…" value="<?= e($search) ?>"
                        id="filterSearch">
                    <?php if ($search): ?>
                    <button type="button" class="filter-clear-search"
                        onclick="document.getElementById('filterSearch').value='';this.form.submit()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <select name="status" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Todos estados</option>
                    <option value="visible" <?= $filterStatus === 'visible' ? 'selected' : '' ?>>Visíveis</option>
                    <option value="hidden" <?= $filterStatus === 'hidden' ? 'selected' : '' ?>>Ocultos</option>
                </select>

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterStatus !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/testimonials" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <?php endif; ?>

                <?php if ($filterStatus !== 'all' || $search): ?>
                <span class="filter-result">
                    <i class="fas fa-info-circle"></i>
                    <?= count($testimonials) ?> resultado<?= count($testimonials) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </form>

            <!-- ═══ TABELA DE DEPOIMENTOS ═══════════════════════════════════ -->
            <div class="table-card">
                <?php if (empty($testimonials)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-star"></i></div>
                    <h3>Nenhum depoimento encontrado</h3>
                    <p>
                        <?php if ($search || $filterStatus !== 'all'): ?>
                        Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/testimonials">ver todos</a>.
                        <?php else: ?>
                        Ainda não adicionaste nenhum depoimento ao portfólio.
                        <?php endif; ?>
                    </p>
                    <a href="<?= BASE_URL ?>/jm-panel/testimonials/add" class="btn btn-primary" style="margin-top:1rem">
                        <i class="fas fa-plus"></i> Criar primeiro depoimento
                    </a>
                </div>
                <?php else: ?>
                <table id="testimonialsTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>Nome</th>
                            <th>Cargo / Empresa</th>
                            <th>Depoimento</th>
                            <th>Avaliação</th>
                            <th style="text-align:center">Ordem</th>
                            <th style="text-align:center">Estado</th>
                            <th style="width:100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $t): ?>
                        <tr id="row-<?= $t['id_testimonial'] ?>">
                            <!-- Foto -->
                            <td>
                                <div class="testi-avatar-cell">
                                    <?php if (!empty($t['photo_testimonial']) && file_exists(ROOT_PATH . '/assets/img/testimonials/' . $t['photo_testimonial'])): ?>
                                    <img src="<?= BASE_URL ?>/assets/img/testimonials/<?= e($t['photo_testimonial']) ?>"
                                        alt="<?= e($t['name_testimonial']) ?>">
                                    <?php else: ?>
                                    <span
                                        class="avatar-letter"><?= strtoupper(mb_substr($t['name_testimonial'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- Nome -->
                            <td>
                                <span class="testi-name"><?= e($t['name_testimonial']) ?></span>
                            </td>
                            <!-- Cargo / Empresa -->
                            <td>
                                <?php if (!empty($t['role_testimonial'])): ?>
                                <div class="testi-role"><?= e($t['role_testimonial']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['company_testimonial'])): ?>
                                <div class="testi-company"><?= e($t['company_testimonial']) ?></div>
                                <?php endif; ?>
                                <?php if (empty($t['role_testimonial']) && empty($t['company_testimonial'])): ?>
                                <span style="color:var(--text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Depoimento (truncado) -->
                            <td>
                                <div class="testi-body-preview">
                                    <?= e(mb_strlen($t['body_testimonial']) > 100 ? mb_substr($t['body_testimonial'], 0, 100) . '…' : $t['body_testimonial']) ?>
                                </div>
                            </td>
                            <!-- Avaliação -->
                            <td>
                                <div class="testi-stars-cell">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fas fa-star"
                                        style="color:<?= $s <= $t['rating_testimonial'] ? 'var(--warning)' : 'var(--border)' ?>"></i>
                                    <?php endfor; ?>
                                    <span
                                        style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-dim);margin-left:4px"><?= $t['rating_testimonial'] ?>/5</span>
                                </div>
                            </td>
                            <!-- Ordem -->
                            <td style="text-align:center">
                                <span class="order-badge"><?= (int)$t['display_order'] ?></span>
                            </td>
                            <!-- Estado -->
                            <td style="text-align:center">
                                <button class="btn-icon toggle-visibility" data-id="<?= $t['id_testimonial'] ?>"
                                    data-visible="<?= $t['status_testimonial'] === 'visible' ? '1' : '0' ?>"
                                    title="<?= $t['status_testimonial'] === 'visible' ? 'Ocultar' : 'Tornar visível' ?>">
                                    <i class="fas fa-<?= $t['status_testimonial'] === 'visible' ? 'eye' : 'eye-slash' ?>"
                                        style="color:<?= $t['status_testimonial'] === 'visible' ? 'var(--success)' : 'var(--text-muted)' ?>"></i>
                                </button>
                            </td>
                            <!-- Ações -->
                            <td>
                                <div class="row-actions">
                                    <a href="<?= BASE_URL ?>/jm-panel/testimonials/edit?id=<?= $t['id_testimonial'] ?>"
                                        class="btn-icon" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <button class="btn-icon btn-icon-danger delete-testimonial"
                                        data-id="<?= $t['id_testimonial'] ?>"
                                        data-title="<?= e($t['name_testimonial']) ?>" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

    <!-- ═══ ESTILOS ESPECÍFICOS DESTA PÁGINA ══════════════════════ -->
    <style>
    /* ── Cabeçalho ──────────────────────────────────────────── */
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

    /* ── KPI mini-cards ─────────────────────────────────────── */
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
        transition: all .22s var(--ease);
        position: relative;
        overflow: hidden
    }

    .kpi-card:not([style*="cursor:default"]):hover {
        transform: translateY(-2px);
        border-color: var(--border-acc);
        box-shadow: 0 6px 24px rgba(0, 0, 0, .25)
    }

    .kpi-card.kpi-active {
        border-color: var(--accent);
        background: var(--accent-soft)
    }

    .kpi-card.kpi-active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--accent)
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

    /* ── Filtros ────────────────────────────────────────────── */
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
        min-width: 180px;
        transition: border-color .2s
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
        font-family: var(--font-body);
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
        font-family: var(--font-body);
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

    /* ── Tabela ─────────────────────────────────────────────── */
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

    /* Células personalizadas */
    .testi-avatar-cell {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--border-acc);
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg)
    }

    .testi-avatar-cell img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .avatar-letter {
        font-family: var(--font-head);
        font-size: .85rem;
        font-weight: 700;
        color: var(--accent)
    }

    .testi-name {
        font-weight: 600;
        font-size: .85rem
    }

    .testi-role {
        font-size: .78rem;
        color: var(--accent)
    }

    .testi-company {
        font-size: .72rem;
        color: var(--text-muted)
    }

    .testi-body-preview {
        font-size: .8rem;
        color: var(--text-dim);
        line-height: 1.5;
        font-style: italic;
        max-width: 300px
    }

    .testi-stars-cell {
        display: flex;
        align-items: center;
        gap: 1px;
        font-size: .75rem
    }

    .order-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: var(--bg);
        border: 1px solid var(--border);
        font-size: .72rem;
        font-family: var(--font-mono);
        color: var(--text-dim)
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

    /* ── Botões genéricos ───────────────────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1.1rem;
        border-radius: 8px;
        font-family: var(--font-body);
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

    /* ── Estado vazio ─────────────────────────────────────── */
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
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

    // ── DataTable ─────────────────────────────────────────────────
    $(document).ready(function() {
        if ($('#testimonialsTable').length) {
            $('#testimonialsTable').DataTable({
                responsive: true,
                pageLength: 15,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [5, 'asc'],
                    [1, 'asc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [0, 3, 4, 6, 7]
                    },
                    {
                        searchable: false,
                        targets: [0, 4, 6, 7]
                    }
                ]
            });
        }
    });

    // ── Toast helper ──────────────────────────────────────────────
    function toast(icon, title, timer = 2800) {
        if (typeof Swal === 'undefined') {
            console.log(icon + ': ' + title);
            return;
        }
        Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon,
            title,
            showConfirmButton: false,
            timer,
            timerProgressBar: true,
            background: 'var(--bg-card)',
            color: 'var(--text)',
        });
    }

    // ── Toggle visibilidade ────────────────────────────────────────
    document.querySelectorAll('.toggle-visibility').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const icon = this.querySelector('i');
            const curr = this.dataset.visible === '1';

            // Feedback imediato
            icon.style.color = curr ? 'var(--text-muted)' : 'var(--success)';
            icon.className = 'fas fa-' + (curr ? 'eye-slash' : 'eye');
            this.dataset.visible = curr ? '0' : '1';
            this.title = curr ? 'Tornar visível' : 'Ocultar';

            try {
                const res = await fetch(BASE + '/jm-panel/testimonials/toggle?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (!data.success) {
                    icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                    icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                    this.dataset.visible = curr ? '1' : '0';
                    toast('error', 'Não foi possível actualizar');
                } else {
                    toast('success', data.status_testimonial === 'visible' ? 'Depoimento visível' :
                        'Depoimento ocultado', 2000);
                }
            } catch (err) {
                icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                this.dataset.visible = curr ? '1' : '0';
            }
        });
    });

    // ── Eliminar depoimento ────────────────────────────────────────
    document.querySelectorAll('.delete-testimonial').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const title = this.dataset.title || 'este depoimento';

            if (typeof Swal === 'undefined') {
                if (!confirm('Eliminar depoimento de "' + title + '"?')) return;
                doDelete(id);
                return;
            }

            Swal.fire({
                title: 'Eliminar depoimento?',
                html: `<span style="color:var(--text-dim);font-size:.9rem">
                       Vais eliminar permanentemente o depoimento de <strong style="color:var(--text)">"${title}"</strong>.
                       <br>Esta ação não pode ser desfeita.
                   </span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sim, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: 'transparent',
                background: 'var(--bg-card)',
                color: 'var(--text)',
                customClass: {
                    cancelButton: 'swal-cancel-btn'
                }
            }).then(result => {
                if (result.isConfirmed) doDelete(id);
            });
        });
    });

    async function doDelete(id) {
        try {
            const res = await fetch(BASE + '/jm-panel/testimonials/delete?id=' + id, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                }
            });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('row-' + id);
                if (row) {
                    row.style.transition = 'opacity .3s, transform .3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 300);
                }
                toast('success', 'Depoimento eliminado com sucesso');
            } else {
                toast('error', data.message || 'Erro ao eliminar');
            }
        } catch (err) {
            toast('error', 'Erro de rede.');
        }
    }
    </script>

</body>

</html>