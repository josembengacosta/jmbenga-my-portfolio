<?php
/**
 * includes/seo-page-defaults.php
 * Metadados SEO de reserva por rota pública do JMbenga.
 * Incluir em includes/header.php ANTES de calcular $_title, $_desc e $_robots.
 * Não substitui metadados específicos já definidos por cada página.
 */

$seoMetaBaseUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
$seoMetaRequestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/home'), PHP_URL_PATH);
$seoMetaRequestPath = is_string($seoMetaRequestPath) && $seoMetaRequestPath !== ''
    ? $seoMetaRequestPath
    : '/home';
$seoMetaBasePath = parse_url($seoMetaBaseUrl, PHP_URL_PATH);
$seoMetaBasePath = is_string($seoMetaBasePath) ? rtrim($seoMetaBasePath, '/') : '';

if (
    $seoMetaBasePath !== ''
    && ($seoMetaRequestPath === $seoMetaBasePath || str_starts_with($seoMetaRequestPath, $seoMetaBasePath . '/'))
) {
    $seoMetaRequestPath = substr($seoMetaRequestPath, strlen($seoMetaBasePath));
}

$seoMetaPath = '/' . ltrim($seoMetaRequestPath, '/');
$seoMetaPath = rtrim($seoMetaPath, '/') ?: '/';
if ($seoMetaPath === '/index.php') {
    $seoMetaPath = '/home';
}

$seoMetaDefaults = [
    '/' => [
        'title' => 'José Mbenga — Desenvolvedor Full Stack em Luanda, Angola | JMbenga',
        'description' => 'Portefólio de José Mbenga, desenvolvedor Full Stack em Luanda, Angola. Explore projectos, serviços de desenvolvimento web, APIs, interfaces digitais e formas de contacto.',
    ],
    '/home' => [
        'title' => 'José Mbenga — Desenvolvedor Full Stack em Luanda, Angola | JMbenga',
        'description' => 'Portefólio de José Mbenga, desenvolvedor Full Stack em Luanda, Angola. Explore projectos, serviços de desenvolvimento web, APIs, interfaces digitais e formas de contacto.',
    ],
    '/about' => [
        'title' => 'Sobre José Mbenga — Percurso e Competências | JMbenga',
        'description' => 'Conheça José Mbenga, desenvolvedor Full Stack em Luanda, Angola: percurso, competências técnicas, áreas de actuação e experiência em projectos digitais.',
    ],
    '/projects' => [
        'title' => 'Projectos de Desenvolvimento Web e Software | JMbenga',
        'description' => 'Explore projectos desenvolvidos por José Mbenga, incluindo soluções web, aplicações, integrações e outras experiências digitais publicadas no portefólio.',
    ],
    '/servicos' => [
        'title' => 'Serviços de Desenvolvimento Web e Software em Angola | JMbenga',
        'description' => 'Conheça os serviços JMbenga: desenvolvimento web, aplicações mobile, APIs e backend, UI/UX design e manutenção de sites e aplicações, com referências em kwanzas quando disponíveis.',
    ],
    '/contact' => [
        'title' => 'Contacto e Orçamentos — José Mbenga | JMbenga',
        'description' => 'Entre em contacto com José Mbenga para apresentar o seu projecto, pedir um orçamento ou conversar sobre desenvolvimento web, APIs, aplicações e serviços digitais.',
    ],
    '/feedback' => [
        'title' => 'Enviar Feedback — JMbenga',
        'description' => 'Partilhe uma sugestão ou feedback sobre a experiência no portefólio JMbenga.',
        'robots' => 'noindex,follow',
    ],
    '/faq' => [
        'title' => 'Perguntas Frequentes sobre Serviços e Projectos | JMbenga',
        'description' => 'Respostas a perguntas frequentes sobre os serviços, orçamento, desenvolvimento de projectos e colaboração com José Mbenga.',
    ],
    '/cv' => [
        'title' => 'Currículo de José Mbenga — Desenvolvedor Full Stack | JMbenga',
        'description' => 'Consulte o currículo profissional de José Mbenga, com percurso, competências técnicas e experiência em desenvolvimento de soluções digitais.',
    ],
];

$seoMetaServiceDefaults = [
    'web-development' => [
        'title' => 'Desenvolvimento Web em Angola — Sites e Sistemas | JMbenga',
        'description' => 'Desenvolvimento de sites institucionais, landing pages, portefólios, lojas online e sistemas web responsivos, com escopo e orçamento definidos por projecto.',
    ],
    'mobile-apps' => [
        'title' => 'Desenvolvimento de Aplicações Mobile | JMbenga',
        'description' => 'Desenvolvimento de aplicações móveis com funcionalidades e integrações definidas conforme os requisitos, plataformas e orçamento de cada projecto.',
    ],
    'api-backend' => [
        'title' => 'Desenvolvimento de APIs e Backend | JMbenga',
        'description' => 'Serviços de backend, APIs, integrações e bases de dados para aplicações e sistemas digitais, com escopo técnico definido antes do desenvolvimento.',
    ],
    'ui-ux-design' => [
        'title' => 'UI/UX Design — Interfaces e Experiência Digital | JMbenga',
        'description' => 'Estruturação de interfaces, fluxos de utilização, hierarquia visual e melhorias de usabilidade para produtos e experiências digitais.',
    ],
    'maintenance' => [
        'title' => 'Manutenção de Sites e Aplicações | JMbenga',
        'description' => 'Manutenção técnica de sites e aplicações, correcção de erros, actualizações e acompanhamento conforme o plano e o âmbito contratado.',
    ],
    'ecommerce' => [
        'title' => 'Desenvolvimento de Lojas Online | JMbenga',
        'description' => 'Desenvolvimento de lojas online e experiências de comércio electrónico, com catálogo, fluxos de compra e integrações definidos conforme os requisitos.',
    ],
    'e-commerce' => [
        'title' => 'Desenvolvimento de Lojas Online | JMbenga',
        'description' => 'Desenvolvimento de lojas online e experiências de comércio electrónico, com catálogo, fluxos de compra e integrações definidos conforme os requisitos.',
    ],
    'automation' => [
        'title' => 'Automação e Integrações de Sistemas | JMbenga',
        'description' => 'Automação de tarefas e integração de sistemas para reduzir trabalho repetitivo e ligar serviços digitais, conforme os requisitos do projecto.',
    ],
    'consulting' => [
        'title' => 'Consultoria Técnica em Desenvolvimento de Software | JMbenga',
        'description' => 'Consultoria técnica para analisar requisitos, arquitectura, integrações, desempenho e opções de implementação de soluções digitais.',
    ],
];

$seoMetaSelected = $seoMetaDefaults[$seoMetaPath] ?? null;
if (preg_match('#^/services/([^/]+)$#i', $seoMetaPath, $seoMetaMatch)) {
    $seoMetaSlug = strtolower(rawurldecode($seoMetaMatch[1]));
    if (isset($seoMetaServiceDefaults[$seoMetaSlug])) {
        $seoMetaSelected = $seoMetaServiceDefaults[$seoMetaSlug];
    }
}

if (preg_match('#^/project/([^/]+)$#i', $seoMetaPath, $seoMetaMatch)) {
    $seoMetaProject = null;
    foreach (['project', 'projectData', 'project_data', 'projectDetails', 'project_details'] as $seoMetaProjectVar) {
        if (isset($$seoMetaProjectVar) && is_array($$seoMetaProjectVar)) {
            $seoMetaProject = $$seoMetaProjectVar;
            break;
        }
    }

    $seoMetaProjectSlug = rawurldecode($seoMetaMatch[1]);
    $seoMetaProjectTitle = is_array($seoMetaProject)
        ? trim((string) ($seoMetaProject['title_project'] ?? $seoMetaProject['title'] ?? ''))
        : '';
    if ($seoMetaProjectTitle === '') {
        $seoMetaProjectTitle = ucwords(str_replace(['-', '_'], ' ', $seoMetaProjectSlug));
    }

    $seoMetaProjectSummary = is_array($seoMetaProject)
        ? trim(preg_replace('/\s+/', ' ', strip_tags((string) ($seoMetaProject['summary_project'] ?? $seoMetaProject['summary'] ?? ''))) ?? '')
        : '';
    if ($seoMetaProjectSummary === '') {
        $seoMetaProjectSummary = 'Conheça o projecto ' . $seoMetaProjectTitle . ', apresentado no portefólio de José Mbenga.';
    }
    if (function_exists('mb_substr')) {
        $seoMetaProjectSummary = mb_substr($seoMetaProjectSummary, 0, 300, 'UTF-8');
    } else {
        $seoMetaProjectSummary = substr($seoMetaProjectSummary, 0, 300);
    }

    $seoMetaSelected = [
        'title' => $seoMetaProjectTitle . ' — Projecto de Portefólio | JMbenga',
        'description' => $seoMetaProjectSummary,
    ];
}

if (is_array($seoMetaSelected)) {
    if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
        $page_title = $seoMetaSelected['title'];
    }
    if (!isset($page_desc) || !is_string($page_desc) || trim($page_desc) === '') {
        $page_desc = $seoMetaSelected['description'];
    }
    if (isset($seoMetaSelected['robots']) && (!isset($page_robots) || trim((string) $page_robots) === '')) {
        $page_robots = $seoMetaSelected['robots'];
    }
}

// Não indexar rotas operacionais caso alguma delas reutilize o cabeçalho público.
if (
    preg_match('#^/(?:api|jm-panel|status|download)(?:/|$)#i', $seoMetaPath)
    && (!isset($page_robots) || trim((string) $page_robots) === '')
) {
    $page_robots = 'noindex,follow';
}