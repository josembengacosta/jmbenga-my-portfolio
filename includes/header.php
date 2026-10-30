<?php
/**
 * includes/header.php — Cabeçalho global do portefólio JMbenga.
 * SEO técnico, Open Graph, tema, recursos CSS e suporte a $extra_head.
 * Mantém compatibilidade com as variáveis de metadados definidas pelas páginas.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

if (
    !function_exists('get_config')
    || !function_exists('e')
    || !function_exists('csrf_token')
) {
    require_once __DIR__ . '/functions.php';
}

/*
 * A leitura de configuração não deve deixar metadados vazios se uma chave
 * estiver ausente ou se a consulta falhar. Os valores de fallback são seguros.
 */
$_cfg = static function (string $key, string $default = '') use ($pdo): string {
    try {
        $value = get_config($pdo, $key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    } catch (Throwable $exception) {
        return $default;
    }
};

/* -------------------------------------------------------------------------
 * Título, descrição e nome do site
 * Cada página pode definir $page_title, $page_desc e $page_robots antes
 * de incluir este cabeçalho.
 * ---------------------------------------------------------------------- */
$_site_name = $_cfg('site_name', 'JMbenga');
if ($_site_name === '') {
    $_site_name = 'JMbenga';
}

$_title_default = 'José Mbenga — Desenvolvedor Full Stack em Luanda, Angola | JMbenga';
$_title = trim((string) ($page_title ?? ''));
if ($_title === '') {
    $_title = $_title_default;
}

$_desc = trim((string) ($page_desc ?? ''));
if ($_desc === '') {
    $_desc = $_cfg('meta_description', '');
}
if ($_desc === '') {
    $_desc = 'Portefólio profissional de José Mbenga, desenvolvedor Full Stack em Luanda, Angola. Conheça os projectos, serviços e formas de contacto.';
}

$_section = isset($page_section) && is_scalar($page_section)
    ? trim((string) $page_section)
    : '';

/* -------------------------------------------------------------------------
 * BASE_URL e URL canónico
 * - Retira parâmetros de consulta e fragmentos.
 * - Normaliza / e /index.php para /home, que é a rota pública principal.
 * - Remove o prefixo /portfolio-jmbenga no ambiente local, sem o retirar
 *   do URL final porque ele já está contido em BASE_URL.
 * ---------------------------------------------------------------------- */
$baseUrl = rtrim((string) BASE_URL, '/');
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/home'), PHP_URL_PATH);
if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = '/home';
}

$basePath = parse_url($baseUrl, PHP_URL_PATH);
$basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

if (
    $basePath !== ''
    && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))
) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$requestPath = '/' . ltrim($requestPath, '/');
$requestPath = rtrim($requestPath, '/') ?: '/';
if ($requestPath === '/' || $requestPath === '/index.php') {
    $requestPath = '/home';
}

// Permitir que uma página defina um canonical explícito, mas apenas HTTP(S).
$_canonical_override = trim((string) ($page_canonical ?? ''));
if (
    $_canonical_override !== ''
    && filter_var($_canonical_override, FILTER_VALIDATE_URL)
    && in_array(strtolower((string) parse_url($_canonical_override, PHP_URL_SCHEME)), ['http', 'https'], true)
) {
    $_canonical_url = $_canonical_override;
} else {
    $_canonical_url = $baseUrl . $requestPath;
}

/* -------------------------------------------------------------------------
 * Imagem Open Graph / Twitter
 * Prioridade: $page_og_image -> chave og_image da configuração -> fallback.
 * Aceita URLs HTTP(S) absolutas ou caminhos locais relativos à aplicação.
 * ---------------------------------------------------------------------- */
$_og_candidate = trim((string) ($page_og_image ?? ''));
if ($_og_candidate === '') {
    $_og_candidate = $_cfg('og_image', '');
}
if ($_og_candidate === '') {
    $_og_candidate = '/assets/img/profile/og.jpg';
}

$_og_parts = parse_url($_og_candidate);
if (
    is_array($_og_parts)
    && isset($_og_parts['scheme'], $_og_parts['host'])
    && in_array(strtolower((string) $_og_parts['scheme']), ['http', 'https'], true)
    && filter_var($_og_candidate, FILTER_VALIDATE_URL)
    && !isset($_og_parts['user'])
    && !isset($_og_parts['pass'])
) {
    $_og_image = $_og_candidate;
} elseif (
    str_starts_with($_og_candidate, '//')
    || preg_match('/[\x00-\x20]/', $_og_candidate)
    || preg_match('/^[a-z][a-z0-9+.-]*:/i', $_og_candidate)
) {
    $_og_image = $baseUrl . '/assets/img/profile/og.jpg';
} else {
    $_og_image = $baseUrl . '/' . ltrim($_og_candidate, '/');
}

/* -------------------------------------------------------------------------
 * Robots e tipo Open Graph configuráveis por página
 * ---------------------------------------------------------------------- */
$_robots = strtolower(trim((string) ($page_robots ?? 'index,follow,max-image-preview:large')));
if (!preg_match('/\A(?:index|noindex)\s*,\s*(?:follow|nofollow)(?:\s*,\s*max-image-preview:(?:large|standard|none))?\z/i', $_robots)) {
    $_robots = 'index,follow,max-image-preview:large';
}

$_og_type = strtolower(trim((string) ($page_og_type ?? 'website')));
if (!in_array($_og_type, ['website', 'article', 'profile'], true)) {
    $_og_type = 'website';
}

$_og_locale = 'pt_AO';
$_og_image_alt = trim((string) ($page_og_image_alt ?? 'Portefólio de José Mbenga — JMbenga'));

?>
<!DOCTYPE html>
<html lang="pt-AO" data-theme="dark" data-base-url="<?= e(BASE_URL) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO básico -->
    <meta name="description" content="<?= e($_desc) ?>">
    <meta name="keywords" content="<?= e($_cfg('meta_keywords', 'José Mbenga, Full Stack, PHP, MySQL')) ?>">
    <meta name="author" content="José Mbenga">
    <meta name="robots" content="<?= e($_robots) ?>">

    <!-- Cor do navegador -->
    <meta name="theme-color" content="#0a0d14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">

    <!-- Segurança e formulários -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- Open Graph -->
    <meta property="og:locale" content="<?= e($_og_locale) ?>">
    <meta property="og:site_name" content="<?= e($_site_name) ?>">
    <meta property="og:title" content="<?= e($_title) ?>">
    <meta property="og:description" content="<?= e($_desc) ?>">
    <meta property="og:type" content="<?= e($_og_type) ?>">
    <meta property="og:url" content="<?= e($_canonical_url) ?>">
    <meta property="og:image" content="<?= e($_og_image) ?>">
    <meta property="og:image:alt" content="<?= e($_og_image_alt) ?>">

    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($_title) ?>">
    <meta name="twitter:description" content="<?= e($_desc) ?>">
    <meta name="twitter:image" content="<?= e($_og_image) ?>">
    <meta name="twitter:image:alt" content="<?= e($_og_image_alt) ?>">

    <title><?= e($_title) ?></title>

    <!-- Canonical: URL final sem parâmetros de consulta -->
    <link rel="canonical" href="<?= e($_canonical_url) ?>">

    <!-- Ícones -->
    <link rel="icon" type="image/svg+xml" href="<?= e($baseUrl) ?>/assets/img/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= e($baseUrl) ?>/assets/img/icons/favicon.ico">
    <link rel="apple-touch-icon" href="<?= e($baseUrl) ?>/assets/img/icons/apple-touch-icon-180x180.png">

    <!-- PWA -->
    <link rel="manifest" href="<?= e($baseUrl) ?>/manifest.php" crossorigin="use-credentials">

    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CSS global -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/animations.css">

    <!-- Cor dinâmica do portfólio -->
    <?php if ($_accentCandidate = trim((string) $_cfg('accent_color', '#2563eb'))): ?>
    <?php if (preg_match('/\A#[0-9a-fA-F]{6}\z/', $_accentCandidate) && strtolower($_accentCandidate) !== '#2563eb'): ?>
    <style>
    :root {
        --accent: <?=e($_accentCandidate) ?>;
        --accent-dim: <?=e($_accentCandidate) ?>;
        --accent-glow: <?=e($_accentCandidate) ?>33;
        --border-acc: <?=e($_accentCandidate) ?>66;
    }

    @media (max-width: 768px) {
        .loader-logo {
            font-size: 1.4rem !important;
            letter-spacing: -0.01em !important;
        }
    }
    </style>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Recursos específicos da página. O conteúdo deve ser HTML confiável do próprio projeto. -->
    <?php
    if (isset($extra_head) && is_string($extra_head)) {
        echo $extra_head;
    }
    ?>

    <?php require __DIR__ . '/seo-schema.php'; ?>
    <?php require_once __DIR__ . '/seo-breadcrumbs.php'; ?>

</head>