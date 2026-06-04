<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/config.php';
?>
{
"name": "JMbenga Dev",
"short_name": "JMbenga",
"start_url": "<?= BASE_URL ?>/",
"scope": "<?= BASE_URL ?>/",
"display": "standalone",
"background_color": "#0f172a",
"theme_color": "#2563eb",
"icons": [
{ "src": "<?= BASE_URL ?>/assets/img/icons/icon-192x192.png", "sizes": "192x192", "type": "image/png" },
{ "src": "<?= BASE_URL ?>/assets/img/icons/icon-512x512.png", "sizes": "512x512", "type": "image/png" }
]
}