<?php
// ══════════════════════════════════════════════════════════════
// ui-ux-design.php — JMbenga Portfolio v3.0
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/visitor.php';

check_maintenance_mode($pdo);

$cfg = function (string $key, string $default = ''): string {
    global $pdo;
    return get_config($pdo, $key, $default);
};

$accent = trim($cfg('accent_color', '#2563eb'));
if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent)) {
    $accent = '#2563eb';
}
if (strlen($accent) === 4) {
    $accent = '#' . $accent[1] . $accent[1] . $accent[2] . $accent[2] . $accent[3] . $accent[3];
}
$accentRgb = implode(', ', [
    hexdec(substr($accent, 1, 2)),
    hexdec(substr($accent, 3, 2)),
    hexdec(substr($accent, 5, 2)),
]);

$isHttpsUrl = static function (string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && strtolower($parts['scheme']) === 'https';
};

$whatsappCandidate = trim($cfg('whatsapp_url'));
$whatsapp = $isHttpsUrl($whatsappCandidate) ? $whatsappCandidate : '';
$whatsappMessage = 'Olá José, vim do seu portfólio';
$whatsappLink = $whatsapp !== ''
    ? $whatsapp . (str_contains($whatsapp, '?') ? '&' : '?') . 'text=' . rawurlencode($whatsappMessage)
    : '';
$email = trim($cfg('email_contact'));

$page_title = 'UI/UX Design — José Mbenga | JMbenga';
$page_desc  = 'Serviços de UI/UX Design, estruturação de interfaces, prototipagem e sistemas de design para produtos digitais. Orçamento definido conforme o âmbito do projecto.';
$meta_desc = $page_desc; // Compatibilidade com cabeçalhos que usam meta_desc.
$page_section = 'services';

// Mostrar apenas projectos de design publicados e existentes na base de dados.
$design_projects = [];
try {
    $design_projects = $pdo->query("        SELECT title_project, slug_project, summary_project, cover_project
        FROM _projects
        WHERE status_project = 'published' AND category_project = 'design'
        ORDER BY is_featured DESC, display_order ASC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('JMbenga UI/UX: não foi possível carregar os projectos publicados: ' . $exception->getMessage());
}
?>
<?php

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/ui-ux.css">

<style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: #1d4ed8;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=e($accentRgb) ?>;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(<?=e($accentRgb) ?>, .35);
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
    --border-acc: rgba(<?=e($accentRgb) ?>, .25);
    --text: #0f172a;
    --text-dim: #475569;
    --text-muted: #94a3b8;
    --shadow: 0 8px 40px rgba(0, 0, 0, .12);
}

/* Blur de luz */
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
</style>

</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($whatsapp !== ''): ?>
    <div class=" whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>



    <!-- Hero -->
    <section id="service-hero">
        <div class="container">
            <div class="service-hero-inner">
                <div class="service-hero-text reveal">
                    <span class="tag">Serviço Premium</span>
                    <h1>UI/UX Design <span>que Converte</span></h1>
                    <p class="lead">Crio interfaces intuitivas e experiências digitais que ajudam os utilizadores a
                        concluir tarefas e dão clareza à apresentação da sua marca.</p>
                    <div class="showcase-badges">
                        <span class="showcase-badge"><i class="fas fa-paint-brush"></i> UI Design</span>
                        <span class="showcase-badge"><i class="fas fa-users"></i> UX Research</span>
                        <span class="showcase-badge"><i class="fas fa-mobile-alt"></i> Mobile First</span>
                    </div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline"><i class="fab fa-whatsapp"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-palette"></i></div>
                    <div class="hero-stats-row" aria-label="Áreas de trabalho em UI/UX">
                        <div class="hero-stat-mini">
                            <div class="num">UI</div>
                            <div class="lbl">Interface</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">UX</div>
                            <div class="lbl">Experiência</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">Pro</div>
                            <div class="lbl">Protótipos</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">DS</div>
                            <div class="lbl">sistemas de design</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Por que Investir em Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Por que Investir?</span>
                <h2 class="section-title">Design Não é Apenas Estética — É <span class="text-gradient">Estratégia de
                        Negócio</span></h2>
            </div>
            <div class="why-grid reveal">
                <div class="why-card">
                    <div class="why-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Conversão & ROI</h3>
                    <p>Um bom design pode tornar a navegação mais clara, ajudar os utilizadores a concluir tarefas e
                        apoiar os objectivos do negócio. Os resultados devem ser avaliados com dados do próprio produto.
                    </p>
                    <ul>
                        <li>Apoio à conversão</li>
                        <li>Melhoria da navegação</li>
                        <li>Percursos de utilização mais claros</li>
                        <li>Menos fricção na utilização</li>
                    </ul>
                </div>
                <div class="why-card">
                    <div class="why-icon"><i class="fas fa-smile"></i></div>
                    <h3>Experiência do Utilizador</h3>
                    <p>UX bem pensada reduz frustração, aumenta satisfação e cria lealdade à marca.</p>
                    <ul>
                        <li>Usabilidade intuitiva</li>
                        <li>Acessibilidade inclusiva</li>
                        <li>Navegação eficiente</li>
                        <li>Feedback positivo constante</li>
                    </ul>
                </div>
                <div class="why-card">
                    <div class="why-icon"><i class="fas fa-trophy"></i></div>
                    <h3>Diferenciação Competitiva</h3>
                    <p>Em mercados saturados, design excelente é o que separa produtos medíocres de líderes.</p>
                    <ul>
                        <li>Identidade visual única</li>
                        <li>Posicionamento premium</li>
                        <li>Reconhecimento de marca</li>
                        <li>Preferência do utilizador</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Serviços de UI/UX Design -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Serviços</span>
                <h2 class="section-title">Soluções Completas de <span class="text-gradient">UI/UX Design</span></h2>
                <p class="section-sub">Do design visual à pesquisa com utilizadores, entrego soluções completas para
                    produtos
                    digitais.</p>
            </div>
            <div class="services-grid reveal">
                <?php
                $services = [
                    ['icon' => 'fa-mobile-alt', 'title' => 'UI Design Mobile', 'desc' => 'Interfaces responsivas e intuitivas para apps iOS e Android. Design systems, componentes reutilizáveis, animações fluidas.', 'tags' => ['iOS', 'Android', 'React Native', 'sistemas de design']],
                    ['icon' => 'fa-globe', 'title' => 'Web Design Responsivo', 'desc' => 'Websites e aplicações web com foco em usabilidade e conversão. Design adaptativo para diferentes dispositivos.', 'tags' => ['Responsivo', 'Landing Pages', 'E-commerce']],
                    ['icon' => 'fa-chart-bar', 'title' => 'Dashboard & Data Viz', 'desc' => 'Interfaces complexas para dashboards empresariais. Visualização de dados clara e tomada de decisão facilitada.', 'tags' => ['Visualização de dados', 'Admin Panels', 'Analytics']],
                    ['icon' => 'fa-search', 'title' => 'Pesquisa e testes UX', 'desc' => 'Pesquisa com utilizadores, testes de usabilidade, análise de concorrência e definição de personas.', 'tags' => ['Testes com utilizadores', 'Personas', 'Percurso do utilizador']],
                    ['icon' => 'fa-fan', 'title' => 'Brand & Visual Identity', 'desc' => 'Identidade visual completa: logos, paleta de cores, tipografia, iconografia e orientações da marca.', 'tags' => ['Branding', 'Logo Design', 'Guias de estilo']],
                    ['icon' => 'fa-cubes', 'title' => 'Sistemas de Design', 'desc' => 'Sistemas de design escaláveis com componentes reutilizáveis, documentação e design tokens.', 'tags' => ['Component Libraries', 'Design Tokens', 'Documentação']],
                ];
                foreach ($services as $svc): ?>
                <div class="service-card reveal">
                    <div class="icon-circle"><i class="fas <?= e($svc['icon']) ?>"></i></div>
                    <h3><?= e($svc['title']) ?></h3>
                    <p><?= e($svc['desc']) ?></p>
                    <div class="tags">
                        <?php foreach ($svc['tags'] as $t): ?>
                        <span class="tag-sm"><?= e($t) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Processo de Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Metodologia</span>
                <h2 class="section-title">O Meu <span class="text-gradient">Processo de Design</span></h2>
                <p class="section-sub">Processo de design centrado nas necessidades do utilizador e nos objectivos do
                    produto.</p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Descoberta & Pesquisa', 'desc' => 'Entendimento profundo do negócio, utilizadores e concorrência.'],
                    ['num' => 2, 'title' => 'Definição & Estratégia', 'desc' => 'Personas, user journeys, arquitectura da informação.'],
                    ['num' => 3, 'title' => 'Ideação & Wireframes', 'desc' => 'Brainstorming, sketches, wireframes de baixa fidelidade.'],
                    ['num' => 4, 'title' => 'Prototipagem & Testes', 'desc' => 'Protótipos interativos, testes de usabilidade, iterações.'],
                    ['num' => 5, 'title' => 'UI Design & Refinamento', 'desc' => 'Design visual, micro-interacções, design system.'],
                    ['num' => 6, 'title' => 'Entrega para implementação', 'desc' => 'Documentação, specs, colaboração com programadores.'],
                ];
                foreach ($steps as $step): ?>
                <div class="process-step">
                    <div class="step-number"><?= (int) $step['num'] ?></div>
                    <h4><?= e($step['title']) ?></h4>
                    <p><?= e($step['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Entregáveis -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Deliverables</span>
                <h2 class="section-title">O Que Está <span class="text-gradient">Incluído</span></h2>
            </div>
            <div class="deliverables-grid reveal">
                <?php
                $deliverables = [
                    ['icon' => 'fab fa-figma', 'title' => 'Ficheiros de design', 'desc' => 'Formatos editáveis definidos na proposta.'],
                    ['icon' => 'fas fa-redo', 'title' => 'Rondas de revisão', 'desc' => 'Duas rondas incluídas no âmbito aprovado; extras podem ser orçamentados.'],
                    ['icon' => 'fas fa-comments', 'title' => 'Acompanhamento', 'desc' => 'Comunicação e apoio nos limites acordados para o projecto.'],
                    ['icon' => 'fas fa-mobile-alt', 'title' => 'Adaptação a ecrãs', 'desc' => 'Dispositivos e resoluções definidos no âmbito do trabalho.'],
                    ['icon' => 'fas fa-book', 'title' => 'Guia visual', 'desc' => 'Quando previsto no serviço contratado.'],
                    ['icon' => 'fas fa-project-diagram', 'title' => 'Wireframes', 'desc' => 'Estrutura das páginas ou ecrãs, conforme o âmbito.'],
                    ['icon' => 'fas fa-play', 'title' => 'Protótipos', 'desc' => 'Interacções incluídas quando previstas na proposta.'],
                    ['icon' => 'fas fa-code', 'title' => 'Entrega para desenvolvimento', 'desc' => 'Especificações e recursos definidos no orçamento.'],
                ];
                foreach ($deliverables as $d): ?>
                <div class="deliverable-card">
                    <i class="<?= e($d['icon']) ?>"></i>
                    <h4><?= e($d['title']) ?></h4>
                    <p><?= e($d['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Projectos de Design: apenas trabalhos realmente publicados na base de dados -->
    <?php if (!empty($design_projects)): ?>
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Portfólio</span>
                <h2 class="section-title">Projectos de <span class="text-gradient">Design</span></h2>
                <p class="section-sub">Uma selecção de trabalhos de design publicados no portfólio.</p>
            </div>
            <div class="portfolio-grid reveal">
                <?php foreach ($design_projects as $project): ?>
                <article class="portfolio-card">
                    <h3><?= e($project['title_project'] ?? 'Projecto de design') ?></h3>
                    <?php if (!empty($project['summary_project'])): ?>
                    <p><?= e($project['summary_project']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($project['slug_project'])): ?>
                    <a class="btn btn-outline"
                        href="<?= BASE_URL ?>/project/<?= rawurlencode((string) $project['slug_project']) ?>">Ver
                        projecto</a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Princípios de Design -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Filosofia</span>
                <h2 class="section-title">Princípios de <span class="text-gradient">Design</span></h2>
                <p class="section-sub">A filosofia que guia cada decisão de design que tomo.</p>
            </div>
            <div class="principles-grid reveal">
                <?php
                $principles = [
                    ['icon' => 'fas fa-user-circle', 'title' => 'User-Centric', 'desc' => 'O utilizador está no centro de cada decisão. Design baseado em necessidades reais, não em suposições.'],
                    ['icon' => 'fas fa-minus-circle', 'title' => 'Simplicidade', 'desc' => 'Menos é mais. Interfaces limpas, intuitivas e sem elementos desnecessários que distraem.'],
                    ['icon' => 'fas fa-universal-access', 'title' => 'Acessibilidade', 'desc' => 'Consideração de boas práticas de acessibilidade. Requisitos formais e testes de conformidade são definidos no âmbito do projecto.'],
                    ['icon' => 'fas fa-th-large', 'title' => 'Consistência', 'desc' => 'Padrões visuais consistentes criam confiança e reduzem a curva de aprendizagem do utilizador.'],
                    ['icon' => 'fas fa-tachometer-alt', 'title' => 'Performance', 'desc' => 'Design optimizado para velocidade. Cada milissegundo impacta a experiência do utilizador.'],
                    ['icon' => 'fas fa-magic', 'title' => 'Delight', 'desc' => 'Micro-interacções que encantam e criam experiências memoráveis que fidelizam utilizadores.'],
                ];
                foreach ($principles as $p): ?>
                <div class="principle-card">
                    <i class="<?= e($p['icon']) ?>"></i>
                    <h4><?= e($p['title']) ?></h4>
                    <p><?= e($p['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ferramentas de Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Stack</span>
                <h2 class="section-title">Ferramentas de <span class="text-gradient">Design</span></h2>
                <p class="section-sub">Tecnologia de ponta para criar designs excepcionais.</p>
            </div>
            <div class="tools-grid reveal">
                <?php
                $tools = [
                    ['icon' => 'fab fa-figma', 'name' => 'Figma', 'sub' => 'Design e prototipagem'],
                    ['icon' => 'fas fa-pen-nib', 'name' => 'Adobe XD', 'sub' => 'UI/UX Design'],
                    ['icon' => 'fas fa-bezier-curve', 'name' => 'Illustrator', 'sub' => 'Gráficos vectoriais'],
                    ['icon' => 'fas fa-image', 'name' => 'Photoshop', 'sub' => 'Edição de imagem'],
                    ['icon' => 'fas fa-film', 'name' => 'After Effects', 'sub' => 'Design em movimento'],
                    ['icon' => 'fas fa-cube', 'name' => 'Blender', 'sub' => '3D Design'],
                    ['icon' => 'fas fa-hand-pointer', 'name' => 'InVision', 'sub' => 'Prototipagem'],
                ];
                foreach ($tools as $tool): ?>
                <div class="tool-card reveal">
                    <i class="<?= e($tool['icon']) ?>"></i>
                    <h4><?= e($tool['name']) ?></h4>
                    <small><?= e($tool['sub']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- sistemas de design -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">sistemas de design</span>
                <h2 class="section-title">Sistemas Escaláveis para <span class="text-gradient">Produtos que
                        Crescem</span>
                </h2>
            </div>
            <div class="ds-grid reveal">
                <div>
                    <p style="font-size:1rem;color:var(--text-dim);line-height:1.8;margin-bottom:24px">
                        sistemas de design não são apenas bibliotecas de componentes. São ecossistemas vivos que
                        garantem
                        consistência, aceleram desenvolvimento e escalam com o seu produto.
                    </p>
                    <div class="ds-components">
                        <?php foreach (['Buttons', 'Inputs', 'Cards', 'Modals', 'Navigation', 'Data Tables', 'Forms', 'Alerts'] as $comp): ?>
                        <span class="ds-comp"><?= e($comp) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="ds-stat">
                        <div class="big">Reutilização</div>
                        <div class="lbl">Componentes partilhados ajudam a manter consistência e podem reduzir retrabalho
                            conforme a dimensão do produto.</div>
                    </div>
                </div>
                <div>
                    <h4 style="font-family:var(--font-head);margin-bottom:20px">O que inclui um sistema de design:</h4>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:12px">
                        <?php foreach (['Biblioteca de componentes reutilizáveis', 'Tokens de design (cores, tipografia e espaçamento)', 'Documentação acordada no âmbito do trabalho', 'Orientações de uso e boas práticas', 'Considerações de acessibilidade', 'Recursos para a equipa de desenvolvimento, quando incluídos', 'Processo de versionamento, quando necessário'] as $item): ?>
                        <li style="display:flex;align-items:center;gap:10px;font-size:.92rem;color:var(--text-dim)"><i
                                class="fas fa-check-circle" style="color:var(--accent)"></i><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Investimento: preços publicados apenas quando existe referência aprovada -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Preços e <span class="text-gradient">Âmbito do Serviço</span></h2>
                <p class="section-sub">O valor depende do número de ecrãs, do estado actual do produto, da investigação
                    necessária e dos entregáveis acordados.</p>
            </div>
            <div class="pricing-grid reveal">
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Redesign de site existente</h3>
                    <div class="price">60.000–180.000 <span>Kz / projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Revisão visual e de navegação</li>
                        <li><i class="fas fa-check"></i>Melhoria da hierarquia da informação</li>
                        <li><i class="fas fa-check"></i>Ajustes de experiência e responsividade conforme o âmbito</li>
                        <li><i class="fas fa-check"></i>Âmbito e entregáveis definidos por escrito</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Redesign+de+site" class="btn btn-outline"
                        style="width:100%">Pedir orçamento</a>
                </div>
                <div class="pricing-card featured">
                    <h3 style="font-family:var(--font-head)">UI/UX para produto digital</h3>
                    <div class="price">Sob orçamento</div>
                    <ul>
                        <li><i class="fas fa-check"></i>Fluxos e estrutura de ecrãs</li>
                        <li><i class="fas fa-check"></i>Wireframes e protótipos conforme o âmbito</li>
                        <li><i class="fas fa-check"></i>Interface visual e estados interactivos</li>
                        <li><i class="fas fa-check"></i>Testes de usabilidade quando contratados</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"
                        style="width:100%">Pedir orçamento</a>
                </div>
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Design System</h3>
                    <div class="price">Sob orçamento</div>
                    <ul>
                        <li><i class="fas fa-check"></i>Tokens visuais e regras de utilização</li>
                        <li><i class="fas fa-check"></i>Componentes reutilizáveis conforme necessidade</li>
                        <li><i class="fas fa-check"></i>Documentação e orientações de uso</li>
                        <li><i class="fas fa-check"></i>Entrega técnica definida na proposta</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Design+System" class="btn btn-outline"
                        style="width:100%">Pedir orçamento</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                O intervalo para redesign é uma referência inicial, não um preço universal. A proposta especificará
                âmbito, entregáveis, prazo, revisões e custos de ferramentas ou serviços externos. A condição de
                pagamento de referência da JMbenga é 50% no início e 50% antes da entrega final/publicação, salvo acordo
                escrito diferente.
            </p>
        </div>
    </section>

    <!-- FAQ de Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Dúvidas</span>
                <h2 class="section-title">Perguntas <span class="text-gradient">Frequentes</span></h2>
            </div>
            <div class="faq-list reveal">
                <?php
                $faqs = [
                    ['q' => 'Qual é a diferença entre UI e UX Design?', 'a' => 'UX (User Experience) trata da experiência global, incluindo tarefas, fluxos, usabilidade e arquitectura da informação. UI (User Interface) trata da interface visual, como cores, tipografia, controlos e composição. As duas áreas complementam-se.'],
                    ['q' => 'Quanto tempo leva um projecto de design?', 'a' => 'O prazo depende do número de ecrãs, da complexidade, dos conteúdos disponíveis e das rondas de revisão. O cronograma é definido depois do briefing e registado na proposta.'],
                    ['q' => 'Que ferramentas utiliza?', 'a' => 'As ferramentas são escolhidas conforme o tipo de trabalho e os requisitos acordados. A proposta identifica os formatos de entrega e eventuais licenças ou serviços de terceiros.'],
                    ['q' => 'Entrega os ficheiros editáveis?', 'a' => 'Os ficheiros e formatos incluídos são especificados na proposta. A transferência de ficheiros, direitos de utilização e eventuais limitações de licenças de terceiros ficam definidos por escrito.'],
                    ['q' => 'Pode colaborar com a equipa de desenvolvimento?', 'a' => 'A colaboração técnica, a documentação e o acompanhamento durante a implementação podem ser incluídos no orçamento, com limites e responsabilidades definidos por escrito.'],
                ];
                foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <h4><i class="fas fa-question-circle"
                            style="color:var(--accent);margin-right:8px"></i><?= e($faq['q']) ?>
                    </h4>
                    <p><?= e($faq['a']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Final -->
    <div class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:100px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Vamos Criar Algo <span class="text-gradient">Incrível Juntos</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Tem um projecto em mente? Vamos conversar sobre suas necessidades de design e como posso ajudar a
                transformar
                sua visão em realidade.
            </p>
            <div
                style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:500px;margin:0 auto 32px;text-align:center">
                <div><i class="fas fa-comments" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Briefing inicial</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Requisitos e objectivos</div>
                </div>
                <div><i class="fas fa-file-alt" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Proposta escrita</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Âmbito, prazo e valor</div>
                </div>
                <div><i class="fas fa-bolt" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Entregáveis definidos</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Conforme o orçamento</div>
                </div>
            </div>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = '';
    $page_scripts = [];
    include __DIR__ . '/../includes/footer.php';
    include __DIR__ . '/../includes/bottom-nav.php';
    ?>


</body>

</html>