<?php
// JMbenga Portfolio — Página 403 (Acesso Proibido)
http_response_code(403);

$site_name = 'JMbenga';
$accent_color = '#f97316';
$contact_email = 'josembengadacosta@gmail.com';
$base_url = '';

try {
    require_once __DIR__ . '/../includes/config.php';

    if (defined('BASE_URL')) {
        $base_url = rtrim((string) BASE_URL, '/');
    }

    // A página de erro não inclui db.php: esse ficheiro pode redirecionar
    // ou terminar a execução quando a base de dados não está disponível.
    // Tentamos obter as opções do site apenas se a configuração DB existir.
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT config_key, config_value FROM _site_config WHERE config_key IN ('site_name','accent_color','email_contact')");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $dbPassword = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : '');
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $errorPdo = new PDO($dsn, DB_USER, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_KEY_PAIR,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $stmt = $errorPdo->query("SELECT config_key, config_value FROM _site_config WHERE config_key IN ('site_name','accent_color','email_contact')");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $configs = [];
    }

    $site_name = $configs['site_name'] ?? $site_name;
    $accent_color = $configs['accent_color'] ?? $accent_color;
    $contact_email = $configs['email_contact'] ?? $contact_email;
} catch (Throwable $e) {
    // A falha da base de dados não deve impedir a apresentação da página 403.
    error_log('JMbenga 403 page: configuração dinâmica indisponível (' . get_class($e) . ').');
}

// Validação de valores que serão usados em HTML/CSS.
if (!is_string($accent_color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $accent_color)) {
    $accent_color = '#f97316';
}
if (!is_string($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $contact_email = 'josembengadacosta@gmail.com';
}
if (!is_string($site_name) || trim($site_name) === '') {
    $site_name = 'JMbenga';
}

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
    <title>403 — Acesso Proibido | <?= $e($site_name) ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --accent: <?=$accent_color ?>;
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
        background: linear-gradient(135deg, var(--accent), #ef4444);
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
        <div class="fade-in error-code" aria-label="Erro 403">403</div>
        <div class="fade-in error-badge"><i class="fas fa-shield-halved" aria-hidden="true"></i> Acesso Proibido</div>
        <h1 class="fade-in error-title">Acesso <span>Negado</span></h1>
        <p class="fade-in error-desc">Não tens permissão para aceder a este recurso. Se acreditas que isto é um erro,
            contacta-nos.</p>
        <div class="fade-in action-row">
            <a href="<?= $e($home_url) ?>" class="btn btn-primary"><i class="fas fa-house" aria-hidden="true"></i>
                Página Inicial</a>
            <button class="btn btn-ghost" type="button" id="backButton"><i class="fas fa-arrow-left"
                    aria-hidden="true"></i> Voltar</button>
        </div>
        <nav class="fade-in quick-links" aria-label="Ligações rápidas">
            <a href="<?= $e($home_url) ?>" class="quick-link">Home</a>
            <a href="<?= $e($about_url) ?>" class="quick-link">Sobre</a>
            <a href="<?= $e($services_url) ?>" class="quick-link">Serviços</a>
            <a href="<?= $e($contact_url) ?>" class="quick-link">Contacto</a>
        </nav>
        <p class="fade-in footer-text">&copy; <?= date('Y') ?> <?= $e($site_name) ?> · <a
                href="mailto:<?= $e($contact_email) ?>">Contactar</a></p>
    </main>
    <script>
    (function() {
        'use strict';
        var html = document.documentElement;
        var toggle = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var backButton = document.getElementById('backButton');
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
        if (backButton) {
            backButton.addEventListener('click', function() {
                if (window.history.length > 1) window.history.back();
                else window.location.href =
                    <?= json_encode($home_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            });
        }
    }());
    </script>
</body>

</html>