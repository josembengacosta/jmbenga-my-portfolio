<?php
// ── Notificações reais da BD ────────────────────────────────
$_notifications = [];
try {
    $db = $GLOBALS['pdo'];

    // Mensagens novas
    $newMsgs = $db->query("
        SELECT id, name_msg AS actor, subject_msg AS detail, created_at AS ts, 'message' AS type
        FROM _contact_message
        WHERE status_msg = 'new'
        ORDER BY created_at DESC
        LIMIT 3
    ")->fetchAll();

    // Actividade recente (login, edições, etc.)
    $recentActs = $db->query("
        SELECT al.action, COALESCE(e.first_name, 'Sistema') AS actor,
               al.creat_log AS ts, 'activity' AS type
        FROM _audit_log al
        LEFT JOIN _employees e ON al.id_employees = e.id_employees
        ORDER BY al.creat_log DESC
        LIMIT 3
    ")->fetchAll();

    $_notifications = array_slice(array_merge($newMsgs, $recentActs), 0, 5);

    // Ordenar por data
    usort($_notifications, fn($a, $b) => strtotime($b['ts']) - strtotime($a['ts']));
} catch (Throwable $_e) {
    $_notifications = [];
}

$_notifCount = count($_notifications);

// ── Breadcrumb (pode ser definido em cada página) ───────────
// Ex.: $pageBreadcrumb = [['label' => 'Projectos', 'url' => '/jm-panel/projects'], ['label' => 'Editar']];
$_breadcrumb  = $pageBreadcrumb ?? [];
$_pageHeading = $pageHeading    ?? $pageTitle ?? 'Painel de Controlo';

// ── Helper: tempo relativo ──────────────────────────────────
function _timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Agora';
    if ($diff < 3600)  return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . ' h atrás';
    return date('d/m', strtotime($datetime));
}
?>

<header class="topbar">

    <!-- Esquerda: toggle + título / breadcrumb -->
    <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle" title="Menu (Alt+M)">
            <i class="fas fa-bars"></i>
        </button>

        <div class="topbar-heading">
            <?php if (!empty($_breadcrumb)): ?>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>/jm-panel/home" class="breadcrumb-item">
                    <i class="fas fa-home"></i>
                </a>
                <?php foreach ($_breadcrumb as $i => $crumb): ?>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <?php if (isset($crumb['url']) && $i < count($_breadcrumb) - 1): ?>
                <a href="<?= BASE_URL . e($crumb['url']) ?>" class="breadcrumb-item">
                    <?= e($crumb['label']) ?>
                </a>
                <?php else: ?>
                <span class="breadcrumb-item breadcrumb-current"><?= e($crumb['label']) ?></span>
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
            <button class="topbar-search-btn" id="searchToggle" title="Pesquisar (Ctrl+K)">
                <i class="fas fa-search"></i>
            </button>
            <div class="topbar-search-box" id="searchBox">
                <i class="fas fa-search search-icon-inner"></i>
                <input type="text" id="searchInput" placeholder="Pesquisar projectos, mensagens…" autocomplete="off"
                    spellcheck="false">
                <kbd class="search-kbd">ESC</kbd>
            </div>
        </div>

        <!-- Notificações -->
        <div class="dropdown" id="notifDropdown">
            <button class="icon-btn" id="notifBtn" title="Notificações">
                <i class="fas fa-bell"></i>
                <?php if ($_notifCount > 0): ?>
                <span class="dot"></span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu notif-menu" id="notifMenu">
                <div class="notif-header">
                    <span class="notif-title">Notificações</span>
                    <?php if ($_notifCount > 0): ?>
                    <span class="notif-count"><?= $_notifCount ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_notifications)): ?>
                <?php foreach ($_notifications as $n): ?>
                <?php
                        $isMsg = ($n['type'] === 'message');
                        $icon  = $isMsg ? 'fa-envelope' : 'fa-circle-dot';
                        $color = $isMsg ? 'var(--danger)' : 'var(--accent)';
                        $label = $isMsg
                            ? 'Nova mensagem de <strong>' . e($n['actor']) . '</strong>'
                            : e(ucfirst(str_replace('_', ' ', $n['action'] ?? ''))) . ' — ' . e($n['actor']);
                        $href = $isMsg
                            ? BASE_URL . '/jm-panel/messages/view?id=' . $n['id']
                            : BASE_URL . '/jm-panel/security';
                        ?>
                <a href="<?= $href ?>" class="dropdown-item notif-item">
                    <span class="notif-icon" style="color:<?= $color ?>">
                        <i class="fas <?= $icon ?>"></i>
                    </span>
                    <span class="notif-body">
                        <span class="notif-label"><?= $label ?></span>
                        <?php if ($isMsg && !empty($n['detail'])): ?>
                        <span class="notif-sub"><?= e(mb_substr($n['detail'], 0, 40)) ?>…</span>
                        <?php endif; ?>
                        <span class="notif-time"><?= _timeAgo($n['ts']) ?></span>
                    </span>
                </a>
                <?php endforeach; ?>

                <div class="notif-footer">
                    <a href="<?= BASE_URL ?>/jm-panel/messages">
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
        <a href="<?= BASE_URL ?>/jm-panel/messages" class="icon-btn" title="Mensagens">
            <i class="fas fa-envelope"></i>
            <?php if (($unreadMessages ?? 0) > 0): ?>
            <span class="dot"></span>
            <?php endif; ?>
        </a>

        <!-- Alternar tema -->
        <button class="theme-toggle" id="themeToggle" title="Alternar tema">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <!-- Perfil -->
        <a href="<?= BASE_URL ?>/jm-panel/profile" class="user-menu">
            <div class="user-avatar">
                <?php if (!empty($adminPhoto)): ?>
                <img src="<?= BASE_URL ?>/assets/img/profile/<?= e($adminPhoto) ?>" alt="Foto de <?= e($adminName) ?>">
                <?php else: ?>
                <?= e($adminInitial ?? 'A') ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($adminName ?? 'Admin') ?></div>
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Data ao vivo ───────────────────────────────────────────────
    (function() {
        var el = document.getElementById('liveDate');
        if (!el) return;
        var days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        var d = new Date();
        el.textContent = days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d
            .getFullYear();
    })();

    // ── Dropdown notificações ──────────────────────────────────────
    (function() {
        var btn = document.getElementById('notifBtn');
        var menu = document.getElementById('notifMenu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('show');
        });
        document.addEventListener('click', function() {
            menu.classList.remove('show')
        });
    })();

    // ── Menu toggle (mobile) ───────────────────────────────────────
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });

    // ── Pesquisa rápida (Ctrl+K / Alt+S) ──────────────────────────
    (function() {
        var btn = document.getElementById('searchToggle');
        var box = document.getElementById('searchBox');
        var input = document.getElementById('searchInput');
        if (!btn || !box || !input) return;

        function openSearch() {
            box.classList.add('open');
            input.focus()
        }

        function closeSearch() {
            box.classList.remove('open');
            input.value = ''
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            box.classList.contains('open') ? closeSearch() : openSearch();
        });
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearch()
            }
            if (e.key === 'Escape') closeSearch();
        });
        document.addEventListener('click', function(e) {
            if (!box.contains(e.target) && e.target !== btn) closeSearch();
        });

        // Pesquisa simples (redireciona com query)
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                var q = encodeURIComponent(this.value.trim());
                window.location.href = '<?= BASE_URL ?>/jm-panel/projects?q=' + q;
            }
        });
    })();

    // ── Tema dark / light ──────────────────────────────────────────
    (function() {
        var h = document.documentElement;
        var t = document.getElementById('themeToggle');
        var i = document.getElementById('themeIcon');

        function applyTheme(v) {
            h.dataset.theme = v;
            if (i) i.className = v === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            localStorage.setItem('jm_theme', v);
        }
        applyTheme(localStorage.getItem('jm_theme') || 'dark');
        if (t) t.addEventListener('click', function() {
            applyTheme(h.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    })();

    // ── Modal de logout ────────────────────────────────────────────
    var _logoutModal = document.getElementById('logoutModal');
    var _sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
    var _closeModalBtn = document.getElementById('closeModal');
    var _cancelLogoutBtn = document.getElementById('cancelLogout');

    if (_logoutModal) {
        var closeModal = function() {
            _logoutModal.classList.remove('show');
        };

        if (_sidebarLogoutBtn) {
            _sidebarLogoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                _logoutModal.classList.add('show');
            });
        }
        if (_closeModalBtn) {
            _closeModalBtn.addEventListener('click', closeModal);
        }
        if (_cancelLogoutBtn) {
            _cancelLogoutBtn.addEventListener('click', closeModal);
        }
        _logoutModal.addEventListener('click', function(e) {
            if (e.target === _logoutModal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    }
});
</script>