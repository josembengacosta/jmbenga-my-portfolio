<?php
// ══════════════════════════════════════════════════════════════
// skills.php — JMbenga Portfolio v3.0
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

$accent   = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');

// ── Buscar todas as skills visíveis da BD ────────────────────
$skills_raw = $pdo->query("
    SELECT * FROM _skills
    WHERE is_visible = 1
    ORDER BY category_skill, display_order ASC
")->fetchAll();

// Agrupar por categoria
$skills = [];
foreach ($skills_raw as $s) {
    $skills[$s['category_skill']][] = $s;
}

// Categorias únicas para os filtros
$categories = array_keys($skills);
$current_cat = $_GET['cat'] ?? ($categories[0] ?? '');
if (!in_array($current_cat, $categories)) {
    $current_cat = $categories[0] ?? '';
}

$page_title = 'Skills — José Mbenga | Full Stack Developer';
$page_desc  = 'Conheça as habilidades técnicas de José Mbenga: PHP, MySQL, JavaScript, React, Vue.js, Node.js e muito mais.';
$page_section = 'skills';

?>
<?php include 'includes/header.php'; ?>


<link rel="stylesheet" href="assets/css/skills.css">
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



    <!-- Hero -->
    <section id="skills-hero">
        <div class="container skills-hero-inner">
            <span class="tag">Competências Técnicas</span>
            <h1>Minhas <span>Skills</span></h1>
            <p>Um panorama completo das tecnologias, ferramentas e competências que domino e utilizo diariamente para
                criar soluções digitais de alto impacto.</p>
        </div>
    </section>

    <!-- Filtros por categoria -->
    <div class="container">
        <div class="filters-bar reveal">
            <?php foreach ($categories as $cat): ?>
            <a href="?cat=<?= e($cat) ?>" class="filter-btn <?= $current_cat === $cat ? 'active' : '' ?>">
                <?= e(ucfirst($cat)) ?> <span class="filter-count">(<?= count($skills[$cat]) ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Grelha de skills -->
    <section class="container">
        <?php if (!empty($skills[$current_cat])): ?>
        <div class="skills-grid">
            <?php foreach ($skills[$current_cat] as $i => $s):
                    // Descrição opcional (pode vir da BD no futuro)
                    $desc = $s['description_skill'] ?? '';
                    if (empty($desc)) {
                        $descs = [
                            'frontend' => 'Domínio sólido em desenvolvimento de interfaces modernas, responsivas e acessíveis.',
                            'backend'  => 'Experiência em arquitectura de servidores, APIs e bases de dados.',
                            'devops'   => 'Conhecimento em deploy, CI/CD e infra-estrutura cloud.',
                            'design'   => 'Habilidade em design de interfaces e prototipagem.',
                            'mobile'   => 'Desenvolvimento de aplicações móveis nativas e híbridas.',
                        ];
                        $desc = $descs[$s['category_skill']] ?? 'Tecnologia dominada com experiência prática em projectos reais.';
                    }
                ?>
            <div class="skill-card reveal" style="transition-delay:<?= min($i * 0.05, 0.5) ?>s">
                <div class="skill-card-header">
                    <div class="skill-icon-name">
                        <div class="skill-icon-circle">
                            <i class="<?= e($s['icon_skill'] ?: 'fas fa-code') ?>"></i>
                        </div>
                        <span class="skill-name"><?= e($s['name_skill']) ?></span>
                    </div>
                    <span class="skill-pct"><?= (int)$s['percentage_skill'] ?>%</span>
                </div>
                <div class="skill-bar-bg">
                    <div class="skill-bar-fill" data-width="<?= (int)$s['percentage_skill'] ?>"></div>
                </div>
                <p class="skill-desc"><?= e($desc) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="text-align:center;padding:80px 20px;color:var(--text-muted)">
            <i class="fas fa-tools"
                style="font-size:3rem;margin-bottom:16px;display:block;color:var(--accent);opacity:.35"></i>
            <h3 style="font-family:var(--font-head);margin-bottom:8px;color:var(--text-dim)">Nenhuma skill nesta
                categoria</h3>
            <p>As skills estão a ser actualizadas. Volte em breve!</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Ferramentas e Tecnologias -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Ferramentas</span>
                <h2 class="section-title">Tecnologias e <span class="text-gradient">Ferramentas</span></h2>
                <p class="section-sub">As principais tecnologias com as quais trabalho no dia a dia.</p>
            </div>
            <div class="tools-grid reveal">
                <?php
                $tools = [
                    ['icon' => 'fab fa-php', 'name' => 'PHP 8.2'],
                    ['icon' => 'fas fa-database', 'name' => 'MySQL'],
                    ['icon' => 'fab fa-js', 'name' => 'JavaScript'],
                    ['icon' => 'fab fa-react', 'name' => 'React'],
                    ['icon' => 'fab fa-vuejs', 'name' => 'Vue.js'],
                    ['icon' => 'fab fa-node-js', 'name' => 'Node.js'],
                    ['icon' => 'fab fa-figma', 'name' => 'Figma'],
                    ['icon' => 'fab fa-git-alt', 'name' => 'Git'],
                    ['icon' => 'fab fa-docker', 'name' => 'Docker'],
                    ['icon' => 'fas fa-cloud', 'name' => 'AWS'],
                    ['icon' => 'fab fa-laravel', 'name' => 'Laravel'],
                    ['icon' => 'fas fa-code', 'name' => 'VS Code'],
                    ['icon' => 'fab fa-ubuntu', 'name' => 'Linux'],
                    ['icon' => 'fas fa-terminal', 'name' => 'CLI'],
                ];
                foreach ($tools as $tool): ?>
                <div class="tool-card reveal">
                    <i class="<?= $tool['icon'] ?>"></i>
                    <h4><?= $tool['name'] ?></h4>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Soft Skills -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Soft Skills</span>
                <h2 class="section-title">Competências <span class="text-gradient">Interpessoais</span></h2>
                <p class="section-sub">Para além da técnica, estas são as qualidades que aplico em cada projecto.</p>
            </div>
            <div class="soft-grid reveal">
                <?php
                $softs = [
                    ['icon' => 'fa-comments', 'title' => 'Comunicação', 'desc' => 'Capacidade de explicar conceitos técnicos de forma clara para clientes e equipas multidisciplinares.'],
                    ['icon' => 'fa-clock', 'title' => 'Gestão de Tempo', 'desc' => 'Organização e cumprimento de prazos, mesmo em projectos com múltiplas entregas simultâneas.'],
                    ['icon' => 'fa-lightbulb', 'title' => 'Resolução de Problemas', 'desc' => 'Abordagem analítica e criativa para encontrar soluções eficientes para desafios complexos.'],
                    ['icon' => 'fa-handshake', 'title' => 'Trabalho em Equipa', 'desc' => 'Experiência em equipas ágeis, colaboração remota e integração com diferentes áreas.'],
                    ['icon' => 'fa-book-open', 'title' => 'Aprendizagem Contínua', 'desc' => 'Paixão por aprender novas tecnologias e manter-me actualizado com as tendências do mercado.'],
                    ['icon' => 'fa-eye', 'title' => 'Atenção ao Detalhe', 'desc' => 'Foco na qualidade do código, design e experiência do utilizador em cada aspecto do projecto.'],
                ];
                foreach ($softs as $soft): ?>
                <div class="soft-card reveal">
                    <i class="fas <?= $soft['icon'] ?>"></i>
                    <h4><?= $soft['title'] ?></h4>
                    <p><?= $soft['desc'] ?></p>
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
                Precisa de um Profissional com <span class="text-gradient">Estas Skills?</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Estou disponível para dar vida ao seu próximo projecto com todo o meu conhecimento técnico.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = <<<JS
// Animar barras de skill ao entrar na viewport
const barObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.width = (e.target.dataset.width || 0) + '%';
      barObserver.unobserve(e.target);
    }
  });
}, { threshold: .3 });
document.querySelectorAll('.skill-bar-fill').forEach(b => barObserver.observe(b));
JS;

    $page_scripts = [];
    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>