<?php
/**
 * Cabeçalho HTML do painel JMbenga.
 * Carrega os módulos CSS em ordem para manter a cascata da folha consolidada.
 * As páginas antigas podem continuar a usar main.css até serem migradas.
 */
$includeLegacyMainCss = isset($includeLegacyMainCss) ? (bool) $includeLegacyMainCss : true;
$legacyMainCssFile = __DIR__ . '/../../assets/css/main.css';
$legacyMainCssVersion = is_file($legacyMainCssFile) ? (string) filemtime($legacyMainCssFile) : '1.0.0';
$panelCssModules = [
    'jmbenga.css',
    'jmbenga-projects.css',
    'jmbenga-project-form.css',
    'jmbenga-project-view.css',
    'jmbenga-project-media.css',
];
$faviconFiles = [
    'favicon.ico' => __DIR__ . '/../../assets/img/icons/favicon.ico',
    'favicon-16x16.png' => __DIR__ . '/../../assets/img/icons/favicon-16x16.png',
    'favicon-32x32.png' => __DIR__ . '/../../assets/img/icons/favicon-32x32.png',
    'apple-touch-icon-180x180.png' => __DIR__ . '/../../assets/img/icons/apple-touch-icon-180x180.png',
];
$faviconVersions = [];
foreach ($faviconFiles as $name => $path) {
    $faviconVersions[$name] = is_file($path) ? (string) filemtime($path) : '1.0.0';
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#07090f" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f3f5f9" media="(prefers-color-scheme: light)">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — Painel JMbenga' : 'Painel JMbenga' ?></title>

    <!-- Favicons do JMbenga: ficheiros existentes em assets/img/icons. -->
    <link rel="icon" type="image/png" sizes="32x32"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/img/icons/favicon-32x32.png') ?>?v=<?= e($faviconVersions['favicon-32x32.png']) ?>">
    <link rel="icon" type="image/png" sizes="16x16"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/img/icons/favicon-16x16.png') ?>?v=<?= e($faviconVersions['favicon-16x16.png']) ?>">
    <link rel="shortcut icon"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/img/icons/favicon.ico') ?>?v=<?= e($faviconVersions['favicon.ico']) ?>">
    <link rel="apple-touch-icon" sizes="180x180"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/img/icons/apple-touch-icon-180x180.png') ?>?v=<?= e($faviconVersions['apple-touch-icon-180x180.png']) ?>">

    <script>
    (function() {
        var theme = 'dark';
        try {
            var savedTheme = localStorage.getItem('jm_theme');
            if (savedTheme === 'light' || savedTheme === 'dark') theme = savedTheme;
        } catch (error) {
            /* O tema escuro fica disponível sem armazenamento. */ }
        document.documentElement.dataset.theme = theme;
    })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php if ($includeLegacyMainCss): ?>
    <link rel="stylesheet"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/css/main.css') ?>?v=<?= e($legacyMainCssVersion) ?>">
    <?php endif; ?>

    <!-- Módulos administrativos, na mesma ordem das secções originais. -->
    <?php foreach ($panelCssModules as $panelCssModule): ?>
    <?php
        $modulePath = __DIR__ . '/../../assets/css/' . $panelCssModule;
        $moduleVersion = is_file($modulePath) ? (string) filemtime($modulePath) : '1.0.0';
        ?>
    <link rel="stylesheet"
        href="<?= e(rtrim(BASE_URL, '/') . '/assets/css/' . $panelCssModule) ?>?v=<?= e($moduleVersion) ?>">
    <?php endforeach; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>