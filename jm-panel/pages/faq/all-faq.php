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
$filterCategory = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : 'all';
$filterStatus   = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : 'all';
$filterStatus   = in_array($filterStatus, ['all', 'visible', 'hidden'], true) ? $filterStatus : 'all';
$filterCategory = $filterCategory === '' ? 'all' : mb_substr($filterCategory, 0, 100);
$search         = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$search         = mb_substr($search, 0, 120);

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
$csrfToken = $_SESSION['admin_csrf_token'] ?? '';

// Preservar pesquisa e categoria ao alternar os cartões de estado.
$filterBaseParams = [];
if ($filterCategory !== 'all') $filterBaseParams['category'] = $filterCategory;
if ($search !== '') $filterBaseParams['search'] = $search;
$filterUrls = [];
foreach (['all', 'visible', 'hidden'] as $statusKey) {
    $params = $filterBaseParams;
    $params['status'] = $statusKey;
    $filterUrls[$statusKey] = '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-faq.css?v=1.0.2">
</head>

<body class="faq-list-page">

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
                <a href="<?= e($filterUrls['all']) ?>"
                    class="kpi-card <?= $filterStatus === 'all' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(100,116,139,.15);color:#94a3b8">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <span class="kpi-val"><?= $totalAll ?></span>
                    <span class="kpi-lbl">Total</span>
                </a>
                <a href="<?= e($filterUrls['visible']) ?>"
                    class="kpi-card <?= $filterStatus === 'visible' ? 'kpi-active' : '' ?>">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)">
                        <i class="fas fa-eye"></i>
                    </span>
                    <span class="kpi-val"><?= $totalVisible ?></span>
                    <span class="kpi-lbl">Visíveis</span>
                </a>
                <a href="<?= e($filterUrls['hidden']) ?>"
                    class="kpi-card <?= $filterStatus === 'hidden' ? 'kpi-active' : '' ?>">
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
                        maxlength="120" id="filterSearch">
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
                                        <?php
                                        $questionPreview = mb_strlen($f['question']) > 80
                                            ? mb_substr($f['question'], 0, 80) . '…'
                                            : $f['question'];
                                        ?>
                                        <span class="faq-question"
                                            title="<?= e($f['question']) ?>"><?= e($questionPreview) ?></span>
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
    <script>
        (() => {
            'use strict';
            const BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

            async function readJsonResponse(response) {
                const raw = await response.text();
                try {
                    return JSON.parse(raw);
                } catch (_) {
                    throw new Error(`Resposta inválida do servidor (HTTP ${response.status}).`);
                }
            }

            function toast(icon, title, timer = 2800) {
                if (typeof Swal === 'undefined') {
                    window.alert(String(title));
                    return;
                }
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon,
                    title: String(title),
                    showConfirmButton: false,
                    timer,
                    timerProgressBar: true,
                    background: 'var(--bg-card)',
                    color: 'var(--text)'
                });
            }

            $(function() {
                if ($.fn.DataTable && $('#faqTable').length) {
                    $('#faqTable').DataTable({
                        responsive: true,
                        pageLength: 15,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                        },
                        order: [], // Mantém a ordenação category_faq/display_order já aplicada pela query SQL.
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

            document.querySelectorAll('.toggle-visibility').forEach(button => {
                button.addEventListener('click', async function() {
                    if (this.disabled) return;
                    const id = this.dataset.id;
                    if (!/^\d+$/.test(id || '') || Number(id) <= 0) {
                        toast('error', 'Identificador de FAQ inválido.');
                        return;
                    }
                    this.disabled = true;
                    try {
                        const response = await fetch(
                            `${BASE}/jm-panel/faq/toggle?id=${encodeURIComponent(id)}`, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-Token': CSRF
                                },
                                credentials: 'same-origin'
                            });
                        const data = await readJsonResponse(response);
                        if (!response.ok || !data.success) throw new Error(data.message ||
                            'Não foi possível alterar a visibilidade.');
                        toast('success', data.message || 'Estado da FAQ actualizado.', 1200);
                        // Recalcula filtros, contadores e estado da linha a partir da base de dados.
                        window.setTimeout(() => window.location.reload(), 500);
                    } catch (error) {
                        this.disabled = false;
                        toast('error', error.message || 'Erro de rede.');
                    }
                });
            });

            document.querySelectorAll('.delete-faq').forEach(button => {
                button.addEventListener('click', async function() {
                    if (this.disabled) return;
                    const id = this.dataset.id;
                    const question = this.dataset.title || 'esta FAQ';
                    let confirmed = false;
                    if (typeof Swal !== 'undefined') {
                        const result = await Swal.fire({
                            title: 'Eliminar FAQ?',
                            text: `Vais eliminar permanentemente “${question}”. Esta acção não pode ser desfeita.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sim, eliminar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#ef4444',
                            background: 'var(--bg-card)',
                            color: 'var(--text)'
                        });
                        confirmed = result.isConfirmed;
                    } else {
                        confirmed = window.confirm(
                            `Eliminar “${question}”? Esta acção não pode ser desfeita.`);
                    }
                    if (!confirmed) return;
                    this.disabled = true;
                    try {
                        const response = await fetch(
                            `${BASE}/jm-panel/faq/delete?id=${encodeURIComponent(id)}`, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-Token': CSRF
                                },
                                credentials: 'same-origin'
                            });
                        const data = await readJsonResponse(response);
                        if (!response.ok || !data.success) throw new Error(data.message ||
                            'Erro ao eliminar a FAQ.');
                        toast('success', data.message || 'FAQ eliminada.', 1200);
                        window.setTimeout(() => window.location.reload(), 500);
                    } catch (error) {
                        this.disabled = false;
                        toast('error', error.message || 'Erro de rede.');
                    }
                });
            });
        })();
    </script>

</body>

</html>