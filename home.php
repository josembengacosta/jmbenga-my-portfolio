 <?php
    // ══════════════════════════════════════════════════════════════
    // home.php — JMbenga Portfolio v2.0
    // Self-contained: CSS, JS e dados da BD num único ficheiro.
    // Preserva: typing, code editor, tech stack, timeline,
    //           theme toggle, loading screen, scroll progress,
    //           WhatsApp float, particles, offcanvas mobile.
    // ══════════════════════════════════════════════════════════════


    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/visitor.php';

    check_maintenance_mode($pdo);

    $cfg = function (string $key, string $default = ''): string {
        global $pdo;
        return get_config($pdo, $key, $default);
    };

    // Dados da BD
    $projects = $pdo->query("
    SELECT * FROM _projects
    WHERE status_project = 'published'
    ORDER BY is_featured DESC, display_order ASC LIMIT 6
")->fetchAll();

    $skills_raw = $pdo->query("
    SELECT * FROM _skills WHERE is_visible = 1
    ORDER BY category_skill, display_order ASC
")->fetchAll();
    $skills = [];
    foreach ($skills_raw as $s) {
        $skills[$s['category_skill']][] = $s;
    }

    // Usar competências efectivamente registadas no painel para evitar stacks promocionais não confirmadas.
    $skills_for_hero = [];
    $seen_hero_skills = [];
    foreach ($skills_raw as $skill_row) {
        $skill_name = trim((string)($skill_row['name_skill'] ?? ''));
        $skill_key = function_exists('mb_strtolower') ? mb_strtolower($skill_name, 'UTF-8') : strtolower($skill_name);
        if ($skill_name === '' || isset($seen_hero_skills[$skill_key])) {
            continue;
        }
        $seen_hero_skills[$skill_key] = true;
        $skills_for_hero[] = $skill_row;
    }
    $skills_for_hero = array_slice($skills_for_hero, 0, 7);

    // O contador público deve reflectir apenas projectos realmente publicados.
    // O valor promocional de projects_count não pode inflacionar esta métrica.
    $total_projects = (int)$pdo->query(
        "SELECT COUNT(*) FROM _projects WHERE status_project = 'published'"
    )->fetchColumn();


    // Aceitar apenas hexadecimal #RRGGBB antes de inserir a cor no CSS.
    $accent_candidate = trim($cfg('accent_color', '#2563eb'));
    $accent = preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent_candidate)
        ? $accent_candidate
        : '#2563eb';
    [$accent_r, $accent_g, $accent_b] = array_map(
        static fn(string $hex): int => hexdec($hex),
        str_split(substr($accent, 1), 2)
    );
    $accent_rgb = implode(', ', [$accent_r, $accent_g, $accent_b]);
    $accent_dim = sprintf(
        '#%02x%02x%02x',
        (int)round($accent_r * 0.8),
        (int)round($accent_g * 0.8),
        (int)round($accent_b * 0.8)
    );

    // Links externos vindos da configuração/base de dados só podem usar HTTP(S).
    $safe_http_url = static function (?string $url): string {
        $url = trim((string)$url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    };
    $wa = $safe_http_url($cfg('whatsapp_url'));
    $wa_text_url = $wa !== ''
        ? $wa . (str_contains($wa, '?') ? '&' : '?')
            . 'text=' . rawurlencode('Olá José, vim do seu portfólio')
        : '';

    $page_title = 'José Mbenga — Desenvolvedor Full Stack em Luanda, Angola | JMbenga';
    $meta_default = 'Desenvolvedor Full Stack em Luanda, Angola. Desenvolvimento web, aplicações, APIs e experiências digitais. Contacte José Mbenga para apresentar o seu projecto.';
    $meta_desc = trim($cfg('meta_description'));
    if ($meta_desc === '') {
        $meta_desc = $meta_default;
        $page_desc = $meta_desc;
    }

    $hero_subtitle = trim($cfg('hero_subtitle'));
    if ($hero_subtitle === '') {
        $hero_subtitle = 'Transformo ideias em soluções digitais funcionais, acessíveis e bem estruturadas.';
    }

    $home_services = [
        [
            'icon' => 'fa-globe',
            'title' => 'Desenvolvimento Web',
            'description' => 'Landing pages, portfólios, sites institucionais e soluções web com escopo definido antes do início.',
            'price' => 'Pacotes de 75.000 a 280.000 Kz',
            'note' => 'Projectos personalizados a partir de 300.000 Kz.',
            'href' => BASE_URL . '/services/web-development',
        ],
        [
            'icon' => 'fa-mobile-screen-button',
            'title' => 'Aplicações Mobile',
            'description' => 'Aplicações para dispositivos móveis, orçamentadas conforme plataformas, funcionalidades e integrações necessárias.',
            'price' => 'Orçamento personalizado',
            'note' => 'O valor depende dos requisitos e do âmbito.',
            'href' => BASE_URL . '/services/mobile-apps',
        ],
        [
            'icon' => 'fa-code',
            'title' => 'APIs & Backend',
            'description' => 'Integrações, serviços de servidor, endpoints e operações com bases de dados para aplicações e sistemas.',
            'price' => 'Integração API: 30.000–100.000 Kz',
            'note' => 'CRUD + base de dados: 300.000–550.000 Kz.',
            'href' => BASE_URL . '/services/api-backend',
        ],
        [
            'icon' => 'fa-pen-ruler',
            'title' => 'UI/UX Design',
            'description' => 'Organização de interfaces, fluxos, hierarquia visual e melhoria da experiência de utilização.',
            'price' => 'Redesign de site: 60.000–180.000 Kz',
            'note' => 'Interfaces novas e design systems sob orçamento.',
            'href' => BASE_URL . '/services/ui-ux-design',
        ],
        [
            'icon' => 'fa-screwdriver-wrench',
            'title' => 'Manutenção',
            'description' => 'Acompanhamento técnico, correcções, actualizações e verificações periódicas de sites e aplicações.',
            'price' => 'Planos de 15.000 a 70.000 Kz/mês',
            'note' => 'Novas funcionalidades são orçamentadas à parte.',
            'href' => BASE_URL . '/services/maintenance',
        ],
    ];
    $service_area_count = count($home_services);

    $page_section = 'home';

    ?>
 <?php include __DIR__ . '/includes/header.php'; ?>

 <link rel="stylesheet" href="assets/css/home.css">

 <style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: <?=e($accent_dim) ?>;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=e($accent_rgb) ?>;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(var(--accent-rgb), .35);
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
    --border-acc: rgba(var(--accent-rgb), .25);
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

/* Catálogo resumido de serviços na página inicial */
.home-services-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-top: 34px;
}

.home-service-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 26px;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--bg-card);
    transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
}

.home-service-card:hover {
    transform: translateY(-4px);
    border-color: var(--border-acc);
    box-shadow: 0 14px 34px rgba(0, 0, 0, .13);
}

.home-service-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    margin-bottom: 17px;
    border-radius: 14px;
    border: 1px solid var(--border-acc);
    background: var(--accent-glow);
    color: var(--accent);
    font-size: 1.2rem;
}

.home-service-card h3 {
    margin: 0 0 10px;
    font-family: var(--font-head);
    font-size: 1.08rem;
    color: var(--text);
}

.home-service-card p {
    color: var(--text-dim);
    font-size: .9rem;
    line-height: 1.75;
    margin: 0 0 16px;
}

.home-service-price {
    margin-top: auto;
    color: var(--text);
    font-size: .94rem;
    line-height: 1.55;
    font-weight: 700;
}

.home-service-note {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: .8rem;
    line-height: 1.6;
}

.home-service-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 18px;
    color: var(--accent);
    font-weight: 700;
    font-size: .88rem;
    text-decoration: none;
}

.home-service-link:hover {
    gap: 13px;
}

.home-services-footer {
    display: flex;
    justify-content: center;
    margin-top: 28px;
}

.home-commercial-note {
    max-width: 900px;
    margin: 18px auto 0;
    color: var(--text-muted);
    font-size: .82rem;
    line-height: 1.7;
    text-align: center;
}

.cta-note {
    max-width: 900px;
    margin: 18px auto 0;
    color: var(--text-dim);
    font-size: .8rem;
    line-height: 1.7;
    text-align: center;
}

@media (max-width: 992px) {
    .home-services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .home-services-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .home-service-card {
        padding: 22px;
    }
}

@media (max-width: 768px) {
    .projects-grid {
        display: block;
    }

    .project-card {
        display: flex;
        flex-direction: column;
        margin-bottom: 2rem;
    }

    /* Coluna visual — code editor */
    .code-editor {
        max-width: 280px;
        word-break: break-all;
    }

    .hero-visual {
        max-width: 100%;
    }

    .section-title {

        font-size: 1.7rem !important;
        line-height: 1.3 !important;
        letter-spacing: normal !important;
    }
}
 </style>

 </head>

 <body>

     <!-- WhatsApp Float -->
     <?php if ($wa !== ''): ?>
     <div class="whatsapp-float" id="whatsappFloat">
         <a href="<?= e($wa_text_url) ?>" target="_blank" rel="noopener noreferrer">
             <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
         </a>
         <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
             <i class="fas fa-times"></i>
         </button>
     </div>
     <?php endif; ?>
     <?php include __DIR__ . '/includes/navbar.php'; ?>

     <!-- ════════ HERO ══════════════════════════════════════════════ -->
     <section id="home">
         <div class="hero-bg-grid"></div>
         <div class="hero-blob"></div>
         <div class="hero-blob-2"></div>

         <canvas id="particles-canvas" style="height: 100vh;" aria-hidden="true"></canvas>


         <div class="hero-grid">
             <!-- Texto -->
             <div class="hero-text">
                 <div class="hero-badge">
                     <div class="hero-badge-dot"></div>
                     <span>Desenvolvedor Full Stack &amp; UI/UX Designer</span>
                 </div>

                 <h1 class="hero-title">
                     <span class="line"><span>Olá, sou</span></span>
                     <span class="line"><span class="text-gradient">José Mbenga</span></span>
                     <span class="line"><span>Da Costa</span></span>
                 </h1>

                 <div class="typing-container">
                     <span class="typing-prefix">Especialista em </span>
                     <span class="typing-word" id="typing-word"></span>
                 </div>

                 <p class="hero-subtitle"><?= e($hero_subtitle) ?></p>

                 <!-- Competências registadas no painel -->
                 <?php if (!empty($skills_for_hero)): ?>
                 <div class="tech-stack">
                     <?php foreach ($skills_for_hero as $hero_skill): ?>
                     <span class="tech-pill"><i
                             class="<?= e(trim((string)($hero_skill['icon_skill'] ?? '')) ?: 'fas fa-code') ?>"
                             aria-hidden="true"></i><?= e($hero_skill['name_skill']) ?></span>
                     <?php endforeach; ?>
                 </div>
                 <?php endif; ?>

                 <div class="hero-cta">
                     <a href="#contact" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Iniciar
                         Projecto</a>
                     <a href="#projects" class="btn btn-secondary"><i class="fas fa-eye"></i>Ver Trabalhos</a>
                     <a href="<?= BASE_URL ?>/servicos" class="btn btn-outline"><i class="fas fa-briefcase"></i>Ver
                         Serviços</a>
                 </div>

                 <div class="hero-stats">
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter"
                                 data-target="<?= $total_projects ?>">0</span><?= $total_projects > 0 ? '<span class="stat-suffix">+</span>' : '' ?>
                         </div>
                         <div class="stat-label">Projectos publicados</div>
                     </div>
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter"
                                 data-target="<?= $service_area_count ?>">0</span></div>
                         <div class="stat-label">Áreas de serviço</div>
                     </div>
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter" data-target="2">2</span></div>
                         <div class="stat-label">Revisões nos pacotes web-base</div>
                     </div>
                 </div>
             </div>

             <!-- Code editor visual -->
             <div class="hero-visual">
                 <div class="hero-deco-ring"></div>

                 <div class="code-editor">
                     <div class="editor-header">
                         <span class="dot red"></span>
                         <span class="dot yellow"></span>
                         <span class="dot green"></span>
                         <span class="editor-title">portfolio.php</span>
                     </div>
                     <div class="code-body">
                         <pre class="code-snippet"><span class="cmt">// José Mbenga Da Costa — Full Stack Dev</span>
<span class="kw">$</span><span class="var">developer</span> = [
  <span class="str">'name'</span>     => <span class="str">'José Mbenga'</span>,
  <span class="str">'location'</span> => <span class="str">'Luanda, Angola 🇦🇴'</span>,
  <span class="str">'services'</span> => [<span class="str">'Web'</span>, <span class="str">'Mobile'</span>, <span class="str">'APIs'</span>, <span class="str">'UI/UX'</span>],
  <span class="str">'focus'</span>    => [<span class="str">'Usabilidade'</span>, <span class="str">'Integração'</span>, <span class="str">'Manutenção'</span>],
];

<span class="kw">$</span><span class="var">work_mode</span> = <span class="str">'orçamento por escopo'</span>;

<span class="kw">function</span> <span class="fn">buildProject</span>(<span class="var">$idea</span>): <span class="var">string</span> {
  <span class="kw">return</span> <span class="fn">transformIntoReality</span>(<span class="var">$idea</span>);
}

<span class="cmt">// <?= e($cfg('email_contact', 'josembengadacosta@gmail.com')) ?></span></pre>
                     </div>
                     <div class="editor-footer">
                         <div class="editor-status"></div>
                         <span>PHP 8.2</span> · <span>MySQL</span> · <span>Luanda, AO</span>
                     </div>
                 </div>

                 <!-- Badge flutuante — stack -->
                 <div class="hero-badge-float badge-bottom-left">
                     <div class="hero-badge-float-icon"><i class="fas fa-code"></i></div>
                     <div>
                         <div class="hbf-text">Full Stack Developer</div>
                         <div class="hbf-sub">PHP · MySQL · JavaScript</div>
                     </div>
                 </div>

                 <!-- Badge flutuante — projectos -->
                 <div class="hero-badge-float badge-top-right">
                     <div>
                         <div class="hbf-text"><?= $total_projects ?><?= $total_projects > 0 ? '+' : '' ?></div>
                         <div class="hbf-sub">Projectos</div>
                     </div>
                 </div>
             </div>
         </div>
     </section>

     <!-- ════════ ABOUT ═════════════════════════════════════════════ -->
     <section id="about" class="section section-alt">
         <div class="container">
             <div class="about-grid">
                 <!-- Imagem -->
                 <div class="about-image-wrap reveal">
                     <?php
                        $profile = get_public_profile_photo($pdo, $cfg('photo_profile'));
                        if ($profile): ?>
                     <img src="<?= e($profile['url']) ?>" alt="José Mbenga" class="about-photo">
                     <?php else: ?>
                     <div class="about-photo-placeholder"><i class="fas fa-user-tie"></i></div>
                     <?php endif; ?>

                     <div class="about-exp-badge">
                         <div class="aeb-num"><?= $service_area_count ?></div>
                         <div class="aeb-label">áreas de<br>serviço</div>
                     </div>

                     <div class="highlights">
                         <div class="highlight-card">
                             <div class="highlight-icon"><i class="fas fa-rocket"></i></div>
                             <h4><?= $total_projects ?> Projectos publicados</h4>
                             <p>Trabalhos visíveis no portfólio</p>
                         </div>
                         <div class="highlight-card">
                             <div class="highlight-icon"><i class="fas fa-layer-group"></i></div>
                             <h4><?= $service_area_count ?> áreas de serviço</h4>
                             <p>Web, mobile, APIs, UI/UX e manutenção</p>
                         </div>
                     </div>
                 </div>

                 <!-- Conteúdo -->
                 <div class="about-content reveal" style="transition-delay:.15s">
                     <span class="tag">Sobre mim</span>
                     <h2 class="section-title" style="margin-top:14px">
                         Tecnologia com foco em<br><span class="text-gradient">problemas reais</span>
                     </h2>

                     <?php
                        $about_text = trim($cfg('about_text'));
                        $paras = $about_text !== ''
                            ? array_filter(array_map('trim', explode("\n", $about_text)))
                            : [
                                'Sou José Mbenga, desenvolvedor e responsável por este portfólio. Trabalho na criação de soluções digitais, desde páginas e websites até interfaces, integrações e sistemas com bases de dados.',
                                'Cada projecto começa pela compreensão dos objectivos e pela definição do escopo. A proposta identifica o que será desenvolvido, os custos previstos, os materiais necessários e o prazo acordado.',
                            ];
                        foreach ($paras as $p): ?>
                     <p class="about-text"><?= e($p) ?></p>
                     <?php endforeach; ?>

                     <div class="detail-cards">
                         <div class="detail-card">
                             <i class="fas fa-map-marker-alt"></i>
                             <div>
                                 <h5>Localização</h5>
                                 <p><?= e($cfg('location', 'Luanda, Angola')) ?></p><small>Projectos remotos conforme o
                                     âmbito</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-layer-group"></i>
                             <div>
                                 <h5>Serviços</h5>
                                 <p><?= $service_area_count ?> áreas de actuação</p><small>Web, mobile, APIs, UI/UX e
                                     manutenção</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-file-signature"></i>
                             <div>
                                 <h5>Escopo definido</h5>
                                 <p>Entregáveis por escrito</p><small>Funcionalidades, exclusões e prazo
                                     acordados</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-wallet"></i>
                             <div>
                                 <h5>Orçamento</h5>
                                 <p>Preços em kwanzas (Kz)</p><small>Custos externos discriminados na proposta</small>
                             </div>
                         </div>
                     </div>

                     <!-- Processo de trabalho -->
                     <div style="margin-top:30px;margin-bottom:10px">
                         <span class="tag">Como trabalho</span>
                         <h3 style="font-family:var(--font-head);font-size:1.15rem;margin:12px 0 0;color:var(--text)">
                             Etapas de cada projecto</h3>
                     </div>
                     <div class="timeline">
                         <div class="timeline-item">
                             <div class="timeline-year">01</div>
                             <div class="timeline-content">
                                 <h4>Briefing e objectivos</h4>
                                 <p>Alinhamos a necessidade, o público, as funcionalidades e as prioridades do projecto.
                                 </p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">02</div>
                             <div class="timeline-content">
                                 <h4>Escopo e orçamento</h4>
                                 <p>A proposta descreve entregáveis, exclusões, custos previstos e prazo estimado.</p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">03</div>
                             <div class="timeline-content">
                                 <h4>Desenvolvimento e revisão</h4>
                                 <p>O trabalho segue o âmbito aprovado, com pontos de validação e revisões acordadas.
                                 </p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">04</div>
                             <div class="timeline-content">
                                 <h4>Entrega e continuidade</h4>
                                 <p>A entrega segue as condições da proposta; a manutenção contínua é contratada
                                     separadamente.</p>
                             </div>
                         </div>
                     </div>

                     <div class="about-cta">
                         <a href="<?= BASE_URL ?>/cv" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                             <i class="fas fa-eye"></i> Ver Currículo
                         </a>
                         <a href="<?= BASE_URL ?>/download/cv" class="btn btn-outline">
                             <i class="fas fa-download"></i> Baixar PDF
                         </a>
                         <a href="#contact" class="btn btn-outline">
                             <i class="fas fa-comment"></i> Fale Comigo
                         </a>
                     </div>
                 </div>
             </div>
         </div>
     </section>

     <!-- ════════ SERVICES ══════════════════════════════════════════ -->
     <section id="services" class="section section-alt">
         <div class="container">
             <div class="section-head reveal">
                 <span class="tag">Serviços</span>
                 <h2 class="section-title" style="margin-top:12px">Soluções digitais com <span
                         class="text-gradient">escopo claro</span></h2>
                 <p class="section-sub">Conheça as áreas de serviço e as referências de preço em kwanzas. O valor final
                     é confirmado depois de definir os requisitos e as entregas.</p>
             </div>

             <div class="home-services-grid">
                 <?php foreach ($home_services as $i => $service): ?>
                 <article class="home-service-card reveal" style="transition-delay:<?= min($i * 0.06, 0.3) ?>s">
                     <div class="home-service-icon"><i class="fas <?= e($service['icon']) ?>" aria-hidden="true"></i>
                     </div>
                     <h3><?= e($service['title']) ?></h3>
                     <p><?= e($service['description']) ?></p>
                     <div class="home-service-price"><?= e($service['price']) ?></div>
                     <div class="home-service-note"><?= e($service['note']) ?></div>
                     <a class="home-service-link" href="<?= e($service['href']) ?>">Ver serviço <i
                             class="fas fa-arrow-right" aria-hidden="true"></i></a>
                 </article>
                 <?php endforeach; ?>
             </div>
             <div class="home-services-footer reveal">
                 <a href="<?= BASE_URL ?>/servicos" class="btn btn-primary"><i class="fas fa-list"
                         aria-hidden="true"></i> Ver catálogo completo</a>
             </div>
             <p class="home-commercial-note">Domínio, alojamento, licenças e serviços de terceiros não estão incluídos,
                 salvo indicação expressa no orçamento. Funcionalidades adicionais podem alterar o preço e o prazo.</p>
         </div>
     </section>

     <!-- ════════ SKILLS ════════════════════════════════════════════ -->
     <section id="skills" class="section">
         <div class="container">
             <div class="section-head reveal">
                 <span class="tag">Habilidades</span>
                 <h2 class="section-title" style="margin-top:12px">Minhas <span class="text-gradient">Skills</span></h2>
                 <p class="section-sub">Competências técnicas registadas no portfólio, organizadas por categoria.</p>
             </div>

             <?php if (!empty($skills)): ?>
             <div class="skills-tabs reveal">
                 <?php $first = true;
                        foreach ($skills as $cat => $items): ?>
                 <button class="skill-tab <?= $first ? 'active' : '' ?>" data-tab="<?= e($cat) ?>"
                     onclick="switchTab(this.dataset.tab)">
                     <?= e(ucfirst($cat)) ?> <span style="opacity:.5;font-size:.8em">(<?= count($items) ?>)</span>
                 </button>
                 <?php $first = false;
                        endforeach; ?>
             </div>

             <?php $first = true;
                    foreach ($skills as $cat => $items): ?>
             <div class="skills-panel <?= $first ? 'active' : '' ?>" id="tab-<?= e($cat) ?>">
                 <?php foreach ($items as $i => $s):
                     $skill_pct = max(0, min(100, (int)($s['percentage_skill'] ?? 0)));
                 ?>
                 <div class="skill-card reveal" style="transition-delay:<?= $i * 0.05 ?>s">
                     <div class="skill-row">
                         <div class="skill-name-wrap">
                             <div class="skill-icon-wrap">
                                 <i class="<?= e($s['icon_skill'] ?: 'fas fa-code') ?>"></i>
                             </div>
                             <span class="skill-name"><?= e($s['name_skill']) ?></span>
                         </div>
                         <span class="skill-pct"><?= $skill_pct ?>%</span>
                     </div>
                     <div class="skill-bar-bg">
                         <div class="skill-bar-fill" data-width="<?= $skill_pct ?>"></div>
                     </div>
                 </div>
                 <?php endforeach; ?>
             </div>
             <?php $first = false;
                    endforeach; ?>

             <?php else: ?>
             <div class="projects-empty">
                 <i class="fas fa-tools"></i>
                 <p>Skills em preparação — volte em breve.</p>
             </div>
             <?php endif; ?>
         </div>
     </section>

     <!-- ════════ PROJECTS ══════════════════════════════════════════ -->
     <section id="projects" class="section section-alt">
         <div class="container">
             <div class="projects-head">
                 <div class="reveal">
                     <span class="tag">Portfólio</span>
                     <h2 class="section-title" style="margin-top:12px">Projectos <span
                             class="text-gradient">Seleccionados</span></h2>
                     <p class="section-sub">Uma selecção dos trabalhos que representam melhor a minha abordagem técnica.
                     </p>
                 </div>
                 <a href="<?= BASE_URL ?>/projects" class="btn btn-outline reveal"
                     style="transition-delay:.1s;flex-shrink:0">
                     Ver todos <i class="fas fa-arrow-right"></i>
                 </a>
             </div>

             <div class="projects-grid">
                 <?php if (!empty($projects)):
                        foreach ($projects as $i => $p):
                            $tech = [];
                            if ($p['tech_stack']) $tech = json_decode($p['tech_stack'], true) ?: [];
                            $icon = match ($p['category_project']) {
                                'mobile'  => 'fa-mobile-alt',
                                'api'     => 'fa-plug',
                                'design'  => 'fa-paint-brush',
                                default   => 'fa-globe'
                            };
                            $demo_url = $safe_http_url($p['url_demo'] ?? '');
                            $github_url = $safe_http_url($p['url_github'] ?? '');
                            $cover_file = basename((string)($p['cover_project'] ?? ''));
                    ?>
                 <article class="project-card reveal" style="transition-delay:<?= min($i * 0.1, 0.5) ?>s">
                     <div class="project-thumb">
                         <?php if ($cover_file !== '' && file_exists(ROOT_PATH . '/assets/img/projects/' . $cover_file)): ?>
                         <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($cover_file) ?>"
                             alt="<?= e($p['title_project']) ?>" loading="lazy">
                         <?php else: ?>
                         <div class="project-thumb-ph"><i class="fas <?= $icon ?>"></i></div>
                         <?php endif; ?>
                         <?php if ($p['is_featured']): ?><div class="project-featured">⭐ Destaque</div><?php endif; ?>
                         <div class="project-overlay">
                             <?php if ($demo_url !== ''): ?>
                             <a href="<?= e($demo_url) ?>" target="_blank" rel="noopener noreferrer" class="pov-btn"
                                 title="Demo"><i class="fas fa-external-link-alt"></i></a>
                             <?php endif; ?>
                             <?php if ($github_url !== ''): ?>
                             <a href="<?= e($github_url) ?>" target="_blank" rel="noopener noreferrer" class="pov-btn"
                                 title="GitHub"><i class="fab fa-github"></i></a>
                             <?php endif; ?>
                             <a href="<?= BASE_URL ?>/project/<?= e($p['slug_project']) ?>" class="pov-btn"
                                 title="Detalhes"><i class="fas fa-info"></i></a>
                         </div>
                     </div>
                     <div class="project-body">
                         <div class="project-cat"><?= e(ucfirst($p['category_project'])) ?></div>
                         <h3 class="project-title">
                             <a
                                 href="<?= BASE_URL ?>/project/<?= e($p['slug_project']) ?>"><?= e($p['title_project']) ?></a>
                         </h3>
                         <?php if ($p['summary_project']): ?>
                         <p class="project-desc"><?= e($p['summary_project']) ?></p>
                         <?php endif; ?>
                         <?php if (!empty($tech)): ?>
                         <div class="project-tags">
                             <?php foreach (array_slice($tech, 0, 5) as $t): ?>
                             <span class="ptag"><?= e(trim($t)) ?></span>
                             <?php endforeach; ?>
                         </div>
                         <?php endif; ?>
                     </div>
                 </article>
                 <?php endforeach;
                    else: ?>
                 <div class="projects-empty">
                     <i class="fas fa-rocket"></i>
                     <p>Projectos em preparação — volte em breve.</p>
                 </div>
                 <?php endif; ?>
             </div>
         </div>
     </section>

     <!-- ════════ CTA ════════════════════════════════════════════════ -->
     <div class="cta-section">
         <div class="cta-inner reveal">
             <h2 class="cta-title">Vamos planear o seu<br><span class="text-gradient">próximo projecto</span></h2>
             <p class="cta-sub">Apresente a sua ideia e receba uma proposta com escopo, entregáveis, preço em Kz e prazo
                 acordado antes do início.</p>
             <div class="cta-actions">
                 <a href="#contact" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                 <?php if ($wa): ?>
                 <a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><i
                         class="fab fa-whatsapp"></i>Conversar
                     no WhatsApp</a>
                 <?php endif; ?>
             </div>
             <div class="cta-stats">
                 <div class="cta-stat"><span class="number">50%</span><span class="label">Pagamento inicial como
                         regra-base</span></div>
                 <div class="cta-stat"><span class="number">2</span><span class="label">Rondas de revisão nos pacotes
                         web-base</span></div>
                 <div class="cta-stat"><span class="number">15–30</span><span class="label">Dias de garantia correctiva
                         a definir na proposta</span></div>
                 <div class="cta-stat"><span class="number">Kz</span><span class="label">Referências em moeda
                         local</span></div>
             </div>
             <p class="cta-note">Os termos finais dependem da proposta aprovada. O prazo começa após a entrada acordada
                 e a recepção dos conteúdos e acessos necessários. O suporte contínuo é separado da garantia correctiva.
             </p>
         </div>
     </div>

     <!-- ════════ CONTACT ═══════════════════════════════════════════ -->
     <section id="contact" class="section">
         <div class="container">
             <div class="contact-grid">
                 <div class="reveal">
                     <span class="tag">Contacto</span>
                     <h2 class="section-title" style="margin-top:14px">
                         Vamos construir algo<br><span class="text-gradient">extraordinário</span>
                     </h2>
                     <p class="section-sub">Tem um projecto em mente? Envie os detalhes para avaliarmos o escopo.</p>

                     <div class="contact-links">
                         <?php if ($e = $cfg('email_contact')): ?>
                         <a href="mailto:<?= e($e) ?>" class="contact-link">
                             <div class="contact-link-icon"><i class="fas fa-envelope"></i></div>
                             <?= e($e) ?>
                         </a>
                         <?php endif; ?>
                         <?php if ($ph = $cfg('phone_contact')): ?>
                         <a href="tel:<?= e(preg_replace('/\s+/', '', $ph)) ?>" class="contact-link">
                             <div class="contact-link-icon"><i class="fas fa-phone"></i></div>
                             <?= e($ph) ?>
                         </a>
                         <?php endif; ?>
                         <div class="contact-link">
                             <div class="contact-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                             <?= e($cfg('location', 'Luanda, Angola')) ?>
                         </div>
                         <?php if ($wa): ?>
                         <a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" class="contact-link">
                             <div class="contact-link-icon"><i class="fab fa-whatsapp"></i></div>
                             Conversar no WhatsApp
                         </a>
                         <?php endif; ?>
                     </div>

                     <div class="socials">
                         <?php $github_social = $safe_http_url($cfg('github_url')); ?>
                         <?php if ($github_social !== ''): ?><a href="<?= e($github_social) ?>" target="_blank"
                             rel="noopener noreferrer" class="social-link" aria-label="GitHub"><i
                                 class="fab fa-github"></i></a><?php endif; ?>
                         <?php $linkedin_social = $safe_http_url($cfg('linkedin_url')); ?>
                         <?php if ($linkedin_social !== ''): ?><a href="<?= e($linkedin_social) ?>" target="_blank"
                             rel="noopener noreferrer" class="social-link" aria-label="LinkedIn"><i
                                 class="fab fa-linkedin-in"></i></a><?php endif; ?>
                         <?php if ($wa !== ''): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"
                             class="social-link" aria-label="WhatsApp"><i
                                 class="fab fa-whatsapp"></i></a><?php endif; ?>
                     </div>
                 </div>

                 <div class="reveal" style="transition-delay:.15s">
                     <form id="contact-form" class="contact-form" novalidate>
                         <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                         <input type="text" name="_hp" style="display:none" tabindex="-1" autocomplete="off">

                         <div class="form-row">
                             <div class="form-group">
                                 <label class="form-label" for="contact-name">Nome *</label>
                                 <input id="contact-name" type="text" name="name" class="form-control"
                                     placeholder="José Silva" required>
                             </div>
                             <div class="form-group">
                                 <label class="form-label" for="contact-email">Email *</label>
                                 <input id="contact-email" type="email" name="email" class="form-control"
                                     placeholder="jose@exemplo.com" required>
                             </div>
                         </div>

                         <div class="form-group">
                             <label class="form-label" for="contact-phone">Telefone</label>
                             <input id="contact-phone" type="tel" name="phone" class="form-control"
                                 placeholder="+244 9xx xxx xxx">
                         </div>
                         <div class="form-group">
                             <label class="form-label" for="contact-subject">Assunto *</label>
                             <input id="contact-subject" type="text" name="subject" class="form-control"
                                 placeholder="Ex.: Desenvolvimento de um site institucional" required>
                         </div>
                         <div class="form-group">
                             <label class="form-label" for="contact-message">Mensagem *</label>
                             <textarea id="contact-message" name="message" class="form-control" rows="5"
                                 placeholder="Descreve o objectivo, as funcionalidades e o prazo pretendido..."
                                 required></textarea>
                         </div>

                         <div id="form-status" class="form-status"></div>

                         <button type="submit" class="btn btn-primary" id="form-submit">
                             <i class="fas fa-paper-plane"></i><span>Enviar Mensagem</span>
                         </button>
                     </form>
                 </div>
             </div>
         </div>
     </section>

     <?php include __DIR__ . '/includes/footer.php'; ?>
     <?php include __DIR__ . '/includes/bottom-nav.php'; ?>

 </body>

 </html>