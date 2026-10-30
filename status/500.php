<?php
// JMbenga Portfolio — Página 500 (Erro Interno do Servidor)
http_response_code(500);

// Esta página deve funcionar mesmo quando a configuração ou a base de dados falha.
$site_name = 'JMbenga';
$contact_email = 'josembengadacosta@gmail.com';

// Calcula o prefixo da aplicação a partir do caminho do próprio ErrorDocument:
// /status/500.php -> "" (produção na raiz)
// /portfolio-jmbenga/status/500.php -> "/portfolio-jmbenga" (XAMPP local)
$script_name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/status/500.php'));
$parent_path = dirname(dirname($script_name));
$base_url = ($parent_path === '/' || $parent_path === '.' || $parent_path === '\\')
    ? ''
    : '/' . trim($parent_path, '/');

$buildUrl = static function (string $path) use ($base_url): string {
    return $base_url . '/' . ltrim($path, '/');
};
$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$home_url = $buildUrl('home');
$about_url = $buildUrl('about');
$services_url = $buildUrl('servicos');
$contact_url = $buildUrl('contact');
$icon_url = $buildUrl('assets/img/icons/favicon.ico');
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <link rel="shortcut icon" href="<?= $e($icon_url) ?>">
    <title>500 — Erro Interno do Servidor | <?= $e($site_name) ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --accent: #ef4444;
        --accent-glow: color-mix(in srgb, var(--accent) 20%, transparent);
        --bg: #0a0d14;
        --bg-2: #0f1623;
        --bg-card: #131b2e;
        --border: rgba(255, 255, 255, .08);
        --text: #e8edf5;
        --text-dim: #8899b4;
        --text-muted: #71809d;
        --font-head: 'Syne', sans-serif;
        --font-body: 'DM Sans', sans-serif;
        --radius: 12px;
    }

    [data-theme="light"] {
        --bg: #f0f4fb;
        --bg-2: #e4eaf6;
        --bg-card: #fff;
        --border: rgba(0, 0, 0, .09);
        --text: #0f172a;
        --text-dim: #475569;
        --text-muted: #718096;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    html,
    body {
        min-height: 100%;
    }

    body {
        min-height: 100vh;
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2rem;
        transition: background .35s, color .35s;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background-image: radial-gradient(circle, var(--border) 1px, transparent 1px);
        background-size: 32px 32px;
        pointer-events: none;
    }

    body::after {
        content: '';
        position: fixed;
        top: -20%;
        left: 50%;
        transform: translateX(-50%);
        width: min(600px, 100vw);
        height: 400px;
        background: radial-gradient(ellipse, var(--accent-glow) 0%, transparent 70%);
        pointer-events: none;
    }

    .page-wrap {
        position: relative;
        z-index: 1;
        max-width: 600px;
        width: 100%;
    }

    .error-code {
        font-family: var(--font-head);
        font-size: clamp(6rem, 15vw, 10rem);
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, var(--accent), #f97316);
        background-clip: text;
        -webkit-background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
        margin-bottom: .3rem;
        animation: pulseGlow 4s ease-in-out infinite;
    }

    @keyframes pulseGlow {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: .72;
        }
    }

    .error-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: color-mix(in srgb, var(--accent) 12%, transparent);
        border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
        border-radius: 8px;
        padding: .3rem .8rem;
        font-family: var(--font-head);
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--accent);
        margin-bottom: 1rem;
    }

    .error-title {
        font-family: var(--font-head);
        font-size: clamp(1.2rem, 3vw, 1.8rem);
        font-weight: 700;
        margin-bottom: .8rem;
    }

    .error-title span {
        color: var(--accent);
    }

    .error-desc {
        font-size: .92rem;
        color: var(--text-dim);
        line-height: 1.7;
        margin: 0 auto 2rem;
        max-width: 460px;
    }

    .action-row,
    .quick-links {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-row {
        margin-bottom: 2rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: .7rem 1.5rem;
        border-radius: var(--radius);
        font-size: .88rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: transform .25s, border-color .25s, color .25s, box-shadow .25s;
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 0 20px var(--accent-glow);
        border: 1px solid transparent;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px var(--accent-glow);
    }

    .btn-ghost {
        background: transparent;
        color: var(--text);
        border: 1px solid var(--border);
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }

    .quick-links {
        gap: 8px;
        margin-bottom: 2rem;
    }

    .quick-link {
        font-size: .78rem;
        color: var(--text-muted);
        text-decoration: none;
        padding: .3rem .8rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        transition: all .2s;
    }

    .quick-link:hover {
        color: var(--accent);
        border-color: var(--accent);
        background: var(--accent-glow);
    }

    .footer-text {
        font-size: .72rem;
        color: var(--text-muted);
        margin-top: 2rem;
    }

    .footer-text a {
        color: var(--accent);
        text-decoration: none;
    }

    .theme-toggle {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 38px;
        height: 38px;
        border-radius: 9px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        font-size: .95rem;
        transition: all .25s;
    }

    .theme-toggle:hover {
        border-color: var(--accent);
        color: var(--accent);
    }

    .fade-in {
        opacity: 0;
        animation: fadeUp .5s ease forwards;
    }

    .fade-in:nth-child(1) {
        animation-delay: .05s;
    }

    .fade-in:nth-child(2) {
        animation-delay: .13s;
    }

    .fade-in:nth-child(3) {
        animation-delay: .21s;
    }

    .fade-in:nth-child(4) {
        animation-delay: .29s;
    }

    .fade-in:nth-child(5) {
        animation-delay: .37s;
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(18px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 1.25rem;
        }

        .action-row {
            gap: 8px;
        }

        .btn {
            padding: .7rem 1rem;
        }
    }
    </style>
</head>

<body>
    <button class="theme-toggle" id="themeToggle" type="button" title="Alternar tema" aria-label="Alternar tema">
        <i class="fas fa-moon" id="themeIcon" aria-hidden="true"></i>
    </button>
    <main class="page-wrap">
        <div class="fade-in error-code" aria-label="Erro 500">500</div>
        <div class="fade-in error-badge"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Erro Interno do
            Servidor</div>
        <h1 class="fade-in error-title">Erro Interno do <span>Servidor</span></h1>
        <p class="fade-in error-desc">Ocorreu um problema inesperado no servidor. Tenta novamente dentro de instantes.
            Se o problema persistir, contacta-nos.</p>
        <div class="fade-in action-row">
            <a href="<?= $e($home_url) ?>" class="btn btn-primary"><i class="fas fa-house" aria-hidden="true"></i>
                Página Inicial</a>
            <button class="btn btn-ghost" type="button" id="retryButton"><i class="fas fa-rotate-right"
                    aria-hidden="true"></i> Tentar novamente</button>
        </div>
        <nav class="fade-in quick-links" aria-label="Ligações rápidas">
            <a href="<?= $e($home_url) ?>" class="quick-link">Home</a>
            <a href="<?= $e($about_url) ?>" class="quick-link">Sobre</a>
            <a href="<?= $e($services_url) ?>" class="quick-link">Serviços</a>
            <a href="<?= $e($contact_url) ?>" class="quick-link">Contacto</a>
        </nav>
        <p class="fade-in footer-text">&copy; <?= date('Y') ?> <?= $e($site_name) ?> · Se o erro persistir, <a
                href="mailto:<?= $e($contact_email) ?>">contacta-nos</a>.</p>
    </main>
    <script>
    (function() {
        'use strict';
        var html = document.documentElement;
        var toggle = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var retryButton = document.getElementById('retryButton');
        var saved = 'dark';

        try {
            var stored = localStorage.getItem('jm_theme');
            if (stored === 'dark' || stored === 'light') saved = stored;
        } catch (e) {
            // O armazenamento pode estar bloqueado; a página continua funcional.
        }

        function applyTheme(theme) {
            var safeTheme = theme === 'light' ? 'light' : 'dark';
            html.setAttribute('data-theme', safeTheme);
            if (icon) icon.className = safeTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            if (toggle) toggle.setAttribute('aria-label', safeTheme === 'dark' ? 'Ativar tema claro' :
                'Ativar tema escuro');
            try {
                localStorage.setItem('jm_theme', safeTheme);
            } catch (e) {}
        }

        applyTheme(saved);
        if (toggle) {
            toggle.addEventListener('click', function() {
                applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
            });
        }
        if (retryButton) {
            retryButton.addEventListener('click', function() {
                window.location.reload();
            });
        }
    }());
    </script>
</body>

</html>