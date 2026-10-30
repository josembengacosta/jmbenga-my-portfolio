<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visitor.php';

check_maintenance_mode($pdo);

$requestedSlug = $_GET['slug'] ?? '';
$slug = is_string($requestedSlug) ? trim($requestedSlug) : '';
if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/status/404.php';
    exit;
}

$projectStmt = $pdo->prepare("SELECT * FROM _projects WHERE slug_project = :slug AND status_project = 'published' LIMIT 1");
$projectStmt->execute(['slug' => $slug]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);
if (!$project) {
    http_response_code(404);
    require __DIR__ . '/status/404.php';
    exit;
}

$mediaStmt = $pdo->prepare('SELECT url_media, caption_media FROM _projects_media WHERE id_project = :id ORDER BY display_order ASC, creat_media ASC');
$mediaStmt->execute(['id' => (int) $project['id_project']]);
$mediaRows = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

$base = rtrim(BASE_URL, '/');
$plainText = static function ($value): string {
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '';
    }
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $text) ?? $text;
    $text = preg_replace('~</?(?:p|div|h[1-6]|li|blockquote|br)\b[^>]*>~i', "\n", $text) ?? $text;
    return trim(strip_tags($text));
};
$safeHttpUrl = static function ($value): string {
    $url = trim((string) ($value ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])) {
        return '';
    }
    return $url;
};
$assetUrl = static function ($value) use ($base, $safeHttpUrl): string {
    $path = trim((string) ($value ?? ''));
    if ($path === '' || str_starts_with($path, '//') || preg_match('/[[:cntrl:]]/', $path) || str_contains($path, chr(92))) {
        return '';
    }
    if (preg_match('/\Ahttps?:\/\//i', $path)) {
        return $safeHttpUrl($path);
    }
    if (preg_match('/\A[a-z][a-z0-9+.-]*:/i', $path) || str_contains($path, '..')) {
        return '';
    }
    $relativePath = ltrim($path, '/');
    if (!str_starts_with($relativePath, 'assets/')) {
        $relativePath = 'assets/img/projects/' . basename($relativePath);
    }
    $rootPath = realpath(__DIR__);
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($rootPath === false || $filePath === false || !is_file($filePath)
        || !str_starts_with($filePath, $rootPath . DIRECTORY_SEPARATOR)) {
        return '';
    }
    $segments = array_map('rawurlencode', explode('/', $relativePath));
    return $base . '/' . implode('/', $segments);
};
$textValue = static fn(string $key): string => $plainText($project[$key] ?? '');

$title = $plainText($project['title_project'] ?? '');
$summary = $plainText($project['summary_project'] ?? '');
$description = $textValue('body_project');
$objectives = $textValue('objectives_project');
$features = $textValue('features_project');
$contribution = $textValue('contribution_project');
$cover = $assetUrl($project['cover_project'] ?? '');
$technologies = json_decode((string) ($project['tech_stack'] ?? '[]'), true);
$technologies = is_array($technologies)
    ? array_values(array_unique(array_filter(array_map(static fn($item): string => is_string($item) ? trim($item) : '', $technologies))))
    : [];

$gallery = [];
foreach ($mediaRows as $mediaItem) {
    $imageUrl = $assetUrl($mediaItem['url_media'] ?? '');
    if ($imageUrl === '') {
        continue;
    }
    $gallery[] = [
        'url' => $imageUrl,
        'caption' => $plainText($mediaItem['caption_media'] ?? ''),
    ];
}

$previewMode = (string) ($project['preview_mode'] ?? 'disabled');
$previewTarget = trim((string) ($project['preview_url'] ?? ''));
$previewAvailable = false;
if (!empty($project['preview_enabled'])) {
    if (in_array($previewMode, ['external_embed', 'external_link'], true)) {
        $previewAvailable = $safeHttpUrl($previewTarget) !== '';
    } elseif ($previewMode === 'internal'
        && preg_match('#\A/demos/[a-zA-Z0-9/_-]+(?:\.html)?/?\z#', $previewTarget)
        && !str_contains($previewTarget, '..')) {
        $demoPath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $previewTarget);
        $demoIndex = rtrim($demoPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        $previewAvailable = is_file($demoPath) || is_file($demoIndex);
    } elseif ($previewMode === 'gallery') {
        $previewAvailable = $gallery !== [];
    }
}

$externalLinks = [];
foreach ([
    'url_demo' => 'Demonstração externa',
    'url_live' => 'Site ao vivo',
    'url_github' => 'Repositório',
] as $field => $label) {
    $url = $safeHttpUrl($project[$field] ?? '');
    if ($url === '') {
        continue;
    }
    $parts = parse_url($url);
    $key = strtolower((string) ($parts['scheme'] ?? '')) . '://'
        . strtolower((string) ($parts['host'] ?? ''))
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . rtrim((string) ($parts['path'] ?? ''), '/')
        . (isset($parts['query']) ? '?' . $parts['query'] : '');
    if (isset($externalLinks[$key])) {
        $externalLinks[$key]['label'] = $externalLinks[$key]['label'] === 'Demonstração externa'
            ? 'Demonstração / site ao vivo'
            : $externalLinks[$key]['label'];
        continue;
    }
    $externalLinks[$key] = ['url' => $url, 'label' => $label];
}
$externalLinks = array_values($externalLinks);

$relatedStmt = $pdo->prepare("SELECT id_project, title_project, slug_project, summary_project, cover_project, category_project
    FROM _projects
    WHERE status_project = 'published' AND id_project <> :id AND category_project = :category
    ORDER BY is_featured DESC, display_order ASC, creat_project DESC
    LIMIT 3");
$relatedStmt->execute([
    'id' => (int) $project['id_project'],
    'category' => (string) $project['category_project'],
]);
$relatedProjects = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$relatedProjects) {
    $relatedStmt = $pdo->prepare("SELECT id_project, title_project, slug_project, summary_project, cover_project, category_project
        FROM _projects WHERE status_project = 'published' AND id_project <> :id
        ORDER BY is_featured DESC, display_order ASC, creat_project DESC LIMIT 3");
    $relatedStmt->execute(['id' => (int) $project['id_project']]);
    $relatedProjects = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = $title . ' — Projectos | JMbenga';
$page_desc = $summary !== '' ? $summary : ($description !== '' ? (function_exists('mb_substr') ? mb_substr($description, 0, 300, 'UTF-8') : substr($description, 0, 300)) : $title);
$page_canonical = $base . '/project/' . rawurlencode($slug);
$page_og_type = 'article';
$page_og_image = $cover;
$page_og_image_alt = $title;
$page_section = 'project';
$extra_head = '<link rel="stylesheet" href="' . e($base) . '/assets/css/project-detail.css">';
require __DIR__ . '/includes/header.php';
?>
<main class="project-detail container">
    <nav class="project-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e($base) ?>/projects">Projectos</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($title) ?></span>
    </nav>

    <header class="project-hero<?= $cover === '' ? ' project-hero-no-cover' : '' ?>">
        <div class="project-hero-copy">
            <?php if (trim((string) ($project['category_project'] ?? '')) !== ''): ?>
                <p class="project-eyebrow"><?= e(ucfirst((string) $project['category_project'])) ?></p>
            <?php endif; ?>
            <h1><?= e($title) ?></h1>
            <?php if ($summary !== ''): ?><p class="project-lede"><?= nl2br(e($summary)) ?></p><?php endif; ?>
            <?php if ($previewAvailable || $externalLinks): ?>
                <div class="project-actions" aria-label="Ligações do projecto">
                    <?php if ($previewAvailable): ?>
                        <a class="project-action project-action-primary" href="<?= e($base) ?>/preview/<?= e(rawurlencode($slug)) ?>">
                            <i class="fas fa-play" aria-hidden="true"></i><span>Ver demonstração configurada</span>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($externalLinks as $link): ?>
                        <a class="project-action" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span><?= e($link['label']) ?></span><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($cover !== ''): ?>
            <figure class="project-cover">
                <img src="<?= e($cover) ?>" alt="<?= e($title) ?>" fetchpriority="high">
            </figure>
        <?php endif; ?>
    </header>

    <?php if ($objectives !== '' || $features !== '' || $description !== '' || $contribution !== ''): ?>
        <div class="project-case-grid">
            <?php if ($objectives !== '' || $features !== '' || $description !== ''): ?>
                <div class="project-case-main">
                    <?php if ($objectives !== ''): ?><section class="project-section"><p class="project-section-kicker">Contexto</p><h2>Objectivos</h2><div class="project-prose"><?= nl2br(e($objectives)) ?></div></section><?php endif; ?>
                    <?php if ($features !== ''): ?><section class="project-section"><p class="project-section-kicker">Produto</p><h2>Funcionalidades</h2><div class="project-prose"><?= nl2br(e($features)) ?></div></section><?php endif; ?>
                    <?php if ($description !== ''): ?><section class="project-section"><p class="project-section-kicker">Estudo de caso</p><h2>Descrição</h2><div class="project-prose"><?= nl2br(e($description)) ?></div></section><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($contribution !== ''): ?><aside class="project-contribution"><p class="project-section-kicker">Implementação</p><h2>Contributo técnico</h2><div class="project-prose"><?= nl2br(e($contribution)) ?></div></aside><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($technologies): ?>
        <section class="project-section project-tech-section" aria-labelledby="project-tech-heading">
            <p class="project-section-kicker">Ferramentas</p><h2 id="project-tech-heading">Tecnologias</h2>
            <ul class="project-tech-list"><?php foreach ($technologies as $technology): ?><li><?= e($technology) ?></li><?php endforeach; ?></ul>
        </section>
    <?php endif; ?>

    <?php if ($gallery): ?>
        <section class="project-section project-gallery-section" aria-labelledby="project-gallery-heading">
            <p class="project-section-kicker">Imagens</p><h2 id="project-gallery-heading">Galeria</h2>
            <div class="project-gallery">
                <?php foreach ($gallery as $index => $image): ?>
                    <button class="project-gallery-item" type="button" data-gallery-index="<?= (int) $index ?>" aria-label="Ampliar imagem <?= (int) ($index + 1) ?>">
                        <img src="<?= e($image['url']) ?>" alt="<?= e($image['caption'] !== '' ? $image['caption'] : $title) ?>" loading="lazy">
                        <?php if ($image['caption'] !== ''): ?><span><?= e($image['caption']) ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <dialog class="project-lightbox" id="project-lightbox" aria-label="Visualização da galeria">
            <button class="project-lightbox-close" type="button" aria-label="Fechar imagem"><i class="fas fa-times" aria-hidden="true"></i></button>
            <img src="" alt="" id="project-lightbox-image">
            <p id="project-lightbox-caption"></p>
        </dialog>
        <script src="<?= e($base) ?>/assets/js/project-detail.js" defer></script>
    <?php endif; ?>

    <?php if ($relatedProjects): ?>
        <section class="project-section project-related" aria-labelledby="project-related-heading">
            <p class="project-section-kicker">Explorar</p><h2 id="project-related-heading">Outros projectos</h2>
            <div class="project-related-grid">
                <?php foreach ($relatedProjects as $related): $relatedCover = $assetUrl($related['cover_project'] ?? ''); $relatedSummary = $plainText($related['summary_project'] ?? ''); ?>
                    <a class="project-related-card" href="<?= e($base) ?>/project/<?= e(rawurlencode((string) $related['slug_project'])) ?>">
                        <?php if ($relatedCover !== ''): ?><img src="<?= e($relatedCover) ?>" alt="" loading="lazy"><?php endif; ?>
                        <span class="project-related-copy"><span class="project-eyebrow"><?= e(ucfirst((string) $related['category_project'])) ?></span><strong><?= e($plainText($related['title_project'])) ?></strong><?php if ($relatedSummary !== ''): ?><span><?= e($relatedSummary) ?></span><?php endif; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
