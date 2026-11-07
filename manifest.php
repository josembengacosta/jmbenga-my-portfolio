<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$baseUrl = rtrim((string) BASE_URL, '/');

$manifest = [
    'id' => $baseUrl . '/',
    'name' => 'JMbenga Portfolio',
    'short_name' => 'JMbenga',
    'description' => 'Portefólio de José Mbenga — desenvolvimento web, backend e interfaces digitais.',
    'start_url' => $baseUrl . '/home',
    'scope' => $baseUrl . '/',
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'background_color' => '#0f172a',
    'theme_color' => '#2563eb',
    'lang' => 'pt-AO',
    'dir' => 'ltr',
    'categories' => ['business', 'productivity', 'education'],
    'prefer_related_applications' => false,
    'icons' => [
        [
            'src' => $baseUrl . '/assets/img/icons/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $baseUrl . '/assets/img/icons/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $baseUrl . '/assets/img/icons/apple-touch-icon-180x180.png',
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Ver projectos',
            'short_name' => 'Projectos',
            'description' => 'Consultar os projectos do portefólio.',
            'url' => $baseUrl . '/projects',
            'icons' => [[
                'src' => $baseUrl . '/assets/img/icons/file-graph.svg',
                'sizes' => '96x96',
                'type' => 'image/svg+xml',
            ]],
        ],
        [
            'name' => 'Descarregar currículo',
            'short_name' => 'Currículo',
            'description' => 'Aceder ao currículo de José Mbenga.',
            'url' => $baseUrl . '/download/cv',
            'icons' => [[
                'src' => $baseUrl . '/assets/img/icons/file-cv.svg',
                'sizes' => '96x96',
                'type' => 'image/svg+xml',
            ]],
        ],
        [
            'name' => 'Entrar em contacto',
            'short_name' => 'Contacto',
            'description' => 'Abrir a secção de contacto.',
            'url' => $baseUrl . '/home#contact',
            'icons' => [[
                'src' => $baseUrl . '/assets/img/icons/contact.svg',
                'sizes' => '96x96',
                'type' => 'image/svg+xml',
            ]],
        ],
        [
            'name' => 'Consultar competências',
            'short_name' => 'Competências',
            'description' => 'Ver as competências técnicas.',
            'url' => $baseUrl . '/home#skills',
            'icons' => [[
                'src' => $baseUrl . '/assets/img/icons/skills.svg',
                'sizes' => '96x96',
                'type' => 'image/svg+xml',
            ]],
        ],
    ],
];

$json = json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ($json === false) {
    http_response_code(500);
    echo '{"error":"manifest_generation_failed"}';
    exit;
}

echo $json;