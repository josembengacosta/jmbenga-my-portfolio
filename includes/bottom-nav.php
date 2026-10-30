<?php
// Navegação inferior: rotas normalizadas para funcionar no XAMPP e em produção.
$bottomBase = rtrim((string) BASE_URL, '/');
$bottomSection = strtolower((string) ($page_section ?? ''));
$bottomPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$bottomPath = is_string($bottomPath) ? $bottomPath : '/';
$bottomBasePath = parse_url($bottomBase, PHP_URL_PATH);
$bottomBasePath = is_string($bottomBasePath) ? rtrim($bottomBasePath, '/') : '';

if (
    $bottomBasePath !== ''
    && ($bottomPath === $bottomBasePath || str_starts_with($bottomPath, $bottomBasePath . '/'))
) {
    $bottomPath = substr($bottomPath, strlen($bottomBasePath));
}
$bottomPath = '/' . ltrim($bottomPath, '/');
$bottomPath = rtrim($bottomPath, '/') ?: '/';

$isBottomHome = $bottomSection === 'home' || $bottomPath === '/' || $bottomPath === '/home';
$isBottomAbout = $bottomSection === 'about' || $bottomPath === '/about';
$isBottomProjects = $bottomSection === 'projects'
    || $bottomPath === '/projects'
    || str_starts_with($bottomPath, '/project/');
$isBottomServices = in_array($bottomSection, [
    'services', 'servicos', 'web-development', 'mobile-apps',
    'api-backend', 'ui-ux-design', 'maintenance'
], true)
    || $bottomPath === '/servicos'
    || str_starts_with($bottomPath, '/servicos/')
    || str_starts_with($bottomPath, '/services/');
$isBottomContact = $bottomSection === 'contact' || $bottomPath === '/contact';
?>
<nav class="bottom-nav" id="bottomNav" aria-label="Navegação principal móvel">
    <a href="<?= e($bottomBase) ?>/home" class="bottom-nav-item <?= $isBottomHome ? 'active' : '' ?>"
        <?= $isBottomHome ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-house" aria-hidden="true"></i><span>Home</span>
    </a>
    <a href="<?= e($bottomBase) ?>/about" class="bottom-nav-item <?= $isBottomAbout ? 'active' : '' ?>"
        <?= $isBottomAbout ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-user" aria-hidden="true"></i><span>Sobre</span>
    </a>
    <a href="<?= e($bottomBase) ?>/servicos" class="bottom-nav-item <?= $isBottomServices ? 'active' : '' ?>"
        <?= $isBottomServices ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-layer-group" aria-hidden="true"></i><span>Serviços</span>
    </a>
    <a href="<?= e($bottomBase) ?>/projects" class="bottom-nav-item <?= $isBottomProjects ? 'active' : '' ?>"
        <?= $isBottomProjects ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-briefcase" aria-hidden="true"></i><span>Projectos</span>
    </a>
    <a href="<?= e($bottomBase) ?>/contact" class="bottom-nav-item <?= $isBottomContact ? 'active' : '' ?>"
        <?= $isBottomContact ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-envelope" aria-hidden="true"></i><span>Contacto</span>
    </a>
</nav>