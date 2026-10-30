<?php
// JMbenga Portfolio — Página 503 (Serviço Temporariamente Indisponível)
http_response_code(503);
header('Retry-After: 3600'); // Recomenda uma nova tentativa após 1 hora.

// Esta página foi concebida para funcionar mesmo quando a base de dados ou
// os ficheiros de configuração da aplicação estão indisponíveis.
$site_name = 'JMbenga';

// Calcula o prefixo da aplicação a partir do caminho do próprio script:
// /status/503.php -> "" (produção na raiz)
// /portfolio-jmbenga/status/503.php -> "/portfolio-jmbenga" (XAMPP local)
$script_name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/status/503.php'));
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
    <title>503 — Serviço Indisponível | <?= $e($site_name) ?></title>
    <link rel="shortcut icon" href="<?= $e($icon_url) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --accent: #eab308;
        --accent-glow: rgba(234, 179, 8, .20);
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
        max-width: 620px;
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
        background: rgba(234, 179, 8, .12);
        border: 1px solid rgba(234, 179, 8, .25);
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
        margin: 0 auto 1.5rem;
        max-width: 480px;
    }

    .countdown {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        background: rgba(234, 179, 8, .08);
        border: 1px solid rgba(234, 179, 8, .2);
        border-radius: var(--radius);
        padding: .7rem 1.2rem;
        font-size: .9rem;
        color: var(--accent);
        margin-bottom: 1.5rem;
    }

    .countdown strong {
        font-variant-numeric: tabular-nums;
    }

    .action-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 1.5rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: .7rem 1.5rem;
        border-radius: var(--radius);
        font-size: .88rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: transform .25s, box-shadow .25s, border-color .25s, color .25s;
        border: 1px solid transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: #111827;
        box-shadow: 0 0 20px rgba(234, 179, 8, .25);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(234, 179, 8, .35);
    }

    .btn-ghost {
        background: transparent;
        color: var(--text);
        border-color: var(--border);
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }

    .quick-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .quick-link {
        font-size: .78rem;
        color: var(--text-muted);
        text-decoration: none;
        padding: .3rem .8rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        transition: color .2s, border-color .2s, background .2s;
    }

    .quick-link:hover {
        color: var(--accent);
        border-color: var(--accent);
        background: rgba(234, 179, 8, .08);
    }

    .footer-text {
        font-size: .72rem;
        color: var(--text-muted);
        margin-top: 1.5rem;
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
            scroll-behavior: auto !important;
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
        }

        .fade-in {
            opacity: 1;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 1.25rem;
        }

        .action-row {
            flex-direction: column;
            align-items: stretch;
        }

        .btn {
            width: 100%;
        }
    }
    </style>
</head>

<body>
    <button class="theme-toggle" id="themeToggle" type="button" title="Alternar tema"
        aria-label="Alternar tema claro/escuro">
        <i class="fas fa-moon" id="themeIcon" aria-hidden="true"></i>
    </button>
    <main class="page-wrap">
        <div class="fade-in error-code" aria-label="Erro 503">503</div>
        <div class="fade-in error-badge"><i class="fas fa-tools" aria-hidden="true"></i> Manutenção</div>
        <h1 class="fade-in error-title">Serviço <span>Temporariamente</span> Indisponível</h1>
        <p class="fade-in error-desc">
            Estamos a realizar manutenção ou melhorias. O serviço deverá voltar a estar disponível mais tarde.
            Podes tentar novamente manualmente a qualquer momento.
        </p>
        <div class="fade-in countdown" role="status" aria-live="polite">
            <i class="fas fa-clock" aria-hidden="true"></i>
            <span>Nova tentativa automática em</span>
            <strong id="countdown">60:00</strong>
        </div>
        <div class="fade-in action-row">
            <button class="btn btn-primary" id="retryButton" type="button" onclick="location.reload()">
                <i class="fas fa-redo" aria-hidden="true"></i> Tentar Agora
            </button>
            <a href="<?= $e($home_url) ?>" class="btn btn-ghost"><i class="fas fa-house" aria-hidden="true"></i> Página
                Inicial</a>
        </div>
        <nav class="fade-in quick-links" aria-label="Ligações úteis">
            <a href="<?= $e($about_url) ?>" class="quick-link">Sobre</a>
            <a href="<?= $e($services_url) ?>" class="quick-link">Serviços</a>
            <a href="<?= $e($contact_url) ?>" class="quick-link">Contacto</a>
        </nav>
        <p class="fade-in footer-text">&copy; <?= date('Y') ?> <?= $e($site_name) ?> · Luanda, Angola</p>
    </main>
    <script>
    (function() {
        'use strict';

        // Retry-After é de 3600 segundos; a tentativa automática respeita a mesma janela.
        var remaining = 3600;
        var countdown = document.getElementById('countdown');
        var intervalId = null;

        function formatTime(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = seconds % 60;
            return (hours > 0 ? String(hours).padStart(2, '0') + ':' : '') +
                String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }

        if (countdown) {
            countdown.textContent = formatTime(remaining);
            intervalId = window.setInterval(function() {
                remaining -= 1;
                if (countdown) countdown.textContent = formatTime(Math.max(remaining, 0));
                if (remaining <= 0) {
                    if (intervalId !== null) window.clearInterval(intervalId);
                    window.location.reload();
                }
            }, 1000);
        }

        var html = document.documentElement;
        var toggle = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var saved = 'dark';

        try {
            var storedTheme = window.localStorage.getItem('jm_theme');
            if (storedTheme === 'dark' || storedTheme === 'light') saved = storedTheme;
        } catch (error) {
            // O armazenamento pode estar bloqueado; o tema padrão continua disponível.
        }

        function applyTheme(theme) {
            var safeTheme = theme === 'light' ? 'light' : 'dark';
            html.setAttribute('data-theme', safeTheme);
            if (icon) icon.className = safeTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            try {
                window.localStorage.setItem('jm_theme', safeTheme);
            } catch (error) {
                // Não impedir a página de funcionar se localStorage estiver indisponível.
            }
        }

        applyTheme(saved);
        if (toggle) {
            toggle.addEventListener('click', function() {
                applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
            });
        }
    })();
    </script>
</body>

</html>