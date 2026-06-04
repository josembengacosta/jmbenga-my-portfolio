<?php
// ── Detecção de rota activa ─────────────────────────────────
$_currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function navActive(string ...$segments): string
{
    global $_currentPath;
    foreach ($segments as $seg) {
        if (str_contains($_currentPath, $seg)) return ' active';
    }
    return '';
}
function navOpen(array $segments): string
{
    global $_currentPath;
    foreach ($segments as $seg) {
        if (str_contains($_currentPath, $seg)) return ' open';
    }
    return '';
}
?>
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <a href="<?= BASE_URL ?>/jm-panel/home" class="sidebar-brand">
        <span class="sidebar-brand-icon"><i class="fas fa-chart-pie"></i></span>
        <span>J<span class="brand-accent">Mbenga</span></span>
    </a>

    <nav class="sidebar-nav">

        <!-- Dashboard -->
        <a href="<?= BASE_URL ?>/jm-panel/home" class="nav-item<?= navActive('/jm-panel/home') ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <!-- ── CONTEÚDO ─────────────────────────────── -->
        <div class="nav-section-label">Conteúdo</div>

        <div class="nav-group">
            <div class="nav-item<?= navOpen(['/projects', '/skills', '/testimonials', '/faq']) ?>"
                data-submenu="content-sub">
                <i class="fas fa-folder-open"></i>
                <span>Conteúdo</span>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <div class="submenu<?= navOpen(['/projects', '/skills', '/testimonials', '/faq']) ? ' open' : '' ?>"
                id="content-sub">
                <a href="<?= BASE_URL ?>/jm-panel/projects" class="<?= navActive('/projects') ?>">
                    <i class="fas fa-briefcase"></i>
                    Projectos
                    <span class="badge"><?= $totalProjects ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/skills" class="<?= navActive('/skills') ?>">
                    <i class="fas fa-code"></i>
                    Skills
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/testimonials" class="<?= navActive('/testimonials') ?>">
                    <i class="fas fa-star"></i>
                    Depoimentos
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/faq" class="<?= navActive('/faq') ?>">
                    <i class="fas fa-circle-question"></i>
                    FAQ
                </a>
            </div>
        </div>

        <!-- ── COMUNICAÇÃO ──────────────────────────── -->
        <div class="nav-section-label">Comunicação</div>

        <div class="nav-group">
            <div class="nav-item<?= navOpen(['/messages', '/feedback']) ?>" data-submenu="comm-sub">
                <i class="fas fa-comments"></i>
                <span>Comunicação</span>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <div class="submenu<?= navOpen(['/messages', '/feedback']) ? ' open' : '' ?>" id="comm-sub">
                <a href="<?= BASE_URL ?>/jm-panel/messages" class="<?= navActive('/messages') ?>">
                    <i class="fas fa-envelope"></i>
                    Mensagens
                    <?php if (($unreadMessages ?? 0) > 0): ?>
                    <span class="badge"><?= $unreadMessages ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/feedback" class="<?= navActive('/feedback') ?>">
                    <i class="fas fa-comment-dots"></i>
                    Feedback
                </a>
            </div>
        </div>

        <!-- ── ANALÍTICA ─────────────────────────────── -->
        <div class="nav-section-label">Analítica</div>

        <a href="<?= BASE_URL ?>/jm-panel/visitors" class="nav-item<?= navActive('/visitors') ?>">
            <i class="fas fa-users"></i>
            <span>Visitantes</span>
            <?php if (($onlineVisitors ?? 0) > 0): ?>
            <span class="badge badge-success"><?= $onlineVisitors ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= BASE_URL ?>/jm-panel/analytics" class="nav-item<?= navActive('/analytics') ?>">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>

        <!-- ── SISTEMA ────────────────────────────────── -->
        <div class="nav-section-label">Sistema</div>

        <div class="nav-group">
            <div class="nav-item<?= navOpen(['/settings', '/security', '/profile']) ?>" data-submenu="sys-sub">
                <i class="fas fa-cog"></i>
                <span>Sistema</span>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <div class="submenu<?= navOpen(['/settings', '/security', '/profile']) ? ' open' : '' ?>" id="sys-sub">
                <a href="<?= BASE_URL ?>/jm-panel/settings" class="<?= navActive('/settings') ?>">
                    <i class="fas fa-sliders-h"></i>
                    Configurações
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/security" class="<?= navActive('/security') ?>">
                    <i class="fas fa-shield-alt"></i>
                    Segurança
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/profile" class="<?= navActive('/profile') ?>">
                    <i class="fas fa-user-circle"></i>
                    Meu Perfil
                </a>
            </div>
        </div>

        <!-- Terminar Sessão -->
        <a href="#" class="nav-item nav-logout" id="sidebarLogoutBtn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Terminar Sessão</span>
        </a>

    </nav>

    <!-- Footer da sidebar -->
    <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span>Sistema operacional</span>
        <span style="margin-left:auto;font-family:var(--font-mono);font-size:.65rem;opacity:.6">v2.0</span>
    </div>

</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var groups = document.querySelectorAll('.nav-item[data-submenu]');
    groups.forEach(function(item) {
        var id = item.dataset.submenu;
        var sub = document.getElementById(id);
        if (!sub) return;

        if (!sub.classList.contains('open')) {
            var saved = localStorage.getItem('jm_sub_' + id);
            if (saved === '1') {
                sub.classList.add('open');
                item.classList.add('open');
            }
        } else {
            item.classList.add('open');
        }

        item.addEventListener('click', function() {
            var isOpen = sub.classList.toggle('open');
            item.classList.toggle('open', isOpen);
            localStorage.setItem('jm_sub_' + id, isOpen ? '1' : '0');
        });
    });
});
</script>