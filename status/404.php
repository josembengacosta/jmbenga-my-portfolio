<?php
// ══════════════════════════════════════════════════════
// JMbenga Portfolio — Página 404
// ══════════════════════════════════════════════════════
http_response_code(404);

// Tentar carregar configs da BD (se falhar, usar defaults)
$site_name     = 'JMbenga';
$accent_color  = '#2563eb';
$contact_email = 'josembengadacosta@gmail.com';
$is_logged_in  = false;

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT config_value FROM _site_config WHERE config_key IN ('site_name','accent_color','email_contact')");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name     = $configs['site_name']     ?? 'JMbenga';
        $accent_color  = $configs['accent_color']  ?? '#2563eb';
        $contact_email = $configs['email_contact'] ?? 'josembengadacosta@gmail.com';
    }
} catch (Throwable $e) {
    // BD indisponível — usar defaults
}

$back_url = BASE_URL ?? '/';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <title>404 — Página Não Encontrada | <?php echo htmlspecialchars($site_name); ?></title>

    <!-- Fonts mínimas (apenas as necessárias) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
    :root {
        --accent: <?php echo htmlspecialchars($accent_color);
        ?>;
        --accent-glow: <?php echo htmlspecialchars($accent_color);
        ?>33;
        --bg: #0a0d14;
        --bg-2: #0f1623;
        --bg-card: #131b2e;
        --border: rgba(255, 255, 255, .06);
        --text: #e8edf5;
        --text-dim: #8899b4;
        --text-muted: #4a5878;
        --font-head: 'Syne', sans-serif;
        --font-body: 'DM Sans', sans-serif;
        --radius: 12px;
        --radius-lg: 20px;
        --nav-h: 70px;
    }

    [data-theme="light"] {
        --bg: #f0f4fb;
        --bg-2: #e4eaf6;
        --bg-card: #ffffff;
        --border: rgba(0, 0, 0, .07);
        --text: #0f172a;
        --text-dim: #475569;
        --text-muted: #94a3b8;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0
    }

    html,
    body {
        height: 100%
    }

    body {
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow-x: hidden;
        transition: background .35s, color .35s;
        text-align: center;
        padding: 2rem;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background-image: radial-gradient(circle, var(--border) 1px, transparent 1px);
        background-size: 32px 32px;
    }

    body::after {
        content: '';
        position: fixed;
        top: -20%;
        left: 50%;
        transform: translateX(-50%);
        width: 600px;
        height: 400px;
        z-index: 0;
        pointer-events: none;
        background: radial-gradient(ellipse, var(--accent-glow) 0%, transparent 70%);
    }

    .page-wrap {
        position: relative;
        z-index: 1;
        max-width: 600px;
        width: 100%
    }

    .error-code {
        font-family: var(--font-head);
        font-size: clamp(6rem, 15vw, 10rem);
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, var(--accent), #a78bfa);
        background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: .3rem;
        animation: pulseGlow 4s ease-in-out infinite;
    }

    @keyframes pulseGlow {

        0%,
        100% {
            opacity: 1
        }

        50% {
            opacity: .7
        }
    }

    .error-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent-glow);
        border: 1px solid var(--border);
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
        color: var(--accent)
    }

    .error-desc {
        font-size: .92rem;
        color: var(--text-dim);
        line-height: 1.7;
        margin-bottom: 2rem;
        max-width: 460px;
        margin-left: auto;
        margin-right: auto
    }

    .action-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 2rem
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: .7rem 1.5rem;
        border-radius: var(--radius);
        font-family: var(--font-body);
        font-size: .88rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all .25s;
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 0 20px var(--accent-glow)
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px var(--accent-glow)
    }

    .btn-ghost {
        background: transparent;
        color: var(--text);
        border: 1px solid var(--border)
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px)
    }

    .quick-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 2rem
    }

    .quick-link {
        font-size: .78rem;
        color: var(--text-muted);
        text-decoration: none;
        padding: .3rem .8rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        transition: all .2s
    }

    .quick-link:hover {
        color: var(--accent);
        border-color: var(--accent);
        background: var(--accent-glow)
    }

    .footer-text {
        font-size: .72rem;
        color: var(--text-muted);
        margin-top: 2rem
    }

    .footer-text a {
        color: var(--accent);
        text-decoration: none
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
        transition: all .25s
    }

    .theme-toggle:hover {
        border-color: var(--accent);
        color: var(--accent)
    }

    .fade-in {
        opacity: 0;
        animation: fadeUp .5s ease forwards
    }

    .fade-in:nth-child(1) {
        animation-delay: .05s
    }

    .fade-in:nth-child(2) {
        animation-delay: .13s
    }

    .fade-in:nth-child(3) {
        animation-delay: .21s
    }

    .fade-in:nth-child(4) {
        animation-delay: .29s
    }

    .fade-in:nth-child(5) {
        animation-delay: .37s
    }

    .fade-in:nth-child(6) {
        animation-delay: .45s
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(18px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }
    </style>
</head>

<body>

    <button class="theme-toggle" id="themeToggle" title="Alternar tema" aria-label="Alternar tema">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="page-wrap">
        <div class="fade-in error-code">404</div>
        <div class="fade-in error-badge"><i class="fas fa-exclamation-circle"></i> HTTP 404 · Não Encontrado</div>
        <h1 class="fade-in error-title">Página <span>Não Encontrada</span></h1>
        <p class="fade-in error-desc">
            A página que procuras não existe, foi movida ou o endereço foi digitado incorrectamente.
            Verifica o URL ou usa uma das opções abaixo para continuar.
        </p>
        <div class="fade-in action-row">
            <a href="<?php echo $back_url; ?>" class="btn btn-primary"><i class="fas fa-house"></i> Página Inicial</a>
            <button class="btn btn-ghost" onclick="history.back()"><i class="fas fa-arrow-left"></i> Voltar
                Atrás</button>
        </div>
        <div class="fade-in quick-links">
            <a href="<?php echo $back_url; ?>/home" class="quick-link">Home</a>
            <a href="<?php echo $back_url; ?>/about" class="quick-link">Sobre</a>
            <a href="<?php echo $back_url; ?>/projects" class="quick-link">Projectos</a>
            <a href="<?php echo $back_url; ?>/contact" class="quick-link">Contacto</a>
        </div>
        <p class="fade-in footer-text">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?> ·
            <a
                href="mailto:<?php echo htmlspecialchars($contact_email); ?>"><?php echo htmlspecialchars($contact_email); ?></a>
        </p>
    </div>

    <script>
    // Tema
    (function() {
        var html = document.documentElement;
        var toggle = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var saved = localStorage.getItem('jm_theme') || 'dark';

        function apply(t) {
            html.dataset.theme = t;
            if (icon) icon.className = t === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            localStorage.setItem('jm_theme', t);
        }
        apply(saved);
        toggle.addEventListener('click', function() {
            apply(html.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    })();
    </script>
</body>

</html>