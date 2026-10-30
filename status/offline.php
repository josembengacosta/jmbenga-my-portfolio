<?php
// JMbenga Portfolio — Página de fallback offline servida por PHP.
// Para o fallback do Service Worker, prefere offline.html: não depende do servidor PHP.
$site_name = 'JMbenga';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <title>Offline | <?= htmlspecialchars($site_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
    :root {
        --accent: #2563eb;
        --bg: #0a0d14;
        --card: #131b2e;
        --border: rgba(255, 255, 255, .09);
        --text: #e8edf5;
        --muted: #9aa9c0
    }

    * {
        box-sizing: border-box
    }

    body {
        min-height: 100vh;
        margin: 0;
        padding: 24px;
        display: grid;
        place-items: center;
        background: radial-gradient(ellipse at top, rgba(37, 99, 235, .16), transparent 55%), var(--bg);
        color: var(--text);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        text-align: center
    }

    .container {
        width: min(100%, 500px);
        padding: clamp(24px, 6vw, 40px);
        border: 1px solid var(--border);
        border-radius: 22px;
        background: var(--card);
        box-shadow: 0 24px 70px rgba(0, 0, 0, .25)
    }

    .icon {
        width: 84px;
        height: 84px;
        margin: 0 auto 22px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(37, 99, 235, .35);
        border-radius: 24px;
        background: rgba(37, 99, 235, .12);
        color: #7da5ff
    }

    .icon svg {
        width: 42px;
        height: 42px
    }

    .eyebrow {
        margin: 0 0 10px;
        color: #7da5ff;
        font-size: .75rem;
        font-weight: 750;
        letter-spacing: .14em;
        text-transform: uppercase
    }

    h1 {
        margin: 0 0 12px;
        font-size: clamp(1.7rem, 6vw, 2.2rem);
        line-height: 1.15
    }

    p {
        margin: 0 auto 22px;
        max-width: 40ch;
        color: var(--muted);
        line-height: 1.7
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 46px;
        padding: 12px 20px;
        border: 0;
        border-radius: 12px;
        background: var(--accent);
        color: #fff;
        font: inherit;
        font-weight: 650;
        cursor: pointer;
        transition: transform .2s ease, box-shadow .2s ease
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(37, 99, 235, .3)
    }

    .btn:focus-visible {
        outline: 3px solid #a9c2ff;
        outline-offset: 3px
    }

    .btn:disabled {
        cursor: wait;
        opacity: .75
    }

    .hint {
        margin: 18px 0 0;
        font-size: .8rem
    }

    .status {
        min-height: 1.3em;
        margin-top: 12px;
        font-size: .85rem;
        color: var(--muted)
    }

    @media(prefers-reduced-motion:reduce) {

        *,
        *::before,
        *::after {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important
        }
    }
    </style>
</head>

<body>
    <main class="container">
        <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M2 8.8A15.8 15.8 0 0 1 19.1 5.5M5.2 12a10.5 10.5 0 0 1 7.9-2.8M8.8 15.4a5.2 5.2 0 0 1 4.8-.4M12 20h.01" />
                <path d="M3 3l18 18" />
            </svg></div>
        <p class="eyebrow"><?= htmlspecialchars($site_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> Portfolio</p>
        <h1>Estás offline</h1>
        <p>A ligação à Internet parece estar indisponível. Algumas páginas e funcionalidades podem não funcionar até
            recuperares a ligação.</p>
        <button class="btn" id="retryButton" type="button"><svg aria-hidden="true" width="18" height="18"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M20 7v5h-5M4 17v-5h5" />
                <path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3" />
            </svg>Tentar novamente</button>
        <p class="status" id="statusMessage" role="status" aria-live="polite"></p>
        <p class="hint">Quando a ligação regressar, tenta actualizar a página.</p>
    </main>
    <script>
    (function() {
        'use strict';
        var button = document.getElementById('retryButton');
        var status = document.getElementById('statusMessage');
        if (!button || !status) return;
        button.addEventListener('click', function() {
            button.disabled = true;
            status.textContent = 'A verificar a ligação…';
            window.location.reload();
            window.setTimeout(function() {
                button.disabled = false;
                status.textContent = navigator.onLine ?
                    'A ligação pode ter regressado. Se esta página continuar, tenta novamente.' :
                    'Ainda sem ligação à Internet. Tenta novamente quando estiver disponível.';
            }, 1200);
        });
    }());
    </script>
</body>

</html>