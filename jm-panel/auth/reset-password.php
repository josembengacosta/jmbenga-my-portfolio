<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Redefinir Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Se já estiver logado, redirecionar para o dashboard
if (isAdminLoggedIn()) {
    redirect('/jm-panel/home');
}

// Validar token (obrigatório)
$token = $_GET['token'] ?? '';
if ($token === '') {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token de recuperação inválido ou expirado.'));
}

// Verificar se o token é válido
$adminId = validateAdminResetToken($token);
if (!$adminId) {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Token de recuperação inválido ou expirado.'));
}

// Obter dados básicos do admin (para mostrar info na página)
$admin = getAdminById($adminId);
if (!$admin || $admin['status_employees'] !== 'active') {
    redirect('/jm-panel/entrar?msg=error&error=' . urlencode('Conta inválida ou inactiva.'));
}

// Feedback (se houver erro após submissão)
$error = $_GET['error'] ?? '';
$msg   = $_GET['msg']   ?? '';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $_SESSION['admin_csrf_token'] ?>">
    <title>Redefinir Senha — Painel JMbenga</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icons/apple-touch-icon-180x180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --accent: #2563eb;
            --accent-glow: rgba(37, 99, 235, .2);
            --bg: #0a0d14;
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
            --ease: cubic-bezier(.16, 1, .3, 1);
        }

        [data-theme="light"] {
            --bg: #f0f4fb;
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
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            transition: background .35s, color .35s;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
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

        .reset-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px
        }

        .reset-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .3), 0 0 40px var(--accent-glow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 560px;
        }

        @media(max-width:768px) {
            .reset-card {
                grid-template-columns: 1fr;
                max-width: 480px;
                margin: 0 auto
            }
        }

        /* Coluna Esquerda */
        .reset-left {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: #fff;
        }

        .reset-left::before {
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

        .illustration {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 5rem;
            opacity: .2
        }

        /* Coluna Direita */
        .reset-right {
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 1.5rem
        }

        .form-title {
            font-family: var(--font-head);
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), #a78bfa);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-subtitle {
            color: var(--text-dim);
            font-size: .9rem;
            margin-top: .25rem
        }

        /* Info da conta */
        .account-info {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .account-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-head);
            font-size: 1.2rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .account-details {
            flex: 1
        }

        .account-name {
            font-weight: 600;
            font-size: .9rem
        }

        .account-email {
            font-size: .78rem;
            color: var(--text-muted)
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
        }

        .alert-danger {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #f87171
        }

        .alert-success {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .3);
            color: #34d399
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

        /* Força da senha */
        .strength-bar {
            height: 4px;
            background: rgba(255, 255, 255, .06);
            border-radius: 2px;
            overflow: hidden;
            margin-top: .5rem
        }

        .strength-fill {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width .3s, background .3s
        }

        .strength-text {
            font-size: .72rem;
            color: var(--text-muted);
            margin-top: .25rem;
            text-align: right
        }

        .btn-reset {
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
            margin-top: .5rem;
        }

        .btn-reset:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--accent-glow)
        }

        .btn-reset:disabled {
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

        @media(max-width:480px) {

            .reset-left,
            .reset-right {
                padding: 2rem 1.5rem
            }

            .login-logo {
                font-size: 1.3rem
            }

            .welcome-title {
                font-size: 1.3rem
            }

            .form-title {
                font-size: 1.5rem
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

    <div class="reset-wrapper">
        <div class="reset-card">
            <!-- Coluna Esquerda -->
            <div class="reset-left">
                <div class="left-content">
                    <div class="login-logo">
                        <i class="fas fa-lock"></i>
                        JM<span>.</span>
                    </div>
                    <h1 class="welcome-title">Nova Senha</h1>
                    <p class="welcome-desc">
                        Escolhe uma senha forte e segura para proteger o acesso ao painel de administração.
                    </p>
                    <ul class="features">
                        <li><i class="fas fa-check"></i> Mínimo 8 caracteres</li>
                        <li><i class="fas fa-check"></i> Combina maiúsculas e minúsculas</li>
                        <li><i class="fas fa-check"></i> Inclui números e símbolos</li>
                    </ul>
                    <div class="illustration">
                        <i class="fas fa-key"></i>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita (Formulário) -->
            <div class="reset-right">
                <div class="form-header">
                    <h2 class="form-title">Redefinir Senha</h2>
                    <p class="form-subtitle">Cria uma nova senha para a tua conta</p>
                </div>

                <!-- Info da conta -->
                <div class="account-info">
                    <div class="account-avatar">
                        <?= strtoupper(mb_substr($admin['first_name'], 0, 1)) ?>
                    </div>
                    <div class="account-details">
                        <div class="account-name"><?= e($admin['first_name'] . ' ' . ($admin['second_name'] ?? '')) ?>
                        </div>
                        <div class="account-email"><?= e($admin['email_employees']) ?></div>
                    </div>
                    <i class="fas fa-check-circle" style="color:#34d399"></i>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
                    </div>
                <?php elseif ($msg === 'success'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Senha redefinida com sucesso! Redirecionando…
                    </div>
                    <script>
                        setTimeout(function() {
                            window.location.href = '<?= BASE_URL ?>/jm-panel/entrar?msg=reset_ok';
                        }, 3000);
                    </script>
                <?php endif; ?>

                <?php if (!$msg || $msg !== 'success'): ?>
                    <form action="reset-password-process" method="post" id="reset-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="form-group">
                            <label class="form-label" for="password">Nova Senha</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="••••••••" required minlength="8" autofocus>
                                <button type="button" class="toggle-pass" id="togglePass" tabindex="-1">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Força da senha</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirmar Senha</label>
                            <div class="input-wrap">
                                <i class="fas fa-check input-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                    placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-reset" id="submit-btn">
                            <i class="fas fa-check"></i> Redefinir Senha
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/jm-panel/entrar" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar ao login
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

        // Força da senha
        document.getElementById('password').addEventListener('input', function() {
            const val = this.value;
            const fill = document.getElementById('strengthFill');
            const text = document.getElementById('strengthText');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const pct = (score / 5) * 100;
            const colors = ['#ef4444', '#f59e0b', '#f59e0b', '#10b981', '#10b981'];
            const labels = ['Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];
            fill.style.width = pct + '%';
            fill.style.background = colors[Math.min(score, 4)];
            text.textContent = labels[Math.min(score, 4)] || 'Força da senha';
        });

        // Loading no submit
        document.getElementById('reset-form').addEventListener('submit', function() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A redefinir…';
        });

        // Tema
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