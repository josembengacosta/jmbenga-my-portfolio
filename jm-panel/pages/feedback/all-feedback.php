<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Feedback (padrão Inbox v2)
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

// ── Filtros via GET ─────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if (in_array($filterStatus, ['new','read','archived'])) {
    $where[]  = 'status_fb = ?';
    $params[] = $filterStatus;
} elseif ($filterStatus === 'starred') {
    $where[] = 'is_starred = 1';
}

if ($search !== '') {
    $where[]  = '(name_fb LIKE ? OR subject_fb LIKE ? OR message_fb LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT * FROM _feedback';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Estatísticas KPI ────────────────────────────────────────
$totalAll      = (int) $db->query("SELECT COUNT(*) FROM _feedback")->fetchColumn();
$totalNew      = (int) $db->query("SELECT COUNT(*) FROM _feedback WHERE status_fb = 'new'")->fetchColumn();
$totalRead     = (int) $db->query("SELECT COUNT(*) FROM _feedback WHERE status_fb = 'read'")->fetchColumn();
$totalArchived = (int) $db->query("SELECT COUNT(*) FROM _feedback WHERE status_fb = 'archived'")->fetchColumn();
$totalStarred  = (int) $db->query("SELECT COUNT(*) FROM _feedback WHERE is_starred = 1")->fetchColumn();

// ── CSRF token para AJAX ────────────────────────────────────
$csrfToken = $_SESSION['admin_csrf_token'];

// ── Helper: badge classe + ícone + label ────────────────────
function fbBadge(string $status): array
{
    return match ($status) {
        'new'      => ['class' => 'badge-new',      'icon' => 'envelope',     'label' => 'Nova'],
        'read'     => ['class' => 'badge-read',     'icon' => 'check',        'label' => 'Lida'],
        'archived' => ['class' => 'badge-archived', 'icon' => 'box-archive',  'label' => 'Arquivada'],
        default    => ['class' => 'badge-read',     'icon' => 'circle',       'label' => ucfirst($status)],
    };
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<style>
/* ── Base ──────────────────────────────────────────────────── */
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

/* ── KPI row ───────────────────────────────────────────────── */
.kpi-row {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem
}

.kpi-card {
    flex: 1;
    min-width: 110px;
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

.kpi-card.kpi-active {
    border-color: var(--accent);
    background: rgba(37, 99, 235, .06)
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

/* ── Filter bar ────────────────────────────────────────────── */
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
    font-size: .8rem;
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

/* ── Table card ────────────────────────────────────────────── */
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

.row-new {
    background: rgba(37, 99, 235, .03)
}

.msg-name {
    font-weight: 600;
    font-size: .85rem
}

.msg-preview {
    font-size: .8rem;
    color: var(--text-dim);
    line-height: 1.5;
    max-width: 280px
}

/* ── Status badges (4 cores distintas) ────────────────────── */
.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700
}

.badge-new {
    background: rgba(37, 99, 235, .1);
    color: var(--accent);
    border: 1px solid rgba(37, 99, 235, .2)
}

.badge-read {
    background: rgba(148, 163, 184, .1);
    color: var(--text-muted);
    border: 1px solid var(--border)
}

.badge-archived {
    background: rgba(100, 116, 139, .1);
    color: #64748b;
    border: 1px solid rgba(100, 116, 139, .2)
}

/* ── Row actions ───────────────────────────────────────────── */
.row-actions {
    display: flex;
    gap: .3rem;
    align-items: center
}

.btn-icon {
    width: 28px;
    height: 28px;
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
    font-size: .78rem
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

.btn-icon-warning:hover {
    background: #d97706;
    border-color: #d97706;
    color: #fff
}

.btn-icon-star {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all .2s;
    font-size: .85rem
}

.btn-icon-star:hover {
    transform: scale(1.2)
}

/* ── Buttons ───────────────────────────────────────────────── */
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
    filter: brightness(1.12);
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

/* ── Empty state ───────────────────────────────────────────── */
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

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <!-- Cabeçalho -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Feedback</span>
                    </div>
                    <h1 class="page-title">Feedback Recebido</h1>
                    <p class="page-sub">
                        <?= $totalAll ?> no total &nbsp;·&nbsp;
                        <?php if ($totalNew > 0): ?>
                        <strong style="color:var(--accent)"><?= $totalNew ?> por ler</strong> &nbsp;·&nbsp;
                        <?php else: ?>
                        0 por ler &nbsp;·&nbsp;
                        <?php endif; ?>
                        <?= $totalRead ?> lidos
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary" title="Ver portfólio">
                        <i class="fas fa-arrow-up-right-from-square"></i> Ver site
                    </a>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="kpi-row">
                <a href="?status=all" class="kpi-card <?= $filterStatus === 'all' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.15);color:#94a3b8"><i
                            class="fas fa-layer-group"></i></span>
                    <div><span class="kpi-val"><?= $totalAll ?></span>
                        <div class="kpi-lbl">Total</div>
                    </div>
                </a>
                <a href="?status=new" class="kpi-card <?= $filterStatus === 'new' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(37,99,235,.12);color:#3b82f6"><i
                            class="fas fa-envelope"></i></span>
                    <div><span class="kpi-val"><?= $totalNew ?></span>
                        <div class="kpi-lbl">Por ler</div>
                    </div>
                </a>
                <a href="?status=read" class="kpi-card <?= $filterStatus === 'read' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(148,163,184,.12);color:#94a3b8"><i
                            class="fas fa-check"></i></span>
                    <div><span class="kpi-val"><?= $totalRead ?></span>
                        <div class="kpi-lbl">Lidos</div>
                    </div>
                </a>
                <a href="?status=starred" class="kpi-card <?= $filterStatus === 'starred' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:#f59e0b"><i
                            class="fas fa-star"></i></span>
                    <div><span class="kpi-val"><?= $totalStarred ?></span>
                        <div class="kpi-lbl">Favoritos</div>
                    </div>
                </a>
                <a href="?status=archived" class="kpi-card <?= $filterStatus === 'archived' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.12);color:#64748b"><i
                            class="fas fa-box-archive"></i></span>
                    <div><span class="kpi-val"><?= $totalArchived ?></span>
                        <div class="kpi-lbl">Arquivados</div>
                    </div>
                </a>
            </div>

            <!-- Barra de filtros -->
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Pesquisar feedbacks…" value="<?= e($search) ?>"
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
                    <option value="new" <?= $filterStatus === 'new' ? 'selected' : '' ?>>Por ler</option>
                    <option value="read" <?= $filterStatus === 'read' ? 'selected' : '' ?>>Lidos</option>
                    <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Arquivados</option>
                    <option value="starred" <?= $filterStatus === 'starred' ? 'selected' : '' ?>>⭐ Favoritos</option>
                </select>

                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <?php if ($filterStatus !== 'all' || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/feedback" class="btn btn-secondary" style="padding:.45rem .9rem">
                    <i class="fas fa-times"></i> Limpar
                </a>
                <span class="filter-result">
                    <i class="fas fa-circle-info"></i>
                    <?= count($feedbacks) ?> resultado<?= count($feedbacks) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </form>

            <!-- Tabela -->
            <div class="table-card">
                <?php if (empty($feedbacks)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-comment-dots"></i></div>
                    <h3>Nenhum feedback encontrado</h3>
                    <p>
                        <?php if ($search || $filterStatus !== 'all'): ?>
                        Tenta ajustar os filtros ou <a href="<?= BASE_URL ?>/jm-panel/feedback">ver todos</a>.
                        <?php else: ?>
                        Ainda não recebeste nenhum feedback.
                        <?php endif; ?>
                    </p>
                </div>
                <?php else: ?>
                <table id="feedbackTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:20px"></th>
                            <th>Nome</th>
                            <th>Assunto</th>
                            <th>Mensagem</th>
                            <th>Página</th>
                            <th style="text-align:center">Estado</th>
                            <th>Data</th>
                            <th style="width:120px;text-align:center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $fb):
                            $badge = fbBadge($fb['status_fb']);
                        ?>
                        <tr id="row-<?= $fb['id'] ?>" class="<?= $fb['status_fb'] === 'new' ? 'row-new' : '' ?>">
                            <!-- Favorito -->
                            <td style="text-align:center">
                                <button class="btn-icon-star toggle-star" data-id="<?= $fb['id'] ?>"
                                    data-starred="<?= $fb['is_starred'] ?>"
                                    title="<?= $fb['is_starred'] ? 'Remover favorito' : 'Marcar como favorito' ?>">
                                    <i class="fas fa-star"
                                        style="color:<?= $fb['is_starred'] ? '#f59e0b' : 'var(--text-muted)' ?>"></i>
                                </button>
                            </td>
                            <!-- Nome -->
                            <td>
                                <span class="msg-name"><?= e($fb['name_fb']) ?></span>
                            </td>
                            <!-- Assunto -->
                            <td style="max-width:200px">
                                <span
                                    style="font-size:.83rem;font-weight:<?= $fb['status_fb'] === 'new' ? '700' : '400' ?>">
                                    <?= e($fb['subject_fb']) ?>
                                </span>
                            </td>
                            <!-- Preview -->
                            <td>
                                <div class="msg-preview">
                                    <?= e(mb_strlen($fb['message_fb']) > 80 ? mb_substr($fb['message_fb'], 0, 80) . '…' : $fb['message_fb']) ?>
                                </div>
                            </td>
                            <!-- Página de origem -->
                            <td style="font-size:.75rem;color:var(--text-muted)"><?= e($fb['page_origin'] ?: '—') ?>
                            </td>
                            <!-- Estado -->
                            <td style="text-align:center">
                                <span class="badge-status <?= $badge['class'] ?>">
                                    <i class="fas fa-<?= $badge['icon'] ?>"></i> <?= $badge['label'] ?>
                                </span>
                            </td>
                            <!-- Data -->
                            <td>
                                <span style="font-family:var(--font-mono);font-size:.74rem;color:var(--text-dim)"
                                    title="<?= date('d/m/Y H:i', strtotime($fb['created_at'])) ?>">
                                    <?= date('d/m/Y', strtotime($fb['created_at'])) ?>
                                </span>
                            </td>
                            <!-- Ações -->
                            <td>
                                <div class="row-actions" style="justify-content:center">
                                    <a href="<?= BASE_URL ?>/jm-panel/feedback/view?id=<?= $fb['id'] ?>"
                                        class="btn-icon" title="Ver feedback">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn-icon mark-read" data-id="<?= $fb['id'] ?>"
                                        data-status="<?= $fb['status_fb'] ?>"
                                        title="<?= $fb['status_fb'] === 'new' ? 'Marcar como lido' : 'Marcar como não lido' ?>">
                                        <i class="fas fa-<?= $fb['status_fb'] === 'new' ? 'check' : 'envelope' ?>"></i>
                                    </button>
                                    <button class="btn-icon btn-icon-warning archive-feedback"
                                        data-id="<?= $fb['id'] ?>" data-status="<?= $fb['status_fb'] ?>"
                                        title="<?= $fb['status_fb'] === 'archived' ? 'Restaurar' : 'Arquivar' ?>"
                                        style="<?= $fb['status_fb'] === 'archived' ? 'color:#f59e0b' : '' ?>">
                                        <i
                                            class="fas fa-<?= $fb['status_fb'] === 'archived' ? 'rotate-left' : 'box-archive' ?>"></i>
                                    </button>
                                    <button class="btn-icon btn-icon-danger delete-feedback" data-id="<?= $fb['id'] ?>"
                                        data-title="<?= e($fb['name_fb']) ?>" title="Eliminar">
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

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

    // DataTables
    $(document).ready(function() {
        if ($('#feedbackTable').length) {
            $('#feedbackTable').DataTable({
                responsive: true,
                pageLength: 20,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [6, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                        orderable: false,
                        targets: [0, 3, 5, 7]
                    },
                    {
                        searchable: false,
                        targets: [0, 5, 7]
                    }
                ]
            });
        }
    });

    // ── Toast (SweetAlert2) ───────────────────────────────────
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
            color: 'var(--text)'
        });
    }

    // ── Toggle favorito ──────────────────────────────────────
    document.querySelectorAll('.toggle-star').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const icon = this.querySelector('i');
            const curr = this.dataset.starred === '1';
            icon.style.color = curr ? 'var(--text-muted)' : '#f59e0b';
            this.dataset.starred = curr ? '0' : '1';
            try {
                const res = await fetch(BASE + '/jm-panel/feedback/toggle-star?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (!data.success) {
                    icon.style.color = curr ? '#f59e0b' : 'var(--text-muted)';
                    this.dataset.starred = curr ? '1' : '0';
                } else {
                    toast('success', data.is_starred ? 'Favorito adicionado' : 'Favorito removido',
                        2000);
                }
            } catch {
                icon.style.color = curr ? '#f59e0b' : 'var(--text-muted)';
                this.dataset.starred = curr ? '1' : '0';
            }
        });
    });

    // ── Marcar como lido / não lido ──────────────────────────
    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const row = document.getElementById('row-' + id);
            try {
                const res = await fetch(BASE + '/jm-panel/feedback/toggle-read?id=' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const badge = row.querySelector('.badge-status');
                    const icon = this.querySelector('i');
                    const newStatus = data.new_status;
                    const labels = {
                        new: ['badge-new', 'envelope', 'Nova'],
                        read: ['badge-read', 'check', 'Lida'],
                        archived: ['badge-archived', 'box-archive', 'Arquivada']
                    };
                    const [cls, ic, lbl] = labels[newStatus] || ['badge-read', 'check', newStatus];
                    badge.className = 'badge-status ' + cls;
                    badge.innerHTML = `<i class="fas fa-${ic}"></i> ${lbl}`;
                    icon.className = 'fas fa-' + (newStatus === 'new' ? 'check' : 'envelope');
                    this.dataset.status = newStatus;
                    row.classList.toggle('row-new', newStatus === 'new');
                    toast('success', newStatus === 'read' ? 'Marcado como lido' :
                        'Marcado como não lido', 2000);
                } else {
                    toast('error', data.message || 'Erro ao alterar estado');
                }
            } catch {
                toast('error', 'Erro de rede.');
            }
        });
    });

    // ── Arquivar / Restaurar ─────────────────────────────────
    document.querySelectorAll('.archive-feedback').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const row = document.getElementById('row-' + id);
            const isArchived = this.dataset.status === 'archived';
            const endpoint = isArchived ?
                BASE + '/jm-panel/feedback/unarchive?id=' + id :
                BASE + '/jm-panel/feedback/archive?id=' + id;
            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (data.success) {
                    if (isArchived) {
                        const badge = row.querySelector('.badge-status');
                        badge.className = 'badge-status badge-read';
                        badge.innerHTML = '<i class="fas fa-check"></i> Lida';
                        this.dataset.status = 'read';
                        this.title = 'Arquivar';
                        this.querySelector('i').className = 'fas fa-box-archive';
                        toast('success', 'Feedback restaurado.', 2000);
                    } else {
                        const currentFilter = new URLSearchParams(window.location.search).get(
                            'status') || 'all';
                        if (currentFilter !== 'archived') {
                            row.style.transition = 'opacity .3s, transform .3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(20px)';
                            setTimeout(() => row.remove(), 300);
                        } else {
                            const badge = row.querySelector('.badge-status');
                            badge.className = 'badge-status badge-archived';
                            badge.innerHTML = '<i class="fas fa-box-archive"></i> Arquivada';
                            this.dataset.status = 'archived';
                            this.title = 'Restaurar';
                            this.querySelector('i').className = 'fas fa-rotate-left';
                        }
                        toast('success', 'Feedback arquivado.', 2000);
                    }
                } else {
                    toast('error', data.message || 'Erro ao arquivar.');
                }
            } catch {
                toast('error', 'Erro de rede.');
            }
        });
    });

    // ── Eliminar feedback ────────────────────────────────────
    document.querySelectorAll('.delete-feedback').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const title = this.dataset.title || 'este feedback';
            if (typeof Swal === 'undefined') {
                if (!confirm('Eliminar feedback de "' + title + '"?')) return;
                doDelete(id);
                return;
            }
            Swal.fire({
                title: 'Eliminar feedback?',
                html: `<span style="color:var(--text-dim);font-size:.9rem">Vais eliminar permanentemente o feedback de <strong style="color:var(--text)">"${title}"</strong>.<br>Esta ação não pode ser desfeita.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sim, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                background: 'var(--bg-card)',
                color: 'var(--text)',
            }).then(r => {
                if (r.isConfirmed) doDelete(id);
            });
        });
    });

    async function doDelete(id) {
        try {
            const res = await fetch(BASE + '/jm-panel/feedback/delete?id=' + id, {
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
                toast('success', 'Feedback eliminado');
            } else {
                toast('error', data.message || 'Erro ao eliminar');
            }
        } catch {
            toast('error', 'Erro de rede.');
        }
    }
    </script>
</body>

</html>