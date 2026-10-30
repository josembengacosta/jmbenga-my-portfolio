<?php
// ══════════════════════════════════════════════════════════════
// mobile-apps.php — JMbenga Portfolio v3.0
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/visitor.php';

check_maintenance_mode($pdo);
$cfg = function (string $key, string $default = ''): string {
    global $pdo;
    return get_config($pdo, $key, $default);
};

// Validar a cor de destaque antes da sua utilização no CSS.
$accent = trim($cfg('accent_color', '#2563eb'));
if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent)) {
    $accent = '#2563eb';
}
if (strlen($accent) === 4) {
    $accent = '#' . $accent[1] . $accent[1] . $accent[2] . $accent[2] . $accent[3] . $accent[3];
}
$accent = strtolower($accent);
$accentChannels = [
    hexdec(substr($accent, 1, 2)),
    hexdec(substr($accent, 3, 2)),
    hexdec(substr($accent, 5, 2)),
];
$accentRgb = implode(', ', $accentChannels);
$accentDim = '#' . implode('', array_map(
    static fn (int $channel): string => str_pad(dechex((int) round($channel * 0.78)), 2, '0', STR_PAD_LEFT),
    $accentChannels
));

// Aceitar apenas URLs externas HTTP/HTTPS válidas e sem credenciais embutidas.
$isSafeExternalUrl = static function (?string $url): bool {
    if ($url === null || trim($url) === '') {
        return false;
    }
    $url = trim($url);
    $parts = parse_url($url);
    return $parts !== false
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        && filter_var($url, FILTER_VALIDATE_URL) !== false
        && !isset($parts['user'])
        && !isset($parts['pass']);
};

$whatsappRaw = trim($cfg('whatsapp_url'));
$whatsapp = $isSafeExternalUrl($whatsappRaw) ? $whatsappRaw : '';
$whatsappContactUrl = '';
if ($whatsapp !== '') {
    $parts = parse_url($whatsapp);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['text'] = 'Olá José, vim do seu portfólio';
    $whatsappContactUrl = $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? '')
        . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

$page_title = 'Desenvolvimento de Aplicações Móveis — JMbenga | José Mbenga';
$page_desc = 'Desenvolvimento de aplicações móveis para Android e iOS, com escopo, funcionalidades e orçamento em kwanzas definidos conforme os requisitos do projecto.';
$meta_desc = $page_desc; // Compatibilidade com cabeçalhos que usam $meta_desc.
$page_section = 'services';

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/mobile-apps.css">

<style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: <?=e($accentDim) ?>;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=e($accentRgb) ?>;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(var(--accent-rgb), .35);
    --text: #e8edf5;
    --text-dim: #8899b4;
    --text-muted: #4a5878;
    --shadow: 0 8px 40px rgba(0, 0, 0, .5);
    --shadow-acc: 0 0 40px var(--accent-glow);

    --font-head: 'Syne', 'Inter', sans-serif;
    --font-body: 'DM Sans', 'Inter', sans-serif;
    --font-mono: 'DM Mono', 'Fira Code', monospace;
    --radius: 12px;
    --radius-lg: 20px;
    --ease: cubic-bezier(.16, 1, .3, 1);
    --nav-h: 70px;

    --grad: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    --grad-text: linear-gradient(135deg, var(--accent), #a78bfa);
}

[data-theme="light"] {
    --bg: #f0f4fb;
    --bg-2: #e4eaf6;
    --bg-card: #ffffff;
    --bg-glass: rgba(255, 255, 255, .85);
    --border: rgba(0, 0, 0, .07);
    --border-acc: rgba(37, 99, 235, .25);
    --text: #0f172a;
    --text-dim: #475569;
    --text-muted: #94a3b8;
    --shadow: 0 8px 40px rgba(0, 0, 0, .12);
}

/* Blur de luz */
.hero-blob {
    position: absolute;
    top: -200px;
    right: -100px;
    z-index: 0;
    width: 700px;
    height: 700px;
    border-radius: 50%;
    background: radial-gradient(circle, <?=e($accent) ?>22 0%, transparent 65%);
    animation: blob 14s ease-in-out infinite alternate;
    pointer-events: none;
}
</style>

</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($whatsappContactUrl !== ''): ?>
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsappContactUrl) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp" aria-hidden="true"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>


    <!-- Hero -->
    <section id="service-hero">
        <div class="container">
            <div class="service-hero-inner">
                <div class="service-hero-text reveal">
                    <span class="tag">Serviço Premium</span>
                    <h1>Aplicações Móveis <span>Profissionais</span></h1>
                    <p class="lead">Planeio e desenvolvo aplicações móveis de acordo com os objectivos do negócio, as
                        funcionalidades necessárias e as plataformas escolhidas. A tecnologia, o prazo e o orçamento são
                        definidos após analisar os requisitos.</p>
                    <div class="hero-stats-row">
                        <div class="hero-stat-mini">
                            <div class="num">iOS</div>
                            <div class="lbl">Aplicações para Apple</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">Android</div>
                            <div class="lbl">Aplicações para Android</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=Aplicativos+Mobile" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline"><i class="fab fa-whatsapp" aria-hidden="true"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-mobile-alt"></i></div>
                    <div class="hero-stats-row">
                        <div class="hero-stat-mini"><i class="fab fa-apple"
                                style="font-size:1.5rem;color:var(--text-dim)"></i>
                            <div class="lbl">iOS</div>
                        </div>
                        <div class="hero-stat-mini"><i class="fab fa-android"
                                style="font-size:1.5rem;color:var(--text-dim)"></i>
                            <div class="lbl">Android</div>
                        </div>
                        <div class="hero-stat-mini"><i class="fab fa-react"
                                style="font-size:1.5rem;color:var(--text-dim)"></i>
                            <div class="lbl">React Native</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tipos de Aplicações -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Tipos de Aplicações</span>
                <h2 class="section-title">Desenvolvo Diferentes Tipos de Aplicação Conforme a Sua <span
                        class="text-gradient">Necessidade</span></h2>
            </div>
            <div class="app-types-grid">
                <?php
                $app_types = [
                    ['icon' => 'fa-shopping-cart', 'title' => 'Aplicações de Comércio Electrónico', 'desc' => 'Lojas para dispositivos móveis com carrinho, finalização de compra, pagamentos e notificações, quando aplicáveis.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-building', 'title' => 'Aplicações Empresariais', 'desc' => 'Sistemas internos, CRM, painéis de controlo e ferramentas de produtividade para equipas em mobilidade.', 'platforms' => ['React Native']],
                    ['icon' => 'fa-heartbeat', 'title' => 'Saúde e Bem-estar', 'desc' => 'Aplicações de treino, nutrição, monitorização da saúde e integração com dispositivos compatíveis.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-graduation-cap', 'title' => 'Educacionais', 'desc' => 'Plataformas de ensino, cursos online, questionários e experiências de aprendizagem interactiva.', 'platforms' => ['Flutter']],
                    ['icon' => 'fa-play-circle', 'title' => 'Entretenimento', 'desc' => 'Aplicações de streaming, redes sociais, jogos casuais e plataformas de conteúdo envolventes.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-tools', 'title' => 'Utilitários', 'desc' => 'Ferramentas, calculadoras, organizadores e aplicações utilitárias para tarefas do dia-a-dia.', 'platforms' => ['Cross-platform']],
                ];
                foreach ($app_types as $type): ?>
                <div class="app-type-card reveal">
                    <div class="icon-circle"><i class="fas <?= e($type['icon']) ?>"></i></div>
                    <h3><?= e($type['title']) ?></h3>
                    <p><?= e($type['desc']) ?></p>
                    <div class="platform-badges">
                        <?php foreach ($type['platforms'] as $p): ?>
                        <span class="platform-badge"><?= e($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Abordagens de Desenvolvimento -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Abordagens de Desenvolvimento</span>
                <h2 class="section-title">Escolha a Melhor Tecnologia para o Seu <span
                        class="text-gradient">Projecto</span>
                </h2>
            </div>
            <div class="approach-grid reveal">
                <?php
                $approaches = [
                    [
                        'title' => 'React Native',
                        'tech' => 'Cross-platform',
                        'desc' => 'Desenvolvimento cross-platform com performance próxima do nativo e código único para iOS e Android.',
                        'advantages' => ['Codebase único', 'Hot Reload', 'Ecossistema vasto', 'Custo reduzido']
                    ],
                    [
                        'title' => 'Flutter',
                        'tech' => 'Google Framework',
                        'desc' => 'Framework Google com UI nativa, performance excelente e desenvolvimento rápido.',
                        'advantages' => ['UI consistente', 'Hot Reload', 'Performance nativa', 'Material Design']
                    ],
                    [
                        'title' => 'Nativo',
                        'tech' => 'Swift + Kotlin',
                        'desc' => 'Swift para iOS e Kotlin para Android. Máxima performance e acesso total às APIs do dispositivo.',
                        'advantages' => ['Performance máxima', 'Acesso total APIs', 'UX nativo', 'Otimização total']
                    ],
                ];
                foreach ($approaches as $app): ?>
                <div class="approach-card">
                    <h4><?= e($app['title']) ?></h4>
                    <div class="tech-name"><?= e($app['tech']) ?></div>
                    <p><?= e($app['desc']) ?></p>
                    <ul>
                        <?php foreach ($app['advantages'] as $adv): ?>
                        <li><?= e($adv) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Processo -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Metodologia</span>
                <h2 class="section-title">O Meu <span class="text-gradient">Processo</span></h2>
                <p class="section-sub">Metodologia ágil para aplicações de sucesso, com entregas iterativas e feedback
                    contínuo.
                </p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Análise e planeamento', 'desc' => 'Levantamento de requisitos, definição das funcionalidades prioritárias e planeamento da primeira versão.'],
                    ['num' => 2, 'title' => 'Design de interface móvel', 'desc' => 'Estrutura dos ecrãs, protótipos e validação dos principais fluxos de utilização.'],
                    ['num' => 3, 'title' => 'Desenvolvimento', 'desc' => 'Implementação faseada das funcionalidades e integração dos componentes definidos no âmbito.'],
                    ['num' => 4, 'title' => 'Testes e qualidade', 'desc' => 'Verificação das funcionalidades, compatibilidade e correcção dos problemas identificados.'],
                    ['num' => 5, 'title' => 'Preparação para publicação', 'desc' => 'Preparação para publicação nas lojas, se contratada e depois de cumpridos os requisitos dos fornecedores.'],
                    ['num' => 6, 'title' => 'Manutenção e actualizações', 'desc' => 'Acompanhamento posterior mediante acordo de manutenção separado.'],
                ];
                foreach ($steps as $step): ?>
                <div class="process-step">
                    <div class="step-number"><?= (int)$step['num'] ?></div>
                    <h4><?= e($step['title']) ?></h4>
                    <p><?= e($step['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Tecnologias -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Stack Tecnológico</span>
                <h2 class="section-title">Tecnologias <span class="text-gradient">possíveis para o projecto</span></h2>
                <p class="section-sub">A selecção final depende dos requisitos, da infraestrutura disponível, do
                    orçamento e da capacidade de manutenção do produto.</p>
            </div>
            <div class="tech-grid reveal">
                <?php
                $techs = [
                    ['icon' => 'fab fa-react', 'name' => 'React Native', 'sub' => 'Cross-platform'],
                    ['icon' => 'fas fa-layer-group', 'name' => 'Flutter', 'sub' => 'Framework Google'],
                    ['icon' => 'fab fa-apple', 'name' => 'Swift', 'sub' => 'Nativo para iOS'],
                    ['icon' => 'fab fa-android', 'name' => 'Kotlin', 'sub' => 'Nativo para Android'],
                    ['icon' => 'fas fa-database', 'name' => 'Firebase', 'sub' => 'Backend e autenticação'],
                    ['icon' => 'fas fa-leaf', 'name' => 'MongoDB', 'sub' => 'Base de dados'],
                    ['icon' => 'fas fa-cloud', 'name' => 'AWS', 'sub' => 'Serviços cloud'],
                    ['icon' => 'fab fa-git-alt', 'name' => 'Git', 'sub' => 'Controlo de versões'],
                ];
                foreach ($techs as $tech): ?>
                <div class="tech-card reveal">
                    <i class="<?= e($tech['icon']) ?>"></i>
                    <h4><?= e($tech['name']) ?></h4>
                    <small><?= e($tech['sub']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Orçamento de aplicações móveis -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Orçamento <span class="text-gradient">à Medida</span></h2>
                <p class="section-sub">O custo de uma aplicação depende das plataformas, dos ecrãs, do backend, das
                    integrações e das necessidades de publicação. A proposta é apresentada em kwanzas (Kz), com o âmbito
                    e os custos externos discriminados.</p>
            </div>
            <div class="pricing-grid reveal" style="grid-template-columns:minmax(0, 680px);justify-content:center;">
                <div class="pricing-card" style="max-width:680px;width:100%;margin:0 auto;">
                    <h3 style="font-family:var(--font-head)">Orçamento personalizado</h3>
                    <div class="price">Em Kz<span> após análise</span></div>
                    <p>Antes de indicar um preço, definimos o que a aplicação precisa de fazer e o que será entregue.
                    </p>
                    <ul>
                        <li><i class="fas fa-check" aria-hidden="true"></i>Plataformas e funcionalidades necessárias
                        </li>
                        <li><i class="fas fa-check" aria-hidden="true"></i>Número e complexidade dos ecrãs</li>
                        <li><i class="fas fa-check" aria-hidden="true"></i>Backend, base de dados e integrações, se
                            aplicáveis</li>
                        <li><i class="fas fa-check" aria-hidden="true"></i>Testes e publicação, conforme o âmbito
                            aprovado</li>
                        <li><i class="fas fa-check" aria-hidden="true"></i>Custos externos discriminados na proposta
                        </li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Aplicativos+Mobile" class="btn btn-primary"
                        style="width:100%">Solicitar orçamento</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                Ainda não existe uma tabela de preços de referência aprovada para aplicações móveis. Por isso, não
                publicamos valores fictícios. Domínios, alojamento, contas de programador, taxas das lojas, licenças e
                serviços de terceiros só são incluídos quando estiverem identificados na proposta.
            </p>
        </div>
    </section>

    <!-- Projectos mobile publicados na base de dados -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Portfólio Mobile</span>
                <h2 class="section-title">Projectos <span class="text-gradient">Publicados</span></h2>
                <p class="section-sub">Esta secção apresenta projectos móveis registados e publicados no portfólio. Não
                    exibimos exemplos fictícios como se fossem trabalhos concluídos.</p>
            </div>
            <?php
            $mobileProjects = $pdo->query("SELECT title_project, slug_project, summary_project, cover_project, tech_stack
                FROM _projects
                WHERE status_project = 'published' AND category_project = 'mobile'
                ORDER BY is_featured DESC, display_order ASC
                LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($mobileProjects)): ?>
            <div class="portfolio-grid reveal">
                <?php foreach ($mobileProjects as $project):
                    $projectCover = basename((string)($project['cover_project'] ?? ''));
                    $projectCoverPath = ROOT_PATH . '/assets/img/projects/' . $projectCover;
                    $hasProjectCover = $projectCover !== '' && is_file($projectCoverPath);
                    $projectTech = json_decode((string)($project['tech_stack'] ?? ''), true);
                    if (!is_array($projectTech)) {
                        $projectTech = [];
                    }
                ?>
                <article class="portfolio-card">
                    <?php if ($hasProjectCover): ?>
                    <img src="<?= BASE_URL ?>/assets/img/projects/<?= e(rawurlencode($projectCover)) ?>"
                        alt="Imagem do projecto <?= e($project['title_project']) ?>" loading="lazy"
                        style="max-width:100%;height:auto;border-radius:12px">
                    <?php else: ?>
                    <div class="app-icon"><i class="fas fa-mobile-alt" aria-hidden="true"></i></div>
                    <?php endif; ?>
                    <h3><?= e($project['title_project']) ?></h3>
                    <?php if (!empty($project['summary_project'])): ?>
                    <p><?= e($project['summary_project']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($projectTech)): ?>
                    <div class="project-tags" aria-label="Tecnologias">
                        <?php foreach (array_slice($projectTech, 0, 5) as $technology): ?>
                        <span class="ptag"><?= e((string)$technology) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/project/<?= e(rawurlencode((string)$project['slug_project'])) ?>"
                        class="btn btn-outline" style="margin-top:16px">Ver projecto</a>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="projects-empty">
                <i class="fas fa-mobile-alt" aria-hidden="true"></i>
                <p>Não existem projectos de aplicações móveis publicados nesta categoria. Quando houver casos
                    confirmados, serão apresentados aqui.</p>
                <a href="<?= BASE_URL ?>/contact?service=Aplicativos+Mobile" class="btn btn-primary">Discutir uma
                    ideia</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Dúvidas</span>
                <h2 class="section-title">Perguntas <span class="text-gradient">Frequentes</span></h2>
            </div>
            <div class="faq-list reveal">
                <?php
                $faqs = [
                    ['q' => 'Quanto tempo demora a desenvolver uma aplicação?', 'a' => 'O prazo depende do número de funcionalidades e ecrãs, das integrações, das plataformas e dos testes necessários. Depois de analisar os requisitos, apresento um calendário estimado por etapas.'],
                    ['q' => 'É melhor desenvolver para Android, iOS ou ambas as plataformas?', 'a' => 'A escolha depende do público-alvo, das funcionalidades, dos custos e dos requisitos técnicos. A proposta explicará a abordagem recomendada e as plataformas abrangidas.'],
                    ['q' => 'A publicação nas lojas está incluída?', 'a' => 'A publicação só está incluída quando estiver expressamente indicada no orçamento. As contas de programador, as taxas das lojas e outros encargos de terceiros são identificados separadamente.'],
                    ['q' => 'Há manutenção após o lançamento?', 'a' => 'A manutenção pode ser contratada separadamente. O orçamento deve especificar as horas incluídas, os tempos de resposta, as actualizações e as exclusões.'],
                    ['q' => 'Quem paga as contas de programador e as taxas das lojas?', 'a' => 'Esses custos são definidos pelos fornecedores e podem mudar. Antes de fechar o orçamento, confirmamos as condições actuais e identificamos os encargos aplicáveis. Sempre que possível, as contas devem ficar em nome do cliente.'],
                ];
                foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <h4><i class="fas fa-question-circle" style="color:var(--accent);margin-right:8px"
                            aria-hidden="true"></i><?= e($faq['q']) ?></h4>
                    <p><?= e($faq['a']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Final -->
    <div class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:100px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Vamos Criar o Seu <span class="text-gradient">App</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Tem uma ideia de aplicação? Vamos conversar sobre como transformá-la em realidade.
            </p>
            <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:24px">
                <i class="fab fa-apple"></i> iOS &nbsp;·&nbsp; <i class="fab fa-android"></i> Android &nbsp;·&nbsp; <i
                    class="fas fa-mobile-alt"></i> Cross-platform
            </div>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Aplicativos+Mobile" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><i
                        class="fab fa-whatsapp" aria-hidden="true"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = '';
    $page_scripts = [];
    include __DIR__ . '/../includes/footer.php';
    include __DIR__ . '/../includes/bottom-nav.php';
    ?>


</body>

</html>