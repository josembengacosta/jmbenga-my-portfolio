<?php
declare(strict_types=1);

// ── Detecção robusta da rota activa ─────────────────────────────
$requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
    ? $_SERVER['REQUEST_URI']
    : '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$currentPath = is_string($parsedPath) && $parsedPath !== ''
    ? '/' . trim($parsedPath, '/')
    : '/';

// Correspondência por segmentos: evita que, por exemplo, "my-projects-old"
// seja considerado a rota "/projects". Também funciona com BASE_URL em subpasta.
$navMatches = static function (array $segments) use ($currentPath): bool {
    $pathForMatch = trim($currentPath, '/');

    foreach ($segments as $segment) {
        if (!is_string($segment) || trim($segment, '/') === '') {
            continue;
        }

        $needle = trim($segment, '/');
        $pattern = '~(?:^|/)' . preg_quote($needle, '~') . '(?:/|$)~';

        if (preg_match($pattern, $pathForMatch) === 1) {
            return true;
        }
    }

    return false;
};

$navActive = static fn(array|string $segments): string =>
    $navMatches(is_array($segments) ? $segments : [$segments]) ? ' active' : '';

$navOpen = static fn(array $segments): string =>
    $navMatches($segments) ? ' open' : '';

$adminBase = rtrim((string) BASE_URL, '/') . '/jm-panel';
$sidebarRole = (string) ($_SESSION['admin_role'] ?? 'admin');
$sidebarCanSeeDashboard = in_array($sidebarRole, ['super_admin', 'admin'], true);
$sidebarCanSeeOperations = in_array($sidebarRole, ['super_admin', 'admin'], true);
$sidebarIsSuperAdmin = $sidebarRole === 'super_admin';

/*
 * Os contadores do menu não podem depender de variáveis definidas apenas
 * no dashboard ou na página de analytics. Quando a página atual ainda não
 * calculou esses valores, obtê-los aqui para manter os badges consistentes
 * em todo o painel (Mensagens, FAQ, Projectos, etc.).
 *
 * Se uma página já tiver calculado um valor, preservá-lo para evitar
 * sobrescrever dados específicos dessa página.
 */
$sidebarDb = $GLOBALS['pdo'] ?? null;

if ($sidebarDb instanceof PDO && $sidebarCanSeeOperations) {
    if (!isset($totalProjects) || !is_numeric($totalProjects)) {
        try {
            $totalProjects = (int) $sidebarDb
                ->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")
                ->fetchColumn();
        } catch (Throwable $e) {
            $totalProjects = 0;
            error_log('[ADMIN SIDEBAR] Não foi possível carregar o contador de projectos: ' . $e->getMessage());
        }
    }

    if (!isset($unreadMessages) || !is_numeric($unreadMessages)) {
        try {
            $unreadMessages = (int) $sidebarDb
                ->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")
                ->fetchColumn();
        } catch (Throwable $e) {
            $unreadMessages = 0;
            error_log('[ADMIN SIDEBAR] Não foi possível carregar o contador de mensagens: ' . $e->getMessage());
        }
    }

    if (!isset($onlineVisitors) || !is_numeric($onlineVisitors)) {
        try {
            $onlineVisitors = (int) $sidebarDb
                ->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")
                ->fetchColumn();
        } catch (Throwable $e) {
            $onlineVisitors = 0;
            error_log('[ADMIN SIDEBAR] Não foi possível carregar o contador de visitantes online: ' . $e->getMessage());
        }
    }
}

$totalProjectsBadge = max(0, (int) ($totalProjects ?? 0));
$unreadMessagesBadge = max(0, (int) ($unreadMessages ?? 0));
$onlineVisitorsBadge = max(0, (int) ($onlineVisitors ?? 0));

$contentRoutes = ['/projects', '/skills', '/testimonials', '/faq'];
$communicationRoutes = ['/messages', '/feedback'];
$systemRoutes = ['/settings', '/security'];
$contentOpen = $navMatches($contentRoutes);
$communicationOpen = $navMatches($communicationRoutes);
$systemOpen = $navMatches($systemRoutes);
?>

<aside class="sidebar" id="sidebar" aria-label="Navegação administrativa">

    <!-- Marca -->
    <a href="<?= e($adminBase) ?>/home" class="sidebar-brand">
        <span class="sidebar-brand-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>
        <span>J<span class="brand-accent">Mbenga</span></span>
    </a>

    <nav class="sidebar-nav" aria-label="Menu principal">

        <!-- Dashboard: Super Admin e Admin -->
        <?php if ($sidebarCanSeeDashboard): ?>
        <a href="<?= e($adminBase) ?>/home" class="nav-item<?= $navActive('/jm-panel/home') ?>">
            <i class="fas fa-home" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- Conteúdo -->
        <div class="nav-section-label">Conteúdo</div>

        <div class="nav-group">
            <div class="nav-item<?= $contentOpen ? ' open' : '' ?>" role="button" tabindex="0"
                data-submenu="content-sub" aria-controls="content-sub"
                aria-expanded="<?= $contentOpen ? 'true' : 'false' ?>">
                <i class="fas fa-folder-open" aria-hidden="true"></i>
                <span>Conteúdo</span>
                <i class="fas fa-chevron-right arrow" aria-hidden="true"></i>
            </div>
            <div class="submenu<?= $contentOpen ? ' open' : '' ?>" id="content-sub">
                <a href="<?= e($adminBase) ?>/projects" class="<?= trim($navActive('/projects')) ?>">
                    <i class="fas fa-briefcase" aria-hidden="true"></i>
                    <span>Projectos</span>
                    <span class="badge"><?= $totalProjectsBadge ?></span>
                </a>
                <a href="<?= e($adminBase) ?>/skills" class="<?= trim($navActive('/skills')) ?>">
                    <i class="fas fa-code" aria-hidden="true"></i>
                    <span>Skills</span>
                </a>
                <a href="<?= e($adminBase) ?>/testimonials" class="<?= trim($navActive('/testimonials')) ?>">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <span>Depoimentos</span>
                </a>
                <a href="<?= e($adminBase) ?>/faq" class="<?= trim($navActive('/faq')) ?>">
                    <i class="fas fa-circle-question" aria-hidden="true"></i>
                    <span>FAQ</span>
                </a>
            </div>
        </div>

        <?php if ($sidebarCanSeeOperations): ?>
        <!-- Comunicação -->
        <div class="nav-section-label">Comunicação</div>

        <div class="nav-group">
            <div class="nav-item<?= $communicationOpen ? ' open' : '' ?>" role="button" tabindex="0"
                data-submenu="comm-sub" aria-controls="comm-sub"
                aria-expanded="<?= $communicationOpen ? 'true' : 'false' ?>">
                <i class="fas fa-comments" aria-hidden="true"></i>
                <span>Comunicação</span>
                <i class="fas fa-chevron-right arrow" aria-hidden="true"></i>
            </div>
            <div class="submenu<?= $communicationOpen ? ' open' : '' ?>" id="comm-sub">
                <a href="<?= e($adminBase) ?>/messages" class="<?= trim($navActive('/messages')) ?>">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <span>Mensagens</span>
                    <?php if ($unreadMessagesBadge > 0): ?>
                    <span class="badge"><?= $unreadMessagesBadge ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= e($adminBase) ?>/feedback" class="<?= trim($navActive('/feedback')) ?>">
                    <i class="fas fa-comment-dots" aria-hidden="true"></i>
                    <span>Feedback</span>
                </a>
            </div>
        </div>

        <?php endif; ?>

        <?php if ($sidebarCanSeeOperations): ?>
        <!-- Analítica -->
        <div class="nav-section-label">Analítica</div>

        <a href="<?= e($adminBase) ?>/visitors" class="nav-item<?= $navActive('/visitors') ?>">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span>Visitantes</span>
            <?php if ($onlineVisitorsBadge > 0): ?>
            <span class="badge badge-success"><?= $onlineVisitorsBadge ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= e($adminBase) ?>/analytics" class="nav-item<?= $navActive('/analytics') ?>">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <span>Analytics</span>
        </a>

        <?php endif; ?>

        <?php if ($sidebarIsSuperAdmin): ?>
        <!-- Sistema -->
        <div class="nav-section-label">Sistema</div>

        <div class="nav-group">
            <div class="nav-item<?= $systemOpen ? ' open' : '' ?>" role="button" tabindex="0" data-submenu="sys-sub"
                aria-controls="sys-sub" aria-expanded="<?= $systemOpen ? 'true' : 'false' ?>">
                <i class="fas fa-cog" aria-hidden="true"></i>
                <span>Sistema</span>
                <i class="fas fa-chevron-right arrow" aria-hidden="true"></i>
            </div>
            <div class="submenu<?= $systemOpen ? ' open' : '' ?>" id="sys-sub">
                <a href="<?= e($adminBase) ?>/settings" class="<?= trim($navActive('/settings')) ?>">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    <span>Configurações</span>
                </a>
                <a href="<?= e($adminBase) ?>/security" class="<?= trim($navActive('/security')) ?>">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    <span>Segurança</span>
                </a>
            </div>
        </div>

        <?php endif; ?>

        <!-- Perfil pessoal: disponível para todos os perfis -->
        <a href="<?= e($adminBase) ?>/profile" class="nav-item<?= $navActive('/profile') ?>">
            <i class="fas fa-user-circle" aria-hidden="true"></i>
            <span>Meu Perfil</span>
        </a>

        <!-- Terminar sessão -->
        <a href="#" class="nav-item nav-logout" id="sidebarLogoutBtn">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Terminar Sessão</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <span class="status-dot" aria-hidden="true"></span>
        <span>Sistema operacional</span>
        <span class="sidebar-version">v2.0</span>
    </div>
</aside>

<style>
.sidebar .nav-item[role="button"]:focus-visible,
.sidebar .submenu a:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}
</style>

<script>
(function() {
    'use strict';

    function safeStorageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeStorageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // A navegação continua funcional mesmo quando o armazenamento é bloqueado.
        }
    }

    function initialiseSidebars() {
        var groups = document.querySelectorAll('.sidebar .nav-item[data-submenu]');

        groups.forEach(function(item) {
            var submenuId = item.getAttribute('data-submenu');
            var submenu = submenuId ? document.getElementById(submenuId) : null;
            if (!submenu) return;

            var storageKey = 'jm_sub_' + submenuId;
            var isOpen = submenu.classList.contains('open');

            if (!isOpen && safeStorageGet(storageKey) === '1') {
                isOpen = true;
                submenu.classList.add('open');
                item.classList.add('open');
            }

            item.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            function toggleSubmenu() {
                var nextOpen = !submenu.classList.contains('open');
                submenu.classList.toggle('open', nextOpen);
                item.classList.toggle('open', nextOpen);
                item.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                safeStorageSet(storageKey, nextOpen ? '1' : '0');
            }

            item.addEventListener('click', toggleSubmenu);
            item.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggleSubmenu();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseSidebars, {
            once: true
        });
    } else {
        initialiseSidebars();
    }
})();
</script>