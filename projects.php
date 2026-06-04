<?php
// ══════════════════════════════════════════════════════════════
// projects.php — JMbenga Portfolio v3.0 (Melhorado)
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

$accent = $cfg('accent_color', '#2563eb');

// ── Filtros e ordenação ──────────────────────────────────────
$current_cat    = $_GET['cat']   ?? 'all';
$current_sort   = $_GET['sort']  ?? 'featured';
$current_search = trim($_GET['search'] ?? '');

$allowed_cats  = ['all', 'web', 'mobile', 'api', 'design', 'desktop', 'other'];
$allowed_sorts = ['featured', 'newest', 'oldest', 'name'];

if (!in_array($current_cat, $allowed_cats))   $current_cat  = 'all';
if (!in_array($current_sort, $allowed_sorts)) $current_sort = 'featured';

// ── Consulta base ───────────────────────────────────────────
$sql = "SELECT * FROM _projects WHERE status_project = 'published'";
$params = [];

if ($current_cat !== 'all') {
    $sql .= " AND category_project = ?";
    $params[] = $current_cat;
}
if (!empty($current_search)) {
    $sql .= " AND (title_project LIKE ? OR summary_project LIKE ? OR slug_project LIKE ?)";
    $params[] = "%$current_search%";
    $params[] = "%$current_search%";
    $params[] = "%$current_search%";
}

// Ordenação
$orderClause = match ($current_sort) {
    'newest'  => 'creat_project DESC',
    'oldest'  => 'creat_project ASC',
    'name'    => 'title_project ASC',
    default   => 'is_featured DESC, display_order ASC',
};
$sql .= " ORDER BY $orderClause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$total_projects = count($projects);

// ── Categorias existentes ───────────────────────────────────
$cat_stmt = $pdo->query("SELECT DISTINCT category_project FROM _projects WHERE status_project = 'published' ORDER BY category_project ASC");
$existing_cats = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// ── Estatísticas globais ────────────────────────────────────
$totalAllProjects   = (int) $pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$totalCategories    = count($existing_cats);
$totalTechnologies  = (int) $pdo->query("SELECT COUNT(*) FROM _skills WHERE is_visible = 1")->fetchColumn();
$recentProjects     = (int) $pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published' AND creat_project >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// ── Meta dados ──────────────────────────────────────────────
$page_title   = 'Projectos — José Mbenga | Full Stack Developer';
$page_desc    = 'Explore o portfólio completo de José Mbenga. Web, Mobile, APIs, UI/UX Design e muito mais.';
$page_section = 'projects';
?>
<?php
include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/projects.css">

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
    --success: #10b981;
    --warning: #f59e0b;
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

/* ─── ESTATÍSTICAS RÁPIDAS (NOVO) ──────────────────────────── */
.hero-stats-row {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.hero-stat-mini {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .9rem 1.3rem;
    text-align: center;
    min-width: 100px;
    transition: all .25s;
}

.hero-stat-mini:hover {
    transform: translateY(-2px);
    border-color: var(--border-acc);
}

.hero-stat-mini .num {
    font-family: var(--font-head);
    font-size: 1.7rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--accent), #a78bfa);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.hero-stat-mini .lbl {
    font-size: .7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-top: 2px;
}

/* ─── FILTROS EXTRA (NOVO) ─────────────────────────────────── */
.filters-extra {
    display: flex;
    gap: .6rem;
    justify-content: center;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 1rem;
}

.filters-extra select,
.filters-extra input {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    padding: .45rem .75rem;
    border-radius: 8px;
    font-family: var(--font-body);
    font-size: .82rem;
    outline: none;
}

.filters-extra select:focus,
.filters-extra input:focus {
    border-color: var(--accent);
}

.search-wrap {
    display: flex;
    align-items: center;
    gap: .4rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0 .7rem;
    flex: 1;
    min-width: 160px;
}

.search-wrap i {
    color: var(--text-muted);
    font-size: .85rem;
}

.search-wrap input {
    border: none;
    background: none;
    flex: 1;
    padding: .45rem 0;
    color: var(--text);
    font-size: .82rem;
    outline: none;
}

.search-wrap input::placeholder {
    color: var(--text-muted);
}

.filter-btn-submit {
    padding: 8px 16px;
    border-radius: 8px;
    background: var(--accent);
    border: none;
    color: #fff;
    font-family: var(--font-body);
    font-size: .82rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .25s;
}

.filter-btn-submit:hover {
    background: var(--accent-dim);
}

/* ─── BADGE "NOVO" (NOVO) ──────────────────────────────────── */
.project-new-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--success);
    color: #fff;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 5px 10px;
    border-radius: 999px;
    z-index: 2;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
    animation: pulse-new 2s infinite;
}

@keyframes pulse-new {

    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, .4);
    }

    50% {
        box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
    }
}

/* ─── META DO CARD (NOVO) ──────────────────────────────────── */
.project-meta {
    display: flex;
    gap: 1rem;
    font-size: .72rem;
    color: var(--text-muted);
    border-top: 1px solid var(--border);
    padding-top: 10px;
    margin-top: 4px;
}

.project-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ─── CTA FINAL (NOVO) ─────────────────────────────────────── */
.cta-section-projects {
    background: var(--bg-2);
    border-top: 1px solid var(--border);
    padding: 100px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta-section-projects::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: radial-gradient(ellipse 70% 80% at 50% 50%, var(--accent-glow) 0%, transparent 65%);
}

.cta-inner {
    position: relative;
    z-index: 1;
    max-width: 640px;
    margin: 0 auto;
}

.cta-title {
    font-family: var(--font-head);
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1.1;
    margin-bottom: 16px;
}

.cta-sub {
    font-size: 1rem;
    color: var(--text-dim);
    margin-bottom: 32px;
    line-height: 1.7;
}

/* ─── CORES POR CATEGORIA (NOVO) ────────────────────────────── */
.cat-icon {
    margin-right: 4px;
}
</style>
</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($wa = $cfg('whatsapp_url')): ?>
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($wa) ?>?text=Olá%20José,%20vim%20do%20seu%20portfólio" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero da página -->
    <section id="projects-hero">
        <div class="container projects-hero-inner">
            <span class="tag">Portfólio</span>
            <h1>Os Meus <span>Projectos</span></h1>
            <p>Uma selecção completa dos trabalhos que reflectem a minha paixão por código limpo, design elegante e
                soluções eficientes.</p>

            <!-- ═══ ESTATÍSTICAS RÁPIDAS (NOVO) ═══ -->
            <div class="hero-stats-row">
                <div class="hero-stat-mini">
                    <div class="num"><?= $totalAllProjects ?></div>
                    <div class="lbl">Projectos</div>
                </div>
                <div class="hero-stat-mini">
                    <div class="num"><?= $totalCategories ?></div>
                    <div class="lbl">Categorias</div>
                </div>
                <div class="hero-stat-mini">
                    <div class="num"><?= $totalTechnologies ?></div>
                    <div class="lbl">Tecnologias</div>
                </div>
                <?php if ($recentProjects > 0): ?>
                <div class="hero-stat-mini" style="border-color: var(--success); border-style: dashed;">
                    <div class="num"
                        style="background:linear-gradient(135deg,var(--success),#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                        <?= $recentProjects ?></div>
                    <div class="lbl" style="color:var(--success)">Novos (30d)</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Filtros -->
    <div class="container">
        <div class="filters-bar reveal">
            <a href="?cat=all<?= $current_sort !== 'featured' ? '&sort=' . $current_sort : '' ?><?= !empty($current_search) ? '&search=' . urlencode($current_search) : '' ?>"
                class="filter-btn <?= $current_cat === 'all' ? 'active' : '' ?>">
                Todos <span class="filter-count">(<?= $totalAllProjects ?>)</span>
            </a>
            <?php foreach ($existing_cats as $cat): ?>
            <?php
                $catCountStmt = $pdo->prepare("SELECT COUNT(*) FROM _projects WHERE status_project='published' AND category_project=?");
                $catCountStmt->execute([$cat]);
                $catCount = $catCountStmt->fetchColumn();
                ?>
            <a href="?cat=<?= e($cat) ?><?= $current_sort !== 'featured' ? '&sort=' . $current_sort : '' ?><?= !empty($current_search) ? '&search=' . urlencode($current_search) : '' ?>"
                class="filter-btn <?= $current_cat === $cat ? 'active' : '' ?>">
                <?= e(ucfirst($cat)) ?> <span class="filter-count">(<?= $catCount ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Ordenação e Pesquisa (NOVO) -->
        <form class="filters-extra reveal" method="get">
            <input type="hidden" name="cat" value="<?= e($current_cat) ?>">
            <select name="sort" onchange="this.form.submit()">
                <option value="featured" <?= $current_sort === 'featured' ? 'selected' : '' ?>>⭐ Destaques</option>
                <option value="newest" <?= $current_sort === 'newest' ? 'selected' : '' ?>>🆕 Recentes</option>
                <option value="oldest" <?= $current_sort === 'oldest' ? 'selected' : '' ?>>📅 Antigos</option>
                <option value="name" <?= $current_sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
            </select>
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" value="<?= e($current_search) ?>" placeholder="Pesquisar projectos...">
                <?php if (!empty($current_search)): ?>
                <a href="?cat=<?= e($current_cat) ?>&sort=<?= e($current_sort) ?>"
                    style="color:var(--text-muted);text-decoration:none">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
            <button type="submit" class="filter-btn-submit">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </form>

        <!-- Contador -->
        <div class="projects-count reveal">
            <?php if ($total_projects > 0): ?>
            A mostrar <strong><?= $total_projects ?></strong> projecto(s)
            <?= $current_cat !== 'all' ? ' na categoria <strong>' . e(ucfirst($current_cat)) . '</strong>' : '' ?>
            <?= !empty($current_search) ? ' para "<strong>' . e($current_search) . '</strong>"' : '' ?>
            <?php else: ?>
            Nenhum projecto encontrado com os filtros actuais.
            <?php endif; ?>
        </div>
    </div>

    <!-- Grelha de projectos -->
    <section class="container">
        <div class="projects-grid">
            <?php if (!empty($projects)): ?>
            <?php foreach ($projects as $i => $p):
                    $tech = [];
                    if (!empty($p['tech_stack'])) {
                        $decoded = json_decode($p['tech_stack'], true);
                        $tech = is_array($decoded) ? $decoded : explode(',', $p['tech_stack']);
                    }
                    $cat_icon = match ($p['category_project']) {
                        'mobile'  => 'fa-mobile-alt',
                        'api'     => 'fa-plug',
                        'design'  => 'fa-paint-brush',
                        'desktop' => 'fa-desktop',
                        default   => 'fa-globe'
                    };
                    $isNew = strtotime($p['creat_project']) > strtotime('-30 days');
                ?>
            <article class="project-card reveal" style="transition-delay: <?= min($i * 0.05, 0.5) ?>s">
                <div class="project-thumb">
                    <?php if (!empty($p['cover_project']) && file_exists(ROOT_PATH . '/assets/img/projects/' . $p['cover_project'])): ?>
                    <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($p['cover_project']) ?>"
                        alt="<?= e($p['title_project']) ?>" loading="lazy">
                    <?php else: ?>
                    <div class="project-thumb-placeholder">
                        <i class="fas <?= $cat_icon ?>"></i>
                    </div>
                    <?php endif; ?>

                    <?php if ($p['is_featured']): ?>
                    <div class="project-featured-badge">⭐ Destaque</div>
                    <?php endif; ?>
                    <?php if ($isNew): ?>
                    <div class="project-new-badge">Novo</div>
                    <?php endif; ?>

                    <div class="project-overlay">
                        <?php if (!empty($p['url_demo'])): ?>
                        <a href="<?= e($p['url_demo']) ?>" target="_blank" rel="noopener" class="project-overlay-btn"
                            title="Demonstração"><i class="fas fa-external-link-alt"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($p['url_github'])): ?>
                        <a href="<?= e($p['url_github']) ?>" target="_blank" rel="noopener" class="project-overlay-btn"
                            title="Repositório"><i class="fab fa-github"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($p['url_live'])): ?>
                        <a href="<?= e($p['url_live']) ?>" target="_blank" rel="noopener" class="project-overlay-btn"
                            title="Site ao vivo"><i class="fas fa-globe"></i></a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/project/<?= e($p['slug_project']) ?>" class="project-overlay-btn"
                            title="Ver detalhes"><i class="fas fa-info"></i></a>
                    </div>
                </div>

                <div class="project-body">
                    <span class="project-category">
                        <i class="fas <?= $cat_icon ?> cat-icon"></i>
                        <?= e(ucfirst($p['category_project'])) ?>
                    </span>
                    <h3 class="project-title">
                        <a href="<?= BASE_URL ?>/project/<?= e($p['slug_project']) ?>"><?= e($p['title_project']) ?></a>
                    </h3>
                    <?php if (!empty($p['summary_project'])): ?>
                    <p class="project-summary"><?= e($p['summary_project']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($tech)): ?>
                    <div class="project-tech-tags">
                        <?php foreach (array_slice($tech, 0, 5) as $t): ?>
                        <span class="project-tech-tag"><?= e(trim($t)) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($tech) > 5): ?>
                        <span class="project-tech-tag">+<?= count($tech) - 5 ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <!-- Meta informações (NOVO) -->
                    <div class="project-meta">
                        <span><i class="far fa-calendar-alt"></i>
                            <?= date('d/m/Y', strtotime($p['creat_project'])) ?></span>
                        <?php if (!empty($p['url_github'])): ?>
                        <span><i class="fab fa-github"></i> Open source</span>
                        <?php endif; ?>
                        <?php if (!empty($p['url_live'])): ?>
                        <span><i class="fas fa-globe"></i> No ar</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="projects-empty">
                <i class="fas fa-rocket"></i>
                <h3 style="font-family:var(--font-head);margin-bottom:8px;color:var(--text-dim)">Nenhum projecto
                    encontrado</h3>
                <p><?= $current_cat !== 'all' ? 'Não existem projectos nesta categoria de momento.' : 'Os projectos ainda estão em preparação. Volte em breve!' ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══ CTA FINAL (NOVO) ═══════════════════════════════════════ -->
    <div class="cta-section-projects">
        <div class="cta-inner reveal">
            <span class="tag">Vamos Trabalhar Juntos?</span>
            <h2 class="cta-title">Gostou do que viu?<br><span class="text-gradient">Vamos conversar!</span></h2>
            <p class="cta-sub">Tem um projecto em mente? Estou disponível para discutir ideias, orçamentos e prazos.
                Resposta em até 24 horas.</p>
            <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?subject=Orçamento de Projecto" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Solicitar Orçamento
                </a>
                <?php if ($wa): ?>
                <a href="<?= e($wa) ?>" target="_blank" class="btn btn-secondary">
                    <i class="fab fa-whatsapp"></i> Conversar no WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = <<<JS
// Animação suave nos botões de filtro
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', function(e) {
    this.style.transform = 'scale(0.93)';
    setTimeout(() => { this.style.transform = ''; }, 150);
  });
});
JS;

    $page_scripts = [];
    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>
</body>

</html>