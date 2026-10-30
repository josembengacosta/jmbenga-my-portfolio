<?php
// ── Notificações reais da BD ────────────────────────────────
$_notifications = [];

try {
    $db = $GLOBALS['pdo'] ?? null;
    if (!$db instanceof PDO) {
        throw new RuntimeException('Ligação PDO indisponível para as notificações do painel.');
    }

    // Mensagens novas.
    $newMsgs = $db->query("
        SELECT id, name_msg AS actor, subject_msg AS detail, created_at AS ts, 'message' AS type
        FROM _contact_message
        WHERE status_msg = 'new'
        ORDER BY created_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Actividade recente (login, edições, etc.).
    $recentActs = $db->query("
        SELECT al.action, COALESCE(NULLIF(e.first_name, ''), 'Sistema') AS actor,
               al.creat_log AS ts, 'activity' AS type
        FROM _audit_log al
        LEFT JOIN _employees e ON al.id_employees = e.id_employees
        ORDER BY al.creat_log DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Ordenar primeiro a lista completa; só depois limitar a cinco notificações.
    $_notifications = array_merge($newMsgs, $recentActs);
    usort($_notifications, static function (array $a, array $b): int {
        $timeA = strtotime((string) ($a['ts'] ?? ''));
        $timeB = strtotime((string) ($b['ts'] ?? ''));
        return ($timeB === false ? 0 : $timeB) <=> ($timeA === false ? 0 : $timeA);
    });
    $_notifications = array_slice($_notifications, 0, 5);
} catch (Throwable $_e) {
    // Falhas das notificações não devem impedir o carregamento do painel.
    $_notifications = [];
}

$_notifCount = count($_notifications);

// ── Breadcrumb, título e perfil (dados opcionais da página) ──
// get_defined_vars() permite ler valores fornecidos pela página sem
// referenciar directamente variáveis que podem não ter sido declaradas.
$_headerScope = get_defined_vars();

$_breadcrumbCandidate = $_headerScope['pageBreadcrumb'] ?? null;
$_headingCandidate = $_headerScope['pageHeading'] ?? null;
$_titleCandidate = $_headerScope['pageTitle'] ?? null;
$_providedAdminName = $_headerScope['adminName']
    ?? ($_SESSION['admin_full_name'] ?? ($_SESSION['admin_name'] ?? null));
$_providedAdminInitial = $_headerScope['adminInitial'] ?? null;
$_providedAdminPhoto = $_headerScope['adminPhoto']
    ?? ($_SESSION['admin_photo'] ?? ($_headerScope['currentAdmin']['photo_employees'] ?? null));

$_breadcrumb = is_array($_breadcrumbCandidate) ? $_breadcrumbCandidate : [];
$_pageHeading = is_string($_headingCandidate) && trim($_headingCandidate) !== ''
    ? trim($_headingCandidate)
    : (is_string($_titleCandidate) && trim($_titleCandidate) !== ''
        ? trim($_titleCandidate)
        : 'Painel de Controlo');

$_adminNameDisplay = is_scalar($_providedAdminName) && trim((string) $_providedAdminName) !== ''
    ? trim((string) $_providedAdminName)
    : 'Administrador';

if (is_string($_providedAdminInitial) && trim($_providedAdminInitial) !== '') {
    $_adminInitialDisplay = trim($_providedAdminInitial);
} else {
    $_adminInitialDisplay = function_exists('mb_substr')
        ? mb_substr($_adminNameDisplay, 0, 1, 'UTF-8')
        : substr($_adminNameDisplay, 0, 1);
}

// Usa apenas o nome do ficheiro, nunca um caminho fornecido externamente.
$_adminPhotoFile = is_string($_providedAdminPhoto)
    ? basename(trim($_providedAdminPhoto))
    : '';

unset(
    $_headerScope,
    $_breadcrumbCandidate,
    $_headingCandidate,
    $_titleCandidate,
    $_providedAdminName,
    $_providedAdminInitial,
    $_providedAdminPhoto
);

// ── Helper: tempo relativo ──────────────────────────────────
if (!function_exists('_timeAgo')) {
    function _timeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Data indisponível';
        }

        $diff = time() - $timestamp;
        if ($diff < 0)       return 'Agora';
        if ($diff < 60)      return 'Agora';
        if ($diff < 3600)    return (string) floor($diff / 60) . ' min atrás';
        if ($diff < 86400)   return (string) floor($diff / 3600) . ' h atrás';
        return date('d/m', $timestamp);
    }
}
?>

<header class="topbar">

    <!-- Esquerda: toggle + título / breadcrumb -->
    <div class="topbar-left">
        <button type="button" class="menu-toggle" id="menuToggle" title="Menu (Alt+M)" aria-label="Abrir menu lateral"
            aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>

        <div class="topbar-heading">
            <?php if (!empty($_breadcrumb)): ?>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?= e(rtrim((string) BASE_URL, '/') . '/jm-panel/home') ?>" class="breadcrumb-item">
                    <i class="fas fa-home"></i>
                </a>
                <?php foreach ($_breadcrumb as $i => $crumb): ?>
                <?php
                    if (!is_array($crumb)) {
                        continue;
                    }
                    $crumbLabel = is_scalar($crumb['label'] ?? null) ? (string) $crumb['label'] : '';
                    $crumbPath = $crumb['url'] ?? null;
                    $crumbHasInternalUrl = is_string($crumbPath)
                        && str_starts_with($crumbPath, '/')
                        && !str_starts_with($crumbPath, '//')
                        && !preg_match('/[\r\n]/', $crumbPath);
                    $crumbHref = $crumbHasInternalUrl ? rtrim((string) BASE_URL, '/') . $crumbPath : '';
                ?>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <?php if ($crumbHref !== '' && $i < count($_breadcrumb) - 1): ?>
                <a href="<?= e($crumbHref) ?>" class="breadcrumb-item">
                    <?= e($crumbLabel) ?>
                </a>
                <?php else: ?>
                <span class="breadcrumb-item breadcrumb-current"><?= e($crumbLabel) ?></span>
                <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php else: ?>
            <h2 class="topbar-title"><?= e($_pageHeading) ?></h2>
            <?php endif; ?>
        </div>
    </div>

    <!-- Direita: pesquisa + acções + perfil -->
    <div class="topbar-right">

        <!-- Data ao vivo -->
        <span class="date-display" id="liveDate"></span>

        <!-- Pesquisa rápida (Ctrl+K) -->
        <div class="topbar-search" id="topbarSearch">
            <button type="button" class="topbar-search-btn" id="searchToggle" title="Pesquisar (Ctrl+K)"
                aria-label="Pesquisar projectos" aria-controls="searchBox" aria-expanded="false">
                <i class="fas fa-search"></i>
            </button>
            <div class="topbar-search-box" id="searchBox">
                <i class="fas fa-search search-icon-inner"></i>
                <input type="text" id="searchInput" placeholder="Pesquisar projectos…" autocomplete="off"
                    spellcheck="false" data-search-url="<?= e(rtrim((string) BASE_URL, '/') . '/jm-panel/projects') ?>">
                <kbd class="search-kbd">ESC</kbd>
            </div>
        </div>

        <!-- Notificações -->
        <div class="dropdown" id="notifDropdown">
            <button type="button" class="icon-btn" id="notifBtn" title="Notificações" aria-label="Notificações"
                aria-controls="notifMenu" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if ($_notifCount > 0): ?>
                <span class="dot"></span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu notif-menu" id="notifMenu">
                <div class="notif-header">
                    <span class="notif-title">Notificações</span>
                    <?php if ($_notifCount > 0): ?>
                    <span class="notif-count"><?= (int) $_notifCount ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_notifications)): ?>
                <?php foreach ($_notifications as $n): ?>
                <?php
                    $isMsg = (($n['type'] ?? '') === 'message');
                    $icon = $isMsg ? 'fa-envelope' : 'fa-circle-dot';
                    $color = $isMsg ? 'var(--danger)' : 'var(--accent)';
                    $actor = is_scalar($n['actor'] ?? null) ? (string) $n['actor'] : 'Sistema';
                    $messageId = filter_var($n['id'] ?? null, FILTER_VALIDATE_INT);
                    $href = $isMsg && $messageId !== false
                        ? rtrim((string) BASE_URL, '/') . '/jm-panel/messages/view?id=' . (int) $messageId
                        : rtrim((string) BASE_URL, '/') . ($isMsg ? '/jm-panel/messages' : '/jm-panel/security');
                    $actionLabel = ucfirst(str_replace('_', ' ', (string) ($n['action'] ?? 'Actividade')));
                    $detail = is_scalar($n['detail'] ?? null) ? trim((string) $n['detail']) : '';
                    $detailShort = function_exists('mb_substr') ? mb_substr($detail, 0, 40, 'UTF-8') : substr($detail, 0, 40);
                    $detailLength = function_exists('mb_strlen') ? mb_strlen($detail, 'UTF-8') : strlen($detail);
                ?>
                <a href="<?= e($href) ?>" class="dropdown-item notif-item">
                    <span class="notif-icon" style="color:<?= e($color) ?>">
                        <i class="fas <?= e($icon) ?>"></i>
                    </span>
                    <span class="notif-body">
                        <span class="notif-label">
                            <?php if ($isMsg): ?>
                            Nova mensagem de <strong><?= e($actor) ?></strong>
                            <?php else: ?>
                            <?= e($actionLabel) ?> — <?= e($actor) ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($isMsg && $detail !== ''): ?>
                        <span class="notif-sub"><?= e($detailShort) ?><?= $detailLength > 40 ? '…' : '' ?></span>
                        <?php endif; ?>
                        <span class="notif-time"><?= e(_timeAgo((string) ($n['ts'] ?? ''))) ?></span>
                    </span>
                </a>
                <?php endforeach; ?>

                <div class="notif-footer">
                    <a href="<?= e(rtrim((string) BASE_URL, '/') . '/jm-panel/messages') ?>">
                        Ver todas as mensagens <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php else: ?>
                <div class="notif-empty">
                    <i class="fas fa-bell-slash"></i>
                    <span>Sem notificações novas</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mensagens (link directo) -->
        <a href="<?= e(rtrim((string) BASE_URL, '/') . '/jm-panel/messages') ?>" class="icon-btn" title="Mensagens"
            aria-label="Mensagens">
            <i class="fas fa-envelope"></i>
            <?php if (($unreadMessages ?? 0) > 0): ?>
            <span class="dot"></span>
            <?php endif; ?>
        </a>

        <!-- Alternar tema -->
        <button type="button" class="theme-toggle" id="themeToggle" title="Alternar tema" aria-label="Alternar tema">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <!-- Perfil -->
        <a href="<?= e(rtrim((string) BASE_URL, '/') . '/jm-panel/profile') ?>" class="user-menu">
            <div class="user-avatar">
                <?php if ($_adminPhotoFile !== '' && $_adminPhotoFile !== '.' && $_adminPhotoFile !== '..'): ?>
                <img src="<?= e(rtrim((string) BASE_URL, '/') . '/assets/img/profile/' . rawurlencode($_adminPhotoFile)) ?>"
                    alt="Foto de <?= e($_adminNameDisplay) ?>" loading="lazy">
                <?php else: ?>
                <?= e($_adminInitialDisplay) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($_adminNameDisplay) ?></div>
                <div class="user-role">Super Admin</div>
            </div>
            <i class="fas fa-chevron-down user-chevron"></i>
        </a>

    </div>
</header>

<style>
/* ═══ TOPBAR EXTRAS ═══════════════════════════════════════════ */
.topbar-heading {
    display: flex;
    flex-direction: column;
    justify-content: center
}

.topbar-title {
    font-family: var(--font-head);
    font-size: 1rem;
    font-weight: 700;
    color: var(--text)
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: .3rem;
    flex-wrap: wrap
}

.breadcrumb-item {
    font-size: .8rem;
    color: var(--text-dim);
    text-decoration: none;
    transition: color .18s;
    white-space: nowrap;
}

.breadcrumb-item:hover {
    color: var(--accent)
}

.breadcrumb-current {
    color: var(--text);
    font-weight: 600
}

.breadcrumb-sep {
    font-size: .55rem;
    color: var(--text-muted)
}

/* Search */
.topbar-search {
    display: flex;
    align-items: center;
    position: relative
}

.topbar-search-btn {
    background: none;
    border: 1px solid var(--border);
    color: var(--text-dim);
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: .9rem;
    transition: all .2s;
}

.topbar-search-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--accent-soft)
}

.topbar-search-box {
    display: none;
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-card);
    border: 1px solid var(--border-accent);
    border-radius: 10px;
    padding: .4rem .75rem;
    align-items: center;
    gap: .5rem;
    min-width: 280px;
    box-shadow: 0 8px 28px rgba(0, 0, 0, .3);
    animation: search-in .18s var(--ease);
    z-index: 60;
}

@keyframes search-in {
    from {
        opacity: 0;
        transform: translateY(-44%) scaleX(.95)
    }

    to {
        opacity: 1;
        transform: translateY(-50%) scaleX(1)
    }
}

.topbar-search-box.open {
    display: flex
}

.search-icon-inner {
    color: var(--text-muted);
    font-size: .85rem;
    flex-shrink: 0
}

.topbar-search-box input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    color: var(--text);
    font-family: var(--font-body);
    font-size: .85rem;
    min-width: 0;
}

.topbar-search-box input::placeholder {
    color: var(--text-muted)
}

.search-kbd {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: .65rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
    flex-shrink: 0;
}

/* Notificações */
.notif-menu {
    min-width: 300px;
    padding: 0;
    overflow: hidden
}

.notif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .7rem 1rem;
    border-bottom: 1px solid var(--border);
}

.notif-title {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--text-muted);
}

.notif-count {
    background: var(--danger);
    color: #fff;
    font-size: .62rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    font-family: var(--font-mono);
}

.notif-item {
    flex-direction: row;
    align-items: flex-start;
    gap: .75rem;
    padding: .75rem 1rem
}

.notif-icon {
    width: 28px;
    text-align: center;
    font-size: .85rem;
    flex-shrink: 0;
    padding-top: 2px
}

.notif-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0
}

.notif-label {
    font-size: .8rem;
    color: var(--text);
    line-height: 1.3
}

.notif-sub {
    font-size: .72rem;
    color: var(--text-dim);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis
}

.notif-time {
    font-size: .66rem;
    color: var(--text-muted);
    font-family: var(--font-mono)
}

.notif-footer {
    padding: .6rem 1rem;
    border-top: 1px solid var(--border);
    text-align: center;
}

.notif-footer a {
    font-size: .76rem;
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    transition: opacity .2s;
}

.notif-footer a:hover {
    opacity: .75
}

.notif-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: 2rem 1rem;
    color: var(--text-muted);
    font-size: .82rem;
}

.notif-empty i {
    font-size: 1.5rem;
    opacity: .35
}

/* Perfil no topbar */
.user-info {
    line-height: 1.2
}

.user-chevron {
    font-size: .6rem;
    color: var(--text-muted);
    margin-left: .1rem
}

@media (max-width: 640px) {
    .date-display {
        display: none;
    }

    .topbar-search-box {
        min-width: 0;
        width: min(320px, calc(100vw - 2rem));
        right: -3.25rem;
    }

    .notif-menu {
        min-width: 0;
        width: min(320px, calc(100vw - 2rem));
        max-width: calc(100vw - 2rem);
    }

    .user-info,
    .user-chevron {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data apresentada no topo.
    (function() {
        var el = document.getElementById('liveDate');
        if (!el) return;
        var days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        var d = new Date();
        el.textContent = days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d
            .getFullYear();
    })();

    // Dropdown de notificações.
    (function() {
        var btn = document.getElementById('notifBtn');
        var menu = document.getElementById('notifMenu');
        if (!btn || !menu) return;

        function closeMenu() {
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function(event) {
            event.stopPropagation();
            var isOpen = menu.classList.toggle('show');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        menu.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        document.addEventListener('click', closeMenu);
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeMenu();
        });
    })();

    // Menu lateral em dispositivos móveis. O cabeçalho pode ser reutilizado
    // em páginas que não carreguem sidebar, por isso verificamos ambos os elementos.
    (function() {
        var toggle = document.getElementById('menuToggle');
        var sidebar = document.getElementById('sidebar');
        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', function() {
            var isOpen = sidebar.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    })();

    // Pesquisa rápida (Ctrl/Cmd+K). Esta caixa encaminha para a pesquisa de projectos.
    (function() {
        var btn = document.getElementById('searchToggle');
        var box = document.getElementById('searchBox');
        var input = document.getElementById('searchInput');
        if (!btn || !box || !input) return;

        function openSearch() {
            box.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            input.focus();
        }

        function closeSearch(clearValue) {
            box.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            if (clearValue) input.value = '';
        }

        btn.addEventListener('click', function(event) {
            event.stopPropagation();
            box.classList.contains('open') ? closeSearch(false) : openSearch();
        });
        document.addEventListener('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openSearch();
            }
            if (event.key === 'Escape') closeSearch(false);
        });
        document.addEventListener('click', function(event) {
            if (!box.contains(event.target) && event.target !== btn) closeSearch(false);
        });
        input.addEventListener('keydown', function(event) {
            if (event.key !== 'Enter' || !this.value.trim()) return;
            var baseUrl = input.getAttribute('data-search-url');
            if (!baseUrl) return;
            var query = encodeURIComponent(this.value.trim());
            window.location.href = baseUrl + '?q=' + query;
        });
    })();

    // Tema claro/escuro com tolerância a navegadores que bloqueiem localStorage.
    (function() {
        var root = document.documentElement;
        var toggle = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var savedTheme = 'dark';

        try {
            var stored = window.localStorage.getItem('jm_theme');
            if (stored === 'dark' || stored === 'light') savedTheme = stored;
        } catch (error) {
            // O tema continua funcional durante a sessão, mesmo sem armazenamento.
        }

        function applyTheme(theme) {
            var safeTheme = theme === 'light' ? 'light' : 'dark';
            root.dataset.theme = safeTheme;
            if (icon) icon.className = safeTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            if (toggle) toggle.setAttribute('aria-label', safeTheme === 'dark' ? 'Activar tema claro' :
                'Activar tema escuro');
            try {
                window.localStorage.setItem('jm_theme', safeTheme);
            } catch (error) {
                // Não bloquear os restantes controlos se o armazenamento estiver indisponível.
            }
        }

        applyTheme(savedTheme);
        if (toggle) {
            toggle.addEventListener('click', function() {
                applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
            });
        }
    })();

    // Modal de logout.
    (function() {
        var modal = document.getElementById('logoutModal');
        var openButton = document.getElementById('sidebarLogoutBtn');
        var closeButton = document.getElementById('closeModal');
        var cancelButton = document.getElementById('cancelLogout');
        if (!modal) return;

        function closeModal() {
            modal.classList.remove('show');
        }

        if (openButton) {
            openButton.addEventListener('click', function(event) {
                event.preventDefault();
                modal.classList.add('show');
            });
        }
        if (closeButton) closeButton.addEventListener('click', closeModal);
        if (cancelButton) cancelButton.addEventListener('click', closeModal);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeModal();
        });
    })();
});
</script>