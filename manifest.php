<?php
header('Content-Type: application/manifest+json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';

$manifest = [
    'name' => 'JMbenga Dev',
    'short_name' => 'JMbenga',
    'description' => 'Portfólio de José Mbenga - Desenvolvedor Full Stack & UI/UX Designer',
    'start_url' => BASE_URL . '/',
    'scope' => BASE_URL . '/',
    'display' => 'standalone',
    'background_color' => '#0f172a',
    'theme_color' => '#2563eb',
    'icons' => [
        [
            'src' => BASE_URL . '/assets/img/icons/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => BASE_URL . '/assets/img/icons/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
