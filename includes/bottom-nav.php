<!-- Bottom Mobile Navigation -->
<nav class="bottom-nav" id="bottomNav">
    <a href="<?= BASE_URL ?>/home" class="bottom-nav-item <?= $page_section === 'home' ? 'active' : '' ?>">
        <i class="fas fa-house"></i><span>Home</span>
    </a>
    <a href="<?= BASE_URL ?>/about" class="bottom-nav-item <?= $page_section === 'about' ? 'active' : '' ?>">
        <i class="fas fa-user"></i><span>Sobre</span>
    </a>
    <a href="<?= BASE_URL ?>/projects" class="bottom-nav-item <?= $page_section === 'projects' ? 'active' : '' ?>">
        <i class="fas fa-briefcase"></i><span>Projectos</span>
    </a>
    <a href="<?= BASE_URL ?>/contact" class="bottom-nav-item <?= $page_section === 'contact' ? 'active' : '' ?>">
        <i class="fas fa-envelope"></i><span>Contacto</span>
    </a>
</nav>