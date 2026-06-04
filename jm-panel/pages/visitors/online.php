<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visitantes Online (com actualização dinâmica)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

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

// ── Buscar visitantes online (inicial) ───────────────────────
$onlineStmt = $db->query("
    SELECT * FROM _visitor
    WHERE is_online = 1 AND is_bot = 0
    ORDER BY last_seen DESC
");
$online = $onlineStmt->fetchAll();

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

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/visitors">Visitantes</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Online</span>
                    </div>
                    <h1 class="page-title">Visitantes Online Agora</h1>
                    <p class="page-sub" id="onlineSubtitle">
                        <?= $onlineVisitors ?> online &nbsp;·&nbsp;
                        <span style="color:var(--success)"><i class="fas fa-circle" style="font-size:.45rem"></i>
                            Actualização em tempo real</span>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/visitors" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Todos
                </a>
            </div>

            <div id="onlineContainer">
                <?php if (empty($online)): ?>
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon"><i class="fas fa-wifi-slash"></i></div>
                    <h3>Nenhum visitante online</h3>
                    <p>Não há visitantes activos neste momento.</p>
                </div>
                <?php else: ?>
                <div class="online-grid" id="onlineGrid">
                    <?php foreach ($online as $v): ?>
                    <div class="online-card" id="online-<?= $v['id_visitor'] ?>">
                        <div class="online-dot"></div>
                        <div class="online-info">
                            <strong><?= e($v['ip_address']) ?></strong>
                            <div class="online-location"><?= e($v['country_name'] ?? '—') ?>,
                                <?= e($v['city'] ?? '—') ?></div>
                            <div class="online-device">
                                <i
                                    class="fas fa-<?= match($v['device_type']){'desktop'=>'desktop','mobile'=>'mobile-alt','tablet'=>'tablet-alt',default=>'question-circle'} ?>"></i>
                                <?= e($v['browser'] ?? '—') ?> / <?= e($v['os'] ?? '—') ?>
                            </div>
                            <div class="online-meta">
                                <?= (int)$v['pages_viewed'] ?> páginas &nbsp;·&nbsp;
                                <?= $v['session_duration'] ? floor($v['session_duration'] / 60) . 'm ' . ($v['session_duration'] % 60) . 's' : 'agora' ?>
                            </div>
                        </div>
                        <div class="online-actions">
                            <a href="<?= BASE_URL ?>/jm-panel/visitors/view?id=<?= $v['id_visitor'] ?>" class="btn-icon"
                                title="Ver"><i class="fas fa-eye"></i></a>
                            <button class="btn-icon btn-icon-danger block-visitor" data-id="<?= $v['id_visitor'] ?>"
                                title="Bloquear"><i class="fas fa-ban"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
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

    .online-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1rem
    }

    .online-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.2rem;
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        transition: all .2s;
        position: relative
    }

    .online-card:hover {
        border-color: var(--border-acc);
        box-shadow: 0 0 20px rgba(16, 185, 129, .08)
    }

    .online-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--success);
        margin-top: 6px;
        flex-shrink: 0;
        animation: pulse-dot 2s infinite
    }

    @keyframes pulse-dot {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, .4)
        }

        50% {
            box-shadow: 0 0 0 8px rgba(16, 185, 129, 0)
        }
    }

    .online-info {
        flex: 1
    }

    .online-info strong {
        font-size: .88rem
    }

    .online-location {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: 2px
    }

    .online-device {
        font-size: .72rem;
        color: var(--text-dim);
        margin-top: 4px
    }

    .online-meta {
        font-size: .68rem;
        color: var(--text-muted);
        margin-top: 4px
    }

    .online-actions {
        display: flex;
        flex-direction: column;
        gap: .3rem
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
        color: var(--text-muted)
    }
    </style>

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';

    // ═══ ACTUALIZAÇÃO DINÂMICA DA GRELHA (SEM RELOAD) ═══
    async function refreshOnlineVisitors() {
        try {
            const res = await fetch(BASE + '/jm-panel/visitors/api?action=online');
            const data = await res.json();
            if (!data.success) return;

            const container = document.getElementById('onlineContainer');
            const subtitle = document.getElementById('onlineSubtitle');
            const count = data.visitors.length;

            // Actualizar subtítulo
            subtitle.innerHTML =
                `${count} online &nbsp;·&nbsp; <span style="color:var(--success)"><i class="fas fa-circle" style="font-size:.45rem"></i> Actualização em tempo real</span>`;

            if (count === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-wifi-slash"></i></div>
                        <h3>Nenhum visitante online</h3>
                        <p>Não há visitantes activos neste momento.</p>
                    </div>`;
                return;
            }

            // Construir grelha
            let html = '<div class="online-grid" id="onlineGrid">';
            data.visitors.forEach(v => {
                const deviceIcon = v.device_type === 'desktop' ? 'desktop' :
                    v.device_type === 'mobile' ? 'mobile-alt' :
                    v.device_type === 'tablet' ? 'tablet-alt' : 'question-circle';
                const duration = v.session_duration ? Math.floor(v.session_duration / 60) + 'm ' + (v
                    .session_duration % 60) + 's' : 'agora';
                html += `
                    <div class="online-card" id="online-${v.id_visitor}">
                        <div class="online-dot"></div>
                        <div class="online-info">
                            <strong>${v.ip_address}</strong>
                            <div class="online-location">${v.country_name || '—'}, ${v.city || '—'}</div>
                            <div class="online-device">
                                <i class="fas fa-${deviceIcon}"></i>
                                ${v.browser || '—'} / ${v.os || '—'}
                            </div>
                            <div class="online-meta">
                                ${v.pages_viewed} páginas &nbsp;·&nbsp; ${duration}
                            </div>
                        </div>
                        <div class="online-actions">
                            <a href="${BASE}/jm-panel/visitors/view?id=${v.id_visitor}" class="btn-icon" title="Ver"><i class="fas fa-eye"></i></a>
                            <button class="btn-icon btn-icon-danger block-visitor" data-id="${v.id_visitor}" title="Bloquear"><i class="fas fa-ban"></i></button>
                        </div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;

            // Re-vincular eventos de bloqueio
            bindBlockButtons();

        } catch (err) {
            /* silencioso */ }
    }

    // Vincular eventos de bloqueio aos botões recém-criados
    function bindBlockButtons() {
        document.querySelectorAll('.block-visitor').forEach(btn => {
            btn.removeEventListener('click', handleBlock);
            btn.addEventListener('click', handleBlock);
        });
    }

    async function handleBlock() {
        const id = this.dataset.id;
        try {
            const res = await fetch(BASE + '/jm-panel/visitors/block?id=' + id, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                }
            });
            const data = await res.json();
            if (data.success) {
                const card = document.getElementById('online-' + id);
                if (card) {
                    card.style.transition = 'opacity .3s';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                }
            }
        } catch (err) {}
    }

    // Vincular eventos iniciais
    bindBlockButtons();

    // Actualizar a cada 30 segundos
    setInterval(refreshOnlineVisitors, 30000);
    </script>
</body>

</html>