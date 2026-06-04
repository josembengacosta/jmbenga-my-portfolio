<?php
// ══════════════════════════════════════════════════════════════
// about.php — JMbenga Portfolio v3.0
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

$years_exp  = (int)$cfg('years_experience', '3');
$clients    = (int)$cfg('clients_count', '100');
$total_projects = max((int)$pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project='published'")->fetchColumn(), (int)$cfg('projects_count', '50'));
$accent     = $cfg('accent_color', '#2563eb');
$whatsapp   = $cfg('whatsapp_url');
$email      = $cfg('email_contact');
$phone      = $cfg('phone_contact');
$location   = $cfg('location', 'Luanda, Angola');

$page_title = 'Sobre — José Mbenga | Full Stack Developer';
$page_desc  = $cfg('about_meta', 'Conheça a trajetória, habilidades e experiência de José Mbenga, desenvolvedor Full Stack em Luanda, Angola.');
$page_section = 'about';

?>
<?php

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/css/about.css">

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


    <!-- Hero da página Sobre -->
    <section id="about-hero">
        <div class="container">
            <div class="about-hero-inner">
                <div class="about-hero-text reveal">
                    <span class="tag">Sobre mim</span>
                    <h1>Transformando ideias em <span>experiências digitais</span></h1>
                    <p>
                        <?= e($cfg('about_summary', "Sou José Mbenga, desenvolvedor Full Stack com mais de $years_exp anos de experiência a criar soluções digitais inovadoras para clientes em Angola e no mundo. A minha paixão é transformar ideias complexas em interfaces intuitivas e sistemas eficientes.")) ?>
                    </p>
                    <div class="about-cta" style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>Entrar em Contacto
                        </a>
                        <?php if ($cfg('cv_file')): ?>
                        <a href="<?= BASE_URL ?>/cv" target="_blank" class="btn btn-primary">
                            <i class="fas fa-eye"></i>Ver Currículo
                        </a>
                        <a href="<?= BASE_URL ?>/download/cv" class="btn btn-outline">
                            <i class="fas fa-download"></i>Baixar CV
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="reveal" style="transition-delay:.15s">
                    <?php
                    $profile = get_public_profile_photo($pdo, $cfg('photo_profile'));          if ($profile): ?>
                    <img src="<?= e($profile['url']) ?>" alt="José Mbenga" class="about-hero-avatar">
                    <?php else: ?>
                    <div class="about-hero-placeholder"><i class="fas fa-user-tie"></i></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Biografia detalhada -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">A Minha História</span>
                <h2 class="section-title">Da Paixão pelo Código à <span class="text-gradient">Excelência Full
                        Stack</span>
                </h2>
                <p class="section-sub">Uma jornada de aprendizado contínuo e evolução profissional.</p>
            </div>

            <div class="reveal" style="max-width:800px;margin:0 auto">
                <?php
                $about_long = $cfg('about_long');
                $paras = $about_long
                    ? array_filter(array_map('trim', explode("\n", $about_long)))
                    : [
                        "Sou José Mbenga da Costa, nascido e criado em Luanda, Angola. A minha jornada na tecnologia começou ainda na escola secundária, quando criei o meu primeiro website HTML para um trabalho de informática. A sensação de construir algo a partir do zero e vê‑lo a funcionar no navegador foi o suficiente para acender uma paixão que nunca mais se apagou.",
                        "Com o tempo, fui aprofundando os meus conhecimentos: comecei com HTML e CSS, depois JavaScript, e rapidamente percebi que o desenvolvimento web era muito mais do que páginas estáticas. Aprendi PHP e MySQL para criar sistemas dinâmicos, e mais tarde mergulhei no ecossistema React e Node.js.",
                        "Hoje, com mais de $years_exp anos de experiência profissional, já colaborei com mais de $clients clientes e entreguei mais de $total_projects projectos — desde simples landing pages a plataformas de e‑commerce completas e dashboards analíticos. Cada projecto é uma oportunidade de aprender algo novo e de superar os meus próprios limites.",
                        "Acredito que o bom código vai além da funcionalidade — deve ser elegante, eficiente e sustentável. O meu foco está em criar soluções que realmente façam a diferença para os meus clientes e para os seus utilizadores."
                    ];
                foreach ($paras as $p): ?>
                <p class="about-text" style="font-size:1rem;color:var(--text-dim);line-height:1.8;margin-bottom:18px">
                    <?= e($p) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Skills (visão geral) -->
    <section class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Habilidades Técnicas</span>
                <h2 class="section-title">Tecnologias e Ferramentas que <span class="text-gradient">Domino</span></h2>
                <p class="section-sub">Um panorama das tecnologias com as quais trabalho diariamente.</p>
            </div>

            <div class="reveal">
                <?php
                $skills_raw = $pdo->query("SELECT * FROM _skills WHERE is_visible = 1 ORDER BY category_skill, display_order ASC")->fetchAll();
                $skills = [];
                foreach ($skills_raw as $s) $skills[$s['category_skill']][] = $s;

                if (!empty($skills)):
                    foreach ($skills as $cat => $items): ?>
                <h3
                    style="font-family:var(--font-head);font-size:1.2rem;margin:32px 0 16px;text-transform:capitalize;color:var(--accent)">
                    <i class="fas fa-code" style="margin-right:8px"></i> <?= e(ucfirst($cat)) ?>
                </h3>
                <div class="skills-panel active"
                    style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:24px">
                    <?php foreach ($items as $s): ?>
                    <div class="skill-card"
                        style="background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:18px 20px">
                        <div class="skill-row"
                            style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                            <div class="skill-name-wrap" style="display:flex;align-items:center;gap:9px">
                                <div class="skill-icon-wrap"
                                    style="width:34px;height:34px;border-radius:8px;background:var(--accent-glow);border:1px solid var(--border-acc);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:.88rem">
                                    <i class="<?= e($s['icon_skill'] ?: 'fas fa-code') ?>"></i>
                                </div>
                                <span class="skill-name"
                                    style="font-weight:600;font-size:.9rem"><?= e($s['name_skill']) ?></span>
                            </div>
                            <span class="skill-pct"
                                style="font-family:var(--font-mono);font-size:.76rem;color:var(--accent)"><?= (int)$s['percentage_skill'] ?>%</span>
                        </div>
                        <div class="skill-bar-bg"
                            style="height:3px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden">
                            <div class="skill-bar-fill" data-width="<?= (int)$s['percentage_skill'] ?>"
                                style="height:100%;width:0;background:linear-gradient(90deg,var(--accent),#818cf8);border-radius:2px;transition:width 1.1s var(--ease)">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach;
                else: ?>
                <div class="empty-state"><i class="fas fa-tools"></i>
                    <h3>Skills em preparação</h3>
                    <p>Volte em breve para conferir as minhas habilidades técnicas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Serviços -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Serviços</span>
                <h2 class="section-title">O Que Posso Fazer por <span class="text-gradient">Si</span></h2>
                <p class="section-sub">Soluções completas para levar o seu negócio ao próximo nível.</p>
            </div>
            <div class="services-grid reveal">
                <?php
                $services = [
                    ['icon' => 'fa-globe', 'title' => 'Desenvolvimento Web', 'desc' => 'Sites institucionais, portfólios e landing pages de alta performance com design moderno e responsivo.'],
                    ['icon' => 'fa-shopping-cart', 'title' => 'E‑commerce', 'desc' => 'Lojas virtuais completas com carrinho, checkout, gestão de produtos e integração com meios de pagamento.'],
                    ['icon' => 'fa-plug', 'title' => 'APIs & Backend', 'desc' => 'Desenvolvimento de APIs REST robustas e sistemas backend escaláveis com PHP, MySQL e Node.js.'],
                    ['icon' => 'fa-mobile-alt', 'title' => 'Aplicações Web', 'desc' => 'Aplicações web progressivas (PWA) com experiência de app nativo, instaláveis e com suporte offline.'],
                    ['icon' => 'fa-paint-brush', 'title' => 'UI/UX Design', 'desc' => 'Design de interfaces modernas e intuitivas, focadas na experiência do utilizador e na conversão.'],
                    ['icon' => 'fa-chart-line', 'title' => 'Consultoria Tech', 'desc' => 'Orientação estratégica para escolha de tecnologias, arquitetura de sistemas e otimização de performance.'],
                ];
                foreach ($services as $svc): ?>
                <div class="service-card">
                    <i class="fas <?= $svc['icon'] ?>"></i>
                    <h3><?= $svc['title'] ?></h3>
                    <p><?= $svc['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Abordagem de trabalho -->
    <section class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Processo</span>
                <h2 class="section-title">Como <span class="text-gradient">Trabalho</span></h2>
                <p class="section-sub">Uma metodologia clara e eficiente para transformar a sua ideia em realidade.</p>
            </div>
            <div class="approach-grid reveal">
                <div class="approach-card">
                    <div class="step">1</div>
                    <h4>Descoberta</h4>
                    <p>Conversamos para entender as suas necessidades, objectivos e o público‑alvo do projecto.</p>
                </div>
                <div class="approach-card">
                    <div class="step">2</div>
                    <h4>Planeamento</h4>
                    <p>Definimos a arquitetura, tecnologias e cronograma do projecto com total transparência.</p>
                </div>
                <div class="approach-card">
                    <div class="step">3</div>
                    <h4>Desenvolvimento</h4>
                    <p>Codificação limpa e componentizada, com entregas iterativas para feedback contínuo.</p>
                </div>
                <div class="approach-card">
                    <div class="step">4</div>
                    <h4>Testes</h4>
                    <p>Testes rigorosos de funcionalidade, performance, segurança e compatibilidade.</p>
                </div>
                <div class="approach-card">
                    <div class="step">5</div>
                    <h4>Lançamento</h4>
                    <p>Deploy em produção, monitorização e otimizações pós‑lançamento.</p>
                </div>
                <div class="approach-card">
                    <div class="step">6</div>
                    <h4>Suporte</h4>
                    <p>Acompanhamento contínuo, actualizações e manutenção para garantir o sucesso a longo prazo.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Final -->
    <div class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:100px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Vamos Trabalhar <span class="text-gradient">Juntos?</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Estou disponível para novos projectos e colaborações. Seja qual for o desafio, estou pronto para ajudar.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = <<<JS
// Reinicializar barras de skill e revelar elementos que entraram na viewport após o carregamento da página
setTimeout(() => {
  document.querySelectorAll('.skill-bar-fill').forEach(b => {
    b.style.width = (b.dataset.width || 0) + '%';
  });
}, 200);
JS;

    $page_scripts = [];

    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>