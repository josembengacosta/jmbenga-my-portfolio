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
$filterStatus = in_array($filterStatus, ['all', 'visible', 'hidden'], true) ? $filterStatus : 'all';
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$search = mb_substr($search, 0, 100);

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
    $where[]  = '(name_testimonial LIKE ? OR role_testimonial LIKE ? OR company_testimonial LIKE ? OR body_testimonial LIKE ?)';
    $params[] = "%$search%";
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
$csrfToken = $_SESSION['admin_csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-testimonials.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-testimonials.css') ? filemtime(ROOT_PATH . '/assets/css/jmbenga-testimonials.css') : '1.0.0' ?>">
</head>

<body class="testimonials-list-page">

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
                                            <?php $photoFile = is_string($t['photo_testimonial'] ?? null) && basename($t['photo_testimonial']) === $t['photo_testimonial'] ? ROOT_PATH . '/assets/img/testimonials/' . $t['photo_testimonial'] : ''; ?>
                                            <?php if ($photoFile !== '' && is_file($photoFile)): ?>
                                                <img src="<?= BASE_URL ?>/assets/img/testimonials/<?= e($t['photo_testimonial']) ?>"
                                                    alt="<?= e($t['name_testimonial']) ?>">
                                            <?php else: ?>
                                                <span
                                                    class="avatar-letter"><?= e(mb_strtoupper(mb_substr((string)$t['name_testimonial'], 0, 1))) ?></span>
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
                                        <div class="testi-body-preview" title="<?= e($t['body_testimonial']) ?>">
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


    <script>
        const BASE = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        $(document).ready(function() {
            if ($('#testimonialsTable').length) {
                $('#testimonialsTable').DataTable({
                    responsive: true,
                    pageLength: 15,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                    },
                    order: [],
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

        async function readJsonResponse(response) {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (_) {
                throw new Error('O servidor devolveu uma resposta inesperada. Verifica a sessão e tenta novamente.');
            }
        }

        function applyVisibilityState(button, visible) {
            const icon = button.querySelector('i');
            button.dataset.visible = visible ? '1' : '0';
            button.title = visible ? 'Ocultar' : 'Tornar visível';
            button.setAttribute('aria-label', button.title);
            if (icon) {
                icon.className = `fas fa-${visible ? 'eye' : 'eye-slash'}`;
                icon.style.color = visible ? 'var(--success)' : 'var(--text-muted)';
            }
        }

        document.querySelectorAll('.toggle-visibility').forEach(button => {
            button.addEventListener('click', async function() {
                if (this.disabled) return;
                const id = this.dataset.id;
                this.disabled = true;
                try {
                    if (!/^\d+$/.test(id || '') || Number(id) <= 0) {
                        toast('error', 'Identificador de depoimento inválido.');
                        return;
                    }
                    const response = await fetch(`${BASE}/jm-panel/testimonials/toggle?id=${encodeURIComponent(id)}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': CSRF
                        },
                        credentials: 'same-origin'
                    });
                    const data = await readJsonResponse(response);
                    if (!response.ok || !data.success) throw new Error(data.message || 'Não foi possível actualizar a visibilidade.');
                    applyVisibilityState(this, data.status_testimonial === 'visible');
                    toast('success', data.message || 'Visibilidade actualizada.', 2000);
                    window.setTimeout(() => window.location.reload(), 450);
                } catch (error) {
                    toast('error', error.message || 'Erro de rede.');
                } finally {
                    this.disabled = false;
                }
            });
        });

        async function doDelete(id, button) {
            if (!/^\d+$/.test(id || '') || Number(id) <= 0) {
                toast('error', 'Identificador de depoimento inválido.');
                return;
            }
            if (button) button.disabled = true;
            try {
                const response = await fetch(`${BASE}/jm-panel/testimonials/delete?id=${encodeURIComponent(id)}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    },
                    credentials: 'same-origin'
                });
                const data = await readJsonResponse(response);
                if (!response.ok || !data.success) throw new Error(data.message || 'Erro ao eliminar o depoimento.');
                toast('success', data.message || 'Depoimento eliminado com sucesso.', 1800);
                // Reload keeps KPI totals and DataTables' internal row cache consistent.
                window.setTimeout(() => window.location.reload(), 450);
            } catch (error) {
                toast('error', error.message || 'Erro de rede.');
                if (button) button.disabled = false;
            }
        }

        document.querySelectorAll('.delete-testimonial').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title || 'este depoimento';
                const trigger = this;
                if (typeof Swal === 'undefined') {
                    if (window.confirm(`Eliminar o depoimento de "${title}"? Esta acção não pode ser desfeita.`)) {
                        doDelete(id, trigger);
                    }
                    return;
                }
                Swal.fire({
                    title: 'Eliminar depoimento?',
                    text: `Vais eliminar permanentemente o depoimento de "${title}". Esta acção não pode ser desfeita.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: 'transparent',
                    background: 'var(--bg-card)',
                    color: 'var(--text)',
                    customClass: {
                        cancelButton: 'swal-cancel-btn'
                    }
                }).then(result => {
                    if (result.isConfirmed) doDelete(id, trigger);
                });
            });
        });
    </script>

</body>

</html>