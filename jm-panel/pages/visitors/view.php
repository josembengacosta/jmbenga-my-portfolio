<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visualizar Visitante (COMPLETO)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/jm-panel/visitors');

$stmt = $db->prepare("SELECT * FROM _visitor WHERE id_visitor = ?");
$stmt->execute([$id]);
$visitor = $stmt->fetch();

if (!$visitor) redirect('/jm-panel/visitors?msg=notfound');

// ── Pageviews do visitante ──────────────────────────────────
$pageviewsStmt = $db->prepare("
    SELECT * FROM _visitor_pageview
    WHERE id_visitor = ?
    ORDER BY creat_pageview DESC
    LIMIT 100
");
$pageviewsStmt->execute([$id]);
$pageviews = $pageviewsStmt->fetchAll();

// ── Contagem de visitas do mesmo IP (CORRIGIDO) ─────────────
$sameIpStmt = $db->prepare("SELECT COUNT(*) FROM _visitor WHERE ip_address = ?");
$sameIpStmt->execute([$visitor['ip_address']]);
$sameIpCount = (int) $sameIpStmt->fetchColumn();

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);
$csrfToken   = $_SESSION['admin_csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>
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
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap
}

.badge-bot {
    display: inline-block;
    background: rgba(245, 158, 11, .15);
    color: var(--warning);
    font-size: .6rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 700
}

.kpi-row {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem
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

.view-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 1.2rem;
    align-items: start
}

@media(max-width:1024px) {
    .view-grid {
        grid-template-columns: 1fr
    }
}

.form-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.2rem
}

.section-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1rem
}

.section-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: .85rem;
    flex-shrink: 0
}

.section-header h3 {
    font-family: var(--font-head);
    font-size: .95rem;
    font-weight: 700
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 0
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: .55rem 0;
    border-bottom: 1px solid var(--border);
    font-size: .82rem;
    gap: .5rem
}

.info-row:last-child {
    border-bottom: none
}

.info-row span:first-child {
    color: var(--text-muted);
    font-size: .75rem;
    flex-shrink: 0;
    min-width: 80px
}

.info-row span:last-child,
.info-row code {
    text-align: right;
    word-break: break-word
}

.info-row code {
    font-family: var(--font-mono);
    font-size: .7rem;
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--accent)
}

.pageview-list {
    display: flex;
    flex-direction: column;
    gap: .6rem;
    max-height: 500px;
    overflow-y: auto
}

.pageview-item {
    padding: .7rem .9rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: border-color .2s
}

.pageview-item:hover {
    border-color: var(--border-acc)
}

.pageview-url {
    font-size: .82rem;
    color: var(--accent);
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-bottom: .25rem;
    word-break: break-all
}

.pageview-url i {
    font-size: .7rem;
    opacity: .6
}

.pageview-meta {
    font-size: .7rem;
    color: var(--text-muted)
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
</style>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <!-- ═══ CABEÇALHO ════════════════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/visitors">Visitantes</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Visualizar</span>
                    </div>
                    <h1 class="page-title"><?= e($visitor['ip_address']) ?></h1>
                    <p class="page-sub">
                        <?php if ($visitor['is_bot']): ?><span class="badge-bot">BOT</span><?php endif; ?>
                        <?= e($visitor['country_name'] ?? 'País desconhecido') ?>
                        &nbsp;·&nbsp; <?= e($visitor['city'] ?? '—') ?>
                        &nbsp;·&nbsp; <?= $sameIpCount ?> visita(s) deste IP
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <?php if ($visitor['status_visitor'] !== 'blocked'): ?>
                    <button class="btn btn-secondary" id="blockBtn" data-id="<?= $id ?>">
                        <i class="fas fa-ban"></i> Bloquear IP
                    </button>
                    <?php else: ?>
                    <button class="btn btn-secondary" id="unblockBtn" data-id="<?= $id ?>">
                        <i class="fas fa-unlock"></i> Desbloquear IP
                    </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/jm-panel/visitors" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>

            <!-- ═══ KPI MINI CARDS ══════════════════════════════════════ -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(37,99,235,.12);color:var(--accent)"><i
                            class="fas fa-eye"></i></span>
                    <span class="kpi-val"><?= (int)$visitor['pages_viewed'] ?></span>
                    <span class="kpi-lbl">Páginas vistas</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)"><i
                            class="fas fa-clock"></i></span>
                    <span
                        class="kpi-val"><?= $visitor['session_duration'] ? floor($visitor['session_duration'] / 60) . 'm' : '—' ?></span>
                    <span class="kpi-lbl">Duração</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)"><i
                            class="fas fa-calendar-check"></i></span>
                    <span class="kpi-val"><?= (int)$visitor['visit_count'] ?></span>
                    <span class="kpi-lbl">Total de visitas</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(139,92,246,.12);color:var(--purple)"><i
                            class="fas fa-<?= $visitor['is_online'] ? 'wifi' : 'power-off' ?>"></i></span>
                    <span class="kpi-val"><?= $visitor['is_online'] ? 'Online' : 'Offline' ?></span>
                    <span class="kpi-lbl">Estado actual</span>
                </div>
            </div>

            <div class="view-grid">
                <!-- ═══ COLUNA PRINCIPAL ══════════════════════════════════ -->
                <div class="view-main">
                    <!-- Pageviews -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-list"></i></span>
                            <div>
                                <h3>Páginas Visitadas (<?= count($pageviews) ?>)</h3>
                            </div>
                        </div>
                        <?php if (empty($pageviews)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Nenhuma pageview registada.</p>
                        <?php else: ?>
                        <div class="pageview-list">
                            <?php foreach ($pageviews as $pv): ?>
                            <div class="pageview-item">
                                <div class="pageview-url">
                                    <i class="fas fa-link"></i>
                                    <?= e($pv['page_url']) ?>
                                </div>
                                <div class="pageview-meta">
                                    <?= e($pv['page_title'] ?? 'Sem título') ?>
                                    &nbsp;·&nbsp;
                                    <?= date('d/m/Y H:i', strtotime($pv['creat_pageview'])) ?>
                                    <?php if ($pv['time_on_page']): ?>
                                    &nbsp;·&nbsp;
                                    <?= $pv['time_on_page'] ?>s na página
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ SIDEBAR DE DETALHES ═══════════════════════════════ -->
                <div class="view-sidebar">
                    <!-- Informações do visitante -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-user-circle"></i></span>
                            <div>
                                <h3>Visitante</h3>
                            </div>
                        </div>
                        <div class="info-list">
                            <div class="info-row"><span>IP</span><code><?= e($visitor['ip_address']) ?></code></div>
                            <div class="info-row"><span>País</span><span><?= e($visitor['country_name'] ?? '—') ?>
                                    (<?= e($visitor['country_code'] ?? '—') ?>)</span></div>
                            <div class="info-row">
                                <span>Cidade</span><span><?= e($visitor['city'] ?? '—') ?><?= $visitor['region'] ? ', ' . e($visitor['region']) : '' ?></span>
                            </div>
                            <?php if ($visitor['latitude']): ?>
                            <div class="info-row"><span>Coordenadas</span><span><?= $visitor['latitude'] ?>,
                                    <?= $visitor['longitude'] ?></span></div>
                            <?php endif; ?>
                            <div class="info-row"><span>Fuso
                                    horário</span><span><?= e($visitor['timezone'] ?? '—') ?></span></div>
                            <div class="info-row"><span>ISP</span><span><?= e($visitor['isp'] ?? '—') ?></span></div>
                        </div>
                    </div>

                    <!-- Dispositivo -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-laptop"></i></span>
                            <div>
                                <h3>Dispositivo &amp; Navegador</h3>
                            </div>
                        </div>
                        <div class="info-list">
                            <div class="info-row">
                                <span>Dispositivo</span><span><?= e(ucfirst($visitor['device_type'] ?? '—')) ?></span>
                            </div>
                            <?php if ($visitor['device_brand']): ?>
                            <div class="info-row"><span>Marca</span><span><?= e($visitor['device_brand']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row"><span>Sistema</span><span><?= e($visitor['os'] ?? '—') ?>
                                    <?= e($visitor['os_version'] ?? '') ?></span></div>
                            <div class="info-row"><span>Navegador</span><span><?= e($visitor['browser'] ?? '—') ?>
                                    <?= e($visitor['browser_version'] ?? '') ?></span></div>
                            <div class="info-row">
                                <span>Resolução</span><span><?= e($visitor['screen_resolution'] ?? '—') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Origem -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-arrow-right"></i></span>
                            <div>
                                <h3>Origem</h3>
                            </div>
                        </div>
                        <div class="info-list">
                            <div class="info-row">
                                <span>Referrer</span><span><?= e($visitor['referrer'] ?: 'Directo / sem referência') ?></span>
                            </div>
                            <?php if ($visitor['utm_source']): ?>
                            <div class="info-row"><span>UTM Source</span><span><?= e($visitor['utm_source']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($visitor['utm_medium']): ?>
                            <div class="info-row"><span>UTM Medium</span><span><?= e($visitor['utm_medium']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($visitor['utm_campaign']): ?>
                            <div class="info-row"><span>UTM
                                    Campaign</span><span><?= e($visitor['utm_campaign']) ?></span></div>
                            <?php endif; ?>
                            <div class="info-row"><span>Página de entrada</span><span
                                    style="font-size:.75rem;word-break:break-all"><?= e($visitor['page_entry'] ?? '—') ?></span>
                            </div>
                            <div class="info-row"><span>Página de saída</span><span
                                    style="font-size:.75rem;word-break:break-all"><?= e($visitor['page_exit'] ?? '—') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Sessão -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-clock"></i></span>
                            <div>
                                <h3>Sessão</h3>
                            </div>
                        </div>
                        <div class="info-list">
                            <div class="info-row"><span>Primeira
                                    visita</span><span><?= date('d/m/Y H:i', strtotime($visitor['creat_visitor'])) ?></span>
                            </div>
                            <div class="info-row"><span>Última
                                    visita</span><span><?= date('d/m/Y H:i', strtotime($visitor['last_seen'])) ?></span>
                            </div>
                            <div class="info-row"><span>Session ID</span><code
                                    style="font-size:.65rem"><?= e($visitor['session_id'] ?? '—') ?></code></div>
                            <div class="info-row"><span>User Agent</span><span
                                    style="font-size:.7rem;word-break:break-all;max-width:180px"><?= e($visitor['user_agent'] ?? '—') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>


    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';
    const VISITOR_ID = <?= $id ?>;

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        let iconClass;
        if (type === 'success') {
            iconClass = 'fa-check-circle';
        } else if (type === 'error') {
            iconClass = 'fa-times-circle';
        } else {
            iconClass = 'fa-info-circle';
        }
        toast.innerHTML = `<i class="fas fa-${iconClass}"></i> ${message}`;
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

    // Bloquear / Desbloquear
    const blockBtn = document.getElementById('blockBtn');
    const unblockBtn = document.getElementById('unblockBtn');

    async function toggleBlock(action) {
        try {
            const res = await fetch(BASE + '/jm-panel/visitors/' + action + '?id=' + VISITOR_ID, {
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
    }

    if (blockBtn) blockBtn.addEventListener('click', () => toggleBlock('block'));
    if (unblockBtn) unblockBtn.addEventListener('click', () => toggleBlock('unblock'));
    </script>
</body>

</html>