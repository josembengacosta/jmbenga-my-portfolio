<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$rawSlug = $_GET['slug'] ?? '';
$slug = is_string($rawSlug) ? trim($rawSlug) : '';

if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/status/404.php';
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id_project, title_project, slug_project, status_project,
                preview_mode, preview_url, preview_enabled
         FROM _projects
         WHERE slug_project = :slug
         LIMIT 1'
    );
    $stmt->execute(['slug' => $slug]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('[project preview] ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'O centro de demonstrações está temporariamente indisponível. A configuração da base de dados precisa de ser actualizada.';
    exit;
}

if (
    !$project
    || $project['status_project'] !== 'published'
    || !(int) $project['preview_enabled']
    || $project['preview_mode'] === 'disabled'
) {
    http_response_code(404);
    require __DIR__ . '/status/404.php';
    exit;
}

$mode = (string) $project['preview_mode'];
$target = trim((string) ($project['preview_url'] ?? ''));
$baseUrl = rtrim((string) BASE_URL, '/');

$safeExternalUrl = static function (string $url): bool {
    $parts = parse_url($url);

    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && is_array($parts)
        && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass']);
};

if (in_array($mode, ['external_embed', 'external_link'], true)) {
    if (!$safeExternalUrl($target)) {
        http_response_code(404);
        require __DIR__ . '/status/404.php';
        exit;
    }
} elseif ($mode === 'internal') {
    if (
        !preg_match('#\A/demos/[a-zA-Z0-9/_-]+(?:\.html)?\z#', $target)
        || str_contains($target, '..')
    ) {
        http_response_code(404);
        require __DIR__ . '/status/404.php';
        exit;
    }
    $target = $baseUrl . $target;
} elseif ($mode !== 'gallery') {
    http_response_code(404);
    require __DIR__ . '/status/404.php';
    exit;
}

/**
 * Resolve the media paths stored by the admin uploader.
 * New records store a filename such as media_123_xxxx.webp, not a site-root URL.
 * Legacy records containing /assets/img/projects/<filename> are also supported.
 */
$resolveGalleryMediaUrl = static function (mixed $storedValue) use ($baseUrl, $safeExternalUrl): ?string {
    if (!is_string($storedValue)) {
        return null;
    }

    $value = trim($storedValue);
    if ($value === '' || str_starts_with($value, '//') || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }

    // Preserve safe HTTP(S) URLs in legacy records.
    if (preg_match('#\Ahttps?://#i', $value)) {
        return $safeExternalUrl($value) ? $value : null;
    }

    // Reject javascript:, data:, and other non-HTTP schemes.
    if (preg_match('/\A[a-z][a-z0-9+.-]*:/i', $value)) {
        return null;
    }

    $normalized = str_replace('\\', '/', $value);
    $normalized = ltrim($normalized, '/');
    $expectedPrefix = 'assets/img/projects/';

    if (str_starts_with($normalized, $expectedPrefix)) {
        $filename = substr($normalized, strlen($expectedPrefix));
    } elseif (!str_contains($normalized, '/')) {
        // This is the format inserted by media-process.php.
        $filename = $normalized;
    } else {
        // Do not resolve arbitrary relative paths or traversal attempts.
        return null;
    }

    if (
        $filename === ''
        || $filename !== basename($filename)
        || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z/i', $filename)
    ) {
        return null;
    }

    return $baseUrl . '/assets/img/projects/' . rawurlencode($filename);
};

$galleryItems = [];
if ($mode === 'gallery') {
    try {
        $mediaStmt = $pdo->prepare(
            'SELECT url_media, caption_media
             FROM _projects_media
             WHERE id_project = :id
             ORDER BY display_order ASC, creat_media ASC'
        );
        $mediaStmt->execute(['id' => (int) $project['id_project']]);
        $media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($media as $item) {
            $source = $resolveGalleryMediaUrl($item['url_media'] ?? null);
            if ($source === null) {
                error_log('[project preview] Ignored invalid gallery media URL for project ID ' . (int) $project['id_project']);
                continue;
            }

            $galleryItems[] = [
                'src' => $source,
                'caption' => trim((string) ($item['caption_media'] ?? '')),
            ];
        }
    } catch (PDOException $exception) {
        error_log('[project preview media] ' . $exception->getMessage());
        $galleryLoadError = true;
    }
}

$page_title = 'Preview: ' . $project['title_project'] . ' | JMbenga';
$page_desc = 'Demonstração do projecto ' . $project['title_project'];
$page_robots = 'noindex,nofollow';
$page_canonical = $baseUrl . '/preview/' . rawurlencode($slug);
$extra_head = '<link rel="stylesheet" href="' . e($baseUrl . '/assets/css/preview.css') . '">';
require __DIR__ . '/includes/header.php';
?>
<main class="preview-shell">
    <header class="preview-toolbar">
        <a href="<?= e($baseUrl . '/project/' . rawurlencode($slug)) ?>">← Voltar ao projecto</a>
        <strong><?= e($project['title_project']) ?></strong>
        <div class="preview-actions">
            <?php if (in_array($mode, ['external_embed', 'external_link', 'internal'], true)): ?>
            <a href="<?= e($target) ?>" target="_blank" rel="noopener noreferrer">Abrir separadamente ↗</a>
            <?php endif; ?>
            <?php if (in_array($mode, ['external_embed', 'internal'], true)): ?>
            <button type="button" id="preview-fullscreen">Ecrã inteiro</button>
            <?php endif; ?>
        </div>
    </header>

    <?php if (in_array($mode, ['external_embed', 'internal'], true)): ?>
    <div class="preview-frame-wrap" id="preview-wrap">
        <iframe title="Preview de <?= e($project['title_project']) ?>" src="<?= e($target) ?>" loading="lazy"
            referrerpolicy="no-referrer" sandbox="allow-forms allow-scripts allow-popups allow-downloads"></iframe>
    </div>
    <?php elseif ($mode === 'external_link'): ?>
    <section class="preview-message">
        <h1><?= e($project['title_project']) ?></h1>
        <p>Este site não permite a incorporação. Pode abrir a demonstração numa nova janela.</p>
        <a class="btn btn-primary" href="<?= e($target) ?>" target="_blank" rel="noopener noreferrer">Abrir demonstração
            ↗</a>
    </section>
    <?php else: ?>
    <section class="preview-gallery" aria-labelledby="preview-gallery-title">
        <h1 id="preview-gallery-title">Galeria — <?= e($project['title_project']) ?></h1>

        <?php if (!empty($galleryLoadError)): ?>
        <p role="status">Não foi possível carregar a galeria neste momento. Tenta novamente mais tarde.</p>
        <?php elseif (!$galleryItems): ?>
        <p role="status">Não há imagens válidas disponíveis para este projecto. Confirma a galeria na área
            administrativa.</p>
        <?php else: ?>
        <div class="preview-gallery-grid">
            <?php foreach ($galleryItems as $index => $item): ?>
            <figure>
                <img src="<?= e($item['src']) ?>"
                    alt="<?= e($item['caption'] !== '' ? $item['caption'] : $project['title_project']) ?>"
                    loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                <?php if ($item['caption'] !== ''): ?>
                <figcaption><?= e($item['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script>
document.getElementById('preview-fullscreen')?.addEventListener('click', () => {
    const element = document.getElementById('preview-wrap');
    if (!element) return;
    if (!document.fullscreenElement) {
        element.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>