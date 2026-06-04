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

$accent = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');
$email = $cfg('email_contact');

$page_title = 'UI/UX Design — José Mbenga | Full Stack Developer';
$page_desc  = 'Design de interfaces premium que converte. UI Design, UX Research, Design Systems e prototipagem para produtos digitais de alto impacto.';
$page_section = 'services';
?>
<?php

include '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/ui-ux.css">

<style>
    /* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
    :root {
        --accent: <?= e($accent) ?>;
        --accent-dim: #1d4ed8;
        --accent-glow: <?= e($accent) ?>26;
        --accent-rgb: 37, 99, 235;

        --bg: #0a0d14;
        --bg-2: #0f1623;
        --bg-card: #131b2e;
        --bg-glass: rgba(13, 22, 35, .8);
        --border: rgba(255, 255, 255, .06);
        --border-acc: rgba(37, 99, 235, .35);
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
        --border-acc: rgba(37, 99, 235, .25);
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
        background: radial-gradient(circle, <?= e($accent) ?>22 0%, transparent 65%);
        animation: blob 14s ease-in-out infinite alternate;
        pointer-events: none;
    }
</style>

</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($wa = $cfg('whatsapp_url')): ?>
        <div class=" whatsapp-float" id="whatsappFloat">
            <a href="<?= e($wa) ?>?text=Olá%20José,%20vim%20do%20seu%20portfólio" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
            </a>
            <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    <?php include '../includes/navbar.php'; ?>



    <!-- Hero -->
    <section id="service-hero">
        <div class="container">
            <div class="service-hero-inner">
                <div class="service-hero-text reveal">
                    <span class="tag">Serviço Premium</span>
                    <h1>UI/UX Design <span>que Converte</span></h1>
                    <p class="lead">Crio interfaces intuitivas e experiências memoráveis que engajam usuários, aumentam
                        conversões e destacam a sua marca no mercado digital.</p>
                    <div class="showcase-badges">
                        <span class="showcase-badge"><i class="fas fa-paint-brush"></i> UI Design</span>
                        <span class="showcase-badge"><i class="fas fa-users"></i> UX Research</span>
                        <span class="showcase-badge"><i class="fas fa-mobile-alt"></i> Mobile First</span>
                    </div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-outline"><i
                                    class="fab fa-whatsapp"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-palette"></i></div>
                    <div class="hero-stats-row">
                        <div class="hero-stat-mini">
                            <div class="num">+41%</div>
                            <div class="lbl">Engajamento</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">-32%</div>
                            <div class="lbl">Taxa de Saída</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">2.3s</div>
                            <div class="lbl">Velocidade Média</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">86%</div>
                            <div class="lbl">Satisfação</div>
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
                    <p>Bom design pode aumentar conversões em até 200%. Cada dólar investido retorna em média $100 em
                        ROI.
                    </p>
                    <ul>
                        <li>Aumento de conversões</li>
                        <li>Redução de bounce rate</li>
                        <li>Maior valor por cliente</li>
                        <li>Redução de suporte</li>
                    </ul>
                </div>
                <div class="why-card">
                    <div class="why-icon"><i class="fas fa-smile"></i></div>
                    <h3>Experiência do Usuário</h3>
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
                        <li>Preferência do usuário</li>
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
                <p class="section-sub">Do design visual à pesquisa com usuários, entrego soluções completas para
                    produtos
                    digitais.</p>
            </div>
            <div class="services-grid reveal">
                <?php
                $services = [
                    ['icon' => 'fa-mobile-alt', 'title' => 'UI Design Mobile', 'desc' => 'Interfaces responsivas e intuitivas para apps iOS e Android. Design systems, componentes reutilizáveis, animações fluidas.', 'tags' => ['iOS', 'Android', 'React Native', 'Design Systems']],
                    ['icon' => 'fa-globe', 'title' => 'Web Design Responsivo', 'desc' => 'Websites e aplicações web com foco em usabilidade e conversão. Design adaptativo para todos dispositivos.', 'tags' => ['Responsive', 'Landing Pages', 'E-commerce']],
                    ['icon' => 'fa-chart-bar', 'title' => 'Dashboard & Data Viz', 'desc' => 'Interfaces complexas para dashboards empresariais. Visualização de dados clara e tomada de decisão facilitada.', 'tags' => ['Data Visualization', 'Admin Panels', 'Analytics']],
                    ['icon' => 'fa-search', 'title' => 'UX Research & Testing', 'desc' => 'Pesquisa com usuários, testes de usabilidade, análise de concorrência e definição de personas.', 'tags' => ['User Testing', 'Personas', 'User Journey']],
                    ['icon' => 'fa-fan', 'title' => 'Brand & Visual Identity', 'desc' => 'Identidade visual completa: logos, paleta de cores, tipografia, iconografia e guidelines de marca.', 'tags' => ['Branding', 'Logo Design', 'Style Guides']],
                    ['icon' => 'fa-cubes', 'title' => 'Design Systems', 'desc' => 'Sistemas de design escaláveis com componentes reutilizáveis, documentação e design tokens.', 'tags' => ['Component Libraries', 'Design Tokens', 'Documentation']],
                ];
                foreach ($services as $svc): ?>
                    <div class="service-card reveal">
                        <div class="icon-circle"><i class="fas <?= $svc['icon'] ?>"></i></div>
                        <h3><?= $svc['title'] ?></h3>
                        <p><?= $svc['desc'] ?></p>
                        <div class="tags">
                            <?php foreach ($svc['tags'] as $t): ?>
                                <span class="tag-sm"><?= $t ?></span>
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
                <h2 class="section-title">Meu <span class="text-gradient">Processo de Design</span></h2>
                <p class="section-sub">Design Thinking aplicado com foco no usuário final em cada etapa.</p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Descoberta & Pesquisa', 'desc' => 'Entendimento profundo do negócio, usuários e concorrência.'],
                    ['num' => 2, 'title' => 'Definição & Estratégia', 'desc' => 'Personas, user journeys, arquitetura de informação.'],
                    ['num' => 3, 'title' => 'Ideação & Wireframes', 'desc' => 'Brainstorming, sketches, wireframes de baixa fidelidade.'],
                    ['num' => 4, 'title' => 'Prototipagem & Testes', 'desc' => 'Protótipos interativos, testes de usabilidade, iterações.'],
                    ['num' => 5, 'title' => 'UI Design & Refinamento', 'desc' => 'Design visual, micro-interações, design system.'],
                    ['num' => 6, 'title' => 'Handoff & Implementação', 'desc' => 'Documentação, specs, colaboração com desenvolvedores.'],
                ];
                foreach ($steps as $step): ?>
                    <div class="process-step">
                        <div class="step-number"><?= $step['num'] ?></div>
                        <h4><?= $step['title'] ?></h4>
                        <p><?= $step['desc'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- O Que Você Recebe -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Deliverables</span>
                <h2 class="section-title">O Que <span class="text-gradient">Você Recebe</span></h2>
            </div>
            <div class="deliverables-grid reveal">
                <?php
                $deliverables = [
                    ['icon' => 'fab fa-figma', 'title' => '100% Figma Files', 'desc' => 'Com acesso completo e editável'],
                    ['icon' => 'fas fa-redo', 'title' => '3-5 Iterações', 'desc' => 'Até você ficar satisfeito'],
                    ['icon' => 'fas fa-headset', 'title' => 'Suporte 24/7', 'desc' => 'Durante todo o projeto'],
                    ['icon' => 'fas fa-mobile-alt', 'title' => 'Responsivo', 'desc' => 'Mobile, tablet, desktop'],
                    ['icon' => 'fas fa-book', 'title' => 'Style Guide', 'desc' => 'Documentação completa'],
                    ['icon' => 'fas fa-project-diagram', 'title' => 'Wireframes', 'desc' => 'Baixa e alta fidelidade'],
                    ['icon' => 'fas fa-play', 'title' => 'Protótipos', 'desc' => 'Interativos e navegáveis'],
                    ['icon' => 'fas fa-code', 'title' => 'Dev Handoff', 'desc' => 'Specs para desenvolvedores'],
                ];
                foreach ($deliverables as $d): ?>
                    <div class="deliverable-card">
                        <i class="<?= $d['icon'] ?>"></i>
                        <h4><?= $d['title'] ?></h4>
                        <p><?= $d['desc'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Projetos de Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Portfólio</span>
                <h2 class="section-title">Projetos de <span class="text-gradient">Design</span></h2>
                <p class="section-sub">Casos reais de design que entregaram resultados mensuráveis.</p>
            </div>
            <div class="portfolio-grid reveal">
                <?php
                $portfolio = [
                    ['title' => 'App Fintech Angola', 'desc' => 'Redesign completo de app bancário mobile com foco em usabilidade e confiança.', 'result' => '+47% Conversões'],
                    ['title' => 'E-commerce Fashion', 'desc' => 'Design de plataforma de moda com experiência de compra premium e fluida.', 'result' => '+32% Vendas Mobile'],
                    ['title' => 'HealthTech Dashboard', 'desc' => 'Interface para gestão de clínicas médicas com foco em usabilidade e clareza.', 'result' => '-40% Erros'],
                ];
                foreach ($portfolio as $p): ?>
                    <div class="portfolio-card">
                        <h4><?= $p['title'] ?></h4>
                        <p><?= $p['desc'] ?></p>
                        <div class="result"><?= $p['result'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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
                    ['icon' => 'fas fa-user-circle', 'title' => 'User-Centric', 'desc' => 'O usuário está no centro de cada decisão. Design baseado em necessidades reais, não em suposições.'],
                    ['icon' => 'fas fa-minus-circle', 'title' => 'Simplicidade', 'desc' => 'Menos é mais. Interfaces limpas, intuitivas e sem elementos desnecessários que distraem.'],
                    ['icon' => 'fas fa-universal-access', 'title' => 'Acessibilidade', 'desc' => 'Design inclusivo para todos os usuários. Conformidade com padrões WCAG 2.1 AA.'],
                    ['icon' => 'fas fa-th-large', 'title' => 'Consistência', 'desc' => 'Padrões visuais consistentes criam confiança e reduzem a curva de aprendizado do usuário.'],
                    ['icon' => 'fas fa-tachometer-alt', 'title' => 'Performance', 'desc' => 'Design otimizado para velocidade. Cada milissegundo impacta a experiência do usuário.'],
                    ['icon' => 'fas fa-magic', 'title' => 'Delight', 'desc' => 'Micro-interações que encantam e criam experiências memoráveis que fidelizam usuários.'],
                ];
                foreach ($principles as $p): ?>
                    <div class="principle-card">
                        <i class="<?= $p['icon'] ?>"></i>
                        <h4><?= $p['title'] ?></h4>
                        <p><?= $p['desc'] ?></p>
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
                    ['icon' => 'fab fa-figma', 'name' => 'Figma', 'sub' => 'Design & Prototyping'],
                    ['icon' => 'fas fa-pen-nib', 'name' => 'Adobe XD', 'sub' => 'UI/UX Design'],
                    ['icon' => 'fas fa-bezier-curve', 'name' => 'Illustrator', 'sub' => 'Vector Graphics'],
                    ['icon' => 'fas fa-image', 'name' => 'Photoshop', 'sub' => 'Photo Editing'],
                    ['icon' => 'fas fa-film', 'name' => 'After Effects', 'sub' => 'Motion Design'],
                    ['icon' => 'fas fa-cube', 'name' => 'Blender', 'sub' => '3D Design'],
                    ['icon' => 'fas fa-hand-pointer', 'name' => 'InVision', 'sub' => 'Prototyping'],
                ];
                foreach ($tools as $tool): ?>
                    <div class="tool-card reveal">
                        <i class="<?= $tool['icon'] ?>"></i>
                        <h4><?= $tool['name'] ?></h4>
                        <small><?= $tool['sub'] ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Design Systems -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Design Systems</span>
                <h2 class="section-title">Sistemas Escaláveis para <span class="text-gradient">Produtos que
                        Crescem</span>
                </h2>
            </div>
            <div class="ds-grid reveal">
                <div>
                    <p style="font-size:1rem;color:var(--text-dim);line-height:1.8;margin-bottom:24px">
                        Design Systems não são apenas bibliotecas de componentes. São ecossistemas vivos que garantem
                        consistência, aceleram desenvolvimento e escalam com o seu produto.
                    </p>
                    <div class="ds-components">
                        <?php foreach (['Buttons', 'Inputs', 'Cards', 'Modals', 'Navigation', 'Data Tables', 'Forms', 'Alerts'] as $comp): ?>
                            <span class="ds-comp"><?= $comp ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="ds-stat">
                        <div class="big">-65%</div>
                        <div class="lbl">Tempo de Desenvolvimento com Design System implementado</div>
                    </div>
                </div>
                <div>
                    <h4 style="font-family:var(--font-head);margin-bottom:20px">O que inclui um Design System:</h4>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:12px">
                        <?php foreach (['Biblioteca de componentes reutilizáveis', 'Design tokens (cores, tipografia, espaçamento)', 'Documentação completa e atualizada', 'Guidelines de uso e boas práticas', 'Padrões de acessibilidade WCAG', 'Ferramentas para desenvolvedores', 'Processo de versionamento e atualização'] as $item): ?>
                            <li style="display:flex;align-items:center;gap:10px;font-size:.92rem;color:var(--text-dim)"><i
                                    class="fas fa-check-circle" style="color:var(--accent)"></i><?= $item ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Planos de Design -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
            </div>
            <div class="pricing-grid reveal">
                <!-- UI Design Básico -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">UI Design Básico</h3>
                    <div class="price">$800<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>1-3 telas principais</li>
                        <li><i class="fas fa-check"></i>Design responsivo</li>
                        <li><i class="fas fa-check"></i>Paleta de cores</li>
                        <li><i class="fas fa-check"></i>Tipografia selecionada</li>
                        <li><i class="fas fa-check"></i>2 revisões</li>
                        <li class="unavailable"><i class="fas fa-times"></i>UX Research</li>
                        <li class="unavailable"><i class="fas fa-times"></i>Design System</li>
                        <li class="unavailable"><i class="fas fa-times"></i>Prototipagem interativa</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-outline"
                        style="width:100%">Começar</a>
                </div>
                <!-- UI/UX Completo -->
                <div class="pricing-card featured">
                    <div
                        style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:4px 16px;border-radius:999px;font-size:.72rem;font-weight:600">
                        MAIS POPULAR</div>
                    <h3 style="font-family:var(--font-head)">UI/UX Completo</h3>
                    <div class="price">$2,500<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>5-10 telas</li>
                        <li><i class="fas fa-check"></i>Pesquisa de usuários</li>
                        <li><i class="fas fa-check"></i>Wireframes & Protótipos</li>
                        <li><i class="fas fa-check"></i>Testes de usabilidade</li>
                        <li><i class="fas fa-check"></i>Design responsivo</li>
                        <li><i class="fas fa-check"></i>UI Kit básico</li>
                        <li><i class="fas fa-check"></i>5 revisões</li>
                        <li class="unavailable"><i class="fas fa-times"></i>Design System completo</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"
                        style="width:100%">Começar</a>
                </div>
                <!-- Design System Pro -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Design System Pro</h3>
                    <div class="price">$5,000<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Design System completo</li>
                        <li><i class="fas fa-check"></i>30+ componentes</li>
                        <li><i class="fas fa-check"></i>Documentação detalhada</li>
                        <li><i class="fas fa-check"></i>Design tokens</li>
                        <li><i class="fas fa-check"></i>Acessibilidade WCAG 2.1</li>
                        <li><i class="fas fa-check"></i>Treinamento da equipe</li>
                        <li><i class="fas fa-check"></i>Revisões ilimitadas</li>
                        <li><i class="fas fa-check"></i>Suporte 1 mês</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-outline"
                        style="width:100%">Começar</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                Todos os projetos incluem arquivos Figma editáveis e suporte durante o desenvolvimento. * Preços para
                projetos em Angola. Design para apps iOS/Android pode variar.
            </p>
        </div>
    </section>

    <!-- Testemunhos -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Clientes</span>
                <h2 class="section-title">O Que Dizem <span class="text-gradient">Clientes de Design</span></h2>
                <p class="section-sub">Experiências de quem investiu em design premium.</p>
            </div>
            <div class="testi-grid reveal">
                <?php
                $testimonials = [
                    ['quote' => 'O redesign do nosso app bancário feito pelo José aumentou nossas conversões em 47% em apenas 3 meses. A experiência do usuário está tão intuitiva que reduzimos o suporte em 30%.', 'author' => 'Maria Fernandes', 'role' => 'Product Manager, Banco Digital'],
                    ['quote' => 'Contratamos o José para criar nosso Design System. Hoje nossa equipe de desenvolvimento é 3x mais produtiva. A consistência visual em todos os produtos elevou nossa marca para outro nível.', 'author' => 'Pedro Costa', 'role' => 'CTO, HealthTech Startup'],
                    ['quote' => 'Como e-commerce de moda, design é tudo. O trabalho do José não só é visualmente impressionante, mas aumentou nossas vendas mobile em 32%. A experiência de compra está fluida e elegante.', 'author' => 'Isabel Santos', 'role' => 'CEO, Fashion E-commerce'],
                ];
                foreach ($testimonials as $t): ?>
                    <div class="testi-card">
                        <div class="quote">"</div>
                        <p class="body"><?= $t['quote'] ?></p>
                        <div class="author"><?= $t['author'] ?></div>
                        <div class="role"><?= $t['role'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                    ['q' => 'Qual a diferença entre UI e UX Design?', 'a' => 'UX (User Experience) foca na experiência completa do usuário: usabilidade, funcionalidade, pesquisa, arquitetura de informação. UI (User Interface) é a parte visual: cores, tipografia, botões, layouts. UX é sobre como funciona, UI é sobre como parece. Trabalham juntos.'],
                    ['q' => 'Quanto tempo leva um projeto de design?', 'a' => 'Depende da complexidade. Um landing page: 1-2 semanas. App mobile completo: 4-8 semanas. Design System: 6-12 semanas. Após o briefing, forneço cronograma detalhado com milestones.'],
                    ['q' => 'Você trabalha com quais ferramentas?', 'a' => 'Principalmente Figma (para design colaborativo e prototipagem). Também Adobe XD, Illustrator, Photoshop, e After Effects para animações. Os arquivos são entregues em formatos editáveis.'],
                    ['q' => 'Você entrega os arquivos fonte?', 'a' => 'Sim, você recebe todos os arquivos fonte editáveis (Figma, XD, etc.) com direitos completos de uso. Também forneço formatos para desenvolvimento (SVG, PNG, PDF specs).'],
                    ['q' => 'Você trabalha com equipes de desenvolvimento?', 'a' => 'Sim, frequentemente! Forneço specs técnicas, guias de estilo, e estou disponível para reuniões com desenvolvedores durante a implementação para garantir fidelidade ao design.'],
                ];
                foreach ($faqs as $faq): ?>
                    <div class="faq-item">
                        <h4><i class="fas fa-question-circle"
                                style="color:var(--accent);margin-right:8px"></i><?= $faq['q'] ?>
                        </h4>
                        <p><?= $faq['a'] ?></p>
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
                Tem um projeto em mente? Vamos conversar sobre suas necessidades de design e como posso ajudar a
                transformar
                sua visão em realidade.
            </p>
            <div
                style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:500px;margin:0 auto 32px;text-align:center">
                <div><i class="fas fa-comments" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Consulta Gratuita</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">30 minutos</div>
                </div>
                <div><i class="fas fa-file-alt" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Proposta Personalizada</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Orçamento detalhado</div>
                </div>
                <div><i class="fas fa-bolt" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Protótipo Rápido</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Mockup em 48h</div>
                </div>
            </div>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=UI+UX+Design" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                    <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                            class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = '';
    $page_scripts = [];
    include '../includes/footer.php';
    include '../includes/bottom-nav.php';
    ?>


</body>

</html>