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
    WHERE is_online = 1 AND is_bot = 0 AND status_visitor <> 'blocked'
    ORDER BY last_seen DESC
");
$online = $onlineStmt->fetchAll();

$csrfToken = $_SESSION['admin_csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-visitors.css?v=<?= file_exists(ROOT_PATH . '/assets/css/jmbenga-visitors.css') ? filemtime(ROOT_PATH . '/assets/css/jmbenga-visitors.css') : '1' ?>">
</head>

<body class="visitors-online-page">
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
    const BASE = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const onlineContainer = document.getElementById('onlineContainer');
    const onlineSubtitle = document.getElementById('onlineSubtitle');

    function makeTextElement(tag, className, value) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        element.textContent = value == null || value === '' ? '—' : String(value);
        return element;
    }

    function makeEmptyOnlineState() {
        const empty = document.createElement('div');
        empty.className = 'empty-state';

        const iconBox = document.createElement('div');
        iconBox.className = 'empty-icon';
        const icon = document.createElement('i');
        icon.className = 'fas fa-wifi-slash';
        iconBox.appendChild(icon);

        empty.append(
            iconBox,
            makeTextElement('h3', '', 'Nenhum visitante online'),
            makeTextElement('p', '', 'Não há visitantes activos neste momento.')
        );
        return empty;
    }

    function renderOnlineVisitors(visitors) {
        onlineContainer.replaceChildren();

        if (!Array.isArray(visitors) || visitors.length === 0) {
            onlineContainer.appendChild(makeEmptyOnlineState());
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'online-grid';
        grid.id = 'onlineGrid';

        visitors.forEach(visitor => {
            const id = Number(visitor.id_visitor);
            if (!Number.isSafeInteger(id) || id <= 0) return;

            const deviceIcon = visitor.device_type === 'desktop' ? 'desktop' :
                visitor.device_type === 'mobile' ? 'mobile-alt' :
                visitor.device_type === 'tablet' ? 'tablet-alt' : 'question-circle';

            const durationSeconds = Math.max(0, Number(visitor.session_duration) || 0);
            const duration = durationSeconds > 0 ?
                `${Math.floor(durationSeconds / 60)}m ${durationSeconds % 60}s` :
                'agora';

            const card = document.createElement('div');
            card.className = 'online-card';
            card.id = `online-${id}`;

            const dot = document.createElement('div');
            dot.className = 'online-dot';

            const info = document.createElement('div');
            info.className = 'online-info';
            info.appendChild(makeTextElement('strong', '', visitor.ip_address));
            info.appendChild(makeTextElement(
                'div',
                'online-location',
                `${visitor.country_name || '—'}, ${visitor.city || '—'}`
            ));

            const device = document.createElement('div');
            device.className = 'online-device';
            const deviceIconElement = document.createElement('i');
            deviceIconElement.className = `fas fa-${deviceIcon}`;
            device.append(deviceIconElement, document.createTextNode(
                ` ${visitor.browser || '—'} / ${visitor.os || '—'}`
            ));

            info.append(
                device,
                makeTextElement(
                    'div',
                    'online-meta',
                    `${Number(visitor.pages_viewed) || 0} páginas · ${duration}`
                )
            );

            const actions = document.createElement('div');
            actions.className = 'online-actions';

            const viewLink = document.createElement('a');
            viewLink.href = `${BASE}/jm-panel/visitors/view?id=${id}`;
            viewLink.className = 'btn-icon';
            viewLink.title = 'Ver';
            const viewIcon = document.createElement('i');
            viewIcon.className = 'fas fa-eye';
            viewLink.appendChild(viewIcon);

            const blockButton = document.createElement('button');
            blockButton.type = 'button';
            blockButton.className = 'btn-icon btn-icon-danger block-visitor';
            blockButton.dataset.id = String(id);
            blockButton.title = 'Bloquear';
            const blockIcon = document.createElement('i');
            blockIcon.className = 'fas fa-ban';
            blockButton.appendChild(blockIcon);

            actions.append(viewLink, blockButton);
            card.append(dot, info, actions);
            grid.appendChild(card);
        });

        onlineContainer.appendChild(grid);
    }

    async function refreshOnlineVisitors() {
        try {
            const response = await fetch(BASE + '/jm-panel/visitors/api?action=online', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });
            const data = await response.json();
            if (!response.ok || !data.success) return;

            const visitors = Array.isArray(data.visitors) ? data.visitors : [];
            const status = document.createElement('span');
            status.className = 'online-live-status';
            const liveDot = document.createElement('i');
            liveDot.className = 'fas fa-circle';
            liveDot.setAttribute('aria-hidden', 'true');
            status.append(liveDot, document.createTextNode(' Actualização em tempo real'));

            onlineSubtitle.replaceChildren(
                document.createTextNode(`${visitors.length} online · `),
                status
            );

            renderOnlineVisitors(visitors);
        } catch (error) {
            // A grelha mantém os últimos dados quando a actualização falha.
        }
    }

    onlineContainer.addEventListener('click', async event => {
        const button = event.target.closest('.block-visitor');
        if (!button) return;

        const id = button.dataset.id;
        if (!/^[1-9]\d*$/.test(id || '')) return;

        button.disabled = true;
        try {
            const response = await fetch(BASE + '/jm-panel/visitors/block?id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                }
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível bloquear o visitante.');
            }

            const card = document.getElementById('online-' + id);
            if (card) card.remove();

            const remaining = onlineContainer.querySelectorAll('.online-card').length;
            if (remaining === 0) renderOnlineVisitors([]);
            onlineSubtitle.firstChild.textContent = `${remaining} online · `;
        } catch (error) {
            button.disabled = false;
            window.alert(error.message || 'Erro de rede ao bloquear o visitante.');
        }
    });

    // Primeira actualização após a renderização; depois, a cada 30 segundos.
    refreshOnlineVisitors();
    setInterval(refreshOnlineVisitors, 30000);
    </script>
</body>

</html>