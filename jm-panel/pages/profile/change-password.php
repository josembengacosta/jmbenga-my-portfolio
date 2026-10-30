<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Alterar Senha
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);

$formErrors = $_SESSION['form_errors'] ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_errors'], $_SESSION['form_success']);
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<?php
$passwordCssPath = ROOT_PATH . '/assets/css/jmbenga-password.css';
$passwordCssVersion = is_file($passwordCssPath) ? (string) filemtime($passwordCssPath) : '1.0.0';
?>
<link rel="stylesheet"
    href="<?= e(rtrim(BASE_URL, '/') . '/assets/css/jmbenga-password.css') ?>?v=<?= e($passwordCssVersion) ?>">
</head>

<body class="change-password-page">
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/profile">Perfil</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Alterar Senha</span>
                    </div>
                    <h1 class="page-title">Alterar Senha</h1>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/profile" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($formSuccess) ?><button
                    class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>
                <div><strong>Erro:</strong>
                    <ul><?php foreach ($formErrors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <section class="password-card" aria-labelledby="passwordCardTitle">
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-lock"></i></span>
                    <div>
                        <h3 id="passwordCardTitle">Nova Senha</h3>
                        <p>Escolhe uma senha forte e segura.</p>
                    </div>
                </div>
                <form id="passwordForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/profile/change-password-process">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Senha Actual <span
                                class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control"
                            placeholder="••••••••" autocomplete="current-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Nova Senha <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control"
                            placeholder="Mínimo 8 caracteres" autocomplete="new-password" required minlength="8"
                            maxlength="72" aria-describedby="passwordHelp strengthText">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText" aria-live="polite">Força da senha</div>
                        <p class="password-help" id="passwordHelp">Usa pelo menos 8 caracteres. O limite técnico é de 72
                            bytes.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nova Senha <span
                                class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            placeholder="••••••••" autocomplete="new-password" required minlength="8" maxlength="72">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg password-submit" id="submitBtn"><i
                            class="fas fa-save"></i> Alterar Senha</button>
                </form>
            </section>
        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>


    <script>
    (() => {
        'use strict';

        const form = document.getElementById('passwordForm');
        const submitBtn = document.getElementById('submitBtn');
        const currentInput = document.getElementById('current_password');
        const newInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const csrfInput = document.getElementById('csrf_token');
        const originalButtonHtml = '<i class="fas fa-save" aria-hidden="true"></i> Alterar Senha';

        function createToastContainer() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'false');
                document.body.appendChild(container);
            }
            return container;
        }

        function showToast(message, type = 'success') {
            const container = createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast toast-${type === 'success' ? 'success' : 'error'}`;
            const icon = document.createElement('i');
            icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}`;
            icon.setAttribute('aria-hidden', 'true');
            const text = document.createElement('span');
            text.textContent = String(message || 'Ocorreu um erro.');
            toast.append(icon, text);
            container.appendChild(toast);
            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                window.setTimeout(() => toast.remove(), 300);
            }, 4500);
        }

        function updateStrength() {
            const value = newInput.value;
            let score = 0;
            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[a-z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            const labels = ['', 'Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];
            const colors = ['transparent', '#ef4444', '#f59e0b', '#f59e0b', '#10b981', '#059669'];
            strengthFill.style.width = `${(score / 5) * 100}%`;
            strengthFill.style.backgroundColor = colors[score];
            strengthText.textContent = value.length === 0 ? 'Força da senha' : labels[score];
        }

        function resetInvalidState() {
            form.querySelectorAll('.is-invalid').forEach((field) => {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');
            });
        }

        newInput.addEventListener('input', updateStrength);
        form.querySelectorAll('input').forEach((field) => {
            field.addEventListener('input', () => {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            resetInvalidState();

            const requiredFields = [currentInput, newInput, confirmInput];
            const missingFields = requiredFields.filter((field) => !field.value);
            if (missingFields.length) {
                missingFields.forEach((field) => {
                    field.classList.add('is-invalid');
                    field.setAttribute('aria-invalid', 'true');
                });
                showToast('Preenche todos os campos obrigatórios.', 'error');
                missingFields[0].focus();
                return;
            }
            if (newInput.value.length < 8) {
                newInput.classList.add('is-invalid');
                newInput.setAttribute('aria-invalid', 'true');
                showToast('A nova senha deve ter pelo menos 8 caracteres.', 'error');
                newInput.focus();
                return;
            }
            if (newInput.value !== confirmInput.value) {
                confirmInput.classList.add('is-invalid');
                confirmInput.setAttribute('aria-invalid', 'true');
                showToast('A nova senha e a confirmação não coincidem.', 'error');
                confirmInput.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML =
            '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> A alterar…';

            try {
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfInput.value
                    }
                });

                const rawBody = await response.text();
                let data;
                try {
                    data = JSON.parse(rawBody);
                } catch (_) {
                    if (response.redirected || /<html|<!doctype/i.test(rawBody)) {
                        throw new Error(
                            'A sessão pode ter expirado. Actualiza a página e inicia sessão novamente.'
                            );
                    }
                    throw new Error(
                        'O servidor devolveu uma resposta inválida. Verifica o PHP e a consola do navegador.'
                        );
                }

                if (!response.ok || !data.success) {
                    const message = Array.isArray(data.errors) ? data.errors.join(' ') : data.message;
                    if (response.status === 401) {
                        throw new Error(message || 'A sessão expirou. Inicia sessão novamente.');
                    }
                    if (response.status === 423) {
                        throw new Error(message ||
                            'O painel está bloqueado. Desbloqueia a sessão e tenta novamente.');
                    }
                    showToast(message || 'Não foi possível alterar a senha.', 'error');
                    return;
                }

                showToast(data.message || 'Senha alterada com sucesso.', 'success');
                if (data.csrf_token) csrfInput.value = data.csrf_token;
                form.reset();
                resetInvalidState();
                updateStrength();
                currentInput.focus();
            } catch (error) {
                showToast(error instanceof Error ? error.message :
                    'Não foi possível comunicar com o servidor.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalButtonHtml;
            }
        });

        updateStrength();
    })();
    </script>
</body>

</html>