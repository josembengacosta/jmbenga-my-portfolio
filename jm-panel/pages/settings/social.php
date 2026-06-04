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

if (empty($formData)) $formData = $configs;

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
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
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

            <form id="socialForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/settings/social-process">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

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
                                    <?php if (!empty($formData[$key])): ?>
                                    <span class="social-status active"><i class="fas fa-check-circle"></i>
                                        Configurado</span>
                                    <?php else: ?>
                                    <span class="social-status"><i class="fas fa-circle"></i> Não configurado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="social-card-body">
                                <div class="input-with-icon">
                                    <i class="fas fa-link"></i>
                                    <input type="url" name="<?= $key ?>" class="form-control"
                                        value="<?= e($formData[$key] ?? '') ?>"
                                        placeholder="<?= $net['placeholder'] ?>">
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
                            if (!empty($formData[$key])):
                                $hasAnySocial = true;
                        ?>
                        <a href="<?= e($formData[$key]) ?>" target="_blank" class="preview-social-link"
                            style="--social-color:<?= $net['color'] ?>" title="<?= $net['label'] ?>">
                            <i class="<?= $net['icon'] ?>"></i>
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
    const form = document.getElementById('socialForm');
    const submitBtn = document.getElementById('submitBtn');

    function showToast(m, t = 'success') {
        const c = document.getElementById('toast-container') || (() => {
            const d = document.createElement('div');
            d.id = 'toast-container';
            d.style.cssText =
                'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
            document.body.appendChild(d);
            return d;
        })();
        const toast = document.createElement('div');
        toast.className = `toast toast-${t}`;
        toast.innerHTML = `<i class="fas fa-${t === 'success' ? 'check-circle' : 'times-circle'}"></i> ${m}`;
        c.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ── Actualizar preview e status ao digitar ─────────────────
    document.querySelectorAll('.social-card input').forEach(input => {
        input.addEventListener('input', function() {
            const card = this.closest('.social-card');
            const status = card.querySelector('.social-status');
            if (this.value.trim()) {
                status.className = 'social-status active';
                status.innerHTML = '<i class="fas fa-check-circle"></i> Configurado';
            } else {
                status.className = 'social-status';
                status.innerHTML = '<i class="fas fa-circle"></i> Não configurado';
            }
            updatePreview();
        });
    });

    function updatePreview() {
        const preview = document.getElementById('socialPreview');
        const inputs = document.querySelectorAll('.social-card input');
        let html = '';
        let hasAny = false;
        inputs.forEach(input => {
            const val = input.value.trim();
            if (val) {
                hasAny = true;
                const card = input.closest('.social-card');
                const color = card.style.getPropertyValue('--social-color');
                const icon = card.querySelector('.social-icon-wrap i').className;
                const label = card.querySelector('h4').textContent;
                html +=
                    `<a href="${val}" target="_blank" class="preview-social-link" style="--social-color:${color}" title="${label}"><i class="${icon}"></i></a>`;
            }
        });
        if (!hasAny) {
            html =
                '<p style="color:var(--text-muted);font-size:.85rem">Nenhuma rede social configurada. Preenche os campos acima para ver a pré-visualização.</p>';
        }
        preview.innerHTML = html;
    }

    // ── Submissão ──────────────────────────────────────────────
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';
        try {
            const fd = new FormData(form);
            const res = await fetch(form.dataset.api, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Redes sociais guardadas!', 'success');
            } else {
                if (data.errors) data.errors.forEach(err => showToast(err, 'error'));
                else showToast(data.message || 'Erro.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Redes Sociais';
        }
    });
    </script>
</body>

</html>