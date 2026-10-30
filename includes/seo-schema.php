<?php
/**
 * includes/seo-schema.php
 * Dados estruturados JSON-LD para o portefólio JMbenga.
 *
 * Incluir dentro do <head> de includes/header.php, depois de definir:
 * $baseUrl, $_canonical_url, $_title, $_desc, $_section e $_cfg.
 * Não depende de uma nova ligação à base de dados.
 */

$schemaBaseUrl = rtrim((string) ($baseUrl ?? ''), '/');
$schemaScheme = strtolower((string) parse_url($schemaBaseUrl, PHP_URL_SCHEME));
$schemaHost = parse_url($schemaBaseUrl, PHP_URL_HOST);
$schemaCanonical = (string) ($_canonical_url ?? '');

// Só emitir dados estruturados com URLs absolutas HTTP(S) válidas.
if (
    $schemaBaseUrl !== ''
    && in_array($schemaScheme, ['http', 'https'], true)
    && is_string($schemaHost)
    && $schemaHost !== ''
    && filter_var($schemaBaseUrl, FILTER_VALIDATE_URL)
    && filter_var($schemaCanonical, FILTER_VALIDATE_URL)
) {
    $schemaRootUrl = $schemaBaseUrl . '/';
    $schemaPersonId = $schemaRootUrl . '#person';
    $schemaWebsiteId = $schemaRootUrl . '#website';
    $schemaPageId = rtrim($schemaCanonical, '/') . '#webpage';

    // Perfis sociais são opcionais e só entram no grafo se forem URLs públicas HTTP(S) válidas.
    $schemaSameAs = [];
    if (isset($_cfg) && is_callable($_cfg)) {
        foreach (['github_url', 'linkedin_url', 'twitter_url', 'instagram_url'] as $schemaSocialKey) {
            try {
                $schemaSocialUrl = trim((string) $_cfg($schemaSocialKey, ''));
            } catch (Throwable $schemaException) {
                $schemaSocialUrl = '';
            }

            if ($schemaSocialUrl === '') {
                continue;
            }

            $schemaSocialParts = parse_url($schemaSocialUrl);
            $schemaSocialScheme = is_array($schemaSocialParts)
                ? strtolower((string) ($schemaSocialParts['scheme'] ?? ''))
                : '';

            if (
                filter_var($schemaSocialUrl, FILTER_VALIDATE_URL)
                && in_array($schemaSocialScheme, ['http', 'https'], true)
                && !isset($schemaSocialParts['user'])
                && !isset($schemaSocialParts['pass'])
            ) {
                $schemaSameAs[] = $schemaSocialUrl;
            }
        }
    }

    $schemaPerson = [
        '@type' => 'Person',
        '@id' => $schemaPersonId,
        'name' => 'José Mbenga Da Costa',
        'url' => $schemaBaseUrl . '/about',
        'jobTitle' => 'Desenvolvedor Full Stack',
        'description' => 'Desenvolvedor Full Stack baseado em Luanda, Angola, com foco em desenvolvimento web, backend, APIs e interfaces digitais.',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Luanda',
            'addressCountry' => 'AO',
        ],
    ];

    if ($schemaSameAs !== []) {
        $schemaPerson['sameAs'] = array_values(array_unique($schemaSameAs));
    }

    $schemaWebsite = [
        '@type' => 'WebSite',
        '@id' => $schemaWebsiteId,
        'url' => $schemaRootUrl,
        'name' => 'JMbenga Portfolio',
        'inLanguage' => 'pt-AO',
        'publisher' => ['@id' => $schemaPersonId],
    ];

    $schemaPageType = 'WebPage';
    $schemaPage = [
        '@type' => $schemaPageType,
        '@id' => $schemaPageId,
        'url' => $schemaCanonical,
        'name' => (string) ($_title ?? 'JMbenga Portfolio'),
        'description' => (string) ($_desc ?? ''),
        'inLanguage' => 'pt-AO',
        'isPartOf' => ['@id' => $schemaWebsiteId],
    ];

    $schemaGraph = [];

    $schemaPath = (string) parse_url($schemaCanonical, PHP_URL_PATH);
    $schemaPath = rtrim($schemaPath, '/') ?: '/';
    $schemaBasePath = rtrim((string) parse_url($schemaBaseUrl, PHP_URL_PATH), '/');
    if ($schemaBasePath !== '' && str_starts_with($schemaPath, $schemaBasePath . '/')) {
        $schemaPath = substr($schemaPath, strlen($schemaBasePath));
    } elseif ($schemaBasePath !== '' && $schemaPath === $schemaBasePath) {
        $schemaPath = '/';
    }

    // A página Sobre descreve uma única pessoa, por isso usa ProfilePage.
    if ($schemaPath === '/about') {
        $schemaPage['@type'] = 'ProfilePage';
        $schemaPage['mainEntity'] = ['@id' => $schemaPersonId];
    } elseif (($_section ?? '') === 'project' && isset($project) && is_array($project)) {
        $projectEntityId = rtrim($schemaCanonical, '/') . '#project';
        $schemaPage['mainEntity'] = ['@id' => $projectEntityId];
        $projectEntity = [
            '@type' => 'CreativeWork',
            '@id' => $projectEntityId,
            'name' => (string) ($project['title_project'] ?? $_title),
            'description' => (string) ($_desc ?? ''),
            'url' => $schemaCanonical,
        ];
        if (isset($project['category_project']) && is_string($project['category_project'])) {
            $projectEntity['genre'] = $project['category_project'];
        }
        if (isset($project['tech_stack'])) {
            $projectTechnologies = json_decode((string) $project['tech_stack'], true);
            if (is_array($projectTechnologies)) {
                $projectEntity['keywords'] = implode(', ', array_filter($projectTechnologies, 'is_string'));
            }
        }
        if (isset($project['cover_project']) && is_string($project['cover_project']) && $project['cover_project'] !== '') {
            $projectEntity['image'] = (string) ($page_og_image ?? '');
        }
        $schemaGraph[] = $projectEntity;
    } elseif (($_section ?? '') === 'services') {
        // As páginas individuais de serviço usam o conteúdo que já definem no PHP.
        $schemaServiceId = rtrim($schemaCanonical, '/') . '#service';
        $schemaPage['mainEntity'] = ['@id' => $schemaServiceId];

        $schemaGraph[] = [
            '@type' => 'Service',
            '@id' => $schemaServiceId,
            'name' => (string) ($_title ?? 'Serviço JMbenga'),
            'description' => (string) ($_desc ?? ''),
            'url' => $schemaCanonical,
            'provider' => ['@id' => $schemaPersonId],
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Angola',
            ],
        ];
    } else {
        $schemaPage['about'] = ['@id' => $schemaPersonId];
    }

    $schemaGraph[] = $schemaPerson;
    $schemaGraph[] = $schemaWebsite;
    $schemaGraph[] = $schemaPage;

    $schemaJson = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $schemaGraph],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_APOS
            | JSON_HEX_AMP
            | JSON_HEX_QUOT
            | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (is_string($schemaJson) && $schemaJson !== '') {
        echo '<script type="application/ld+json">' . $schemaJson . '</script>' . "\n";
    }
}
