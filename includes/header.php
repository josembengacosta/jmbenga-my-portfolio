<?php

/**
 * includes/header.php — actualizado
 * Agora inclui tema toggle, offcanvas e suporte a $extra_head.
 */
if (!defined('BASE_URL')) {
  require_once __DIR__ . '/config.php';
}
if (!isset($pdo)) {
  require_once __DIR__ . '/db.php';
}
if (!function_exists('get_config')) {
  require_once __DIR__ . '/functions.php';
}

$_cfg = fn(string $k, string $d = '') => get_config($pdo, $k, $d);

$_title   = $page_title  ?? ($_cfg('site_name', 'JMbenga') . ' — Full Stack Developer');
$_desc    = $page_desc   ?? $_cfg('meta_description', 'Portfólio profissional de José Mbenga.');
$_section = $page_section ?? '';
$_accent  = $_cfg('accent_color', '#2563eb');
$_og_image = BASE_URL . '/assets/img/profile/og.jpg';

?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($_desc) ?>">
    <meta name="keywords" content="<?= e($_cfg('meta_keywords', 'José Mbenga,Full Stack,PHP,MySQL')) ?>">
    <meta name="author" content="José Mbenga">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0a0d14" media="(prefers-color-scheme:dark)">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme:light)">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta property="og:title" content="<?= e($_title) ?>">
    <meta property="og:description" content="<?= e($_desc) ?>">
    <meta property="og:image" content="<?= $_og_image ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL . '/' . $_section ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($_title) ?>">
    <meta name="twitter:description" content="<?= e($_desc) ?>">
    <meta name="twitter:image" content="<?= $_og_image ?>">
    <title><?= e($_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icons/apple-touch-icon-180x180.png">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.php" crossorigin="use-credentials">
    <link rel="canonical" href="<?= BASE_URL ?>/home">
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CSS base -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animations.css">

    <?php if ($_accent !== '#2563eb'): ?>
    <style>
    :root {
        --accent: <?=e($_accent) ?>;
        --accent-dim: <?=e($_accent) ?>;
        --accent-glow: <?=e($_accent) ?>33;
        --border-acc: <?=e($_accent) ?>66;
    }
    </style>
    <?php endif; ?>