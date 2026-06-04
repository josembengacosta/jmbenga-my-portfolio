<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de FAQ
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
$filterCategory = $_GET['category'] ?? 'all';
$filterStatus   = $_GET['status']   ?? 'all';
$search         = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterCategory !== 'all') {
    $where[]  = 'category_faq = ?';
    $params[] = $filterCategory;
}
if ($filterStatus === 'visible') {
    $where[]  = 'status_faq = ?';
    $params[] = 'visible';
} elseif ($filterStatus === 'hidden') {
    $where[]  = 'status_faq = ?';
    $params[] = 'hidden';
}
if ($search !== '') {
    $where[]  = '(question LIKE ? OR answer LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _faq';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY category_faq ASC, display_order ASC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$faqs = $stmt->fetchAll();

// ── Estatísticas para os KPI cards ─────────────────────────
$totalAll     = (int) $db->query("SELECT COUNT(*) FROM _faq")->fetchColumn();
$totalVisible = (int) $db->query("SELECT COUNT(*) FROM _faq WHERE status_faq = 'visible'")->fetchColumn();
$totalHidden  = $totalAll - $totalVisible;

// Categorias para o filtro
$categories = $db->query("SELECT DISTINCT category_faq FROM _faq ORDER BY category_faq")->fetchAll(PDO::FETCH_COLUMN);

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
                        <span>FAQ</span>
                    </div>
                    <h1 class="page-title">Gestão de FAQ</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> perguntas no total &nbsp;·&nbsp;
                        <?= $totalVisible ?> visíveis &nbsp;·&nbsp;
                        <?= $totalHidden ?> ocultas
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary" title="Ver portfólio">
                        <i class="fas fa-external-link-alt"></i> Ver site
                    </a>
                    <a href="<?= BASE_URL ?>/jm-panel/faq/add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nova FAQ
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
                    <span class="kpi-lbl">Ocultas</span>
                </a>
            </div>

            <!-- ═══ BARRA DE FILTROS ═════════════════════════════════════ -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar FAQ…" value="<?= e($search) ?>"
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
                    <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Todos estados</option>
                    <option value="visible" <?= $filterStatus === 'visible' ? 'selected' : '' ?>>Visíveis</option>
                    <option value="hidden" <?= $filterStatus === 'hidden' ? 'selected' : '' ?>>Ocultas</option>
                </select>

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterCategory !== 'all' || $filterStatus !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/faq" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <?php endif; ?>

                <?php if ($filterCategory !== 'all' || $filterStatus !== 'all' || $search): ?>
                <span class="filter-result">
                    <i class="fas fa-info-circle"></i>
                    <?= count($faqs) ?> resultado<?= count($faqs) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </form>

            <!-- ═══ TABELA DE FAQ ═══════════════════════════════════════════ -->
            <div class="table-card">
                <?php if (empty($faqs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-question-circle"></i></div>
                    <h3>Nenhuma FAQ encontrada</h3>
                    <p>
                        <?php if ($search || $filterCategory !== 'all' || $filterStatus !== 'all'): ?>
                        Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/faq">ver todas</a>.
                        <?php else: ?>
                        Ainda não adicionaste nenhuma pergunta frequente.
                        <?php endif; ?>
                    </p>
                    <a href="<?= BASE_URL ?>/jm-panel/faq/add" class="btn btn-primary" style="margin-top:1rem">
                        <i class="fas fa-plus"></i> Criar primeira FAQ
                    </a>
                </div>
                <?php else: ?>
                <table id="faqTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Pergunta</th>
                            <th>Resposta</th>
                            <th>Categoria</th>
                            <th style="text-align:center">Ordem</th>
                            <th style="text-align:center">Estado</th>
                            <th style="width:100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs as $f): ?>
                        <tr id="row-<?= $f['id_faq'] ?>">
                            <td>
                                <span class="faq-question"><?= e($f['question']) ?></span>
                            </td>
                            <td>
                                <div class="faq-answer-preview">
                                    <?= e(mb_strlen($f['answer']) > 120 ? mb_substr(strip_tags($f['answer']), 0, 120) . '…' : strip_tags($f['answer'])) ?>
                                </div>
                            </td>
                            <td>
                                <span class="cat-tag" style="--cat-color:#2563eb"><?= e($f['category_faq']) ?></span>
                            </td>
                            <td style="text-align:center">
                                <span class="order-badge"><?= (int)$f['display_order'] ?></span>
                            </td>
                            <td style="text-align:center">
                                <button class="btn-icon toggle-visibility" data-id="<?= $f['id_faq'] ?>"
                                    data-visible="<?= $f['status_faq'] === 'visible' ? '1' : '0' ?>"
                                    title="<?= $f['status_faq'] === 'visible' ? 'Ocultar' : 'Tornar visível' ?>">
                                    <i class="fas fa-<?= $f['status_faq'] === 'visible' ? 'eye' : 'eye-slash' ?>"
                                        style="color:<?= $f['status_faq'] === 'visible' ? 'var(--success)' : 'var(--text-muted)' ?>"></i>
                                </button>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="<?= BASE_URL ?>/jm-panel/faq/edit?id=<?= $f['id_faq'] ?>" class="btn-icon"
                                        title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <button class="btn-icon btn-icon-danger delete-faq" data-id="<?= $f['id_faq'] ?>"
                                        data-title="<?= e($f['question']) ?>" title="Eliminar">
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

    <!-- ═══ ESTILOS ═══════════════════════════════════════════════ -->
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
        transition: all .22s var(--ease);
        position: relative;
        overflow: hidden
    }

    .kpi-card:hover {
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

    .faq-question {
        font-weight: 600;
        font-size: .85rem
    }

    .faq-answer-preview {
        font-size: .78rem;
        color: var(--text-dim);
        line-height: 1.5;
        max-width: 350px
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

    $(document).ready(function() {
        if ($('#faqTable').length) {
            $('#faqTable').DataTable({
                responsive: true,
                pageLength: 15,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [3, 'asc'],
                    [0, 'asc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [1, 4, 5]
                    },
                    {
                        searchable: false,
                        targets: [4, 5]
                    }
                ]
            });
        }
    });

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

            icon.style.color = curr ? 'var(--text-muted)' : 'var(--success)';
            icon.className = 'fas fa-' + (curr ? 'eye-slash' : 'eye');
            this.dataset.visible = curr ? '0' : '1';
            this.title = curr ? 'Tornar visível' : 'Ocultar';

            try {
                const res = await fetch(BASE + '/jm-panel/faq/toggle?id=' + id, {
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
                    toast('success', data.status_faq === 'visible' ? 'FAQ visível' : 'FAQ ocultada',
                        2000);
                }
            } catch (err) {
                icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                this.dataset.visible = curr ? '1' : '0';
            }
        });
    });

    // ── Eliminar FAQ ─────────────────────────────────────────────
    document.querySelectorAll('.delete-faq').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const title = this.dataset.title || 'esta FAQ';

            if (typeof Swal === 'undefined') {
                if (!confirm('Eliminar "' + title + '"?')) return;
                doDelete(id);
                return;
            }

            Swal.fire({
                title: 'Eliminar FAQ?',
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
            const res = await fetch(BASE + '/jm-panel/faq/delete?id=' + id, {
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
                toast('success', 'FAQ eliminada com sucesso');
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