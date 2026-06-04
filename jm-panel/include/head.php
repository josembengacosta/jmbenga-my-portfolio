<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#07090f" media="(prefers-color-scheme:dark)">
    <meta name="theme-color" content="#f3f5f9" media="(prefers-color-scheme:light)">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' — Painel JMbenga' : 'Painel JMbenga' ?></title>

    <!-- Preconnect para fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fontes: Syne (títulos) + DM Sans (corpo) + DM Mono (código) -->
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    </noscript>

    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icons/apple-touch-icon-180x180.png">

    <!-- DataTables CSS (não-bloqueante) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" media="print"
        onload="this.media='all'">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css"
        media="print" onload="this.media='all'">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" media="print"
        onload="this.media='all'">

    <!-- CSS do painel -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga.css">

    <!-- Scripts críticos (jQuery necessário antes de DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Scripts diferidos (não bloqueiam render) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

    <!-- Tema: aplicar antes do render para evitar flash -->
    <script>
    (function() {
        var t = localStorage.getItem('jm_theme') || 'dark';
        document.documentElement.dataset.theme = t;
    })();
    </script>