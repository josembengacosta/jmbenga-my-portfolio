<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Skills
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

// ── Contadores globais (sidebar/header) ─────────────────────
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
$filterCategory = $_GET['category'] ?? 'all';
$search         = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterCategory !== 'all') {
    $where[]  = 'category_skill = ?';
    $params[] = $filterCategory;
}
if ($search !== '') {
    $where[]  = '(name_skill LIKE ? OR category_skill LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _skills';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY category_skill ASC, display_order ASC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$skills = $stmt->fetchAll();

// ── Estatísticas para os KPI cards ─────────────────────────
$totalAll      = (int) $db->query("SELECT COUNT(*) FROM _skills")->fetchColumn();
$totalVisible  = (int) $db->query("SELECT COUNT(*) FROM _skills WHERE is_visible = 1")->fetchColumn();
$totalHidden   = $totalAll - $totalVisible;

// Contagem por categoria
$catCounts = $db->query("SELECT category_skill, COUNT(*) AS cnt FROM _skills GROUP BY category_skill")->fetchAll(PDO::FETCH_KEY_PAIR);

// Categorias para o filtro
$categories = $db->query("SELECT DISTINCT category_skill FROM _skills ORDER BY category_skill")->fetchAll(PDO::FETCH_COLUMN);

// ── CSRF token para AJAX ────────────────────────────────────
$csrfToken = $_SESSION['admin_csrf_token'];

// Cores por categoria
function skillCategoryColor(string $cat): string {
    return match ($cat) {
        'frontend' => '#2563eb',
        'backend'  => '#10b981',
        'devops'   => '#f59e0b',
        'design'   => '#ec4899',
        default    => '#64748b',
    };
}
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
                        <span>Skills</span>
                    </div>
                    <h1 class="page-title">Gestão de Skills</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> skills no total &nbsp;·&nbsp;
                        <?= $totalVisible ?> visíveis &nbsp;·&nbsp;
                        <?= $totalHidden ?> ocultas
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary" title="Ver portfólio">
                        <i class="fas fa-external-link-alt"></i> Ver site
                    </a>
                    <a href="<?= BASE_URL ?>/jm-panel/skills/add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nova Skill
                    </a>
                </div>
            </div>

            <!-- ═══ KPI MINI-CARDS ══════════════════════════════════════ -->
            <div class="kpi-row">
                <a href="?category=all" class="kpi-card <?= $filterCategory === 'all' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.15);color:#94a3b8">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <span class="kpi-val"><?= $totalAll ?></span>
                    <span class="kpi-lbl">Total</span>
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= e($cat) ?>" class="kpi-card <?= $filterCategory === $cat ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon"
                        style="background:rgba(<?= implode(',', sscanf(skillCategoryColor($cat), "#%02x%02x%02x")) ?>,.12);color:<?= skillCategoryColor($cat) ?>">
                        <i
                            class="fas fa-<?= match($cat){'frontend'=>'desktop','backend'=>'server','devops'=>'infinity','design'=>'paint-brush',default=>'code'} ?>"></i>
                    </span>
                    <span class="kpi-val"><?= $catCounts[$cat] ?? 0 ?></span>
                    <span class="kpi-lbl"><?= e(ucfirst($cat)) ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ═══ BARRA DE FILTROS ═════════════════════════════════════ -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar skills…" value="<?= e($search) ?>"
                        id="filterSearch">
                    <?php if ($search): ?>
                    <button type="button" class="filter-clear-search"
                        onclick="document.getElementById('filterSearch').value='';this.form.submit()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <select name="category" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>Todas categorias</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                        <?= e(ucfirst($cat)) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterCategory !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/skills" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <?php endif; ?>

                <?php if ($filterCategory !== 'all' || $search): ?>
                <span class="filter-result">
                    <i class="fas fa-info-circle"></i>
                    <?= count($skills) ?> resultado<?= count($skills) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </form>

            <!-- ═══ TABELA DE SKILLS ═══════════════════════════════════════ -->
            <div class="table-card">
                <?php if (empty($skills)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-code"></i></div>
                    <h3>Nenhuma skill encontrada</h3>
                    <p>
                        <?php if ($search || $filterCategory !== 'all'): ?>
                        Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/skills">ver todas</a>.
                        <?php else: ?>
                        Ainda não adicionaste nenhuma skill ao portfólio.
                        <?php endif; ?>
                    </p>
                    <a href="<?= BASE_URL ?>/jm-panel/skills/add" class="btn btn-primary" style="margin-top:1rem">
                        <i class="fas fa-plus"></i> Criar primeira skill
                    </a>
                </div>
                <?php else: ?>
                <table id="skillsTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>Nome</th>
                            <th>Percentagem</th>
                            <th>Categoria</th>
                            <th>Ícone</th>
                            <th style="text-align:center">Ordem</th>
                            <th style="text-align:center">Estado</th>
                            <th style="width:100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skills as $s):
                            $catColor = skillCategoryColor($s['category_skill']);
                        ?>
                        <tr id="row-<?= $s['id_skill'] ?>">
                            <td>
                                <div class="skill-icon-cell" style="color:<?= $catColor ?>">
                                    <i class="<?= e($s['icon_skill'] ?: 'fas fa-code') ?>"></i>
                                </div>
                            </td>
                            <td>
                                <span class="skill-name-cell"><?= e($s['name_skill']) ?></span>
                            </td>
                            <td>
                                <div class="skill-bar-cell">
                                    <div class="skill-bar-bg">
                                        <div class="skill-bar-fill"
                                            style="width:<?= (int)$s['percentage_skill'] ?>%;background:<?= $catColor ?>">
                                        </div>
                                    </div>
                                    <span class="skill-bar-pct"><?= (int)$s['percentage_skill'] ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="cat-tag" style="--cat-color:<?= $catColor ?>">
                                    <?= e(ucfirst($s['category_skill'])) ?>
                                </span>
                            </td>
                            <td>
                                <code class="icon-code"><?= e($s['icon_skill'] ?: '—') ?></code>
                            </td>
                            <td style="text-align:center">
                                <span class="order-badge"><?= (int)$s['display_order'] ?></span>
                            </td>
                            <td style="text-align:center">
                                <button class="btn-icon toggle-visibility" data-id="<?= $s['id_skill'] ?>"
                                    data-visible="<?= $s['is_visible'] ?>"
                                    title="<?= $s['is_visible'] ? 'Ocultar' : 'Tornar visível' ?>">
                                    <i class="fas fa-<?= $s['is_visible'] ? 'eye' : 'eye-slash' ?>"
                                        style="color:<?= $s['is_visible'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i>
                                </button>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="<?= BASE_URL ?>/jm-panel/skills/edit?id=<?= $s['id_skill'] ?>"
                                        class="btn-icon" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <button class="btn-icon btn-icon-danger delete-skill"
                                        data-id="<?= $s['id_skill'] ?>" data-title="<?= e($s['name_skill']) ?>"
                                        title="Eliminar">
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
        margin-bottom: 1.5rem;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: .5rem;
    }

    .breadcrumb a {
        color: var(--text-dim);
        text-decoration: none;
        transition: color .2s;
    }

    .breadcrumb a:hover {
        color: var(--accent);
    }

    .breadcrumb i {
        font-size: .6rem;
    }

    .page-title {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -.02em;
        margin-bottom: .2rem;
    }

    .page-sub {
        font-size: .8rem;
        color: var(--text-muted);
    }

    /* ── KPI mini-cards ─────────────────────────────────────── */
    .kpi-row {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
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
        overflow: hidden;
    }

    .kpi-card:not([style*="cursor:default"]):hover {
        transform: translateY(-2px);
        border-color: var(--border-acc);
        box-shadow: 0 6px 24px rgba(0, 0, 0, .25);
    }

    .kpi-card.kpi-active {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .kpi-card.kpi-active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--accent);
    }

    .kpi-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }

    .kpi-val {
        font-family: var(--font-head);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -.02em;
        line-height: 1;
    }

    .kpi-lbl {
        font-size: .68rem;
        color: var(--text-muted);
        margin-top: 2px;
        white-space: nowrap;
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
        padding: .85rem 1rem;
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
        transition: border-color .2s;
    }

    .filter-search:focus-within {
        border-color: var(--accent);
    }

    .filter-search i {
        color: var(--text-muted);
        font-size: .85rem;
        flex-shrink: 0;
    }

    .filter-search input {
        background: none;
        border: none;
        outline: none;
        color: var(--text);
        font-family: var(--font-body);
        font-size: .84rem;
        flex: 1;
        min-width: 0;
    }

    .filter-search input::placeholder {
        color: var(--text-muted);
    }

    .filter-clear-search {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: .8rem;
        padding: 0;
        line-height: 1;
        transition: color .2s;
    }

    .filter-clear-search:hover {
        color: var(--danger);
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
        transition: border-color .2s;
    }

    .filter-select:focus {
        border-color: var(--accent);
    }

    .filter-result {
        font-size: .78rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-left: auto;
    }

    /* ── Tabela ─────────────────────────────────────────────── */
    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.5rem;
    }

    table.dataTable {
        width: 100% !important;
        border-collapse: collapse;
    }

    table.dataTable th,
    table.dataTable td {
        padding: .7rem .8rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        font-size: .85rem;
    }

    table.dataTable th {
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: .7rem;
    }

    table.dataTable tbody tr:hover {
        background: rgba(255, 255, 255, .02);
    }

    /* Células personalizadas */
    .skill-icon-cell {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--bg);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
    }

    .skill-name-cell {
        font-weight: 600;
        font-size: .85rem;
    }

    .skill-bar-cell {
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .skill-bar-bg {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
        flex: 1;
        min-width: 60px;
    }

    .skill-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width .3s;
    }

    .skill-bar-pct {
        font-family: var(--font-mono);
        font-size: .75rem;
        color: var(--text-dim);
        min-width: 35px;
        text-align: right;
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
        border: 1px solid color-mix(in srgb, var(--cat-color) 25%, transparent);
    }

    .icon-code {
        font-family: var(--font-mono);
        font-size: .72rem;
        background: var(--bg);
        padding: 2px 6px;
        border-radius: 4px;
        color: var(--accent);
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
        color: var(--text-dim);
    }

    .row-actions {
        display: flex;
        gap: .3rem;
        align-items: center;
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
        font-size: .8rem;
    }

    .btn-icon:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
    }

    .btn-icon-danger:hover {
        background: var(--danger);
        border-color: var(--danger);
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
        white-space: nowrap;
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
    }

    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px var(--accent-glow);
    }

    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim);
    }

    .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--border-acc);
        color: var(--text);
    }

    /* ── Estado vazio ─────────────────────────────────────── */
    .empty-state {
        padding: 3.5rem 2rem;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .6rem;
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
        margin-bottom: .5rem;
    }

    .empty-state h3 {
        font-family: var(--font-head);
        font-size: 1.1rem;
        font-weight: 700;
    }

    .empty-state p {
        font-size: .85rem;
        color: var(--text-muted);
        max-width: 360px;
    }

    .empty-state a {
        color: var(--accent);
    }
    </style>

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

    // ── DataTable ─────────────────────────────────────────────────
    $(document).ready(function() {
        if ($('#skillsTable').length) {
            $('#skillsTable').DataTable({
                responsive: true,
                pageLength: 15,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [6, 'asc'],
                    [1, 'asc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [0, 4, 6, 7]
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
                const res = await fetch(BASE + '/jm-panel/skills/toggle-visibility?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (!data.success) {
                    // Reverter
                    icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                    icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                    this.dataset.visible = curr ? '1' : '0';
                    toast('error', 'Não foi possível actualizar');
                } else {
                    toast('success', data.is_visible ? 'Skill visível' : 'Skill ocultada', 2000);
                }
            } catch (err) {
                icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                this.dataset.visible = curr ? '1' : '0';
            }
        });
    });

    // ── Eliminar skill ─────────────────────────────────────────────
    document.querySelectorAll('.delete-skill').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const title = this.dataset.title || 'esta skill';

            if (typeof Swal === 'undefined') {
                if (!confirm('Eliminar "' + title + '"?')) return;
                doDelete(id);
                return;
            }

            Swal.fire({
                title: 'Eliminar skill?',
                html: `<span style="color:var(--text-dim);font-size:.9rem">
                       Vais eliminar permanentemente <strong style="color:var(--text)">"${title}"</strong>.
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
            const res = await fetch(BASE + '/jm-panel/skills/delete?id=' + id, {
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
                toast('success', 'Skill eliminada com sucesso');
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