<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Projectos
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

// ── Variáveis para includes ─────────────────────────────────
$pageTitle      = 'Projectos';
$pageHeading    = 'Gestão de Projectos';
$pageBreadcrumb = [
    ['label' => 'Conteúdo'],
    ['label' => 'Projectos'],
];

// ── Contadores globais (sidebar/header) ─────────────────────
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();

// ── Tempo de sessão & cliente (para modal_logout) ───────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Filtros via GET ─────────────────────────────────────────
$filterCategory = trim($_GET['category'] ?? 'all');
$filterStatus   = trim($_GET['status']   ?? 'all');
$search         = trim($_GET['search']   ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterCategory !== 'all') {
    $where[]  = 'category_project = ?';
    $params[] = $filterCategory;
}
if ($filterStatus !== 'all') {
    $where[]  = 'status_project = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[]  = '(title_project LIKE ? OR summary_project LIKE ? OR slug_project LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _projects';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY display_order ASC, creat_project DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// ── Estatísticas para os KPI cards ─────────────────────────
$totalAll       = (int) $db->query("SELECT COUNT(*) FROM _projects")->fetchColumn();
$totalPublished = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$totalDrafts    = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'draft'")->fetchColumn();
$totalArchived  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'archived'")->fetchColumn();
$totalFeatured  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE is_featured = 1")->fetchColumn();

// Categorias para o filtro
$categories = $db->query("
    SELECT DISTINCT category_project
    FROM _projects
    ORDER BY category_project
")->fetchAll(PDO::FETCH_COLUMN);

// ── CSRF token para AJAX ────────────────────────────────────
$csrfToken = $_SESSION['admin_csrf_token'];

// ── Cor por categoria (mapeamento) ──────────────────────────
function categoryColor(string $cat): string {
    $map = [
        'web'       => '#2563eb',
        'mobile'    => '#10b981',
        'design'    => '#8b5cf6',
        'backend'   => '#f59e0b',
        'frontend'  => '#14b8a6',
        'fullstack' => '#ec4899',
        'devops'    => '#f97316',
        'other'     => '#64748b',
    ];
    return $map[strtolower($cat)] ?? '#64748b';
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
                    <h1 class="page-title">Gestão de Projectos</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> no total &nbsp;·&nbsp;
                        <?= $totalPublished ?> publicados &nbsp;·&nbsp;
                        <?= $totalDrafts ?> rascunhos &nbsp;·&nbsp;
                        <?= $totalFeatured ?> em destaque
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary" title="Ver portfólio">
                        <i class="fas fa-external-link-alt"></i> Ver site
                    </a>
                    <a href="<?= BASE_URL ?>/jm-panel/projects/add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Projecto
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
                <a href="?status=published" class="kpi-card <?= $filterStatus === 'published' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <span class="kpi-val"><?= $totalPublished ?></span>
                    <span class="kpi-lbl">Publicados</span>
                </a>
                <a href="?status=draft" class="kpi-card <?= $filterStatus === 'draft' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
                        <i class="fas fa-clock"></i>
                    </span>
                    <span class="kpi-val"><?= $totalDrafts ?></span>
                    <span class="kpi-lbl">Rascunhos</span>
                </a>
                <a href="?status=archived" class="kpi-card <?= $filterStatus === 'archived' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.12);color:var(--text-muted)">
                        <i class="fas fa-archive"></i>
                    </span>
                    <span class="kpi-val"><?= $totalArchived ?></span>
                    <span class="kpi-lbl">Arquivados</span>
                </a>
                <div class="kpi-card" style="cursor:default">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)">
                        <i class="fas fa-star"></i>
                    </span>
                    <span class="kpi-val"><?= $totalFeatured ?></span>
                    <span class="kpi-lbl">Em Destaque</span>
                </div>
            </div>

            <!-- ═══ BARRA DE FILTROS ═════════════════════════════════════ -->
            <form class="filter-bar" method="get" id="filterForm">
                <!-- Pesquisa -->
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar por título, slug…" value="<?= e($search) ?>"
                        id="filterSearch">
                    <?php if ($search): ?>
                    <button type="button" class="filter-clear-search"
                        onclick="document.getElementById('filterSearch').value='';document.getElementById('filterForm').submit()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Categoria -->
                <select name="category" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>
                        Todas as categorias
                    </option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                        <?= e(ucfirst($cat)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Status oculto (controlado pelos KPI cards) -->
                <input type="hidden" name="status" value="<?= e($filterStatus) ?>">

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterCategory !== 'all' || $filterStatus !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/projects" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <?php endif; ?>

                <!-- Resultado -->
                <?php if ($filterCategory !== 'all' || $filterStatus !== 'all' || $search): ?>
                <span class="filter-result">
                    <i class="fas fa-info-circle"></i>
                    <?= count($projects) ?> resultado<?= count($projects) !== 1 ? 's' : '' ?>
                    <?= $search ? 'para "<strong>' . e($search) . '</strong>"' : '' ?>
                </span>
                <?php endif; ?>
            </form>

            <!-- ═══ TABELA DE PROJECTOS ═══════════════════════════════════ -->
            <div class="table-card">
                <?php if (empty($projects)): ?>
                <!-- Estado vazio -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>Nenhum projecto encontrado</h3>
                    <p>
                        <?php if ($search || $filterCategory !== 'all' || $filterStatus !== 'all'): ?>
                        Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/projects">ver todos</a>.
                        <?php else: ?>
                        Ainda não adicionaste nenhum projecto ao portfólio.
                        <?php endif; ?>
                    </p>
                    <a href="<?= BASE_URL ?>/jm-panel/projects/add" class="btn btn-primary" style="margin-top:1rem">
                        <i class="fas fa-plus"></i> Criar primeiro projecto
                    </a>
                </div>

                <?php else: ?>
                <table id="projectsTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:36px"></th><!-- thumbnail -->
                            <th>Projecto</th>
                            <th>Categoria</th>
                            <th>Tecnologias</th>
                            <th>Estado</th>
                            <th style="text-align:center">Destaque</th>
                            <th style="text-align:center">Ordem</th>
                            <th>Data</th>
                            <th style="width:100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p):
                            $tech     = json_decode($p['tech_stack'] ?? '[]', true) ?: [];
                            $imgPath  = BASE_URL . '/assets/img/projects/' . ($p['thumb_project'] ?? '');
                            $hasImg   = !empty($p['thumb_project']);
                            $catColor = categoryColor($p['category_project']);
                            $statusTs = [
                                'published' => ['label' => 'Publicado', 'class' => 'badge-published', 'icon' => 'check-circle'],
                                'draft'     => ['label' => 'Rascunho',  'class' => 'badge-draft',     'icon' => 'clock'],
                                'archived'  => ['label' => 'Arquivado', 'class' => 'badge-archived',  'icon' => 'archive'],
                            ];
                            $st = $statusTs[$p['status_project']] ?? $statusTs['draft'];
                        ?>
                        <tr id="row-<?= $p['id_project'] ?>" data-title="<?= e($p['title_project']) ?>"
                            data-status="<?= e($p['status_project']) ?>">

                            <!-- Thumbnail -->
                            <td>
                                <div class="proj-thumb">
                                    <?php if ($hasImg): ?>
                                    <img src="<?= $imgPath ?>" alt="<?= e($p['title_project']) ?>">
                                    <?php else: ?>
                                    <span style="color:<?= $catColor ?>">
                                        <?= strtoupper(mb_substr($p['title_project'], 0, 1)) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Projecto -->
                            <td>
                                <div class="proj-title"><?= e($p['title_project']) ?></div>
                                <div class="proj-slug">/<?= e($p['slug_project']) ?></div>
                            </td>

                            <!-- Categoria -->
                            <td>
                                <span class="cat-tag" style="--cat-color:<?= $catColor ?>">
                                    <?= e(ucfirst($p['category_project'])) ?>
                                </span>
                            </td>

                            <!-- Tecnologias -->
                            <td>
                                <?php if ($tech): ?>
                                <div class="tech-tags">
                                    <?php foreach (array_slice($tech, 0, 3) as $t): ?>
                                    <span class="tech-tag"><?= e($t) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($tech) > 3): ?>
                                    <span class="tech-more">+<?= count($tech) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado -->
                            <td>
                                <span class="badge-status <?= $st['class'] ?>">
                                    <i class="fas fa-<?= $st['icon'] ?>"></i>
                                    <?= $st['label'] ?>
                                </span>
                            </td>

                            <!-- Destaque -->
                            <td style="text-align:center">
                                <button class="btn-icon toggle-featured" data-id="<?= $p['id_project'] ?>"
                                    data-featured="<?= (int)$p['is_featured'] ?>"
                                    title="<?= $p['is_featured'] ? 'Remover destaque' : 'Marcar como destaque' ?>">
                                    <i class="fas fa-star"
                                        style="color:<?= $p['is_featured'] ? 'var(--warning)' : 'var(--text-muted)' ?>"></i>
                                </button>
                            </td>

                            <!-- Ordem -->
                            <td style="text-align:center">
                                <span class="order-badge"><?= (int)$p['display_order'] ?></span>
                            </td>

                            <!-- Data -->
                            <td>
                                <span title="<?= date('d/m/Y H:i', strtotime($p['creat_project'])) ?>"
                                    style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-dim)">
                                    <?= date('d/m/Y', strtotime($p['creat_project'])) ?>
                                </span>
                            </td>

                            <!-- Acções -->
                            <td>
                                <div class="row-actions">
                                    <!-- Link para gerir media -->
                                    <a href="<?= BASE_URL ?>/jm-panel/projects/media?id=<?= $p['id_project'] ?>"
                                        class="btn-icon" title="Gerir imagens">
                                        <i class="fas fa-images"></i>
                                    </a>

                                    <?php if ($p['status_project'] === 'published'): ?>
                                    <a href="<?= BASE_URL ?>/projects/<?= e($p['slug_project']) ?>" target="_blank"
                                        class="btn-icon" title="Ver no site">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <?php endif; ?>

                                    <a href="<?= BASE_URL ?>/jm-panel/projects/edit?id=<?= $p['id_project'] ?>"
                                        class="btn-icon" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/jm-panel/projects/view?id=<?= $p['id_project'] ?>"
                                        class="btn-icon" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <button class="btn-icon toggle-status" data-id="<?= $p['id_project'] ?>"
                                        data-status="<?= e($p['status_project']) ?>"
                                        title="<?= $p['status_project'] === 'published' ? 'Despublicar' : 'Publicar' ?>">
                                        <i
                                            class="fas fa-<?= $p['status_project'] === 'published' ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>

                                    <button class="btn-icon btn-icon-danger delete-project"
                                        data-id="<?= $p['id_project'] ?>" data-title="<?= e($p['title_project']) ?>"
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
    /* ── Cabeçalho da página ─────────────────────────────────── */
    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
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
        color: var(--text-muted)
    }

    /* ── KPI mini-cards ──────────────────────────────────────── */
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
        border-color: var(--border-accent);
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

    /* ── Barra de filtros ────────────────────────────────────── */
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
        min-width: 0;
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
        transition: color .2s;
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
        transition: border-color .2s;
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
        margin-left: auto;
    }

    .filter-result i {
        font-size: .72rem
    }

    /* ── Tabela: células personalizadas ──────────────────────── */
    .proj-thumb {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--bg), var(--border));
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        font-family: var(--font-head);
        font-weight: 800;
        font-size: .85rem;
        flex-shrink: 0;
    }

    .proj-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .proj-title {
        font-weight: 600;
        font-size: .85rem;
        line-height: 1.3
    }

    .proj-slug {
        font-size: .68rem;
        color: var(--text-muted);
        font-family: var(--font-mono);
        margin-top: 2px
    }

    /* Categoria tag */
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

    /* Tech tags */
    .tech-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 3px
    }

    .tech-tag {
        display: inline-block;
        background: var(--bg);
        border: 1px solid var(--border);
        padding: 2px 7px;
        border-radius: 5px;
        font-size: .67rem;
        color: var(--text-dim);
        font-family: var(--font-mono);
        white-space: nowrap;
    }

    .tech-more {
        font-size: .67rem;
        color: var(--text-muted);
        padding: 2px 5px;
        font-family: var(--font-mono);
    }

    /* Ordem badge */
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

    /* Row actions */
    .row-actions {
        display: flex;
        gap: .3rem;
        align-items: center
    }

    .btn-icon-danger:hover {
        background: var(--danger) !important;
        border-color: var(--danger) !important;
        color: #fff !important;
    }

    /* ── Estado vazio ──────────────────────────────────────────── */
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

    /* Botão secundário */
    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim);
    }

    .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--border-accent);
        color: var(--text);
    }
    </style>

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

    // ── DataTable ─────────────────────────────────────────────────
    $(document).ready(function() {
        if ($('#projectsTable').length) {
            $('#projectsTable').DataTable({
                responsive: true,
                pageLength: 15,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [7, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [0, 4, 5, 8]
                    },
                    {
                        searchable: false,
                        targets: [0, 5, 6, 8]
                    },
                ]
            });
        }
    });

    // ── Toast helper (SweetAlert2) ────────────────────────────────
    function toast(icon, title, timer = 2800) {
        if (typeof Swal === 'undefined') {
            console.log(icon + ': ' + title);
            return
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

    // ── Eliminar projecto ─────────────────────────────────────────
    document.querySelectorAll('.delete-project').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const title = this.dataset.title || 'este projecto';

            if (typeof Swal === 'undefined') {
                if (!confirm('Eliminar "' + title + '"? Esta ação não pode ser desfeita.')) return;
                doDelete(id);
                return;
            }

            Swal.fire({
                title: 'Eliminar projecto?',
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
            const res = await fetch(BASE + '/jm-panel/projects/delete?id=' + id, {
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
                toast('success', 'Projecto eliminado com sucesso');
            } else {
                toast('error', data.message || 'Erro ao eliminar');
            }
        } catch (err) {
            toast('error', 'Erro de rede. Tenta novamente.');
        }
    }

    // ── Toggle destaque ───────────────────────────────────────────
    document.querySelectorAll('.toggle-featured').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const icon = this.querySelector('i');
            const curr = this.dataset.featured === '1';

            // Feedback imediato
            icon.style.color = curr ? 'var(--text-muted)' : 'var(--warning)';
            this.dataset.featured = curr ? '0' : '1';
            this.title = curr ? 'Marcar como destaque' : 'Remover destaque';

            try {
                const res = await fetch(BASE + '/jm-panel/projects/toggle-featured?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (!data.success) {
                    // Reverter
                    icon.style.color = curr ? 'var(--warning)' : 'var(--text-muted)';
                    this.dataset.featured = curr ? '1' : '0';
                    toast('error', 'Não foi possível actualizar');
                } else {
                    toast('success', data.is_featured ? 'Adicionado ao destaque' :
                        'Removido do destaque', 2000);
                }
            } catch (err) {
                icon.style.color = curr ? 'var(--warning)' : 'var(--text-muted)';
                this.dataset.featured = curr ? '1' : '0';
            }
        });
    });

    // ── Toggle publicar / despublicar ─────────────────────────────
    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const row = document.getElementById('row-' + id);
            const status = this.dataset.status;

            try {
                const res = await fetch(BASE + '/jm-panel/projects/toggle-status?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const ns = data.new_status;
                    const badge = row.querySelector('.badge-status');
                    const icon = this.querySelector('i');

                    badge.className = 'badge-status ' + (ns === 'published' ? 'badge-published' :
                        'badge-draft');
                    badge.innerHTML = ns === 'published' ?
                        '<i class="fas fa-check-circle"></i> Publicado' :
                        '<i class="fas fa-clock"></i> Rascunho';

                    icon.className = 'fas fa-' + (ns === 'published' ? 'eye-slash' : 'eye');
                    this.dataset.status = ns;
                    this.title = ns === 'published' ? 'Despublicar' : 'Publicar';
                    row.dataset.status = ns;

                    toast('success', ns === 'published' ? 'Projecto publicado' :
                        'Projecto colocado como rascunho', 2200);
                } else {
                    toast('error', data.message || 'Erro ao alterar estado');
                }
            } catch (err) {
                toast('error', 'Erro de rede.');
            }
        });
    });
    </script>

</body>

</html>