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
$allowedSkillCategories = ['frontend', 'backend', 'devops', 'design', 'other'];
$rawCategory = $_GET['category'] ?? 'all';
$filterCategory = is_string($rawCategory) && ($rawCategory === 'all' || in_array($rawCategory, $allowedSkillCategories, true))
    ? $rawCategory
    : 'all';
$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? trim(mb_substr($rawSearch, 0, 100)) : '';

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
$csrfToken = (string) ($_SESSION['admin_csrf_token'] ?? '');

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
<link rel="stylesheet"
    href="<?= e(BASE_URL) ?>/assets/css/jmbenga-skills.css?v=<?= file_exists(ROOT_PATH . '/assets/css/jmbenga-skills.css') ? (int) filemtime(ROOT_PATH . '/assets/css/jmbenga-skills.css') : 1 ?>">
</head>

<body class="skills-list-page">

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
                        <span><span id="totalVisibleCount"><?= $totalVisible ?></span> visíveis</span> &nbsp;·&nbsp;
                        <span><span id="totalHiddenCount"><?= $totalHidden ?></span> ocultas</span>
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


    <script>
    const BASE = <?= json_encode(rtrim(BASE_URL, '/'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // ── DataTable ─────────────────────────────────────────────────
    $(document).ready(function() {
        if ($('#skillsTable').length) {
            $('#skillsTable').DataTable({
                responsive: true,
                pageLength: 15,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [], // Preserva a ordem category_skill/display_order definida pela query SQL.
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

    async function readJsonResponse(response) {
        const body = await response.text();
        try {
            return JSON.parse(body);
        } catch (_error) {
            if (response.redirected) throw new Error(
                'A sessão expirou ou o servidor redireccionou o pedido. Inicia sessão novamente.');
            throw new Error(
                'O servidor devolveu uma resposta inválida. Verifica a sessão e os endpoints de Skills.');
        }
    }

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
                const data = await readJsonResponse(res);
                if (!res.ok || !data.success) {
                    // Reverter a alteração optimista se o servidor a rejeitar.
                    icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                    icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                    this.dataset.visible = curr ? '1' : '0';
                    this.title = curr ? 'Ocultar' : 'Tornar visível';
                    const reason = Array.isArray(data.errors) ? data.errors.join(' ') : (data
                        .message || 'Não foi possível actualizar a visibilidade.');
                    toast('error', reason);
                } else {
                    const serverVisible = data.is_visible === true || Number(data.is_visible) === 1;
                    const newVisible = serverVisible ? 1 : 0;
                    icon.style.color = serverVisible ? 'var(--success)' : 'var(--text-muted)';
                    icon.className = 'fas fa-' + (serverVisible ? 'eye' : 'eye-slash');
                    this.dataset.visible = String(newVisible);
                    this.title = serverVisible ? 'Ocultar' : 'Tornar visível';

                    const visibleCounter = document.getElementById('totalVisibleCount');
                    const hiddenCounter = document.getElementById('totalHiddenCount');
                    const change = newVisible - (curr ? 1 : 0);
                    if (visibleCounter) visibleCounter.textContent = String(Math.max(0, Number(
                        visibleCounter.textContent) + change));
                    if (hiddenCounter) hiddenCounter.textContent = String(Math.max(0, Number(
                        hiddenCounter.textContent) - change));
                    toast('success', serverVisible ? 'Skill visível' : 'Skill ocultada', 2000);
                }
            } catch (err) {
                icon.style.color = curr ? 'var(--success)' : 'var(--text-muted)';
                icon.className = 'fas fa-' + (curr ? 'eye' : 'eye-slash');
                this.dataset.visible = curr ? '1' : '0';
                this.title = curr ? 'Ocultar' : 'Tornar visível';
                toast('error', err instanceof Error ? err.message :
                    'Falha ao actualizar a visibilidade.');
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
                text: `Vais eliminar permanentemente "${title}". Esta acção não pode ser desfeita.`,
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
            const data = await readJsonResponse(res);
            if (res.ok && data.success) {
                toast('success', data.message || 'Skill eliminada com sucesso');
                // Recarregar preserva os filtros da URL e actualiza os KPI e a tabela DataTables.
                window.setTimeout(() => window.location.reload(), 450);
            } else {
                toast('error', data.message || (Array.isArray(data.errors) ? data.errors.join(' ') :
                    'Erro ao eliminar'));
            }
        } catch (err) {
            toast('error', err instanceof Error ? err.message : 'Falha de comunicação com o servidor.');
        }
    }
    </script>

</body>

</html>