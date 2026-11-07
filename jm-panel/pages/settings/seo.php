<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Centro de Controlo SEO, Indexação e Robots
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

$adminName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr((string) $adminName, 0, 1));
$adminPhoto = $_SESSION['admin_photo'] ?? null;
$totalProjects = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime = $_SESSION['admin_login_time'] ?? time();
$sessionMins = (int) floor((time() - $loginTime) / 60);

// Carregar apenas as configurações SEO. O nome da marca continua a ser
// gerido em Configurações Gerais; não duplicamos a chave site_name aqui.
$configStmt = $db->query("SELECT config_key, config_value FROM _site_config WHERE config_group = 'seo' ORDER BY config_key");
$configs = [];
foreach ($configStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $configs[(string) $row['config_key']] = (string) ($row['config_value'] ?? '');
}

// O ficheiro robots.txt existente é a fonte actual da política publicada.
// Se não existir, usamos o valor guardado na BD e, por último, um modelo seguro.
$baseUrl = rtrim((string) BASE_URL, '/');
$robotsUrl = $baseUrl . '/robots.txt';
$sitemapUrl = $baseUrl . '/sitemap.php';
$homeUrl = $baseUrl . '/home';

$robotsPath = rtrim((string) ROOT_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'robots.txt';
$liveRobotsTxt = '';
if (is_file($robotsPath) && !is_link($robotsPath) && is_readable($robotsPath)) {
    $liveContent = file_get_contents($robotsPath);
    if (is_string($liveContent) && strlen($liveContent) <= 20000) {
        $liveRobotsTxt = $liveContent;
    }
}

$defaultRobots = implode("\n", [
    '# JMbenga Portfolio — política de rastreio',
    '# Este ficheiro orienta crawlers; não substitui a segurança do servidor.',
    '',
    'User-agent: *',
    'Allow: /',
    'Disallow: /api/',
    'Disallow: /jm-panel/',
    'Disallow: /status/',
    'Disallow: /includes/',
    'Disallow: /database/',
    '',
    'Sitemap: ' . $sitemapUrl,
]);

$robotsTxt = $liveRobotsTxt !== '' ? $liveRobotsTxt : (string) ($configs['robots_txt'] ?? '');
if (trim($robotsTxt) === '') {
    $robotsTxt = $defaultRobots;
}

// O sitemap do projecto é sitemap.php. Substituímos apenas a linha Sitemap
// apresentada no editor para evitar publicar o antigo localhost/sitemap.xml.
$robotsTxt = preg_replace('/^[ \t]*Sitemap[ \t]*:.*$/im', '', $robotsTxt) ?? $robotsTxt;
$robotsTxt = rtrim($robotsTxt) . "\n\nSitemap: " . $sitemapUrl;

$formData = $_SESSION['form_data'] ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['form_success']);
if (!is_array($formData)) {
    $formData = [];
}

$getValue = static function (string $key, string $default = '') use ($formData, $configs): string {
    $value = $formData[$key] ?? $configs[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
};

$siteName = (string) ($db->query("SELECT config_value FROM _site_config WHERE config_key = 'site_name' LIMIT 1")->fetchColumn() ?: 'JMbenga');
$homeMetaTitle = $getValue('home_meta_title', 'José Mbenga — Desenvolvedor Full Stack em Luanda, Angola | JMbenga');
$metaDescription = $getValue('meta_description', 'Portefólio de José Mbenga, desenvolvedor Full Stack em Luanda, Angola. Explore projectos, serviços de desenvolvimento web, APIs, interfaces digitais e formas de contacto.');
$metaKeywords = $getValue('meta_keywords', '');
$ogImage = $getValue('og_image', 'assets/img/profile/og.png');
$googleAnalytics = $getValue('google_analytics', '');
$googleVerification = $getValue('google_site_verification', '');
$homeIndexingEnabled = $getValue('home_indexing_enabled', '1') === '0' ? '0' : '1';

// Pré-visualização segura da imagem: apenas HTTPS absoluto ou caminho local.
$ogImagePreview = '';
$ogParts = parse_url($ogImage);
if (
    is_array($ogParts)
    && isset($ogParts['scheme'], $ogParts['host'])
    && strtolower((string) $ogParts['scheme']) === 'https'
    && filter_var($ogImage, FILTER_VALIDATE_URL)
    && !isset($ogParts['user'])
    && !isset($ogParts['pass'])
) {
    $ogImagePreview = $ogImage;
} elseif (
    $ogImage !== ''
    && !str_starts_with($ogImage, '//')
    && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $ogImage)
    && !preg_match('~(^|/)\.\.?(/|$)|[\x00-\x20]~', $ogImage)
) {
    $ogImagePreview = $baseUrl . '/' . ltrim($ogImage, '/');
}
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

            <div class="seo-status-grid">
                <div class="seo-status-card">
                    <span class="seo-status-icon"><i class="fas fa-globe"></i></span>
                    <div>
                        <span class="seo-status-label">Página principal</span>
                        <strong><?= $homeIndexingEnabled === '1' ? 'Indexação permitida' : 'Noindex activo' ?></strong>
                        <small><?= $homeIndexingEnabled === '1' ? 'Os motores de busca podem indexar a homepage.' : 'A homepage pede aos motores de busca para não a indexarem.' ?></small>
                    </div>
                    <span class="seo-status-pill <?= $homeIndexingEnabled === '1' ? 'is-on' : 'is-off' ?>">
                        <?= $homeIndexingEnabled === '1' ? 'INDEX' : 'NOINDEX' ?>
                    </span>
                </div>
                <div class="seo-status-card">
                    <span class="seo-status-icon"><i class="fas fa-robot"></i></span>
                    <div>
                        <span class="seo-status-label">Robots.txt</span>
                        <strong>Ficheiro publicado na raiz</strong>
                        <small><a href="<?= e($robotsUrl) ?>" target="_blank" rel="noopener noreferrer">Abrir
                                robots.txt</a></small>
                    </div>
                    <span class="seo-status-pill is-on">LIVE</span>
                </div>
                <div class="seo-status-card">
                    <span class="seo-status-icon"><i class="fas fa-sitemap"></i></span>
                    <div>
                        <span class="seo-status-label">Sitemap XML</span>
                        <strong>Sitemap dinâmico disponível</strong>
                        <small><a href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener noreferrer">Abrir
                                sitemap.php</a></small>
                    </div>
                    <span class="seo-status-pill is-on">XML</span>
                </div>
            </div>

            <form id="seoForm" novalidate data-api="<?= e(BASE_URL) ?>/jm-panel/settings/seo-process">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">

                <!-- SECÇÃO 1: CONTROLO DA HOMEPAGE -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-house"></i></span>
                        <div>
                            <h3>Visibilidade da Página Principal</h3>
                            <p>Controla se a homepage pode aparecer nos resultados dos motores de busca.</p>
                        </div>
                    </div>

                    <div class="visibility-control">
                        <div class="visibility-copy">
                            <strong>Permitir indexação da homepage</strong>
                            <p>Quando desactivado, a página principal recebe a directiva <code>noindex,follow</code>.
                                Isto não protege páginas nem substitui autenticação ou permissões.</p>
                        </div>
                        <label class="seo-switch">
                            <input type="checkbox" name="home_indexing_enabled" value="1"
                                <?= $homeIndexingEnabled === '1' ? 'checked' : '' ?>>
                            <span class="seo-switch-track"></span>
                            <span
                                class="seo-switch-label"><?= $homeIndexingEnabled === '1' ? 'Permitida' : 'Desactivada' ?></span>
                        </label>
                    </div>

                    <div class="form-hint seo-integration-note">
                        <i class="fas fa-code"></i>
                        A opção é guardada neste painel. Para a directiva chegar ao HTML público,
                        instala também o ficheiro <code>seo-page-defaults-corrigido.php</code> conforme o README.
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label" for="home_meta_title">Título SEO da Página Principal</label>
                            <input type="text" id="home_meta_title" name="home_meta_title" class="form-control"
                                value="<?= e($homeMetaTitle) ?>" maxlength="140"
                                placeholder="José Mbenga — Desenvolvedor Full Stack em Luanda | JMbenga" required>
                            <span class="form-hint" id="titleCount">0/60 caracteres (referência, não limite)</span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label" for="meta_description">Descrição SEO principal</label>
                            <textarea id="meta_description" name="meta_description" class="form-control textarea-sm"
                                rows="3" maxlength="320"
                                placeholder="Descreve de forma clara o portfólio e o que o visitante encontra."><?= e($metaDescription) ?></textarea>
                            <span class="form-hint" id="metaDescCount">0/160 caracteres (referência, não limite)</span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label" for="meta_keywords">Palavras-chave (opcional)</label>
                            <input type="text" id="meta_keywords" name="meta_keywords" class="form-control"
                                value="<?= e($metaKeywords) ?>" maxlength="1000"
                                placeholder="desenvolvedor full stack, Angola, PHP, MySQL, JavaScript">
                            <span class="form-hint">Campo legado mantido por compatibilidade; não é um factor principal
                                de posicionamento no Google.</span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Canonical automática da homepage</label>
                            <input type="url" class="form-control" value="<?= e($homeUrl) ?>" readonly
                                aria-readonly="true">
                            <span class="form-hint">A URL canónica é calculada pelo cabeçalho público para cada rota.
                                Não aplicamos uma canonical global a todas as páginas.</span>
                        </div>
                    </div>
                </div>

                <!-- SECÇÃO 2: PARTILHA SOCIAL -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-share-nodes"></i></span>
                        <div>
                            <h3>Pré-visualização e Partilha Social</h3>
                            <p>Imagem apresentada quando a página é partilhada em redes sociais e aplicações de
                                mensagens.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="og_image">Imagem Open Graph</label>
                            <input type="text" id="og_image" name="og_image" class="form-control"
                                value="<?= e($ogImage) ?>" maxlength="500" placeholder="assets/img/profile/og.png">
                            <span class="form-hint">Usa um caminho dentro do site ou um URL HTTPS. Recomendação: 1200 ×
                                630 px.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pré-visualização da imagem</label>
                            <div class="og-preview seo-og-preview" id="ogPreview">
                                <?php if ($ogImagePreview !== ''): ?>
                                    <img id="ogPreviewImage" src="<?= e($ogImagePreview) ?>" alt="Imagem Open Graph"
                                        loading="lazy" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div class="seo-og-empty" id="ogPreviewEmpty">Indica uma imagem Open Graph válida.</div>
                                <?php endif; ?>
                                <div class="og-overlay">
                                    <div class="og-site"><?= e($siteName) ?></div>
                                    <div class="og-title" id="ogPreviewTitle"><?= e($homeMetaTitle) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="serp-preview seo-serp-preview">
                        <div class="serp-url" id="serpUrl"><?= e($homeUrl) ?> <i class="fas fa-caret-down"></i></div>
                        <div class="serp-title" id="serpTitle"><?= e($homeMetaTitle) ?></div>
                        <div class="serp-desc" id="serpDesc">
                            <?= e($metaDescription ?: 'A descrição da homepage aparecerá aqui.') ?></div>
                    </div>
                </div>

                <!-- SECÇÃO 3: GOOGLE ANALYTICS E SEARCH CONSOLE -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h3>Medição e Verificação</h3>
                            <p>Configura o Google Analytics 4 e a verificação de propriedade do Search Console.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="google_analytics">Google Analytics 4 — Measurement ID</label>
                            <div class="input-with-icon">
                                <i class="fas fa-chart-bar"></i>
                                <input type="text" id="google_analytics" name="google_analytics" class="form-control"
                                    value="<?= e($googleAnalytics) ?>" maxlength="24" placeholder="G-XXXXXXXXXX">
                            </div>
                            <span class="form-hint">Aceita o ID GA4 no formato G-XXXXXXXXXX. O código só será carregado
                                depois da integração no cabeçalho público.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="google_site_verification">Google Search Console —
                                Verification</label>
                            <input type="text" id="google_site_verification" name="google_site_verification"
                                class="form-control" value="<?= e($googleVerification) ?>" maxlength="255"
                                placeholder="Token fornecido pelo Search Console">
                            <span class="form-hint">Cola apenas o conteúdo do atributo content, não a tag HTML
                                inteira.</span>
                        </div>
                    </div>
                    <div class="form-hint seo-integration-note">
                        <i class="fas fa-plug"></i>
                        Os IDs ficam guardados na base de dados. O ficheiro <code>seo-head-tags.php</code> acrescenta
                        as tags ao <code>&lt;head&gt;</code> público quando integrado conforme o README.
                    </div>
                </div>

                <!-- SECÇÃO 4: ROBOTS.TXT -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-robot"></i></span>
                        <div>
                            <h3>Política de Crawling — robots.txt</h3>
                            <p>Define quais os caminhos que os crawlers podem ou não solicitar.</p>
                        </div>
                    </div>
                    <div class="robots-warning">
                        <i class="fas fa-triangle-exclamation"></i>
                        <div>
                            <strong>Importante: robots.txt não é uma barreira de segurança.</strong>
                            <p>Não uses <code>Disallow: /</code> para esconder a homepage se queres que os motores
                                vejam uma directiva <code>noindex</code>. Um URL bloqueado no robots.txt pode não ser
                                rastreado para ler a meta tag. Nunca coloques segredos neste ficheiro.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="robots_txt">Conteúdo publicado de robots.txt</label>
                        <textarea id="robots_txt" name="robots_txt" class="form-control textarea-code" rows="15"
                            maxlength="20000" spellcheck="false"><?= e($robotsTxt) ?></textarea>
                        <div class="robots-footer">
                            <span class="form-hint" id="robotsCount">0/20000 caracteres</span>
                            <span class="form-hint">Ao guardar, o painel actualiza também o ficheiro físico
                                <code>robots.txt</code> na raiz.</span>
                        </div>
                    </div>
                    <div class="robots-links">
                        <a href="<?= e($robotsUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-secondary">
                            <i class="fas fa-file-lines"></i> Ver robots.txt publicado
                        </a>
                        <a href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-secondary">
                            <i class="fas fa-sitemap"></i> Ver sitemap XML
                        </a>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Configurações SEO
                    </button>
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

        .seo-status-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .8rem;
            margin: 0 0 1.4rem;
        }

        .seo-status-card {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: 1rem;
            min-width: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .seo-status-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--accent-glow);
            color: var(--accent);
        }

        .seo-status-card>div {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .seo-status-label {
            color: var(--text-muted);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .seo-status-card strong {
            color: var(--text);
            font-size: .82rem;
            line-height: 1.35;
        }

        .seo-status-card small {
            color: var(--text-muted);
            font-size: .7rem;
            overflow-wrap: anywhere;
        }

        .seo-status-card small a {
            color: var(--accent);
            text-decoration: none;
        }

        .seo-status-pill {
            margin-left: auto;
            flex: 0 0 auto;
            align-self: flex-start;
            border-radius: 99px;
            padding: 3px 7px;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .seo-status-pill.is-on {
            color: #34d399;
            background: rgba(16, 185, 129, .12);
        }

        .seo-status-pill.is-off {
            color: #f87171;
            background: rgba(239, 68, 68, .12);
        }

        .visibility-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.2rem;
            padding: 1.1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--bg);
        }

        .visibility-copy {
            min-width: 0;
        }

        .visibility-copy strong {
            font-size: .9rem;
            display: block;
            margin-bottom: .25rem;
        }

        .visibility-copy p {
            color: var(--text-muted);
            font-size: .76rem;
            line-height: 1.55;
            max-width: 700px;
        }

        .visibility-copy code,
        .robots-warning code {
            color: var(--accent);
        }

        .seo-switch {
            display: flex;
            align-items: center;
            gap: .55rem;
            cursor: pointer;
            flex: 0 0 auto;
        }

        .seo-switch input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .seo-switch-track {
            width: 44px;
            height: 25px;
            border-radius: 99px;
            background: var(--border);
            position: relative;
            transition: background .2s;
        }

        .seo-switch-track::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 19px;
            height: 19px;
            background: #fff;
            border-radius: 50%;
            transition: transform .2s;
        }

        .seo-switch input:checked+.seo-switch-track {
            background: var(--accent);
        }

        .seo-switch input:checked+.seo-switch-track::after {
            transform: translateX(19px);
        }

        .seo-switch input:focus-visible+.seo-switch-track {
            outline: 3px solid var(--accent-glow);
            outline-offset: 2px;
        }

        .seo-switch-label {
            color: var(--text-dim);
            font-size: .74rem;
            font-weight: 700;
            min-width: 75px;
        }

        .seo-integration-note {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            margin: .9rem 0 1rem;
            line-height: 1.55;
        }

        .seo-integration-note i {
            color: var(--accent);
            margin-top: 2px;
        }

        .seo-og-preview {
            min-height: 150px;
            background: var(--bg);
        }

        .seo-og-preview img {
            min-height: 150px;
            max-height: 220px;
        }

        .seo-og-empty {
            min-height: 150px;
            display: grid;
            place-items: center;
            color: var(--text-muted);
            font-size: .78rem;
            padding: 1rem;
        }

        .seo-serp-preview {
            margin-top: 1.2rem;
        }

        .robots-warning {
            display: flex;
            gap: .75rem;
            padding: 1rem;
            margin-bottom: 1.1rem;
            border-radius: var(--radius);
            border: 1px solid rgba(245, 158, 11, .28);
            background: rgba(245, 158, 11, .07);
            color: var(--text-dim);
        }

        .robots-warning>i {
            color: var(--warning);
            font-size: 1rem;
            margin-top: 2px;
            flex: 0 0 auto;
        }

        .robots-warning strong {
            display: block;
            color: var(--text);
            font-size: .8rem;
            margin-bottom: .2rem;
        }

        .robots-warning p {
            font-size: .75rem;
            line-height: 1.55;
        }

        .robots-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .25rem;
        }

        .robots-links {
            display: flex;
            gap: .6rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .seo-form-error {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .12) !important;
        }

        @media(max-width:900px) {
            .seo-status-grid {
                grid-template-columns: 1fr;
            }

            .seo-status-card {
                align-items: center;
            }
        }

        @media(max-width:600px) {
            .visibility-control {
                align-items: flex-start;
                flex-direction: column;
            }

            .seo-switch {
                width: 100%;
            }

            .robots-footer {
                flex-direction: column;
            }

            .robots-links .btn {
                width: 100%;
                justify-content: center;
            }

            .seo-status-pill {
                margin-left: auto;
            }
        }
    </style>

    <script>
        (() => {
            'use strict';

            const form = document.getElementById('seoForm');
            if (!form) return;

            const submitBtn = document.getElementById('submitBtn');
            const titleInput = document.getElementById('home_meta_title');
            const descInput = document.getElementById('meta_description');
            const canonicalUrl =
                <?= json_encode($homeUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const robotsInput = document.getElementById('robots_txt');
            const ogImageInput = document.getElementById('og_image');
            const indexingSwitch = document.querySelector('[name="home_indexing_enabled"]');
            const indexingLabel = document.querySelector('.seo-switch-label');

            function toast(message, type = 'success') {
                let container = document.getElementById('toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-container';
                    container.style.cssText =
                        'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:min(420px,calc(100vw - 2rem))';
                    document.body.appendChild(container);
                }

                const node = document.createElement('div');
                node.className = `toast toast-${type}`;
                const icon = document.createElement('i');
                icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}`;
                const text = document.createElement('span');
                text.textContent = String(message || 'Ocorreu um erro.');
                node.append(icon, text);
                container.appendChild(node);

                window.setTimeout(() => {
                    node.style.opacity = '0';
                    node.style.transform = 'translateX(16px)';
                    window.setTimeout(() => node.remove(), 250);
                }, 4500);
            }

            function setCount(elementId, current, limit, label) {
                const element = document.getElementById(elementId);
                if (!element) return;
                element.textContent = `${current}/${limit} ${label}`;
                element.style.color = current > limit ? 'var(--danger)' : 'var(--text-muted)';
            }

            function updatePreviews() {
                const title = (titleInput?.value || '').trim() || 'Título da página principal';
                const description = (descInput?.value || '').trim() || 'A descrição da homepage aparecerá aqui.';
                const serpTitle = document.getElementById('serpTitle');
                const serpDesc = document.getElementById('serpDesc');
                const ogTitle = document.getElementById('ogPreviewTitle');

                if (serpTitle) serpTitle.textContent = title;
                if (serpDesc) serpDesc.textContent = description.length > 160 ? `${description.slice(0, 157)}…` :
                    description;
                if (ogTitle) ogTitle.textContent = title;

                setCount('titleCount', titleInput?.value.length || 0, 60, 'caracteres (referência)');
                setCount('metaDescCount', descInput?.value.length || 0, 160, 'caracteres (referência)');
                setCount('robotsCount', robotsInput?.value.length || 0, 20000, 'caracteres');
            }

            function updateIndexingLabel() {
                if (indexingLabel && indexingSwitch) {
                    indexingLabel.textContent = indexingSwitch.checked ? 'Permitida' : 'Desactivada';
                }
            }

            function validOgPreviewValue(value) {
                const candidate = String(value || '').trim();
                if (!candidate || candidate.startsWith('//') || /[\u0000-\u0020]/.test(candidate)) return '';
                try {
                    const base = (document.documentElement.dataset.baseUrl || window.location.origin).replace(/\/+$/,
                        '') + '/';
                    const isAbsolute = /^https:\/\//i.test(candidate);
                    const resolved = isAbsolute ? candidate : base + candidate.replace(/^\/+/, '');
                    const parsed = new URL(resolved);
                    if (parsed.protocol !== 'https:' && !(parsed.protocol === 'http:' && ['localhost', '127.0.0.1']
                            .includes(parsed.hostname))) return '';
                    return parsed.href;
                } catch (_) {
                    return '';
                }
            }

            titleInput?.addEventListener('input', updatePreviews);
            descInput?.addEventListener('input', updatePreviews);
            robotsInput?.addEventListener('input', updatePreviews);
            indexingSwitch?.addEventListener('change', updateIndexingLabel);

            ogImageInput?.addEventListener('change', () => {
                const imageUrl = validOgPreviewValue(ogImageInput.value);
                const preview = document.getElementById('ogPreview');
                if (!preview) return;
                let img = document.getElementById('ogPreviewImage');
                let empty = document.getElementById('ogPreviewEmpty');

                if (!imageUrl) {
                    if (img) img.remove();
                    if (!empty) {
                        empty = document.createElement('div');
                        empty.id = 'ogPreviewEmpty';
                        empty.className = 'seo-og-empty';
                        empty.textContent = 'Indica uma imagem HTTPS ou um caminho local válido.';
                        preview.prepend(empty);
                    }
                    return;
                }

                if (!img) {
                    img = document.createElement('img');
                    img.id = 'ogPreviewImage';
                    img.alt = 'Imagem Open Graph';
                    img.loading = 'lazy';
                    preview.prepend(img);
                }
                if (empty) empty.remove();
                img.style.display = 'block';
                img.src = imageUrl;
            });

            updatePreviews();
            updateIndexingLabel();

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                form.querySelectorAll('.seo-form-error').forEach(el => el.classList.remove(
                    'seo-form-error'));
                const requiredTitle = (titleInput?.value || '').trim();
                if (requiredTitle.length < 2) {
                    titleInput?.classList.add('seo-form-error');
                    toast('O título SEO da homepage deve ter pelo menos 2 caracteres.', 'error');
                    titleInput?.focus();
                    return;
                }

                const robotsContent = robotsInput?.value || '';
                if (/^\s*Disallow\s*:\s*\/\s*$/im.test(robotsContent)) {
                    const confirmed = window.confirm(
                        'A política robots.txt contém Disallow: / e pode impedir os crawlers de visitar o site inteiro. Queres mesmo publicar esta política?'
                    );
                    if (!confirmed) return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';

                try {
                    const response = await fetch(form.dataset.api, {
                        method: 'POST',
                        body: new window.FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': form.querySelector('[name="csrf_token"]').value
                        },
                        credentials: 'same-origin'
                    });

                    let data;
                    try {
                        data = await response.json();
                    } catch (_) {
                        throw new Error(
                            `O servidor devolveu uma resposta inválida (HTTP ${response.status}).`);
                    }

                    if (!response.ok || !data.success) {
                        const errors = Array.isArray(data.errors) ? data.errors : [];
                        if (errors.length) errors.forEach(error => toast(error, 'error'));
                        else toast(data.message || `Não foi possível guardar (HTTP ${response.status}).`,
                            'error');
                        return;
                    }

                    toast(data.message || 'Configurações SEO guardadas.', 'success');
                    window.setTimeout(() => window.location.reload(), 1000);
                } catch (error) {
                    toast(error?.message || 'Erro de rede ao guardar as configurações.', 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Configurações SEO';
                }
            });
        })();
    </script>
</body>

</html>