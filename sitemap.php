<?php
declare(strict_types=1);

/**
 * JMbenga Portfolio — sitemap XML dinâmico
 * Instalar na raiz do projecto como /sitemap.php.
 * Inclui páginas públicas conhecidas e projectos publicados da tabela _projects.
 */

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600, must-revalidate');

$baseUrl = 'https://jmbenga.freedev.app';
$projectRoot = __DIR__;

// Carrega apenas as constantes de configuração. Não inclui db.php, porque esse
// ficheiro pode terminar/redireccionar a execução quando a BD está indisponível.
try {
    $configPath = $projectRoot . '/includes/config.php';
    if (is_file($configPath)) {
        require_once $configPath;
    }

    if (defined('BASE_URL')) {
        $configuredBase = trim((string) BASE_URL);
        if ($configuredBase !== '') {
            $parts = parse_url($configuredBase);
            if (
                is_array($parts)
                && isset($parts['scheme'], $parts['host'])
                && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            ) {
                $baseUrl = rtrim($configuredBase, '/');
            }
        }
    }
} catch (Throwable $e) {
    // Mantém o domínio público como fallback e devolve pelo menos URLs estáticas.
    error_log('[sitemap] Configuração indisponível; a usar o domínio público predefinido.');
}

$baseUrl = rtrim($baseUrl, '/');

/** Escapa um valor para XML. */
$xmlEscape = static function (string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
};

/**
 * Páginas públicas confirmadas no projecto.
 * Não incluir formulários auxiliares, APIs, painel administrativo, páginas de
 * erro/manutenção ou o endpoint de download do CV como URLs canónicas.
 */
$publicPaths = [
    '/home',
    '/about',
    '/projects',
    '/servicos',
    '/services/web-development',
    '/services/mobile-apps',
    '/services/api-backend',
    '/services/ui-ux-design',
    '/services/maintenance',
    '/faq',
    '/contact',
    '/cv',
];

$urls = [];
foreach ($publicPaths as $path) {
    $loc = $baseUrl . $path;
    $urls[$loc] = null;
}

// Adiciona apenas projectos publicados, usando o slug real da base de dados.
// Se a BD não estiver disponível, o sitemap continua válido com as páginas fixas.
try {
    if (
        class_exists(PDO::class)
        && defined('DB_HOST')
        && defined('DB_NAME')
        && defined('DB_USER')
    ) {
        $dbPassword = defined('DB_PASS') ? (string) DB_PASS : '';
        $dbCharset = defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4';
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $dbCharset)) {
            $dbCharset = 'utf8mb4';
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            (string) DB_HOST,
            (string) DB_NAME,
            $dbCharset
        );

        $pdo = new PDO($dsn, (string) DB_USER, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2,
        ]);

        $stmt = $pdo->query(
            "SELECT slug_project, modif_project
             FROM _projects
             WHERE status_project = 'published'
               AND slug_project <> ''
             ORDER BY modif_project DESC"
        );

        foreach ($stmt->fetchAll() as $project) {
            $slug = trim((string) ($project['slug_project'] ?? ''));

            // Slugs de URL amigável; impede inserir caminhos arbitrários no sitemap.
            if ($slug === '' || !preg_match('/\A[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*\z/', $slug)) {
                continue;
            }

            $loc = $baseUrl . '/project/' . rawurlencode($slug);
            $lastmod = null;
            $modified = trim((string) ($project['modif_project'] ?? ''));
            if ($modified !== '') {
                $timestamp = strtotime($modified);
                if ($timestamp !== false) {
                    $lastmod = date('Y-m-d', $timestamp);
                }
            }

            $urls[$loc] = $lastmod;
        }
    }
} catch (Throwable $e) {
    // Não expõe credenciais nem detalhes da BD na resposta XML.
    error_log('[sitemap] Não foi possível carregar projectos publicados; a usar URLs estáticas.');
}

// Elimina duplicados e ordena os URLs para produzir XML consistente.
ksort($urls, SORT_STRING);

// A resposta é XML válido mesmo que a base de dados esteja temporariamente offline.
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $loc => $lastmod) {
    echo " <url>\n";
        echo ' <loc>' . $xmlEscape($loc) . "</loc>\n";
        if (is_string($lastmod) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $lastmod)) {
        echo ' <lastmod>' . $xmlEscape($lastmod) . "</lastmod>\n";
        }
        echo " </url>\n";
    }

    echo '</urlset>' . "\n";