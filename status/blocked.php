<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Página de Bloqueio (Blocked)
// ══════════════════════════════════════════════════════════════
http_response_code(403);

$site_name     = 'JMbenga';
$accent_color  = '#2563eb';
$contact_email = 'josembengadacosta@gmail.com';
$platform_ver  = '1.0';
$back_url      = 'javascript:history.back()';

// Tentar carregar da BD (se disponível)
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';

    if (isset($pdo)) {
        $configStmt = $pdo->query("SELECT config_value FROM _site_config WHERE config_key IN ('site_name','accent_color','email_contact')");
        $configs = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name     = $configs['site_name']     ?? 'JMbenga';
        $accent_color  = $configs['accent_color']  ?? '#2563eb';
        $contact_email = $configs['email_contact'] ?? 'josembengadacosta@gmail.com';

        $platformStmt = $pdo->query("SELECT version FROM _platform WHERE id_platform = 1 LIMIT 1");
        if ($platformStmt) {
            $row = $platformStmt->fetch();
            if ($row) $platform_ver = htmlspecialchars($row['version'] ?? '1.0');
        }
    }
} catch (Throwable $e) {
    // BD indisponível — usar valores padrão
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <title>Site Bloqueado — <?php echo htmlspecialchars($site_name); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap"
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
        --font-mono: 'DM Mono', monospace;
        --radius: 12px;
        --radius-lg: 20px;
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
        min-height: 100vh;
        text-align: center;
        padding: 2rem;
        transition: background .35s, color .35s;
        overflow-x: hidden;
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
        background: radial-gradient(ellipse, <?php echo htmlspecialchars($accent_color); ?>15 0%, transparent 70%);
    }

    .page-wrap {
        position: relative;
        z-index: 1;
        max-width: 560px;
        width: 100%
    }

    .blocked-icon {
        font-size: 4rem;
        color: var(--accent);
        margin-bottom: 1rem;
        opacity: .8;
        animation: iconPulse 3s ease-in-out infinite
    }

    @keyframes iconPulse {

        0%,
        100% {
            transform: scale(1);
            opacity: .8
        }

        50% {
            transform: scale(1.08);
            opacity: 1
        }
    }

    .error-code {
        font-family: var(--font-head);
        font-size: clamp(5rem, 15vw, 9rem);
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, var(--accent), #f97316);
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
            opacity: .8
        }
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent-glow);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: .35rem 1rem;
        font-family: var(--font-head);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--accent);
        margin-bottom: 1.2rem;
    }

    .badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulse-dot 1.4s ease-in-out infinite
    }

    @keyframes pulse-dot {

        0%,
        100% {
            box-shadow: 0 0 0 0 var(--accent-glow)
        }

        50% {
            box-shadow: 0 0 0 8px transparent
        }
    }

    .title {
        font-family: var(--font-head);
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 700;
        margin-bottom: .8rem;
    }

    .title span {
        color: var(--accent)
    }

    .desc {
        font-size: .92rem;
        color: var(--text-dim);
        line-height: 1.7;
        margin-bottom: 2rem;
        max-width: 420px;
        margin-left: auto;
        margin-right: auto;
    }

    .alert-box {
        display: inline-flex;
        align-items: flex-start;
        gap: 10px;
        background: rgba(239, 68, 68, .08);
        border: 1px solid rgba(239, 68, 68, .2);
        border-radius: var(--radius);
        padding: .9rem 1.2rem;
        font-size: .84rem;
        color: #f87171;
        max-width: 440px;
        margin: 0 auto 2rem;
        text-align: left;
    }

    .alert-box i {
        font-size: 1rem;
        flex-shrink: 0;
        margin-top: 1px
    }

    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 2rem
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: .65rem 1.4rem;
        border-radius: 10px;
        font-size: .88rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all .25s;
        border: 1px solid var(--border);
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
        box-shadow: 0 0 20px var(--accent-glow)
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px var(--accent-glow)
    }

    .btn-ghost {
        background: transparent;
        color: var(--text)
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent)
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
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        font-size: 1rem;
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
        <div class="fade-in blocked-icon">
            <i class="fas fa-shield-haltered"></i>
        </div>

        <div class="fade-in error-code">403</div>

        <div class="fade-in badge">
            <span class="dot"></span> Acesso Bloqueado
        </div>

        <h1 class="fade-in title">Site <span>Temporariamente Bloqueado</span></h1>

        <p class="fade-in desc">
            O acesso a este site foi temporariamente suspenso pelo administrador.
            Não se trata de um erro — o conteúdo estará novamente disponível em breve.
        </p>

        <div class="fade-in alert-box">
            <i class="fas fa-info-circle"></i>
            <span>
                Se precisares de entrar em contacto urgente, envia um email para
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>"
                    style="color:#f87171;text-decoration:underline">
                    <?php echo htmlspecialchars($contact_email); ?>
                </a>
            </span>
        </div>

        <div class="fade-in actions">
            <a href="<?php echo isset($back_url) ? $back_url : '/'; ?>" class="btn btn-primary">
                <i class="fas fa-home"></i> Ir para a Página Inicial
            </a>
            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="btn btn-ghost">
                <i class="fas fa-envelope"></i> Contactar Admin
            </a>
        </div>

        <p class="fade-in footer-text">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?> &nbsp;·&nbsp;
            v<?php echo $platform_ver; ?>
        </p>
    </div>

    <script>
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
        if (toggle) toggle.addEventListener('click', function() {
            apply(html.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    })();

    // ── Polling: verificar se o bloqueio foi levantado ───────────
    (function() {
        var CHECK_INTERVAL = 10000; // 10 segundos
        var HOME_URL = '<?php echo isset($back_url) ? $back_url : "/"; ?>';

        function checkStatus() {
            fetch(HOME_URL, {
                    method: 'HEAD',
                    redirect: 'follow'
                })
                .then(function(response) {
                    // Se a resposta for bem-sucedida e não for a página de bloqueio,
                    // significa que o bloqueio foi levantado.
                    if (response.ok && response.url.indexOf('/status/blocked') === -1) {
                        window.location.href = HOME_URL;
                    }
                })
                .catch(function() {
                    // Em caso de erro de rede, não faz nada e tenta novamente depois
                });
        }

        setInterval(checkStatus, CHECK_INTERVAL);
    })();
    </script>
</body>

</html>