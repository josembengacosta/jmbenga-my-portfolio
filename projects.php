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

// Validar a cor antes de a utilizar nos estilos desta página.
$accentCandidate = trim((string) $cfg('accent_color', '#2563eb'));
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accentCandidate)
    ? $accentCandidate
    : '#2563eb';
$accentHex = substr($accent, 1);
$accentRgb = [
    hexdec(substr($accentHex, 0, 2)),
    hexdec(substr($accentHex, 2, 2)),
    hexdec(substr($accentHex, 4, 2)),
];
$accentRgbCss = implode(', ', $accentRgb);

// Validar URLs externas para impedir protocolos inesperados nos links.
$safeHttpUrl = static function ($value): string {
    $url = trim((string) ($value ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
};

$wa = $safeHttpUrl($cfg('whatsapp_url'));
$waHref = $wa !== ''
    ? $wa . (str_contains($wa, '?') ? '&' : '?') . 'text=' . rawurlencode('Olá, José! Vim do seu portfólio e gostaria de conversar sobre um projecto.')
    : '';

// Categorias e contagens reais dos projectos publicados.
$categoryRows = $pdo->query(
    "SELECT category_project, COUNT(*) AS total
     FROM _projects
     WHERE status_project = 'published'
     GROUP BY category_project
     ORDER BY category_project ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$categoryCounts = [];
foreach ($categoryRows as $categoryRow) {
    $categoryName = trim((string) ($categoryRow['category_project'] ?? ''));
    if ($categoryName !== '') {
        $categoryCounts[$categoryName] = (int) $categoryRow['total'];
    }
}
$existing_cats = array_keys($categoryCounts);

// ── Filtros e ordenação ──────────────────────────────────────
$requestedCat = $_GET['cat'] ?? 'all';
$current_cat = is_string($requestedCat)
    && in_array($requestedCat, array_merge(['all'], $existing_cats), true)
        ? $requestedCat
        : 'all';

$requestedSort = $_GET['sort'] ?? 'featured';
$allowed_sorts = ['featured', 'newest', 'oldest', 'name'];
$current_sort = is_string($requestedSort)
    && in_array($requestedSort, $allowed_sorts, true)
        ? $requestedSort
        : 'featured';

$requestedSearch = $_GET['search'] ?? '';
$current_search = is_string($requestedSearch) ? trim($requestedSearch) : '';
if (function_exists('mb_substr')) {
    $current_search = mb_substr($current_search, 0, 100, 'UTF-8');
} else {
    $current_search = substr($current_search, 0, 100);
}

// ── Consulta base ───────────────────────────────────────────
$sql = "SELECT * FROM _projects WHERE status_project = 'published'";
$params = [];

if ($current_cat !== 'all') {
    $sql .= " AND category_project = ?";
    $params[] = $current_cat;
}
if ($current_search !== '') {
    // ESCAPE trata %, _ e ! digitados como caracteres de pesquisa literais.
    $searchLiteral = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $current_search);
    $searchPattern = '%' . $searchLiteral . '%';
    $sql .= " AND (title_project LIKE ? ESCAPE '!' OR summary_project LIKE ? ESCAPE '!' OR slug_project LIKE ? ESCAPE '!')";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

// A cláusula vem de uma lista fixa, nunca directamente do pedido.
$orderClause = match ($current_sort) {
    'newest'  => 'creat_project DESC',
    'oldest'  => 'creat_project ASC',
    'name'    => 'title_project ASC',
    default   => 'is_featured DESC, display_order ASC, creat_project DESC',
};
$sql .= " ORDER BY $orderClause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_projects = count($projects);

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
// Preparar os estilos da página para serem inseridos dentro do <head> global.
ob_start();
?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/projects.css">

<style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: #1d4ed8;
    --accent-glow: rgba(<?=e($accentRgbCss) ?>, .15);
    --accent-rgb: <?=e($accentRgbCss) ?>;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(<?=e($accentRgbCss) ?>, .35);
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
    --border-acc: rgba(<?=e($accentRgbCss) ?>, .25);
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
<?php
$extra_head = (string) ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

<body>

    <!-- WhatsApp Float -->
    <?php if ($wa !== ''): ?>
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($waHref) ?>" target="_blank" rel="noopener noreferrer">
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
                    <div class="lbl">Competências</div>
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
            <a href="?cat=all<?= $current_sort !== 'featured' ? '&amp;sort=' . e(rawurlencode($current_sort)) : '' ?><?= $current_search !== '' ? '&amp;search=' . e(rawurlencode($current_search)) : '' ?>"
                class="filter-btn <?= $current_cat === 'all' ? 'active' : '' ?>">
                Todos <span class="filter-count">(<?= $totalAllProjects ?>)</span>
            </a>
            <?php foreach ($existing_cats as $cat): ?>
            <a href="?cat=<?= e(rawurlencode($cat)) ?><?= $current_sort !== 'featured' ? '&amp;sort=' . e(rawurlencode($current_sort)) : '' ?><?= $current_search !== '' ? '&amp;search=' . e(rawurlencode($current_search)) : '' ?>"
                class="filter-btn <?= $current_cat === $cat ? 'active' : '' ?>"
                <?= $current_cat === $cat ? 'aria-current="page"' : '' ?>>
                <?= e(ucfirst($cat)) ?> <span class="filter-count">(<?= (int) ($categoryCounts[$cat] ?? 0) ?>)</span>
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
                <input type="text" name="search" value="<?= e($current_search) ?>" maxlength="100"
                    placeholder="Pesquisar projectos..." aria-label="Pesquisar projectos">
                <?php if (!empty($current_search)): ?>
                <a href="?cat=<?= e(rawurlencode($current_cat)) ?>&amp;sort=<?= e(rawurlencode($current_sort)) ?>"
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
                        $decoded = json_decode((string) $p['tech_stack'], true);
                        $techCandidates = is_array($decoded)
                            ? $decoded
                            : explode(',', (string) $p['tech_stack']);
                        foreach ($techCandidates as $candidate) {
                            if (!is_scalar($candidate)) {
                                continue;
                            }
                            $candidateText = trim((string) $candidate);
                            if ($candidateText !== '') {
                                $tech[] = $candidateText;
                            }
                        }
                    }
                    $cat_icon = match ($p['category_project']) {
                        'mobile'  => 'fa-mobile-alt',
                        'api'     => 'fa-plug',
                        'design'  => 'fa-paint-brush',
                        'desktop' => 'fa-desktop',
                        default   => 'fa-globe'
                    };
                    $createdAt = strtotime((string) ($p['creat_project'] ?? ''));
                    $isNew = $createdAt !== false && $createdAt > strtotime('-30 days');
                ?>
            <article class="project-card reveal" style="transition-delay: <?= min($i * 0.05, 0.5) ?>s">
                <div class="project-thumb">
                    <?php
                        $coverCandidate = trim((string) ($p['cover_project'] ?? ''));
                        $projectsDir = realpath(ROOT_PATH . '/assets/img/projects');
                        $coverName = basename(str_replace('\\', '/', $coverCandidate));
                        $coverPath = ($projectsDir && $coverCandidate !== '')
                            ? realpath($projectsDir . DIRECTORY_SEPARATOR . $coverName)
                            : false;
                        $coverDirPrefix = $projectsDir
                            ? rtrim($projectsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                            : '';
                        $hasSafeCover = $coverPath !== false
                            && is_file($coverPath)
                            && $coverDirPrefix !== ''
                            && str_starts_with($coverPath, $coverDirPrefix)
                            && $coverName === $coverCandidate;
                    ?>
                    <?php if ($hasSafeCover): ?>
                    <img src="<?= e(BASE_URL) ?>/assets/img/projects/<?= e(rawurlencode($coverName)) ?>"
                        alt="<?= e($p['title_project']) ?>" loading="lazy" decoding="async">
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
                        <?php $demoUrl = $safeHttpUrl($p['url_demo'] ?? ''); ?>
                        <?php if ($demoUrl !== ''): ?>
                        <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="project-overlay-btn" title="Demonstração"><i
                                class="fas fa-external-link-alt"></i></a>
                        <?php endif; ?>
                        <?php $githubUrl = $safeHttpUrl($p['url_github'] ?? ''); ?>
                        <?php if ($githubUrl !== ''): ?>
                        <a href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="project-overlay-btn" title="Repositório"><i class="fab fa-github"></i></a>
                        <?php endif; ?>
                        <?php $liveUrl = $safeHttpUrl($p['url_live'] ?? ''); ?>
                        <?php if ($liveUrl !== ''): ?>
                        <a href="<?= e($liveUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="project-overlay-btn" title="Site ao vivo"><i class="fas fa-globe"></i></a>
                        <?php endif; ?>
                        <a href="<?= e(BASE_URL) ?>/project/<?= e(rawurlencode((string) $p['slug_project'])) ?>"
                            class="project-overlay-btn" title="Ver detalhes"><i class="fas fa-info"></i></a>
                    </div>
                </div>

                <div class="project-body">
                    <span class="project-category">
                        <i class="fas <?= $cat_icon ?> cat-icon"></i>
                        <?= e(ucfirst($p['category_project'])) ?>
                    </span>
                    <h3 class="project-title">
                        <a
                            href="<?= e(BASE_URL) ?>/project/<?= e(rawurlencode((string) $p['slug_project'])) ?>"><?= e($p['title_project']) ?></a>
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
                            <?= $createdAt !== false ? e(date('d/m/Y', $createdAt)) : '—' ?></span>
                        <?php if (($githubUrl ?? '') !== ''): ?>
                        <span><i class="fab fa-github"></i> Open source</span>
                        <?php endif; ?>
                        <?php if (($liveUrl ?? '') !== ''): ?>
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
            <p class="cta-sub">Tem um projecto em mente? Podemos conversar sobre as necessidades, o âmbito do trabalho e
                os próximos passos.</p>
            <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= e(BASE_URL) ?>/contact" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Solicitar Orçamento
                </a>
                <a href="<?= e(BASE_URL) ?>/servicos" class="btn btn-outline">
                    <i class="fas fa-layer-group"></i> Conhecer serviços
                </a>
                <?php if ($waHref !== ''): ?>
                <a href="<?= e($waHref) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                    <i class="fab fa-whatsapp"></i> Conversar no WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    include __DIR__ . '/includes/footer.php';
    include __DIR__ . '/includes/bottom-nav.php';
    ?>
</body>

</html>