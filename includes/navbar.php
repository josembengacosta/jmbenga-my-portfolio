<!-- Loading -->
<div id="loading">
    <div class="loader-logo" style="display: flex; justify-content: center; align-items: center; ">
        Carregando<span style="color:var(--accent)">...</span></div>
    <div class="loader-bar">
        <div class="loader-fill"></div>
    </div>
</div>

<!-- Scroll Progress -->
<div id="scroll-progress"></div>

<!-- Cursor personalizado -->
<div id="cursor"></div>
<div id="cursor-ring"></div>

<!-- Offcanvas backdrop -->
<div class="offcanvas-backdrop" id="backdrop" onclick="closeMenu()"></div>

<!-- Offcanvas Menu -->
<?php
if (!isset($cfg) || !is_callable($cfg)) {
    $cfg = function ($key) {
        return '';
    };
}

// Resolver a rota actual tanto no XAMPP (/portfolio-jmbenga/) como em produção (/).
$jmBasePath = defined('BASE_URL') ? (string) (parse_url(BASE_URL, PHP_URL_PATH) ?? '') : '';
$jmBasePath = rtrim($jmBasePath, '/');
$jmRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$jmRequestPath = is_string($jmRequestPath) ? $jmRequestPath : '/';

if ($jmBasePath !== '' && ($jmRequestPath === $jmBasePath || str_starts_with($jmRequestPath, $jmBasePath . '/'))) {
    $jmRequestPath = substr($jmRequestPath, strlen($jmBasePath));
}

$jmCurrentPath = '/' . trim($jmRequestPath, '/');
if ($jmCurrentPath === '/') {
    $jmCurrentPath = '/home';
}

$jmRouteIsActive = static function (array $routes) use ($jmCurrentPath): bool {
    foreach ($routes as $route) {
        $route = rtrim($route, '/') ?: '/';
        if ($jmCurrentPath === $route || ($route !== '/' && str_starts_with($jmCurrentPath, $route . '/'))) {
            return true;
        }
    }
    return false;
};
?>
<div class="offcanvas" id="offcanvas">
    <div class="offcanvas-header">
        <div class="logo">
            <!-- <div class="logo-icon"><i class="fas fa-code"></i></div> -->
            <span class="logo-accent">JM</span>benga
        </div>
        <button class="offcanvas-close" onclick="closeMenu()" aria-label="Fechar menu"><i
                class="fas fa-times"></i></button>
    </div>
    <div class="offcanvas-body">
        <a href="<?= BASE_URL ?>/home" class="offcanvas-link<?= $jmRouteIsActive(['/home']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/home']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-house"></i>Home</a>
        <a href="<?= BASE_URL ?>/about" class="offcanvas-link<?= $jmRouteIsActive(['/about']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/about']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-user-astronaut"></i>Sobre</a>
        <a href="<?= BASE_URL ?>/skills" class="offcanvas-link<?= $jmRouteIsActive(['/skills']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/skills']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-code"></i>Skills</a>
        <a href="<?= BASE_URL ?>/projects"
            class="offcanvas-link<?= $jmRouteIsActive(['/projects', '/project']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/projects', '/project']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-laptop-code"></i>Projectos</a>
        <a href="<?= BASE_URL ?>/servicos"
            class="offcanvas-link<?= $jmRouteIsActive(['/servicos', '/services']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/servicos', '/services']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-layer-group"></i>Serviços</a>
        <?php if (!empty($testimonials)): ?>
        <a href="<?= BASE_URL ?>#testimonials" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-comments"></i>Depoimentos</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/faq" class="offcanvas-link<?= $jmRouteIsActive(['/faq']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/faq']) ? 'aria-current="page"' : '' ?>><i class="fas fa-message"></i>FAQ</a>
        <a href="<?= BASE_URL ?>/contact" class="offcanvas-link<?= $jmRouteIsActive(['/contact']) ? ' active' : '' ?>"
            <?= $jmRouteIsActive(['/contact']) ? 'aria-current="page"' : '' ?> onclick="closeMenu()"><i
                class="fas fa-paper-plane"></i>Contacto</a>
    </div>
    <div class="offcanvas-social">
        <?php if ($cfg('github_url')): ?><a href="<?= e($cfg('github_url')) ?>" target="_blank"
            rel="noopener noreferrer" class="social-link" aria-label="GitHub"><i
                class="fab fa-github"></i></a><?php endif; ?>
        <?php if ($cfg('linkedin_url')): ?><a href="<?= e($cfg('linkedin_url')) ?>" target="_blank"
            rel="noopener noreferrer" class="social-link" aria-label="LinkedIn"><i
                class="fab fa-linkedin-in"></i></a><?php endif; ?>
        <?php if ($cfg('whatsapp_url')): ?><a href="<?= e($cfg('whatsapp_url')) ?>" target="_blank"
            rel="noopener noreferrer" class="social-link" aria-label="WhatsApp"><i
                class="fab fa-whatsapp"></i></a><?php endif; ?>
        <?php if ($cfg('email_contact')): ?><a href="mailto:<?= e($cfg('email_contact')) ?>" class="social-link"
            aria-label="Email"><i class="fas fa-envelope"></i></a><?php endif; ?>
    </div>
</div>

<!-- ════════ NAVBAR ════════════════════════════════════════════ -->
<header class="header" id="header">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>/home" class="logo">
            <!-- <div class="logo-icon"><i class="fas fa-code"></i></div> -->
            <span class="logo-accent">JM</span>benga
        </a>

        <nav class="nav-desktop" aria-label="Navegação principal">
            <a href="<?= BASE_URL ?>/home" class="nav-link<?= $jmRouteIsActive(['/home']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/home']) ? 'aria-current="page"' : '' ?>><i class="fas fa-house"></i>Home</a>
            <a href="<?= BASE_URL ?>/about" class="nav-link<?= $jmRouteIsActive(['/about']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/about']) ? 'aria-current="page"' : '' ?>><i class="fas fa-user"></i>Sobre</a>
            <a href="<?= BASE_URL ?>/skills" class="nav-link<?= $jmRouteIsActive(['/skills']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/skills']) ? 'aria-current="page"' : '' ?>><i class="fas fa-gears"></i>Skills</a>
            <a href="<?= BASE_URL ?>/projects"
                class="nav-link<?= $jmRouteIsActive(['/projects', '/project']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/projects', '/project']) ? 'aria-current="page"' : '' ?>><i
                    class="fas fa-briefcase"></i>Projectos</a>
            <a href="<?= BASE_URL ?>/servicos"
                class="nav-link<?= $jmRouteIsActive(['/servicos', '/services']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/servicos', '/services']) ? 'aria-current="page"' : '' ?>><i
                    class="fas fa-layer-group"></i>Serviços</a>
            <?php if (!empty($testimonials)): ?>
            <a href="<?= BASE_URL ?>#testimonials" class="nav-link"><i class="fas fa-quote-left"></i>Depoimentos</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/faq" class="nav-link<?= $jmRouteIsActive(['/faq']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/faq']) ? 'aria-current="page"' : '' ?>><i class="fas fa-message"></i>FAQ</a>
            <a href="<?= BASE_URL ?>/contact" class="nav-link<?= $jmRouteIsActive(['/contact']) ? ' active' : '' ?>"
                <?= $jmRouteIsActive(['/contact']) ? 'aria-current="page"' : '' ?>><i
                    class="fas fa-envelope"></i>Contacto</a>
        </nav>

        <div class="nav-actions">
            <a href="<?= BASE_URL ?>/contact" class="btn btn-primary nav-btn-cta">
                <i class="fas fa-paper-plane"></i>Iniciar Projecto
            </a>
            <button class="theme-toggle" id="themeToggle" title="Alternar tema" aria-label="Alternar tema">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <button class="mobile-toggle" id="menuToggle" onclick="openMenu()" aria-label="Abrir menu"
                aria-controls="offcanvas" aria-expanded="false">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </button>
        </div>
    </div>
</header>