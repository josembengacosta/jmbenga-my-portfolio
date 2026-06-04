<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Lockscreen (Refactored)
// ══════════════════════════════════════════════════════════════
declare(strict_types=1);

require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

if (empty($_SESSION['admin_id'])) {
    redirect('/jm-panel/entrar');
}

if (empty($_SESSION['admin_lockscreen'])) {
    redirect('/jm-panel/home');
}

$db = $GLOBALS['pdo'];
$adminId = (int) $_SESSION['admin_id'];

$stmt = $db->prepare("
    SELECT e.first_name, e.second_name, e.email_employees, e.photo_employees, e.status_employees,
           s.access_code, s.lockscreen
    FROM _employees e
    LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
    WHERE e.id_employees = ?
    LIMIT 1
");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin || $admin['status_employees'] !== 'active') {
    logoutAdmin();
    redirect('/jm-panel/entrar');
}

$adminName    = trim(($admin['first_name'] ?? '') . ' ' . ($admin['second_name'] ?? ''));
$adminEmail   = $admin['email_employees'] ?? '';
$adminPhoto   = null;
$adminInitial = strtoupper(mb_substr((string)($admin['first_name'] ?? ''), 0, 1));

// Segurança: validar nome do arquivo antes de usar em path
if (!empty($admin['photo_employees']) && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $admin['photo_employees'])) {
    $photoPath = ROOT_PATH . '/assets/img/profile/' . $admin['photo_employees'];
    if (is_file($photoPath) && is_readable($photoPath)) {
        $adminPhoto = $admin['photo_employees'];
    }
}

$error = $_GET['error'] ?? '';
$clientIp = getRealClientIp();
$osInfo = parseOSFromUA($_SERVER['HTTP_USER_AGENT'] ?? '');
$loginTime = $_SESSION['admin_login_time'] ?? time();
$sessionMins = max(0, (int) floor((time() - $loginTime) / 60));

// Base URL seguro para JS
$baseUrlJson = json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$csrfToken = $_SESSION['admin_csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title>Bloqueado — Painel JMbenga</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --accent: #2563eb;
        --accent-glow: rgba(37, 99, 235, .2);
        --bg: #080b11;
        --bg-card: #111827;
        --border: rgba(255, 255, 255, .07);
        --text: #f1f5f9;
        --text-dim: #94a3b8;
        --text-muted: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --font-head: 'Syne', sans-serif;
        --font-body: 'DM Sans', sans-serif;
        --font-mono: 'DM Mono', monospace;
        --radius: 12px;
        --radius-lg: 20px;
        --ease: cubic-bezier(.16, 1, .3, 1);
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
        height: 100%;
    }

    body {
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 1.5rem;
        overflow: hidden;
    }

    /* Fundo animado */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        background:
            radial-gradient(circle at 20% 30%, rgba(37, 99, 235, .1) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(139, 92, 246, .1) 0%, transparent 50%);
        animation: bgMove 20s ease infinite;
        will-change: transform;
    }

    @keyframes bgMove {

        0%,
        100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(-20px, 20px);
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
        }

        body::before {
            animation: none;
        }
    }

    .lockscreen-wrapper {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
    }

    .lockscreen-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 3rem 2.5rem;
        backdrop-filter: blur(20px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, .4), 0 0 40px var(--accent-glow);
        text-align: center;
    }

    /* Avatar */
    .lock-avatar-wrap {
        position: relative;
        margin-bottom: 2rem;
        display: inline-block;
    }

    .lock-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin: 0 auto;
        border: 3px solid var(--accent);
        overflow: hidden;
        box-shadow: 0 0 30px var(--accent-glow);
    }

    .lock-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .lock-avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--accent), #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-head);
        font-size: 2.5rem;
        font-weight: 800;
        color: #fff;
    }

    .lock-icon {
        position: absolute;
        bottom: -8px;
        right: -8px;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--accent);
        border: 3px solid var(--bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: .9rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
    }

    .lock-icon svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
    }

    /* Texto */
    .lock-title {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: .25rem;
    }

    .lock-subtitle {
        color: var(--text-dim);
        font-size: .9rem;
        margin-bottom: 1.5rem;
    }

    /* Alertas */
    .alert {
        padding: .75rem 1rem;
        border-radius: var(--radius);
        margin-bottom: 1.5rem;
        font-size: .85rem;
        display: flex;
        align-items: center;
        gap: .5rem;
        text-align: left;
    }

    .alert-danger {
        background: rgba(239, 68, 68, .1);
        border: 1px solid rgba(239, 68, 68, .3);
        color: #f87171;
    }

    .alert-success {
        background: rgba(16, 185, 129, .1);
        border: 1px solid rgba(16, 185, 129, .3);
        color: #34d399;
    }

    /* Campos PIN */
    .pin-group {
        display: flex;
        justify-content: center;
        gap: .6rem;
        margin-bottom: 1.5rem;
    }

    .pin-input {
        width: 52px;
        height: 60px;
        text-align: center;
        font-family: var(--font-mono);
        font-size: 1.6rem;
        font-weight: 600;
        background: var(--bg);
        border: 2px solid var(--border);
        border-radius: 10px;
        color: var(--text);
        outline: none;
        transition: border-color .2s, box-shadow .2s, transform .2s;
        caret-color: var(--accent);
    }

    .pin-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
        transform: translateY(-2px);
    }

    .pin-input.error {
        border-color: var(--danger);
        animation: shake .4s;
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-6px);
        }

        75% {
            transform: translateX(6px);
        }
    }

    /* Botões */
    .btn-lock {
        width: 100%;
        padding: .8rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-family: var(--font-body);
        font-size: .95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
        box-shadow: 0 0 20px var(--accent-glow);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
    }

    .btn-lock:hover:not(:disabled) {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 8px 30px var(--accent-glow);
    }

    .btn-lock:disabled {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-logout {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        margin-top: 1.2rem;
        color: var(--text-muted);
        text-decoration: none;
        font-size: .82rem;
        transition: color .2s;
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
    }

    .btn-logout:hover {
        color: var(--danger);
    }

    .btn-logout svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    /* Info da sessão */
    .session-info {
        margin-top: 1.5rem;
        padding-top: 1.2rem;
        border-top: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: .35rem;
        text-align: left;
    }

    .session-info .row {
        display: flex;
        justify-content: space-between;
        font-size: .75rem;
        color: var(--text-muted);
    }

    .session-info .row span:last-child {
        color: var(--text-dim);
        font-weight: 500;
    }

    .session-info svg {
        width: 12px;
        height: 12px;
        fill: currentColor;
        margin-right: .25rem;
        vertical-align: middle;
    }

    /* Toasts */
    #toast-container {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: .5rem;
        pointer-events: none;
    }

    .toast {
        padding: .75rem 1rem;
        border-radius: var(--radius);
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--text);
        font-size: .85rem;
        display: flex;
        align-items: center;
        gap: .5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
        transition: opacity .3s, transform .3s;
        pointer-events: auto;
        min-width: 260px;
    }

    .toast-error {
        border-color: rgba(239, 68, 68, .4);
        color: #f87171;
    }

    .toast-success {
        border-color: rgba(16, 185, 129, .4);
        color: #34d399;
    }

    .toast svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
        flex-shrink: 0;
    }

    /* Skip link para acessibilidade */
    .skip-link {
        position: absolute;
        top: -40px;
        left: 0;
        background: var(--accent);
        color: #fff;
        padding: 8px;
        text-decoration: none;
        z-index: 100;
        border-radius: 0 0 4px 0;
    }

    .skip-link:focus {
        top: 0;
    }

    /* Timer de bloqueio */
    .lockout-timer {
        font-size: .8rem;
        color: var(--danger);
        margin-bottom: 1rem;
        min-height: 1.2em;
    }
    </style>
</head>

<body>

    <a href="#pinGroup" class="skip-link">Ir para código de acesso</a>

    <div class="lockscreen-wrapper">
        <div class="lockscreen-card">
            <!-- Avatar -->
            <div class="lock-avatar-wrap" aria-hidden="true">
                <div class="lock-avatar">
                    <?php if ($adminPhoto): ?>
                    <img src="<?= e(BASE_URL) ?>/assets/img/profile/<?= e($adminPhoto) ?>" alt="" loading="eager">
                    <?php else: ?>
                    <div class="lock-avatar-placeholder" aria-hidden="true"><?= e($adminInitial) ?></div>
                    <?php endif; ?>
                </div>
                <div class="lock-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                    </svg>
                </div>
            </div>

            <h1 class="lock-title"><?= e($adminName) ?></h1>
            <p class="lock-subtitle"><?= e($adminEmail) ?></p>

            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert" aria-live="assertive">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                </svg>
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <div class="lockout-timer" id="lockoutTimer" aria-live="polite"></div>

            <!-- PIN Inputs -->
            <form id="lockscreenForm" novalidate data-api="<?= e(BASE_URL) ?>/jm-panel/lockscreen-process">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="pin-group" id="pinGroup" role="group" aria-label="Código de acesso de 6 dígitos">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="one-time-code"
                        class="pin-input" aria-label="Dígito <?= $i + 1 ?> de 6" <?= $i === 0 ? 'autofocus' : '' ?>>
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn-lock" id="unlockBtn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path
                            d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6h1.9c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm0 12H6V10h12v10z" />
                    </svg>
                    Desbloquear
                </button>
            </form>

            <form action="<?= e(BASE_URL) ?>/jm-panel/logout" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn-logout">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z" />
                    </svg>
                    Terminar Sessão
                </button>
            </form>

            <!-- Info da sessão -->
            <div class="session-info" aria-label="Informações da sessão">
                <div class="row">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                        </svg>
                        Tempo de sessão
                    </span>
                    <span><?= $sessionMins > 0 ? $sessionMins . ' min' : 'Recém iniciada' ?></span>
                </div>
                <div class="row">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                        </svg>
                        IP
                    </span>
                    <span><?= e($clientIp) ?></span>
                </div>
                <div class="row">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M20 18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z" />
                        </svg>
                        Sistema
                    </span>
                    <span><?= e($osInfo) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
    (function() {
        'use strict';

        const form = document.getElementById('lockscreenForm');
        const unlockBtn = document.getElementById('unlockBtn');
        const pinInputs = document.querySelectorAll('.pin-input');
        const lockoutTimer = document.getElementById('lockoutTimer');
        const BASE_URL = <?= $baseUrlJson ?>;
        const API_URL = form.dataset.api;
        const MAX_ATTEMPTS = 3;
        const LOCKOUT_MS = 30000; // 30 segundos

        let attempts = 0;
        let lockoutEnd = 0;

        // ── Navegação entre campos PIN ────────────────────────────────
        pinInputs.forEach((input, index) => {
            // Bloquear input não numérico
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 1);
                if (this.value && index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
                updateSubmitState();
            });

            input.addEventListener('keydown', function(e) {
                // Backspace em campo vazio → voltar
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    e.preventDefault();
                    pinInputs[index - 1].focus();
                    return;
                }
                // Setas de navegação
                if (e.key === 'ArrowLeft' && index > 0) {
                    e.preventDefault();
                    pinInputs[index - 1].focus();
                }
                if (e.key === 'ArrowRight' && index < pinInputs.length - 1) {
                    e.preventDefault();
                    pinInputs[index + 1].focus();
                }
                // Enter no último campo → submit
                if (e.key === 'Enter' && index === pinInputs.length - 1) {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
            });

            // Colar código completo
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '').slice(
                    0, 6);
                if (!paste) return;

                pinInputs.forEach((inp, i) => {
                    inp.value = paste[i] || '';
                });

                const focusIndex = Math.min(paste.length, pinInputs.length - 1);
                pinInputs[focusIndex].focus();
                updateSubmitState();
            });
        });

        function updateSubmitState() {
            const pin = Array.from(pinInputs).map(i => i.value).join('');
            unlockBtn.disabled = (pin.length !== 6) || (Date.now() < lockoutEnd);
        }

        function isLockedOut() {
            if (Date.now() < lockoutEnd) {
                const remaining = Math.ceil((lockoutEnd - Date.now()) / 1000);
                lockoutTimer.textContent = `Muitas tentativas. Aguarde ${remaining}s.`;
                return true;
            }
            lockoutTimer.textContent = '';
            return false;
        }

        // ── Submissão ──────────────────────────────────────────────────
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (isLockedOut()) return;

            const pin = Array.from(pinInputs).map(i => i.value).join('');
            if (pin.length !== 6) {
                shakeInputs();
                return;
            }

            unlockBtn.disabled = true;
            const originalHtml = unlockBtn.innerHTML;
            unlockBtn.innerHTML =
                '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:.5rem;"></span> A verificar…';

            try {
                const formData = new FormData(form);
                formData.append('access_code', pin);

                const res = await fetch(API_URL, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                    },
                    credentials: 'same-origin'
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const data = await res.json();

                if (data.success) {
                    window.location.href = data.return_url || (BASE_URL + '/jm-panel/home');
                    return;
                }

                // Erro
                attempts++;
                if (attempts >= MAX_ATTEMPTS) {
                    lockoutEnd = Date.now() + LOCKOUT_MS;
                    isLockedOut();
                    setTimeout(() => {
                        attempts = 0;
                        lockoutEnd = 0;
                        isLockedOut();
                    }, LOCKOUT_MS);
                }

                shakeInputs();
                showToast(data.message || 'Código incorrecto.', 'error');

            } catch (err) {
                showToast('Erro de rede. Tenta novamente.', 'error');
            } finally {
                unlockBtn.innerHTML = originalHtml;
                updateSubmitState();
            }
        });

        function shakeInputs() {
            pinInputs.forEach((inp, i) => {
                inp.classList.add('error');
                // Não limpar tudo — focar no campo problemático
                if (i === pinInputs.length - 1) {
                    setTimeout(() => inp.focus(), 50);
                }
            });
            setTimeout(() => pinInputs.forEach(inp => inp.classList.remove('error')), 500);
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');

            const icon = type === 'success' ?
                '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>' :
                '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>';

            toast.innerHTML = `${icon} <span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            requestAnimationFrame(() => {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Estilo inline para spinner
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
        document.head.appendChild(style);

        // Focar primeiro campo se necessário
        if (!pinInputs[0].value) {
            pinInputs[0].focus();
        }
    })();
    </script>
</body>

</html>