<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador SEO (AJAX)
// Actualiza _site_config e o robots.txt físico na raiz do projecto.
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$db = $GLOBALS['pdo'];
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

$respond = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    $respond(['success' => false, 'message' => 'Token de segurança inválido ou expirado.'], 403);
}

if ($adminId <= 0) {
    $respond(['success' => false, 'message' => 'Sessão administrativa inválida. Inicia sessão novamente.'], 401);
}

$errors = [];

$getString = static function (string $key, string $default = '') use (&$errors): string {
    $value = $_POST[$key] ?? $default;
    if (!is_string($value)) {
        $errors[] = 'O campo "' . $key . '" tem um formato inválido.';
        return $default;
    }
    return trim($value);
};

$homeMetaTitle = $getString('home_meta_title');
$metaDescription = $getString('meta_description');
$metaKeywords = $getString('meta_keywords');
$ogImage = $getString('og_image');
$googleAnalytics = strtoupper($getString('google_analytics'));
$googleVerification = $getString('google_site_verification');
$homeIndexingEnabled = $getString('home_indexing_enabled', '0');
$robotsTxt = $_POST['robots_txt'] ?? null;

if (!is_string($robotsTxt)) {
    $errors[] = 'O conteúdo de robots.txt é inválido.';
    $robotsTxt = '';
}

// Limites e validações textuais.
if ($homeMetaTitle === '' || mb_strlen($homeMetaTitle, 'UTF-8') < 2) {
    $errors[] = 'O título SEO da página principal deve ter pelo menos 2 caracteres.';
} elseif (mb_strlen($homeMetaTitle, 'UTF-8') > 140) {
    $errors[] = 'O título SEO da página principal não pode ultrapassar 140 caracteres.';
}

if (mb_strlen($metaDescription, 'UTF-8') > 320) {
    $errors[] = 'A descrição SEO não pode ultrapassar 320 caracteres.';
}
if (mb_strlen($metaKeywords, 'UTF-8') > 1000) {
    $errors[] = 'As palavras-chave não podem ultrapassar 1.000 caracteres.';
}

if (!in_array($homeIndexingEnabled, ['0', '1'], true)) {
    $errors[] = 'A opção de indexação da homepage é inválida.';
}

// Validar imagem Open Graph: HTTPS absoluto ou caminho local de imagem.
if ($ogImage !== '') {
    if (preg_match('/[\x00-\x20\\\\]/', $ogImage) || str_starts_with($ogImage, '//')) {
        $errors[] = 'A imagem Open Graph contém caracteres ou formato de URL não permitidos.';
    } elseif (preg_match('/^[a-z][a-z0-9+.-]*:/i', $ogImage)) {
        $parts = parse_url($ogImage);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        if (
            $scheme !== 'https'
            || !filter_var($ogImage, FILTER_VALIDATE_URL)
            || !is_array($parts)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            $errors[] = 'A imagem Open Graph externa deve usar um URL HTTPS válido, sem credenciais.';
        }
    } else {
        $decodedPath = rawurldecode($ogImage);
        if (
            preg_match('~(^|/)\.\.?(/|$)~', $decodedPath)
            || !preg_match('~\A/?[A-Za-z0-9._/-]+\.(?:png|jpe?g|webp|gif|avif)\z~i', $ogImage)
        ) {
            $errors[] = 'Indica um caminho local de imagem válido, sem ../, ou um URL HTTPS.';
        }
    }
}

// O processador só configura Google Analytics 4 (GA4).
if ($googleAnalytics !== '' && !preg_match('/\AG-[A-Z0-9]{6,20}\z/', $googleAnalytics)) {
    $errors[] = 'O ID do Google Analytics deve ser um Measurement ID GA4 no formato G-XXXXXXXXXX.';
}
if ($googleVerification !== '' && !preg_match('/\A[A-Za-z0-9_-]{2,255}\z/', $googleVerification)) {
    $errors[] = 'O token do Google Search Console contém caracteres inválidos.';
}

// Normalizar e validar o texto robots.txt.
$robotsTxt = str_replace(["\r\n", "\r"], "\n", $robotsTxt);
$robotsTxt = str_replace("\0", '', $robotsTxt);
$robotsTxt = trim($robotsTxt);

if ($robotsTxt === '') {
    $robotsTxt = implode("\n", [
        '# JMbenga Portfolio — política de rastreio',
        'User-agent: *',
        'Allow: /',
        'Disallow: /api/',
        'Disallow: /jm-panel/',
        'Disallow: /status/',
        'Disallow: /includes/',
        'Disallow: /database/',
    ]);
}
if (strlen($robotsTxt) > 20000) {
    $errors[] = 'O robots.txt não pode ultrapassar 20.000 bytes.';
}
foreach (explode("\n", $robotsTxt) as $lineNumber => $line) {
    if (strlen($line) > 1000) {
        $errors[] = 'A linha ' . ($lineNumber + 1) . ' de robots.txt ultrapassa 1.000 caracteres.';
        break;
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line)) {
        $errors[] = 'O robots.txt contém caracteres de controlo não permitidos.';
        break;
    }
}

// BASE_URL é a origem canónica do projecto; o sitemap existente chama-se sitemap.php.
$baseUrl = rtrim((string) BASE_URL, '/');
$baseParts = parse_url($baseUrl);
$baseScheme = is_array($baseParts) ? strtolower((string) ($baseParts['scheme'] ?? '')) : '';
if (
    !filter_var($baseUrl, FILTER_VALIDATE_URL)
    || !in_array($baseScheme, ['http', 'https'], true)
    || !is_array($baseParts)
    || empty($baseParts['host'])
) {
    $errors[] = 'BASE_URL não é um URL HTTP(S) válido. Corrige a configuração da aplicação antes de guardar SEO.';
}
$sitemapUrl = $baseUrl . '/sitemap.php';

// Remove qualquer Sitemap antigo (incluindo localhost/sitemap.xml) e publica sempre a rota real.
$robotsTxt = preg_replace('/^[ \t]*Sitemap[ \t]*:.*(?:\n|$)/im', '', $robotsTxt) ?? $robotsTxt;
$robotsTxt = rtrim($robotsTxt) . "\n\nSitemap: " . $sitemapUrl . "\n";

if ($errors !== []) {
    $respond(['success' => false, 'errors' => $errors], 422);
}

// A escrita do robots.txt é uma parte da operação. Validar o caminho antes
// de abrir a transacção impede escrever fora da raiz ou seguir um symlink.
if (!defined('ROOT_PATH')) {
    $respond(['success' => false, 'message' => 'ROOT_PATH não está definido. Não foi possível localizar a raiz do projecto.'], 500);
}
$projectRoot = realpath((string) ROOT_PATH);
if ($projectRoot === false || !is_dir($projectRoot) || !is_writable($projectRoot)) {
    $respond(['success' => false, 'message' => 'A raiz do projecto não existe ou não permite actualizar robots.txt.'], 500);
}

$robotsPath = $projectRoot . DIRECTORY_SEPARATOR . 'robots.txt';
if (is_link($robotsPath) || (file_exists($robotsPath) && !is_file($robotsPath))) {
    $respond(['success' => false, 'message' => 'O caminho robots.txt não é um ficheiro regular seguro.'], 500);
}

$oldRobotsContents = null;
$oldRobotsMode = 0644;
if (is_file($robotsPath)) {
    if (!is_readable($robotsPath) || !is_writable($robotsPath)) {
        $respond(['success' => false, 'message' => 'O ficheiro robots.txt não tem permissões de leitura e escrita para o PHP.'], 500);
    }
    $oldRobotsContents = file_get_contents($robotsPath);
    if (!is_string($oldRobotsContents)) {
        $respond(['success' => false, 'message' => 'Não foi possível ler o robots.txt actual. Nada foi alterado.'], 500);
    }
    $permissions = fileperms($robotsPath);
    if (is_int($permissions)) {
        $oldRobotsMode = $permissions & 0777;
        if ($oldRobotsMode === 0) {
            $oldRobotsMode = 0644;
        }
    }
}

$tempPath = tempnam($projectRoot, '.robots-tmp-');
if (!is_string($tempPath) || $tempPath === '') {
    $respond(['success' => false, 'message' => 'Não foi possível preparar o novo robots.txt.'], 500);
}
$backupPath = null;
$robotsInstalled = false;
$dbInTransaction = false;

try {
    if (file_put_contents($tempPath, $robotsTxt, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível escrever o ficheiro temporário robots.txt.');
    }
    @chmod($tempPath, $oldRobotsMode);

    $db->beginTransaction();
    $dbInTransaction = true;

    $configFields = [
        'home_meta_title' => $homeMetaTitle,
        'meta_description' => $metaDescription,
        'meta_keywords' => $metaKeywords,
        'og_image' => $ogImage,
        'google_analytics' => $googleAnalytics,
        'google_site_verification' => $googleVerification,
        'home_indexing_enabled' => $homeIndexingEnabled,
        'robots_txt' => $robotsTxt,
    ];

    $oldValues = [];
    $updatedKeys = [];

    $selectConfig = $db->prepare(
        'SELECT id_config, config_value FROM _site_config WHERE config_key = ? LIMIT 1 FOR UPDATE'
    );
    $updateConfig = $db->prepare(
        "UPDATE _site_config
         SET config_value = ?, config_group = 'seo', is_public = 1
         WHERE id_config = ?"
    );
    $insertConfig = $db->prepare(
        "INSERT INTO _site_config (config_key, config_value, config_group, is_public)
         VALUES (?, ?, 'seo', 1)"
    );

    foreach ($configFields as $key => $value) {
        $selectConfig->execute([$key]);
        $existing = $selectConfig->fetch(PDO::FETCH_ASSOC);

        $oldValues[$key] = is_array($existing) ? (string) ($existing['config_value'] ?? '') : null;

        if (is_array($existing)) {
            $updateConfig->execute([$value, (int) $existing['id_config']]);
        } else {
            $insertConfig->execute([$key, $value]);
        }

        $updatedKeys[] = $key;
    }

    // Instala o novo ficheiro de forma reversível: primeiro preserva o actual,
    // depois coloca o temporário no lugar. Se o COMMIT falhar, restaura o anterior.
    if (is_file($robotsPath)) {
        $backupPath = tempnam($projectRoot, '.robots-backup-');
        if (!is_string($backupPath) || $backupPath === '') {
            throw new RuntimeException('Não foi possível reservar um backup temporário de robots.txt.');
        }
        if (!unlink($backupPath)) {
            throw new RuntimeException('Não foi possível preparar o backup temporário de robots.txt.');
        }
        if (!rename($robotsPath, $backupPath)) {
            throw new RuntimeException('Não foi possível preservar o robots.txt anterior.');
        }
    }

    if (!rename($tempPath, $robotsPath)) {
        throw new RuntimeException('Não foi possível publicar o novo robots.txt.');
    }
    $tempPath = null;
    $robotsInstalled = true;

    logAudit(
        $adminId,
        null,
        'settings.seo.update',
        '_site_config',
        null,
        [
            'home_indexing_enabled' => $oldValues['home_indexing_enabled'],
            'robots_sha256' => $oldValues['robots_txt'] !== null ? hash('sha256', $oldValues['robots_txt']) : null,
        ],
        [
            'updated_keys' => $updatedKeys,
            'home_indexing_enabled' => $homeIndexingEnabled,
            'robots_sha256' => hash('sha256', $robotsTxt),
            'robots_file' => 'robots.txt',
        ]
    );

    $db->commit();
    $dbInTransaction = false;

    if (is_string($backupPath) && is_file($backupPath)) {
        if (!@unlink($backupPath)) {
            error_log('[SEO] Não foi possível remover o backup temporário de robots.txt: ' . $backupPath);
        }
    }

    $respond([
        'success' => true,
        'message' => 'Configurações SEO guardadas e robots.txt actualizado na raiz do projecto.',
        'updated_keys' => $updatedKeys,
        'robots_file_updated' => true,
        'sitemap_url' => $sitemapUrl,
    ]);
} catch (Throwable $exception) {
    if ($dbInTransaction && $db->inTransaction()) {
        try {
            $db->rollBack();
        } catch (Throwable $rollbackError) {
            error_log('[SEO ROLLBACK] ' . $rollbackError->getMessage());
        }
    }

    // Restaurar exactamente o robots.txt anterior, ou remover o novo se não existia.
    if ($robotsInstalled && is_file($robotsPath)) {
        @unlink($robotsPath);
    }
    if (is_string($backupPath) && is_file($backupPath)) {
        if (!@rename($backupPath, $robotsPath)) {
            error_log('[SEO CRITICAL] Falha ao restaurar robots.txt anterior. Backup preservado em: ' . $backupPath);
        }
    } elseif ($oldRobotsContents === null && is_file($robotsPath)) {
        @unlink($robotsPath);
    }

    if (is_string($tempPath) && is_file($tempPath)) {
        @unlink($tempPath);
    }

    error_log('[SETTINGS SEO] ' . $exception->getMessage());
    $respond([
        'success' => false,
        'message' => 'Não foi possível concluir a gravação SEO. A operação foi revertida sempre que possível. Verifica as permissões do robots.txt e os logs do PHP.',
    ], 500);
}