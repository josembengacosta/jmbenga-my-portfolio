<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações de SEO & Meta
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
$configStmt = $db->query("SELECT * FROM _site_config WHERE config_group = 'seo' ORDER BY config_key");
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

// Valores actuais
$siteName        = $formData['site_name']        ?? 'JMbenga';
$metaDescription = $formData['meta_description'] ?? '';
$metaKeywords    = $formData['meta_keywords']    ?? '';
$ogImage         = $formData['og_image']         ?? 'assets/img/profile/og.jpg';
$googleAnalytics = $formData['google_analytics'] ?? '';
$robotsTxt       = $formData['robots_txt']       ?? "User-agent: *\nAllow: /\nSitemap: " . BASE_URL . "/sitemap.xml";
$canonicalUrl    = $formData['canonical_url']    ?? BASE_URL;
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
                        <span>SEO &amp; Meta</span>
                    </div>
                    <h1 class="page-title">SEO &amp; Meta Tags</h1>
                    <p class="page-sub">Otimiza o teu portfólio para motores de busca e redes sociais.</p>
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

            <form id="seoForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/settings/seo-process"
                enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">

                <!-- ═══ SECÇÃO 1: META TAGS ════════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-tags"></i></span>
                        <div>
                            <h3>Meta Tags Principais</h3>
                            <p>Informações que aparecem nos resultados de pesquisa e partilhas.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Título do Site (Home)</label>
                            <input type="text" name="site_name" class="form-control" value="<?= e($siteName) ?>"
                                placeholder="José Mbenga — Full Stack Developer">
                            <span class="form-hint">Aparece no topo do navegador e nos resultados de pesquisa.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL Canónica</label>
                            <input type="url" name="canonical_url" class="form-control" value="<?= e($canonicalUrl) ?>"
                                placeholder="<?= BASE_URL ?>">
                            <span class="form-hint">URL principal do site para evitar conteúdo duplicado.</span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" class="form-control textarea-sm" rows="2"
                                placeholder="Portfólio profissional de José Mbenga — Desenvolvedor Full Stack com mais de 3 anos de experiência..."><?= e($metaDescription) ?></textarea>
                            <span class="form-hint" id="metaDescCount">0/160 caracteres (ideal até 160)</span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Meta Keywords</label>
                            <input type="text" name="meta_keywords" class="form-control" value="<?= e($metaKeywords) ?>"
                                placeholder="desenvolvedor full stack, angola, php, mysql, javascript">
                            <span class="form-hint">Separadas por vírgula. Menos relevantes hoje, mas ainda
                                usadas.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Imagem OG (Open Graph)</label>
                            <input type="text" name="og_image" class="form-control" value="<?= e($ogImage) ?>"
                                placeholder="assets/img/profile/og.jpg">
                            <span class="form-hint">Caminho relativo à raiz do site. Recomendado: 1200×630 px.</span>
                            <?php if (!empty($ogImage)): ?>
                            <div class="og-preview">
                                <img src="<?= BASE_URL ?>/<?= e($ogImage) ?>" alt="OG Image Preview">
                                <div class="og-overlay">
                                    <div class="og-site"><?= e($siteName) ?></div>
                                    <div class="og-title">
                                        <?= e($metaDescription ? mb_substr($metaDescription, 0, 60) . '…' : 'Título da página') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2: FERRAMENTAS EXTERNAS ════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h3>Ferramentas de Análise</h3>
                            <p>Integração com Google Analytics e outras ferramentas.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Google Analytics (ID de medição)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-chart-bar"></i>
                            <input type="text" name="google_analytics" class="form-control"
                                value="<?= e($googleAnalytics) ?>" placeholder="G-XXXXXXXXXX ou UA-XXXXXXXX-X">
                        </div>
                        <span class="form-hint">O código será inserido automaticamente em todas as páginas.</span>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 3: ROBOTS.TXT ═════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-robot"></i></span>
                        <div>
                            <h3>Robots.txt</h3>
                            <p>Controla o que os motores de busca podem indexar.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conteúdo do robots.txt</label>
                        <textarea name="robots_txt" class="form-control textarea-code" rows="6"
                            placeholder="User-agent: *\nAllow: /\nSitemap: https://..."><?= e($robotsTxt) ?></textarea>
                        <span class="form-hint">Este conteúdo será servido dinamicamente em /robots.txt.</span>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 4: PREVIEW NOS MOTORES DE BUSCA ═══════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-eye"></i></span>
                        <div>
                            <h3>Pré-visualização no Google</h3>
                            <p>Simula como o teu site aparecerá nos resultados de pesquisa.</p>
                        </div>
                    </div>
                    <div class="serp-preview">
                        <div class="serp-url"><?= e($canonicalUrl) ?> <i class="fas fa-caret-down"></i></div>
                        <div class="serp-title"><?= e($siteName ?: 'José Mbenga — Full Stack Developer') ?></div>
                        <div class="serp-desc">
                            <?php if ($metaDescription): ?>
                            <?= e(mb_strlen($metaDescription) > 160 ? mb_substr($metaDescription, 0, 157) . '…' : $metaDescription) ?>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-style:italic">A meta description não está
                                definida. Preenche o campo acima para ver a pré-visualização.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-save"></i>
                        Guardar Configurações SEO</button>
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

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem
    }

    .form-group.full-width {
        grid-column: 1/-1
    }

    @media(max-width:768px) {
        .form-grid {
            grid-template-columns: 1fr
        }
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: .35rem
    }

    .form-label {
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--text-dim)
    }

    .form-control {
        padding: .65rem .85rem;
        background: var(--bg);
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

    .form-hint {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .15rem
    }

    .textarea-sm {
        resize: vertical;
        min-height: 60px;
        line-height: 1.6
    }

    .textarea-code {
        font-family: var(--font-mono);
        font-size: .82rem;
        line-height: 1.7;
        resize: vertical;
        min-height: 120px
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
        font-size: .85rem;
        pointer-events: none;
        z-index: 1
    }

    .input-with-icon .form-control {
        padding-left: 2.2rem
    }

    /* OG Preview */
    .og-preview {
        position: relative;
        margin-top: .5rem;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--border);
        max-width: 400px
    }

    .og-preview img {
        width: 100%;
        display: block;
        aspect-ratio: 1200/630;
        object-fit: cover
    }

    .og-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: .6rem .8rem;
        background: linear-gradient(transparent, rgba(0, 0, 0, .8))
    }

    .og-site {
        font-size: .65rem;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .7);
        margin-bottom: .15rem
    }

    .og-title {
        font-size: .8rem;
        color: #fff;
        font-weight: 600;
        line-height: 1.3
    }

    /* SERP Preview */
    .serp-preview {
        padding: 1rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius)
    }

    .serp-url {
        font-size: .75rem;
        color: var(--text-muted);
        margin-bottom: .2rem;
        display: flex;
        align-items: center;
        gap: .2rem
    }

    .serp-title {
        font-size: 1.1rem;
        color: #8ab4f8;
        font-weight: 600;
        margin-bottom: .25rem;
        cursor: pointer
    }

    .serp-title:hover {
        text-decoration: underline
    }

    .serp-desc {
        font-size: .82rem;
        color: var(--text-dim);
        line-height: 1.5
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
    const form = document.getElementById('seoForm');
    const submitBtn = document.getElementById('submitBtn');
    const metaDesc = document.querySelector('[name="meta_description"]');
    const metaDescCount = document.getElementById('metaDescCount');
    const serpTitle = document.querySelector('.serp-title');
    const serpDesc = document.querySelector('.serp-desc');
    const serpUrl = document.querySelector('.serp-url');
    const siteNameInput = document.querySelector('[name="site_name"]');
    const canonicalInput = document.querySelector('[name="canonical_url"]');

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

    // ── Contador de meta description ────────────────────────────
    metaDesc.addEventListener('input', function() {
        const len = this.value.length;
        metaDescCount.textContent = len + '/160 caracteres (ideal até 160)';
        metaDescCount.style.color = len > 160 ? 'var(--danger)' : 'var(--text-muted)';
        updateSERP();
    });

    // ── Actualizar preview SERP ─────────────────────────────────
    function updateSERP() {
        const title = siteNameInput.value || 'José Mbenga — Full Stack Developer';
        const desc = metaDesc.value || '';
        const url = canonicalInput.value || '<?= BASE_URL ?>';

        serpTitle.textContent = title;
        serpUrl.innerHTML = url + ' <i class="fas fa-caret-down"></i>';
        serpDesc.innerHTML = desc ?
            (desc.length > 160 ? desc.substring(0, 157) + '…' : desc) :
            '<span style="color:var(--text-muted);font-style:italic">A meta description não está definida.</span>';
    }

    siteNameInput.addEventListener('input', updateSERP);
    canonicalInput.addEventListener('input', function() {
        serpUrl.innerHTML = (this.value || '<?= BASE_URL ?>') + ' <i class="fas fa-caret-down"></i>';
    });

    // Inicializar
    metaDesc.dispatchEvent(new Event('input'));

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
                showToast(data.message || 'SEO actualizado!', 'success');
            } else {
                if (data.errors) data.errors.forEach(err => showToast(err, 'error'));
                else showToast(data.message || 'Erro.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Configurações SEO';
        }
    });
    </script>
</body>

</html>