<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações de Redes Sociais
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);

// ── Carregar configurações actuais ──────────────────────────
$configStmt = $db->query("SELECT * FROM _site_config WHERE config_group = 'social' ORDER BY config_key");
$configs = [];
foreach ($configStmt->fetchAll() as $row) {
    $configs[$row['config_key']] = $row['config_value'];
}

// ── Recuperar feedback ─────────────────────────────────────
$formData    = $_SESSION['form_data']    ?? [];
$formErrors  = $_SESSION['form_errors']  ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['form_success']);

if (!is_array($formData) || empty($formData)) {
    $formData = $configs;
}

if (!is_array($formData)) {
    $formData = [];
}

$isHttpUrl = static function (string $url): bool {
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass']);
};

// Redes sociais com ícones, cores e placeholders
$socialNetworks = [
    'github_url'      => ['label' => 'GitHub',       'icon' => 'fab fa-github',        'color' => '#333333', 'placeholder' => 'https://github.com/josembengacosta'],
    'linkedin_url'    => ['label' => 'LinkedIn',     'icon' => 'fab fa-linkedin-in',   'color' => '#0a66c2', 'placeholder' => 'https://linkedin.com/in/josembengadacosta'],
    'whatsapp_url'    => ['label' => 'WhatsApp',     'icon' => 'fab fa-whatsapp',      'color' => '#25d366', 'placeholder' => 'https://wa.me/244922030116'],
    'twitter_url'     => ['label' => 'Twitter / X',  'icon' => 'fab fa-x-twitter',     'color' => '#000000', 'placeholder' => 'https://x.com/seuuser'],
    'instagram_url'   => ['label' => 'Instagram',    'icon' => 'fab fa-instagram',     'color' => '#e4405f', 'placeholder' => 'https://instagram.com/seuuser'],
    'youtube_url'     => ['label' => 'YouTube',      'icon' => 'fab fa-youtube',       'color' => '#ff0000', 'placeholder' => 'https://youtube.com/@seucanal'],
    'behance_url'     => ['label' => 'Behance',      'icon' => 'fab fa-behance',       'color' => '#1769ff', 'placeholder' => 'https://behance.net/seuuser'],
    'dribbble_url'    => ['label' => 'Dribbble',     'icon' => 'fab fa-dribbble',      'color' => '#ea4c89', 'placeholder' => 'https://dribbble.com/seuuser'],
    'medium_url'      => ['label' => 'Medium',       'icon' => 'fab fa-medium-m',      'color' => '#000000', 'placeholder' => 'https://medium.com/@seuuser'],
    'devto_url'       => ['label' => 'Dev.to',       'icon' => 'fab fa-dev',           'color' => '#0a0a0a', 'placeholder' => 'https://dev.to/seuuser'],
    'facebook_url'    => ['label' => 'Facebook',     'icon' => 'fab fa-facebook-f',    'color' => '#1877f2', 'placeholder' => 'https://facebook.com/seuuser'],
    'telegram_url'    => ['label' => 'Telegram',     'icon' => 'fab fa-telegram-plane','color' => '#26a5e4', 'placeholder' => 'https://t.me/seuuser'],
    'tiktok_url'      => ['label' => 'TikTok',       'icon' => 'fab fa-tiktok',        'color' => '#000000', 'placeholder' => 'https://tiktok.com/@seuuser'],
    'discord_url'     => ['label' => 'Discord',      'icon' => 'fab fa-discord',       'color' => '#5865f2', 'placeholder' => 'https://discord.gg/seuconvite'],
    'stackoverflow_url' => ['label' => 'Stack Overflow','icon' => 'fab fa-stack-overflow','color' => '#f58025', 'placeholder' => 'https://stackoverflow.com/users/seuuser'],
    'codepen_url'     => ['label' => 'CodePen',      'icon' => 'fab fa-codepen',       'color' => '#000000', 'placeholder' => 'https://codepen.io/seuuser'],
];

foreach ($socialNetworks as $key => $network) {
    $value = $formData[$key] ?? '';
    $formData[$key] = is_string($value) ? trim($value) : '';
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= e(BASE_URL) ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/settings">Configurações</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Redes Sociais</span>
                    </div>
                    <h1 class="page-title">Redes Sociais</h1>
                    <p class="page-sub">Configura os links das tuas redes sociais que aparecem no site.</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/settings" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <!-- Feedback -->
            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($formSuccess) ?><button
                    class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>
                <div><strong>Corrige:</strong>
                    <ul style="margin:.3rem 0 0 1.2rem"><?php foreach ($formErrors as $err): ?><li><?= e($err) ?></li>
                        <?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <form id="socialForm" data-api="<?= e(BASE_URL) ?>/jm-panel/settings/social-process">
                <input type="hidden" name="csrf_token" value="<?= e((string) ($_SESSION['admin_csrf_token'] ?? '')) ?>">

                <!-- Redes Sociais -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-share-alt"></i></span>
                        <div>
                            <h3>Links das Redes Sociais</h3>
                            <p>Preenche apenas as redes que queres exibir. As vazias não aparecerão no site.</p>
                        </div>
                    </div>
                    <div class="social-grid">
                        <?php foreach ($socialNetworks as $key => $net): ?>
                        <div class="social-card" style="--social-color:<?= $net['color'] ?>">
                            <div class="social-card-header">
                                <div class="social-icon-wrap">
                                    <i class="<?= $net['icon'] ?>"></i>
                                </div>
                                <div class="social-info">
                                    <h4><?= $net['label'] ?></h4>
                                    <?php
                                        $socialValue = $formData[$key] ?? '';
                                        $socialConfigured = $socialValue !== '' && $isHttpUrl($socialValue);
                                    ?>
                                    <?php if ($socialConfigured): ?>
                                    <span class="social-status active"><i class="fas fa-check-circle"></i>
                                        Configurado</span>
                                    <?php elseif ($socialValue !== ''): ?>
                                    <span class="social-status invalid"><i class="fas fa-exclamation-circle"></i>
                                        URL inválida</span>
                                    <?php else: ?>
                                    <span class="social-status"><i class="fas fa-circle"></i> Não configurado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="social-card-body">
                                <div class="input-with-icon">
                                    <i class="fas fa-link"></i>
                                    <input type="url" name="<?= e($key) ?>" class="form-control"
                                        value="<?= e($formData[$key] ?? '') ?>"
                                        placeholder="<?= e($net['placeholder']) ?>" maxlength="2048" autocomplete="url"
                                        inputmode="url">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Preview -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-eye"></i></span>
                        <div>
                            <h3>Pré-visualização</h3>
                            <p>Como os ícones aparecerão no site (apenas os preenchidos).</p>
                        </div>
                    </div>
                    <div class="social-preview" id="socialPreview">
                        <?php
                        $hasAnySocial = false;
                        foreach ($socialNetworks as $key => $net):
                            $previewUrl = $formData[$key] ?? '';
                            if ($previewUrl !== '' && $isHttpUrl($previewUrl)):
                                $hasAnySocial = true;
                        ?>
                        <a href="<?= e($previewUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="preview-social-link" style="--social-color:<?= e($net['color']) ?>"
                            title="<?= e($net['label']) ?>">
                            <i class="<?= e($net['icon']) ?>"></i>
                        </a>
                        <?php endif;
                        endforeach;
                        if (!$hasAnySocial): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Nenhuma rede social configurada. Preenche os
                            campos acima para ver a pré-visualização.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-save"></i>
                        Guardar Redes Sociais</button>
                </div>
            </form>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <style>
    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: .5rem
    }

    .breadcrumb a {
        color: var(--text-dim);
        text-decoration: none;
        transition: color .2s
    }

    .breadcrumb a:hover {
        color: var(--accent)
    }

    .breadcrumb i {
        font-size: .6rem
    }

    .page-title {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -.02em;
        margin-bottom: .2rem
    }

    .page-sub {
        font-size: .8rem;
        color: var(--text-muted)
    }

    .alert {
        padding: 1rem 1.2rem;
        border-radius: var(--radius);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        font-size: .85rem
    }

    .alert-success {
        background: rgba(16, 185, 129, .1);
        border: 1px solid rgba(16, 185, 129, .3);
        color: #34d399
    }

    .alert-danger {
        background: rgba(239, 68, 68, .1);
        border: 1px solid rgba(239, 68, 68, .3);
        color: #f87171
    }

    .alert-close {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        opacity: .6;
        font-size: .9rem
    }

    .alert-close:hover {
        opacity: 1
    }

    .form-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.8rem;
        margin-bottom: 1.2rem
    }

    .section-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.2rem;
        border-bottom: 1px solid var(--border)
    }

    .section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--accent-glow);
        border: 1px solid var(--border-acc);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-size: .95rem;
        flex-shrink: 0
    }

    .section-header h3 {
        font-family: var(--font-head);
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: .15rem
    }

    .section-header p {
        font-size: .78rem;
        color: var(--text-muted)
    }

    .social-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1rem
    }

    @media(max-width:768px) {
        .social-grid {
            grid-template-columns: 1fr
        }
    }

    .social-card {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.2rem;
        transition: all .2s
    }

    .social-card:hover {
        border-color: var(--border-acc)
    }

    .social-card-header {
        display: flex;
        align-items: center;
        gap: .8rem;
        margin-bottom: .8rem
    }

    .social-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--social-color) 12%, transparent);
        border: 1px solid color-mix(in srgb, var(--social-color) 25%, transparent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: var(--social-color);
        flex-shrink: 0
    }

    .social-info h4 {
        font-size: .88rem;
        font-weight: 600;
        margin-bottom: .1rem
    }

    .social-status {
        font-size: .7rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .25rem
    }

    .social-status.active {
        color: var(--success)
    }

    .social-status.invalid {
        color: var(--danger, #f87171)
    }

    .social-status i {
        font-size: .55rem
    }

    .input-with-icon {
        position: relative;
        display: flex;
        align-items: center
    }

    .input-with-icon i {
        position: absolute;
        left: .7rem;
        color: var(--text-muted);
        font-size: .8rem;
        pointer-events: none;
        z-index: 1
    }

    .input-with-icon .form-control {
        padding-left: 2.2rem
    }

    .form-control {
        padding: .65rem .85rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-family: var(--font-body);
        font-size: .88rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
        width: 100%
    }

    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow)
    }

    .form-control::placeholder {
        color: var(--text-muted)
    }

    .social-preview {
        display: flex;
        gap: .8rem;
        flex-wrap: wrap;
        align-items: center
    }

    .preview-social-link {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--bg);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dim);
        font-size: 1.1rem;
        text-decoration: none;
        transition: all .2s
    }

    .preview-social-link:hover {
        background: var(--social-color);
        border-color: var(--social-color);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px color-mix(in srgb, var(--social-color) 30%, transparent)
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1.1rem;
        border-radius: 8px;
        font-size: .83rem;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        border: 1px solid var(--border);
        white-space: nowrap
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent)
    }

    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px var(--accent-glow)
    }

    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-dim)
    }

    .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--border-acc);
        color: var(--text)
    }

    .btn-lg {
        padding: .7rem 1.5rem;
        font-size: .9rem
    }

    .form-actions {
        display: flex;
        gap: .75rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border)
    }
    </style>

    <script>
    (() => {
        'use strict';

        const form = document.getElementById('socialForm');
        const submitBtn = document.getElementById('submitBtn');
        const preview = document.getElementById('socialPreview');

        if (!form || !submitBtn || !preview) return;

        const isHttpUrl = (value) => {
            try {
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) &&
                    Boolean(url.hostname) &&
                    !url.username &&
                    !url.password;
            } catch {
                return false;
            }
        };

        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');

            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText =
                    'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            const icon = document.createElement('i');
            icon.className = type === 'success' ?
                'fas fa-check-circle' :
                'fas fa-times-circle';
            icon.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.textContent = String(message ?? '');

            toast.append(icon, text);
            container.appendChild(toast);

            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                window.setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function setStatus(card, value) {
            const status = card?.querySelector('.social-status');
            if (!status) return;

            status.replaceChildren();
            const icon = document.createElement('i');
            icon.setAttribute('aria-hidden', 'true');

            if (!value) {
                status.className = 'social-status';
                icon.className = 'fas fa-circle';
                status.append(icon, document.createTextNode(' Não configurado'));
            } else if (isHttpUrl(value)) {
                status.className = 'social-status active';
                icon.className = 'fas fa-check-circle';
                status.append(icon, document.createTextNode(' Configurado'));
            } else {
                status.className = 'social-status invalid';
                icon.className = 'fas fa-exclamation-circle';
                status.append(icon, document.createTextNode(' URL inválida'));
            }
        }

        function updatePreview() {
            preview.replaceChildren();
            let hasAny = false;

            form.querySelectorAll('.social-card input[type="url"]').forEach((input) => {
                const value = input.value.trim();
                if (!value || !isHttpUrl(value)) return;

                const card = input.closest('.social-card');
                const iconSource = card?.querySelector('.social-icon-wrap i');
                const labelSource = card?.querySelector('h4');

                if (!card || !iconSource || !labelSource) return;

                const link = document.createElement('a');
                link.href = new URL(value).href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'preview-social-link';
                link.title = labelSource.textContent.trim();
                link.style.setProperty(
                    '--social-color',
                    card.style.getPropertyValue('--social-color').trim()
                );

                const icon = document.createElement('i');
                icon.className = iconSource.className;
                icon.setAttribute('aria-hidden', 'true');
                link.appendChild(icon);

                preview.appendChild(link);
                hasAny = true;
            });

            if (!hasAny) {
                const empty = document.createElement('p');
                empty.style.color = 'var(--text-muted)';
                empty.style.fontSize = '.85rem';
                empty.textContent =
                    'Nenhuma rede social configurada. Preenche os campos acima para ver a pré-visualização.';
                preview.appendChild(empty);
            }
        }

        form.querySelectorAll('.social-card input[type="url"]').forEach((input) => {
            input.addEventListener('input', () => {
                setStatus(input.closest('.social-card'), input.value.trim());
                updatePreview();
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitBtn.disabled) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML =
            '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> A guardar…';

            try {
                const token = form.querySelector('[name="csrf_token"]')?.value ?? '';
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': token
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                let data;
                try {
                    data = await response.json();
                } catch {
                    throw new Error('O servidor devolveu uma resposta inválida.');
                }

                if (!response.ok || !data || data.success !== true) {
                    const errors = Array.isArray(data?.errors) ?
                        data.errors.filter((item) => typeof item === 'string') :
                        [];

                    if (errors.length) {
                        errors.forEach((error) => showToast(error, 'error'));
                    } else {
                        showToast(
                            typeof data?.message === 'string' ?
                            data.message :
                            'Não foi possível guardar as redes sociais.',
                            'error'
                        );
                    }
                    return;
                }

                showToast(
                    typeof data.message === 'string' ?
                    data.message :
                    'Redes sociais guardadas com sucesso.',
                    'success'
                );
            } catch (error) {
                showToast(
                    error instanceof Error ? error.message : 'Erro de comunicação com o servidor.',
                    'error'
                );
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML =
                    '<i class="fas fa-save" aria-hidden="true"></i> Guardar Redes Sociais';
            }
        });

        updatePreview();
    })();
    </script>
</body>

</html>