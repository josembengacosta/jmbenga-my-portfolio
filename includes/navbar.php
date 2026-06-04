<!-- Loading -->
<div id="loading">
    <div class="loader-logo">Carregando<span style="color:var(--accent)">.</span></div>
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
<div class="offcanvas" id="offcanvas">
    <div class="offcanvas-header">
        <div class="logo">
            <!-- <div class="logo-icon"><i class="fas fa-code"></i></div> -->
            <span class="logo-accent">JM</span>benga
        </div>
        <button class="offcanvas-close" onclick="closeMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="offcanvas-body">
        <a href="<?= BASE_URL ?>/home" class="offcanvas-link" onclick="closeMenu()"><i class="fas fa-house"></i>Home</a>
        <a href="<?= BASE_URL ?>/about" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-user-astronaut"></i>Sobre</a>
        <a href="<?= BASE_URL ?>/skills" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-code"></i>Skills</a>
        <a href="<?= BASE_URL ?>/projects" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-laptop-code"></i>Projectos</a>
        <?php if (!empty($testimonials)): ?>
        <a href="<?= BASE_URL ?>#testimonials" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-comments"></i>Depoimentos</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/faq" class="offcanvas-link"><i class="fas fa-message"></i>FAQ</a>
        <a href="<?= BASE_URL ?>/contact" class="offcanvas-link" onclick="closeMenu()"><i
                class="fas fa-paper-plane"></i>Contacto</a>
    </div>
    <div class="offcanvas-social">
        <?php if ($cfg('github_url')):   ?><a href="<?= e($cfg('github_url')) ?>" target="_blank" class="social-link"><i
                class="fab fa-github"></i></a><?php endif; ?>
        <?php if ($cfg('linkedin_url')): ?><a href="<?= e($cfg('linkedin_url')) ?>" target="_blank"
            class="social-link"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
        <?php if ($cfg('whatsapp_url')): ?><a href="<?= e($cfg('whatsapp_url')) ?>" target="_blank"
            class="social-link"><i class="fab fa-whatsapp"></i></a><?php endif; ?>
        <a href="mailto:<?= e($cfg('email_contact')) ?>" class="social-link"><i class="fas fa-envelope"></i></a>
    </div>
</div>

<!-- ════════ NAVBAR ════════════════════════════════════════════ -->
<header class="header" id="header">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>/home" class="logo">
            <!-- <div class="logo-icon"><i class="fas fa-code"></i></div> -->
            <span class="logo-accent">JM</span>benga
        </a>

        <nav class="nav-desktop">
            <a href="<?= BASE_URL ?>/home" class="nav-link active"><i class="fas fa-house"></i>Home</a>
            <a href="<?= BASE_URL ?>/about" class="nav-link"><i class="fas fa-user"></i>Sobre</a>
            <a href="<?= BASE_URL ?>/skills" class="nav-link"><i class="fas fa-gears"></i>Skills</a>
            <a href="<?= BASE_URL ?>/projects" class="nav-link"><i class="fas fa-briefcase"></i>Projectos</a>
            <?php if (!empty($testimonials)): ?>
            <a href="<?= BASE_URL ?>#testimonials" class="nav-link"><i class="fas fa-quote-left"></i>Depoimentos</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/faq" class="nav-link"><i class="fas fa-message"></i>FAQ</a>
            <a href="<?= BASE_URL ?>/contact" class="nav-link"><i class="fas fa-envelope"></i>Contacto</a>
        </nav>

        <div class="nav-actions">
            <a href="<?= BASE_URL ?>/contact" class="btn btn-primary nav-btn-cta">
                <i class="fas fa-paper-plane"></i>Iniciar Projecto
            </a>
            <button class="theme-toggle" id="themeToggle" title="Alternar tema">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <button class="mobile-toggle" id="menuToggle" onclick="openMenu()" aria-label="Abrir menu">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </button>
        </div>
    </div>
</header>