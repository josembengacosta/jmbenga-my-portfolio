<?php
/**
 * includes/seo-breadcrumbs.php
 * Emite BreadcrumbList JSON-LD para rotas públicas conhecidas do JMbenga.
 * Incluir dentro de <head>, depois de calcular $baseUrl e $_canonical_url.
 * Não consulta a base de dados e não cria HTML visual de navegação.
 */

$crumbBaseUrl = rtrim((string) ($baseUrl ?? (defined('BASE_URL') ? BASE_URL : '')), '/');
$crumbCanonical = (string) ($_canonical_url ?? '');
$crumbScheme = strtolower((string) parse_url($crumbBaseUrl, PHP_URL_SCHEME));
$crumbHost = parse_url($crumbBaseUrl, PHP_URL_HOST);

if (
    $crumbBaseUrl !== ''
    && in_array($crumbScheme, ['http', 'https'], true)
    && is_string($crumbHost) && $crumbHost !== ''
    && filter_var($crumbBaseUrl, FILTER_VALIDATE_URL)
    && filter_var($crumbCanonical, FILTER_VALIDATE_URL)
) {
    $crumbPath = parse_url($crumbCanonical, PHP_URL_PATH);
    $crumbPath = is_string($crumbPath) && $crumbPath !== '' ? $crumbPath : '/';
    $crumbBasePath = parse_url($crumbBaseUrl, PHP_URL_PATH);
    $crumbBasePath = is_string($crumbBasePath) ? rtrim($crumbBasePath, '/') : '';

    // O canonical local pode conter /portfolio-jmbenga; retira-se apenas para reconhecer a rota.
    if ($crumbBasePath !== '' && ($crumbPath === $crumbBasePath || str_starts_with($crumbPath, $crumbBasePath . '/'))) {
        $crumbPath = substr($crumbPath, strlen($crumbBasePath));
    }
    $crumbPath = '/' . ltrim($crumbPath, '/');
    $crumbPath = rtrim($crumbPath, '/') ?: '/';

    $crumbSlug = static function (string $slug): string {
        $label = preg_replace('/[-_]+/', ' ', $slug) ?? $slug;
        return trim(ucwords($label));
    };

    $crumbProjectName = '';
    foreach (['project', 'projectData', 'project_data', 'projectDetails', 'project_details'] as $crumbProjectVar) {
        if (isset($$crumbProjectVar) && is_array($$crumbProjectVar)) {
            $candidate = $$crumbProjectVar['title_project'] ?? $$crumbProjectVar['title'] ?? '';
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $crumbProjectName = trim((string) $candidate);
                break;
            }
        }
    }

    $crumbItems = [];
    $crumbAdd = static function (string $name, string $path) use (&$crumbItems, $crumbBaseUrl): void {
        $crumbItems[] = [
            'name' => $name,
            'item' => $crumbBaseUrl . '/' . ltrim($path, '/'),
        ];
    };

    $crumbServiceNames = [
        'web-development' => 'Desenvolvimento Web',
        'mobile-apps' => 'Aplicações Mobile',
        'api-backend' => 'APIs e Backend',
        'ui-ux-design' => 'UI/UX Design',
        'maintenance' => 'Manutenção e Suporte',
        'ecommerce' => 'Desenvolvimento de Lojas Online',
        'e-commerce' => 'Desenvolvimento de Lojas Online',
        'automation' => 'Automação e Integrações',
        'consulting' => 'Consultoria Técnica',
    ];

    // Não emitir breadcrumbs para endpoints, downloads, painel, páginas de erro ou formulários de fluxo.
    $crumbExcluded = preg_match('#^/(?:api|jm-panel|status|download)(?:/|$)#i', $crumbPath)
        || in_array($crumbPath, ['/feedback', '/robots.txt', '/sitemap.php'], true);

    if (!$crumbExcluded) {
        if ($crumbPath === '/' || $crumbPath === '/home' || $crumbPath === '/index.php') {
            // O Google requer pelo menos dois itens; na página inicial não há trilho útil.
            $crumbItems = [];
        } elseif ($crumbPath === '/about') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Sobre', 'about');
        } elseif ($crumbPath === '/projects') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Projectos', 'projects');
        } elseif (preg_match('#^/project/([^/]+)$#i', $crumbPath, $crumbMatch)) {
            $crumbAdd('Início', 'home');
            $crumbAdd('Projectos', 'projects');
            $crumbName = $crumbProjectName !== '' ? $crumbProjectName : $crumbSlug(rawurldecode($crumbMatch[1]));
            $crumbItems[] = ['name' => $crumbName, 'item' => $crumbCanonical];
        } elseif ($crumbPath === '/servicos') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Serviços', 'servicos');
        } elseif (preg_match('#^/services/([^/]+)$#i', $crumbPath, $crumbMatch)) {
            $crumbAdd('Início', 'home');
            $crumbAdd('Serviços', 'servicos');
            $crumbServiceSlug = strtolower(rawurldecode($crumbMatch[1]));
            $crumbServiceName = $crumbServiceNames[$crumbServiceSlug] ?? $crumbSlug($crumbServiceSlug);
            $crumbItems[] = ['name' => $crumbServiceName, 'item' => $crumbCanonical];
        } elseif ($crumbPath === '/contact') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Contacto', 'contact');
        } elseif ($crumbPath === '/faq') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Perguntas Frequentes', 'faq');
        } elseif ($crumbPath === '/cv') {
            $crumbAdd('Início', 'home');
            $crumbAdd('Currículo', 'cv');
        }
    }

    // Cada trilho contém pelo menos dois itens e corresponde a uma rota pública reconhecida.
    if (count($crumbItems) >= 2) {
        $crumbList = [];
        $crumbLast = count($crumbItems) - 1;
        foreach ($crumbItems as $index => $crumbItem) {
            $listItem = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumbItem['name'],
            ];
            // O item final pode omitir item; aqui é explícito para manter o trilho auditável.
            $listItem['item'] = $crumbItem['item'];
            $crumbList[] = $listItem;
        }

        $crumbJson = json_encode(
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $crumbList,
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_AMP
                | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (is_string($crumbJson) && $crumbJson !== '') {
            echo '<script type="application/ld+json">' . $crumbJson . '</script>' . "\n";
        }
    }
} 