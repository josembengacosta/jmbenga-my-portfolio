<?php
// ══════════════════════════════════════════════════════════════
// about.php — JMbenga Portfolio
// Página Sobre: biografia, competências, serviços e contacto.
// ══════════════════════════════════════════════════════════════

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visitor.php';

check_maintenance_mode($pdo);

$cfg = static function (string $key, string $default = '') use ($pdo): string {
    return get_config($pdo, $key, $default);
};

/* ── Configuração e metadados ─────────────────────────────── */
$accentCandidate = trim($cfg('accent_color', '#2563eb'));
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accentCandidate)
    ? $accentCandidate
    : '#2563eb';

$whatsappCandidate = trim($cfg('whatsapp_url'));
$whatsapp = '';
if ($whatsappCandidate !== '' && filter_var($whatsappCandidate, FILTER_VALIDATE_URL)) {
    $scheme = strtolower((string) parse_url($whatsappCandidate, PHP_URL_SCHEME));
    if ($scheme === 'https') {
        $whatsapp = $whatsappCandidate;
    }
}

$whatsappMessageUrl = '';
if ($whatsapp !== '') {
    $separator = str_contains($whatsapp, '?') ? '&' : '?';
    $whatsappMessageUrl = $whatsapp . $separator . http_build_query(
        ['text' => 'Olá José, vim do seu portfólio.'],
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

$cvFile = trim($cfg('cv_file'));
$hasCv = $cvFile !== '';
$profile = get_public_profile_photo($pdo, $cfg('photo_profile'));

$page_title = 'Sobre José Mbenga — Percurso e Competências | JMbenga';
$page_desc = $cfg(
    'about_meta',
    'Conheça José Mbenga, o seu percurso na tecnologia, competências e serviços digitais em Luanda, Angola.'
);
$page_section = 'about';

/*
 * O header.php actual aceita $extra_head. Bufferiza os recursos específicos
 * desta página para que sejam incluídos dentro do <head>, não depois dele.
 */
ob_start();
?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/about.css">
<style>
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: <?=e($accent) ?>;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>;
    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(<?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>, .35);
    --text: #e8edf5;
    --text-dim: #8899b4;
    --text-muted: #4a5878;
    --shadow: 0 8px 40px rgba(0, 0, 0, .5);
    --shadow-acc: 0 0 40px var(--accent-glow);
    --font-head: 'Syne', 'Inter', sans-serif;
    --font-body: 'DM Sans', 'Inter', sans-serif;
    --font-mono: 'DM Mono', 'Fira Code', monospace;
    --radius: 12px;
    --radius-lg: 20px;
    --ease: cubic-bezier(.16, 1, .3, 1);
    --nav-h: 70px;
    --grad: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    --grad-text: linear-gradient(135deg, var(--accent), #a78bfa);
}

[data-theme="light"] {
    --bg: #f0f4fb;
    --bg-2: #e4eaf6;
    --bg-card: #ffffff;
    --bg-glass: rgba(255, 255, 255, .85);
    --border: rgba(0, 0, 0, .07);
    --border-acc: rgba(<?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>, .25);
    --text: #0f172a;
    --text-dim: #475569;
    --text-muted: #94a3b8;
    --shadow: 0 8px 40px rgba(0, 0, 0, .12);
}

.hero-blob {
    position: absolute;
    top: -200px;
    right: -100px;
    z-index: 0;
    width: 700px;
    height: 700px;
    border-radius: 50%;
    background: radial-gradient(circle, <?=e($accent) ?>22 0%, transparent 65%);
    animation: blob 14s ease-in-out infinite alternate;
    pointer-events: none;
}

@media (max-width: 768px) {
    .about-hero-text h1 {
        font-size: 1.6rem !important;
        line-height: 1.3 !important;
        letter-spacing: normal !important;
        margin-bottom: 12px !important;
    }
}
</style>
<?php
$extra_head = (string) ob_get_clean();

require_once __DIR__ . '/includes/header.php';
?>

<body>
    <?php if ($whatsappMessageUrl !== ''): ?>
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsappMessageUrl) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
            <span>Vamos conversar?</span>
        </a>
        <button type="button" class="close-whatsapp" onclick="this.closest('#whatsappFloat').hidden = true"
            aria-label="Fechar contacto do WhatsApp">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero da página Sobre -->
    <section id="about-hero">
        <div class="container">
            <div class="about-hero-inner">
                <div class="about-hero-text reveal">
                    <span class="tag">Sobre mim</span>
                    <h1>Transformando ideias em <span>experiências digitais</span></h1>
                    <p><?= e($cfg(
                        'about_summary',
                        'Sou José Mbenga, desenvolvedor Full Stack. Trabalho na criação de experiências digitais, interfaces intuitivas e sistemas orientados às necessidades de cada projecto.'
                    )) ?></p>
                    <div class="about-cta" style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= e(BASE_URL) ?>/contact" class="btn btn-primary">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i> Entrar em contacto
                        </a>
                        <?php if ($hasCv): ?>
                        <a href="<?= e(BASE_URL) ?>/cv" target="_blank" rel="noopener noreferrer"
                            class="btn btn-primary">
                            <i class="fas fa-eye" aria-hidden="true"></i> Ver currículo
                        </a>
                        <a href="<?= e(BASE_URL) ?>/download/cv" class="btn btn-outline">
                            <i class="fas fa-download" aria-hidden="true"></i> Descarregar CV
                        </a>
                        <?php endif; ?>
                        <a href="<?= e(BASE_URL) ?>/servicos" class="btn btn-outline">
                            <i class="fas fa-layer-group" aria-hidden="true"></i> Ver serviços
                        </a>
                    </div>
                </div>

                <div class="reveal" style="transition-delay:.15s">
                    <?php if ($profile): ?>
                    <img src="<?= e($profile['url']) ?>" alt="Fotografia de José Mbenga" class="about-hero-avatar">
                    <?php else: ?>
                    <div class="about-hero-placeholder" aria-label="Fotografia não disponível">
                        <i class="fas fa-user-tie" aria-hidden="true"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Biografia detalhada -->
    <section class="section" id="about-history">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">A minha história</span>
                <h2 class="section-title">Da paixão pelo código à <span class="text-gradient">excelência Full
                        Stack</span></h2>
                <p class="section-sub">Uma jornada de aprendizagem contínua e evolução profissional.</p>
            </div>

            <div class="reveal" style="max-width:800px;margin:0 auto">
                <?php
                $aboutLong = trim($cfg('about_long'));
                $paragraphs = $aboutLong !== ''
                    ? array_values(array_filter(
                        array_map('trim', preg_split('/\R/u', $aboutLong) ?: []),
                        static fn(string $paragraph): bool => $paragraph !== ''
                    ))
                    : [
                        'Sou José Mbenga da Costa, de Luanda, Angola. O meu percurso na tecnologia começou com a curiosidade de construir páginas web e perceber como o código transforma ideias em experiências que podem ser utilizadas no navegador.',
                        'Ao longo do meu percurso, fui aprofundando conhecimentos em HTML, CSS, JavaScript, PHP e MySQL, explorando também ferramentas e tecnologias utilizadas no desenvolvimento de aplicações e interfaces digitais.',
                        'Procuro continuar a aprender e a aperfeiçoar a forma como planeio e desenvolvo soluções, com atenção à funcionalidade, à clareza da interface e à manutenção do código.',
                        'Acredito que uma boa solução digital deve responder às necessidades reais das pessoas, ser compreensível para quem a mantém e ter um âmbito bem definido desde o início.'
                    ];
                ?>
                <?php foreach ($paragraphs as $paragraph): ?>
                <p class="about-text" style="font-size:1rem;color:var(--text-dim);line-height:1.8;margin-bottom:18px">
                    <?= e($paragraph) ?>
                </p>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Competências técnicas -->
    <section class="section section-alt" id="about-skills">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Competências técnicas</span>
                <h2 class="section-title">Tecnologias e ferramentas <span class="text-gradient">utilizadas</span></h2>
                <p class="section-sub">Tecnologias registadas no portfólio e apresentadas por categoria.</p>
            </div>

            <div class="reveal">
                <?php
                $skills = [];
                try {
                    $skillsRaw = $pdo->query(
                        "SELECT name_skill, category_skill, percentage_skill, icon_skill
                         FROM _skills
                         WHERE is_visible = 1
                         ORDER BY category_skill ASC, display_order ASC, name_skill ASC"
                    )->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($skillsRaw as $skill) {
                        $category = trim((string) ($skill['category_skill'] ?? '')) ?: 'Outras';
                        $skills[$category][] = $skill;
                    }
                } catch (Throwable $e) {
                    error_log('[about.php] Não foi possível carregar as competências.');
                    $skills = [];
                }
                ?>

                <?php if ($skills): ?>
                <?php foreach ($skills as $category => $items): ?>
                <h3
                    style="font-family:var(--font-head);font-size:1.2rem;margin:32px 0 16px;text-transform:capitalize;color:var(--accent)">
                    <i class="fas fa-code" style="margin-right:8px" aria-hidden="true"></i> <?= e(ucfirst($category)) ?>
                </h3>
                <div class="skills-panel active"
                    style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:24px">
                    <?php foreach ($items as $skill): ?>
                    <?php $percentage = max(0, min(100, (int) ($skill['percentage_skill'] ?? 0))); ?>
                    <div class="skill-card"
                        style="background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:18px 20px">
                        <div class="skill-row"
                            style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                            <div class="skill-name-wrap" style="display:flex;align-items:center;gap:9px">
                                <div class="skill-icon-wrap"
                                    style="width:34px;height:34px;border-radius:8px;background:var(--accent-glow);border:1px solid var(--border-acc);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:.88rem">
                                    <i class="<?= e((string) ($skill['icon_skill'] ?: 'fas fa-code')) ?>"
                                        aria-hidden="true"></i>
                                </div>
                                <span class="skill-name"
                                    style="font-weight:600;font-size:.9rem"><?= e((string) $skill['name_skill']) ?></span>
                            </div>
                            <span class="skill-pct"
                                style="font-family:var(--font-mono);font-size:.76rem;color:var(--accent)"><?= $percentage ?>%</span>
                        </div>
                        <div class="skill-bar-bg"
                            style="height:3px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden">
                            <div class="skill-bar-fill" data-width="<?= $percentage ?>"
                                style="height:100%;width:0;background:linear-gradient(90deg,var(--accent),#818cf8);border-radius:2px;transition:width 1.1s var(--ease)">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools" aria-hidden="true"></i>
                    <h3>Competências em preparação</h3>
                    <p>As competências técnicas ainda não estão disponíveis para apresentação.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Serviços: alinhados com as cinco categorias do catálogo -->
    <section class="section" id="about-services">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Serviços</span>
                <h2 class="section-title">Como posso ajudar <span class="text-gradient">o seu projecto</span></h2>
                <p class="section-sub">Explore as áreas de trabalho e consulte os respectivos detalhes antes de
                    solicitar um orçamento.</p>
            </div>

            <?php
            $services = [
                [
                    'icon' => 'fa-globe',
                    'title' => 'Desenvolvimento Web',
                    'desc' => 'Sites, landing pages, portfólios e sistemas web adaptados ao âmbito de cada projecto.',
                    'url' => BASE_URL . '/services/web-development',
                ],
                [
                    'icon' => 'fa-mobile-screen-button',
                    'title' => 'Aplicações Mobile',
                    'desc' => 'Análise e desenvolvimento de aplicações conforme as plataformas e funcionalidades necessárias.',
                    'url' => BASE_URL . '/services/mobile-apps',
                ],
                [
                    'icon' => 'fa-plug',
                    'title' => 'APIs & Backend',
                    'desc' => 'APIs, integrações, bases de dados e lógica de servidor para sistemas digitais.',
                    'url' => BASE_URL . '/services/api-backend',
                ],
                [
                    'icon' => 'fa-palette',
                    'title' => 'UI/UX Design',
                    'desc' => 'Estrutura e desenho de interfaces com foco na clareza e na experiência de utilização.',
                    'url' => BASE_URL . '/services/ui-ux-design',
                ],
                [
                    'icon' => 'fa-screwdriver-wrench',
                    'title' => 'Manutenção',
                    'desc' => 'Correcções, actualizações e melhorias de sistemas existentes conforme as condições acordadas.',
                    'url' => BASE_URL . '/services/maintenance',
                ],
            ];
            ?>
            <div class="services-grid reveal">
                <?php foreach ($services as $service): ?>
                <article class="service-card">
                    <i class="fas <?= e($service['icon']) ?>" aria-hidden="true"></i>
                    <h3><?= e($service['title']) ?></h3>
                    <p><?= e($service['desc']) ?></p>
                    <a class="service-card-link" href="<?= e($service['url']) ?>">
                        Conhecer serviço <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <div class="about-services-cta" style="text-align:center;margin-top:28px">
                <a href="<?= e(BASE_URL) ?>/servicos" class="btn btn-outline">
                    <i class="fas fa-layer-group" aria-hidden="true"></i> Consultar todos os serviços
                </a>
            </div>
        </div>
    </section>

    <!-- Abordagem de trabalho -->
    <section class="section section-alt" id="about-process">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Processo</span>
                <h2 class="section-title">Como <span class="text-gradient">trabalho</span></h2>
                <p class="section-sub">Etapas para clarificar as necessidades, acordar o âmbito e acompanhar o
                    desenvolvimento.</p>
            </div>
            <div class="approach-grid reveal">
                <div class="approach-card">
                    <div class="step">1</div>
                    <h4>Descoberta</h4>
                    <p>Compreender as necessidades, os objectivos e o público do projecto.</p>
                </div>
                <div class="approach-card">
                    <div class="step">2</div>
                    <h4>Planeamento</h4>
                    <p>Definir o âmbito, as funcionalidades, as tecnologias e as etapas de trabalho.</p>
                </div>
                <div class="approach-card">
                    <div class="step">3</div>
                    <h4>Desenvolvimento</h4>
                    <p>Implementar a solução de acordo com o escopo aprovado.</p>
                </div>
                <div class="approach-card">
                    <div class="step">4</div>
                    <h4>Verificação</h4>
                    <p>Verificar as funcionalidades e corrigir os problemas identificados no âmbito acordado.</p>
                </div>
                <div class="approach-card">
                    <div class="step">5</div>
                    <h4>Entrega</h4>
                    <p>Preparar a entrega e, quando contratado, a publicação no ambiente definido.</p>
                </div>
                <div class="approach-card">
                    <div class="step">6</div>
                    <h4>Acompanhamento</h4>
                    <p>Definir o suporte e a manutenção conforme a proposta acordada.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA final -->
    <section class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:100px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Vamos trabalhar <span class="text-gradient">juntos?</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Descreva o que pretende construir ou melhorar para avaliarmos o âmbito e os próximos passos.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= e(BASE_URL) ?>/contact" class="btn btn-primary"><i class="fas fa-paper-plane"
                        aria-hidden="true"></i> Solicitar orçamento</a>
                <?php if ($whatsapp !== ''): ?>
                <a href="<?= e($whatsappMessageUrl) ?>" target="_blank" rel="noopener noreferrer"
                    class="btn btn-secondary">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i> Conversar no WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    require __DIR__ . '/includes/bottom-nav.php';
    ?>
</body>

</html>