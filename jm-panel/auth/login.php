<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Login do Painel
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Sistema de feedback baseado no parâmetro 'msg'
$msg_type = $_GET['msg'] ?? null;
$error    = $_GET['error'] ?? '';   // ainda aceitamos mensagem extra

$feedback = match ($msg_type) {
    'session'   => [
        'type' => 'warning',
        'icon' => 'fa-clock',
        'text' => 'A tua sessão expirou. Inicia sessão novamente para continuar.'
    ],
    'expired'   => [
        'type' => 'warning',
        'icon' => 'fa-clock',
        'text' => 'A tua sessão expirou. Introduz as credenciais novamente.'
    ],
    'blocked'   => [
        'type' => 'danger',
        'icon' => 'fa-lock',
        'text' => 'Conta temporariamente bloqueada por excesso de tentativas.'
    ],
    'inactive'  => [
        'type' => 'danger',
        'icon' => 'fa-user-slash',
        'text' => 'Esta conta está inactiva. Contacta o super administrador.'
    ],
    'error'     => [
        'type' => 'danger',
        'icon' => 'fa-exclamation-triangle',
        'text' => $error ?: 'Credenciais inválidas. Verifica o e‑mail/username e a senha.'
    ],
    'logout'    => [
        'type' => 'success',
        'icon' => 'fa-check-circle',
        'text' => 'Sessão terminada com sucesso.'
    ],
    default     => null,
};

// Se não houver msg mas houver um return (sessão expirada)
if (!$feedback && isset($_GET['return']) && !isset($_GET['msg'])) {
    $feedback = [
        'type' => 'warning',
        'icon' => 'fa-clock',
        'text' => 'A tua sessão expirou. Inicia sessão novamente para continuar.',
    ];
}

// Se já estiver logado, redirecionar para o dashboard
if (isAdminLoggedIn()) {
    redirect('/jm-panel/home');
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $_SESSION['admin_csrf_token'] ?>">
    <title>Login — Painel JMbenga</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icons/apple-touch-icon-180x180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">

    <style>
        :root {
            --accent: #2563eb;
            --accent-glow: rgba(37, 99, 235, .2);
            --bg: #0a0d14;
            --bg-2: #0f1623;
            --bg-card: #131b2e;
            --border: rgba(255, 255, 255, .06);
            --border-acc: rgba(37, 99, 235, .35);
            --text: #e8edf5;
            --text-dim: #8899b4;
            --text-muted: #4a5878;
            --font-head: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
            --font-mono: 'DM Mono', monospace;
            --radius: 12px;
            --radius-lg: 20px;
            --ease: cubic-bezier(.16, 1, .3, 1);
        }

        [data-theme="light"] {
            --bg: #f0f4fb;
            --bg-2: #e4eaf6;
            --bg-card: #ffffff;
            --border: rgba(0, 0, 0, .07);
            --border-acc: rgba(37, 99, 235, .25);
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
            padding: 1rem;
            transition: background .35s, color .35s;
            overflow-x: hidden;
            overflow-y: auto;
            /* permite scroll vertical */
            -webkit-overflow-scrolling: touch;
            /* scroll suave no iOS */
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 20% 30%, rgba(37, 99, 235, .12) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, .12) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(16, 185, 129, .08) 0%, transparent 50%);
            animation: bgMove 20s ease infinite;
        }

        @keyframes bgMove {

            0%,
            100% {
                transform: translate(0, 0)
            }

            25% {
                transform: translate(-2%, 2%)
            }

            50% {
                transform: translate(2%, -2%)
            }

            75% {
                transform: translate(-2%, -2%)
            }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .3), 0 0 40px var(--accent-glow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        @media(max-width:768px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 480px;
                margin: 0 auto
            }
        }

        /* Coluna Esquerda */
        .login-left {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: #fff;
        }

        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, .1) 0%, transparent 60%);
            pointer-events: none;
        }

        .left-content {
            position: relative;
            z-index: 1
        }

        .login-logo {
            font-family: var(--font-head);
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 2rem;
        }

        .login-logo span {
            color: rgba(255, 255, 255, .7)
        }

        .welcome-title {
            font-family: var(--font-head);
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .welcome-desc {
            font-size: .95rem;
            opacity: .85;
            line-height: 1.7;
            margin-bottom: 2rem
        }

        .features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .75rem
        }

        .features li {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .9rem
        }

        .features li i {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
        }

        .login-illustration {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 5rem;
            opacity: .2
        }

        /* Coluna Direita */
        .login-right {
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem
        }

        .form-title {
            font-family: var(--font-head);
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), #a78bfa);
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-subtitle {
            color: var(--text-dim);
            font-size: .9rem;
            margin-top: .25rem
        }

        .alert-error {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #f87171
        }

        .alert {
            padding: .75rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #f87171;
        }

        .alert-warning {
            background: rgba(245, 158, 11, .1);
            border: 1px solid rgba(245, 158, 11, .3);
            color: #fbbf24;
        }

        .alert-success {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .3);
            color: #34d399;
        }

        .alert-info {
            background: rgba(37, 99, 235, .1);
            border: 1px solid rgba(37, 99, 235, .3);
            color: #60a5fa;
        }

        .form-group {
            margin-bottom: 1.2rem
        }

        .form-label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-dim);
            margin-bottom: .4rem
        }

        .input-wrap {
            position: relative
        }

        .form-control {
            width: 100%;
            padding: .8rem 1rem .8rem 2.5rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-family: var(--font-body);
            font-size: .92rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow)
        }

        .form-control::placeholder {
            color: var(--text-muted)
        }

        .input-icon {
            position: absolute;
            left: .8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .9rem;
            pointer-events: none;
        }

        .toggle-pass {
            position: absolute;
            right: .8rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
        }

        .toggle-pass:hover {
            color: var(--accent)
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: .82rem
        }

        .remember {
            display: flex;
            align-items: center;
            gap: .4rem;
            color: var(--text-dim);
            cursor: pointer
        }

        .remember input {
            accent-color: var(--accent)
        }

        .forgot-link {
            color: var(--accent);
            text-decoration: none
        }

        .forgot-link:hover {
            text-decoration: underline
        }

        .btn-login {
            width: 100%;
            padding: .85rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-family: var(--font-body);
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: all .2s;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--accent-glow)
        }

        .btn-login:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-size: .82rem;
            color: var(--text-muted);
            text-decoration: none
        }

        .back-link:hover {
            color: var(--accent)
        }

        /* Tema toggle */
        .theme-toggle {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 10
        }

        .theme-btn {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all .25s;
        }

        .theme-btn:hover {
            border-color: var(--accent);
            color: var(--accent)
        }

        @media (max-width: 480px) {

            .login-left,
            .login-right {
                padding: 2rem 1.5rem;
            }

            .login-logo {
                font-size: 1.3rem;
            }

            .welcome-title {
                font-size: 1.3rem;
            }

            .form-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="theme-toggle">
        <button class="theme-btn" id="themeToggle" title="Alternar tema">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
    </div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Coluna Esquerda -->
            <div class="login-left">
                <div class="left-content">
                    <div class="login-logo">
                        <i class="fas fa-shield-haltered"></i>
                        J<span>Mbenga</span>
                    </div>
                    <h1 class="welcome-title">Bem-vindo de Volta!</h1>
                    <p class="welcome-desc">
                        Aceda ao painel para gerir projectos, visualizar estatísticas e controlar todo o seu portfólio.
                    </p>
                    <ul class="features">
                        <li><i class="fas fa-check"></i> Gestão de Projectos</li>
                        <li><i class="fas fa-check"></i> Estatísticas em tempo real</li>
                        <li><i class="fas fa-check"></i> Controle total do portfólio</li>
                        <li><i class="fas fa-check"></i> Segurança e backups</li>
                    </ul>
                    <div class="login-illustration">
                        <i class="fas fa-door-open"></i>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita (Formulário) -->
            <div class="login-right">
                <div class="form-header">
                    <h2 class="form-title">Faça Login</h2>
                    <p class="form-subtitle">Entre com as suas credenciais</p>
                </div>

                <!-- Alerta de feedback -->
                <?php if ($feedback): ?>
                    <div class="alert alert-<?= $feedback['type'] ?>">
                        <i class="fas <?= $feedback['icon'] ?>"></i>
                        <span><?= htmlspecialchars($feedback['text']) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login-process" method="post" id="login-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                    <div class="form-group">
                        <label class="form-label" for="login">Email ou Username</label>
                        <div class="input-wrap">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="login" name="login" class="form-control"
                                placeholder="jose@exemplo.com" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Senha</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="••••••••" required>
                            <button type="button" class="toggle-pass" id="togglePass" tabindex="-1">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember">
                            <input type="checkbox" name="remember" value="1"> Lembrar-me
                        </label>
                        <a href="forgot-password" class="forgot-link">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn-login" id="submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Entrar no Painel
                    </button>
                </form>
            </div>
        </div>

        <a href="<?= BASE_URL ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar ao site
        </a>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePass').addEventListener('click', function() {
            const pw = document.getElementById('password');
            const icon = this.querySelector('i');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.className = 'far fa-eye-slash';
            } else {
                pw.type = 'password';
                icon.className = 'far fa-eye';
            }
        });

        // Loading state on submit
        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Autenticando…';
        });

        // Theme toggle
        (function() {
            const html = document.documentElement;
            const toggle = document.getElementById('themeToggle');
            const icon = document.getElementById('themeIcon');
            const saved = localStorage.getItem('jm_theme') || 'dark';

            function apply(t) {
                html.dataset.theme = t;
                if (icon) icon.className = t === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
                localStorage.setItem('jm_theme', t);
            }
            apply(saved);
            toggle.addEventListener('click', () => apply(html.dataset.theme === 'dark' ? 'light' : 'dark'));
        })();
    </script>
</body>

</html>