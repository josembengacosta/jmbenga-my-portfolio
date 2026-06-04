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

    $testimonials = $pdo->query("
    SELECT * FROM _testimonials
    WHERE status_testimonial = 'visible'
    ORDER BY display_order ASC LIMIT 6
")->fetchAll();

    $total_projects = max(
        (int)$pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project='published'")->fetchColumn(),
        (int)$cfg('projects_count', '50')
    );
    $years_exp  = (int)$cfg('years_experience', '3');
    $clients    = (int)$cfg('clients_count', '100');
    $accent     = $cfg('accent_color', '#2563eb');

    $page_title = 'José Mbenga — Full Stack Developer | Angola';
    $meta_desc  = $cfg(
        'meta_description',
        'Desenvolvedor Full Stack com 3+ anos de experiência. Especialista em PHP, MySQL, JavaScript, React e Node.js. Baseado em Luanda, Angola.'
    );

    $page_section = 'home';

    ?>
 <?php include 'includes/header.php'; ?>

 <link rel="stylesheet" href="assets/css/home.css">

 <style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: #1d4ed8;
    --accent-glow: <?=e($accent) ?>26;
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
    background: radial-gradient(circle, <?=e($accent) ?>22 0%, transparent 65%);
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
     <?php include __DIR__ . '/includes/navbar.php'; ?>

     <!-- ════════ HERO ══════════════════════════════════════════════ -->
     <section id="home">
         <div class="hero-bg-grid"></div>
         <div class="hero-blob"></div>
         <div class="hero-blob-2"></div>

         <canvas id="particles-canvas" style="height: 100vh;"></canvas>


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

                 <p class="hero-subtitle">
                     <?= e($cfg(
                            'hero_subtitle',
                            'Transformo ideias complexas em soluções digitais elegantes e performáticas. '
                                . 'Mais de ' . $years_exp . ' anos criando experiências excepcionais para clientes globais.'
                        )) ?>
                 </p>

                 <!-- Tech pills -->
                 <div class="tech-stack">
                     <span class="tech-pill"><i class="fab fa-php"></i>PHP</span>
                     <span class="tech-pill"><i class="fas fa-database"></i>MySQL</span>
                     <span class="tech-pill"><i class="fab fa-js"></i>JavaScript</span>
                     <span class="tech-pill"><i class="fab fa-react"></i>React</span>
                     <span class="tech-pill"><i class="fab fa-vuejs"></i>Vue.js</span>
                     <span class="tech-pill"><i class="fab fa-node-js"></i>Node.js</span>
                     <span class="tech-pill"><i class="fab fa-git-alt"></i>Git</span>
                 </div>

                 <div class="hero-cta">
                     <a href="#contact" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Iniciar
                         Projecto</a>
                     <a href="#projects" class="btn btn-secondary"><i class="fas fa-eye"></i>Ver Trabalhos</a>
                     <a href="<?= BASE_URL ?>/projects" class="btn btn-outline"><i class="fas fa-list"></i>Todos os
                         Projectos</a>
                 </div>

                 <div class="hero-stats">
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter"
                                 data-target="<?= $total_projects ?>">0</span><span class="stat-suffix">+</span>
                         </div>
                         <div class="stat-label">Projectos</div>
                     </div>
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter" data-target="<?= $years_exp ?>">0</span><span
                                 class="stat-suffix">+</span></div>
                         <div class="stat-label">Anos Exp.</div>
                     </div>
                     <div class="stat-item">
                         <div class="stat-number"><span class="counter" data-target="<?= $clients ?>">0</span><span
                                 class="stat-suffix">+</span></div>
                         <div class="stat-label">Clientes</div>
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
  <span class="str">'frontend'</span> => [<span class="str">'React'</span>, <span class="str">'Vue.js'</span>, <span class="str">'TypeScript'</span>],
  <span class="str">'backend'</span>  => [<span class="str">'PHP'</span>, <span class="str">'MySQL'</span>, <span class="str">'Node.js'</span>],
  <span class="str">'tools'</span>    => [<span class="str">'Git'</span>, <span class="str">'Figma'</span>, <span class="str">'AWS'</span>],
];

<span class="kw">$</span><span class="var">available</span> = <span class="kw">true</span>; <span class="cmt">// aberto a projectos</span>

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
                         <div class="hbf-text"><?= $total_projects ?>+</div>
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
                         <div class="aeb-num"><?= $years_exp ?>+</div>
                         <div class="aeb-label">Anos de<br>experiência</div>
                     </div>

                     <div class="highlights">
                         <div class="highlight-card">
                             <div class="highlight-icon"><i class="fas fa-rocket"></i></div>
                             <h4><?= $total_projects ?>+ Projectos</h4>
                             <p>Entregues com sucesso</p>
                         </div>
                         <div class="highlight-card">
                             <div class="highlight-icon"><i class="fas fa-users"></i></div>
                             <h4><?= $clients ?>+ Clientes</h4>
                             <p>Satisfeitos globalmente</p>
                         </div>
                     </div>
                 </div>

                 <!-- Conteúdo -->
                 <div class="about-content reveal" style="transition-delay:.15s">
                     <span class="tag">Sobre mim</span>
                     <h2 class="section-title" style="margin-top:14px">
                         Inovando com código<br>desde <span class="text-gradient">2023</span>
                     </h2>

                     <?php
                        $about_text = $cfg('about_text');
                        $paras = $about_text
                            ? array_filter(array_map('trim', explode("\n", $about_text)))
                            : [
                                'Sou José Mbenga, desenvolvedor Full Stack com mais de ' . $years_exp . ' anos de experiência a criar soluções digitais inovadoras. A minha paixão é transformar ideias complexas em interfaces intuitivas e sistemas eficientes.',
                                'Especializado em PHP, MySQL, JavaScript moderno, React e Node.js, acredito que o bom código vai além da funcionalidade — deve ser elegante, eficiente e sustentável.',
                            ];
                        foreach ($paras as $p): ?>
                     <p class="about-text"><?= e($p) ?></p>
                     <?php endforeach; ?>

                     <div class="detail-cards">
                         <div class="detail-card">
                             <i class="fas fa-map-marker-alt"></i>
                             <div>
                                 <h5>Localização</h5>
                                 <p><?= e($cfg('location', 'Angola, Luanda')) ?></p><small>Disponível para projectos
                                     remotos</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-graduation-cap"></i>
                             <div>
                                 <h5>Formação</h5>
                                 <p>Engenharia Informática</p><small>+5 Certificações Tech</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-language"></i>
                             <div>
                                 <h5>Idiomas</h5>
                                 <p>Português, Inglês, Francês</p><small>Lingala · fluente em 4 idiomas</small>
                             </div>
                         </div>
                         <div class="detail-card">
                             <i class="fas fa-briefcase"></i>
                             <div>
                                 <h5>Experiência</h5>
                                 <p><?= $years_exp ?>+ Anos</p><small>Desenvolvimento Web Full Stack</small>
                             </div>
                         </div>
                     </div>

                     <!-- Timeline -->
                     <div class="timeline">
                         <div class="timeline-item">
                             <div class="timeline-year">2023</div>
                             <div class="timeline-content">
                                 <h4>Início como Freelancer</h4>
                                 <p>Primeiros projectos web para clientes em Angola</p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">2023</div>
                             <div class="timeline-content">
                                 <h4>Expansão Internacional</h4>
                                 <p>Clientes globais e projectos em escala empresarial</p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">2024</div>
                             <div class="timeline-content">
                                 <h4>Foco em UI/UX &amp; Performance</h4>
                                 <p>Optimização avançada e experiências de utilizador premium</p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">2025</div>
                             <div class="timeline-content">
                                 <h4>Especialização em React.js</h4>
                                 <p>Certificação e projectos avançados com React e TypeScript</p>
                             </div>
                         </div>
                         <div class="timeline-item">
                             <div class="timeline-year">2026</div>
                             <div class="timeline-content">
                                 <h4>Full Stack Architecture</h4>
                                 <p>Projectos completos PHP+MySQL, Node.js e sistemas escaláveis</p>
                             </div>
                         </div>
                     </div>

                     <div class="about-cta">
                         <a href="<?= BASE_URL ?>/cv" target="_blank" class="btn btn-primary">
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

     <!-- ════════ SKILLS ════════════════════════════════════════════ -->
     <section id="skills" class="section">
         <div class="container">
             <div class="section-head reveal">
                 <span class="tag">Habilidades</span>
                 <h2 class="section-title" style="margin-top:12px">Minhas <span class="text-gradient">Skills</span></h2>
                 <p class="section-sub">Tecnologias e ferramentas que domino e uso no dia a dia.</p>
             </div>

             <?php if (!empty($skills)): ?>
             <div class="skills-tabs reveal">
                 <?php $first = true;
                        foreach ($skills as $cat => $items): ?>
                 <button class="skill-tab <?= $first ? 'active' : '' ?>" data-tab="<?= e($cat) ?>"
                     onclick="switchTab('<?= e($cat) ?>')">
                     <?= e(ucfirst($cat)) ?> <span style="opacity:.5;font-size:.8em">(<?= count($items) ?>)</span>
                 </button>
                 <?php $first = false;
                        endforeach; ?>
             </div>

             <?php $first = true;
                    foreach ($skills as $cat => $items): ?>
             <div class="skills-panel <?= $first ? 'active' : '' ?>" id="tab-<?= e($cat) ?>">
                 <?php foreach ($items as $i => $s): ?>
                 <div class="skill-card reveal" style="transition-delay:<?= $i * 0.05 ?>s">
                     <div class="skill-row">
                         <div class="skill-name-wrap">
                             <div class="skill-icon-wrap">
                                 <i class="<?= e($s['icon_skill'] ?: 'fas fa-code') ?>"></i>
                             </div>
                             <span class="skill-name"><?= e($s['name_skill']) ?></span>
                         </div>
                         <span class="skill-pct"><?= (int)$s['percentage_skill'] ?>%</span>
                     </div>
                     <div class="skill-bar-bg">
                         <div class="skill-bar-fill" data-width="<?= (int)$s['percentage_skill'] ?>"></div>
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
                    ?>
                 <article class="project-card reveal" style="transition-delay:<?= min($i * 0.1, 0.5) ?>s">
                     <div class="project-thumb">
                         <?php if ($p['cover_project'] && file_exists(ROOT_PATH . '/assets/img/projects/' . $p['cover_project'])): ?>
                         <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($p['cover_project']) ?>"
                             alt="<?= e($p['title_project']) ?>" loading="lazy">
                         <?php else: ?>
                         <div class="project-thumb-ph"><i class="fas <?= $icon ?>"></i></div>
                         <?php endif; ?>
                         <?php if ($p['is_featured']): ?><div class="project-featured">⭐ Destaque</div><?php endif; ?>
                         <div class="project-overlay">
                             <?php if ($p['url_demo']): ?>
                             <a href="<?= e($p['url_demo']) ?>" target="_blank" class="pov-btn" title="Demo"><i
                                     class="fas fa-external-link-alt"></i></a>
                             <?php endif; ?>
                             <?php if ($p['url_github']): ?>
                             <a href="<?= e($p['url_github']) ?>" target="_blank" class="pov-btn" title="GitHub"><i
                                     class="fab fa-github"></i></a>
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

     <!-- ════════ TESTIMONIALS ══════════════════════════════════════ -->
     <?php if (!empty($testimonials)): ?>
     <section id="testimonials" class="section">
         <div class="container">
             <div class="section-head reveal">
                 <span class="tag">Depoimentos</span>
                 <h2 class="section-title" style="margin-top:12px">O que dizem os <span
                         class="text-gradient">Clientes</span></h2>
                 <p class="section-sub">Feedback real de pessoas e empresas com quem tive o prazer de trabalhar.</p>
             </div>
             <div class="testi-grid">
                 <?php foreach ($testimonials as $i => $t): ?>
                 <div class="testi-card reveal" style="transition-delay:<?= $i * 0.1 ?>s">
                     <div class="testi-quote">"</div>
                     <p class="testi-body"><?= e($t['body_testimonial']) ?></p>
                     <div class="testi-stars">
                         <?php for ($s = 0; $s < 5; $s++): ?>
                         <i class="fa<?= $s < $t['rating_testimonial'] ? 's' : 'r' ?> fa-star"></i>
                         <?php endfor; ?>
                     </div>
                     <div class="testi-author">
                         <?php if ($t['photo_testimonial']): ?>
                         <img src="<?= BASE_URL ?>/assets/img/testimonials/<?= e($t['photo_testimonial']) ?>"
                             class="testi-avatar" alt="<?= e($t['name_testimonial']) ?>">
                         <?php else: ?>
                         <div class="testi-avatar-ph"><?= strtoupper(substr($t['name_testimonial'], 0, 1)) ?></div>
                         <?php endif; ?>
                         <div>
                             <div class="testi-name"><?= e($t['name_testimonial']) ?></div>
                             <div class="testi-role">
                                 <?= e(implode(' · ', array_filter([$t['role_testimonial'], $t['company_testimonial']]))) ?>
                             </div>
                         </div>
                     </div>
                 </div>
                 <?php endforeach; ?>
             </div>
         </div>
     </section>
     <?php endif; ?>

     <!-- ════════ CTA ════════════════════════════════════════════════ -->
     <div class="cta-section">
         <div class="cta-inner reveal">
             <h2 class="cta-title">Pronto para Iniciar o<br><span class="text-gradient">Próximo Projecto?</span></h2>
             <p class="cta-sub">Vamos transformar a sua ideia em realidade. Desenvolvimento ágil, código limpo e
                 resultados excepcionais.</p>
             <div class="cta-actions">
                 <a href="#contact" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                 <?php if ($wa): ?>
                 <a href="<?= e($wa) ?>" target="_blank" class="btn btn-secondary"><i
                         class="fab fa-whatsapp"></i>Conversar
                     no WhatsApp</a>
                 <?php endif; ?>
             </div>
             <div class="cta-stats">
                 <div class="cta-stat"><span class="number">24h</span><span class="label">Resposta Garantida</span>
                 </div>
                 <div class="cta-stat"><span class="number">100%</span><span class="label">Satisfação</span></div>
                 <div class="cta-stat"><span class="number">15d</span><span class="label">Entrega Média</span></div>
                 <div class="cta-stat"><span class="number"><?= $total_projects ?>+</span><span
                         class="label">Projectos</span></div>
             </div>
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
                     <p class="section-sub">Tem um projecto em mente? Estou disponível para conversar.</p>

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
                         <a href="<?= e($wa) ?>" target="_blank" class="contact-link">
                             <div class="contact-link-icon"><i class="fab fa-whatsapp"></i></div>
                             Conversar no WhatsApp
                         </a>
                         <?php endif; ?>
                     </div>

                     <div class="socials">
                         <?php if ($cfg('github_url')):   ?><a href="<?= e($cfg('github_url')) ?>" target="_blank"
                             class="social-link"><i class="fab fa-github"></i></a><?php endif; ?>
                         <?php if ($cfg('linkedin_url')): ?><a href="<?= e($cfg('linkedin_url')) ?>" target="_blank"
                             class="social-link"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
                         <?php if ($wa):                  ?><a href="<?= e($wa) ?>" target="_blank"
                             class="social-link"><i class="fab fa-whatsapp"></i></a><?php endif; ?>
                     </div>
                 </div>

                 <div class="reveal" style="transition-delay:.15s">
                     <form id="contact-form" class="contact-form" novalidate>
                         <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                         <input type="text" name="_hp" style="display:none" tabindex="-1" autocomplete="off">

                         <div class="form-row">
                             <div class="form-group">
                                 <label class="form-label">Nome *</label>
                                 <input type="text" name="name" class="form-control" placeholder="José Silva" required>
                             </div>
                             <div class="form-group">
                                 <label class="form-label">Email *</label>
                                 <input type="email" name="email" class="form-control" placeholder="jose@exemplo.com"
                                     required>
                             </div>
                         </div>

                         <div class="form-group">
                             <label class="form-label">Telefone</label>
                             <input type="tel" name="phone" class="form-control" placeholder="+244 9xx xxx xxx">
                         </div>
                         <div class="form-group">
                             <label class="form-label">Assunto *</label>
                             <input type="text" name="subject" class="form-control" placeholder="Preciso de um site..."
                                 required>
                         </div>
                         <div class="form-group">
                             <label class="form-label">Mensagem *</label>
                             <textarea name="message" class="form-control" rows="5" placeholder="Descreva o projecto..."
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

     <?php include 'includes/footer.php'; ?>
     <?php include 'includes/bottom-nav.php'; ?>

 </body>

 </html>