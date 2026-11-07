<?php

/**
 * services/index.php — Catálogo geral de serviços JMbenga Portfolio
 * Rota pública prevista: /servicos
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/visitor.php';

check_maintenance_mode($pdo);

$cfg = static function (string $key, string $default = ''): string {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return $default;
    }
    return get_config($pdo, $key, $default);
};

$accentRaw = $cfg('accent_color', '#2563eb');
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accentRaw) ? $accentRaw : '#2563eb';
$whatsappRaw = trim($cfg('whatsapp_url'));
$whatsapp = '';
if ($whatsappRaw !== '') {
    $parsedWhatsApp = filter_var($whatsappRaw, FILTER_VALIDATE_URL) ? parse_url($whatsappRaw) : false;
    if (is_array($parsedWhatsApp) && in_array(strtolower((string)($parsedWhatsApp['scheme'] ?? '')), ['http', 'https'], true)) {
        $whatsapp = $whatsappRaw;
    }
}

$page_title = 'Serviços e Preços — JMbenga | José Mbenga';
$page_desc = 'Conheça os serviços de desenvolvimento web, aplicações mobile, APIs e backend, UI/UX design e manutenção, com referências de preços em kwanzas.';
$page_section = 'servicos';

$services = [
    [
        'icon' => 'fa-globe',
        'title' => 'Desenvolvimento Web',
        'description' => 'Landing pages, sites institucionais, portfólios, lojas online e sistemas web ajustados aos objectivos do projecto.',
        'price' => 'Pacotes de 75.000 a 280.000 Kz',
        'note' => 'Projectos personalizados: a partir de 300.000 Kz.',
        'href' => BASE_URL . '/services/web-development',
        'service' => 'Desenvolvimento Web',
    ],
    [
        'icon' => 'fa-mobile-screen-button',
        'title' => 'Aplicações Mobile',
        'description' => 'Aplicações para dispositivos móveis, com funcionalidades definidas a partir dos requisitos e da plataforma pretendida.',
        'price' => 'Orçamento personalizado',
        'note' => 'O valor depende do escopo, das integrações e das plataformas.',
        'href' => BASE_URL . '/services/mobile-apps',
        'service' => 'Aplicativo Mobile',
    ],
    [
        'icon' => 'fa-code',
        'title' => 'APIs & Backend',
        'description' => 'Desenvolvimento de lógica de servidor, integrações, endpoints, operações com bases de dados e serviços de suporte a aplicações.',
        'price' => 'Integração API: 30.000–100.000 Kz',
        'note' => 'CRUD + base de dados: 300.000–550.000 Kz; sistemas complexos sob orçamento.',
        'href' => BASE_URL . '/services/api-backend',
        'service' => 'APIs & Backend',
    ],
    [
        'icon' => 'fa-pen-ruler',
        'title' => 'UI/UX Design',
        'description' => 'Estruturação de interfaces, fluxos de utilização, hierarquia visual e melhoria da experiência digital.',
        'price' => 'Redesign de site: 60.000–180.000 Kz',
        'note' => 'Interfaces novas e design systems são avaliados conforme o escopo.',
        'href' => BASE_URL . '/services/ui-ux-design',
        'service' => 'UI/UX Design',
    ],
    [
        'icon' => 'fa-screwdriver-wrench',
        'title' => 'Manutenção',
        'description' => 'Acompanhamento técnico, pequenas correcções, actualizações e verificações periódicas de sites e aplicações.',
        'price' => 'Planos mensais de 15.000 a 70.000 Kz',
        'note' => 'Novas funcionalidades e trabalho fora do plano são orçamentados à parte.',
        'href' => BASE_URL . '/services/maintenance',
        'service' => 'Manutenção de Site / App',
    ],
];

$webPackages = [
    [
        'name' => 'JMbenga Start',
        'price' => '75.000–100.000 Kz',
        'description' => 'Para uma presença digital inicial e objectivos bem delimitados.',
        'features' => ['Escopo acordado antes do início', 'Design responsivo', 'Conteúdo fornecido pelo cliente', 'Duas rondas de revisão incluídas'],
        'featured' => false,
    ],
    [
        'name' => 'JMbenga Business',
        'price' => '180.000–280.000 Kz',
        'description' => 'Para negócios que precisam de um website institucional mais completo.',
        'features' => ['Até 5 páginas no escopo-base', 'Design responsivo', 'Estrutura de conteúdo acordada', 'Duas rondas de revisão incluídas'],
        'featured' => true,
    ],
    [
        'name' => 'JMbenga Custom',
        'price' => 'A partir de 300.000 Kz',
        'description' => 'Para requisitos específicos, sistemas e integrações que exigem levantamento técnico.',
        'features' => ['Requisitos definidos em conjunto', 'Funcionalidades sob medida', 'Plano de entrega acordado', 'Orçamento conforme complexidade'],
        'featured' => false,
    ],
];

$carePackages = [
    [
        'name' => 'Care Basic',
        'price' => '15.000 Kz',
        'period' => '/mês',
        'description' => 'Acompanhamento essencial para um site existente.',
        'features' => ['Até 1 hora de apoio ou pequenas alterações por mês', 'Verificações mensais do site'],
    ],
    [
        'name' => 'Care Business',
        'price' => '35.000 Kz',
        'period' => '/mês',
        'description' => 'Para sites que precisam de acompanhamento regular.',
        'features' => ['Até 3 horas de trabalho por mês', 'Actualizações simples de conteúdo', 'Verificações periódicas e backups quando suportados'],
    ],
    [
        'name' => 'Care Pro',
        'price' => '70.000 Kz',
        'period' => '/mês',
        'description' => 'Acompanhamento mais abrangente e prioritário.',
        'features' => ['Até 6 horas de trabalho por mês', 'Actualizações e testes controlados', 'Apoio prioritário e relatório mensal'],
    ],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/services.css">
<style>
:root {
    --catalog-accent: <?=e($accent) ?>;
    --accent: <?=e($accent) ?>;
    --accent-dim: <?=e($accent) ?>;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>;
    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(<?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>, .35);
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
    --border-acc: rgba(<?=hexdec(substr($accent, 1, 2)) ?>, <?=hexdec(substr($accent, 3, 2)) ?>, <?=hexdec(substr($accent, 5, 2)) ?>, .25);
    --text: #0f172a;
    --text-dim: #475569;
    --text-muted: #94a3b8;
    --shadow: 0 8px 40px rgba(0, 0, 0, .12);
}

.catalog-hero {
    position: relative;
    overflow: hidden;
    padding: calc(var(--nav-h, 70px) + 76px) 0 70px;
    background: var(--bg-2);
}

.catalog-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: linear-gradient(var(--border) 1px, transparent 1px), linear-gradient(90deg, var(--border) 1px, transparent 1px);
    background-size: 54px 54px;
    mask-image: radial-gradient(ellipse 85% 85% at 50% 100%, #000 0%, transparent 76%);
}

.catalog-hero-inner {
    position: relative;
    z-index: 1;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
}

.catalog-kicker {
    display: inline-flex;
    gap: 9px;
    align-items: center;
    border: 1px solid var(--border-acc);
    border-radius: 999px;
    padding: 8px 13px;
    color: var(--text-dim);
    font-size: .82rem;
    margin-bottom: 20px;
}

.catalog-kicker i {
    color: var(--accent);
}

.catalog-hero h1 {
    font-family: var(--font-head);
    font-size: clamp(2.2rem, 5vw, 4rem);
    line-height: 1.08;
    letter-spacing: -.035em;
    font-weight: 800;
    margin: 0 auto 20px;
    max-width: 900px;
}

.catalog-hero h1 span {
    background: linear-gradient(135deg, var(--accent), #a78bfa);
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.catalog-hero p {
    color: var(--text-dim);
    font-size: 1.08rem;
    line-height: 1.8;
    max-width: 730px;
    margin: 0 auto 28px;
}

.catalog-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

.catalog-section {
    padding: 76px 0;
}

.catalog-section.catalog-alt {
    background: var(--bg-2);
}

.catalog-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    margin-top: 38px;
}

.catalog-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--bg-card);
    transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
}

.catalog-card:hover {
    transform: translateY(-5px);
    border-color: var(--border-acc);
    box-shadow: 0 16px 40px rgba(0, 0, 0, .12);
}

.catalog-icon {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border-radius: 16px;
    color: var(--accent);
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    font-size: 1.35rem;
    margin-bottom: 20px;
}

.catalog-card h3 {
    font-family: var(--font-head);
    font-size: 1.16rem;
    margin: 0 0 10px;
}

.catalog-card p {
    color: var(--text-dim);
    font-size: .91rem;
    line-height: 1.75;
    margin: 0 0 16px;
}

.catalog-price {
    color: var(--text);
    font-weight: 700;
    line-height: 1.5;
    font-size: .96rem;
    margin-top: auto;
}

.catalog-note {
    color: var(--text-muted);
    font-size: .81rem;
    line-height: 1.6;
    margin-top: 7px;
}

.catalog-card .catalog-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 20px;
    color: var(--accent);
    text-decoration: none;
    font-weight: 700;
    font-size: .9rem;
}

.catalog-card .catalog-link:hover {
    gap: 13px;
}

.catalog-pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    align-items: stretch;
    margin-top: 38px;
}

.catalog-plan {
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 30px 26px;
}

.catalog-plan.is-featured {
    border-color: var(--accent);
    box-shadow: 0 0 32px var(--accent-glow);
}

.catalog-plan h3 {
    font-family: var(--font-head);
    margin: 0 0 9px;
    font-size: 1.2rem;
}

.catalog-plan .plan-price {
    font-size: 1.4rem;
    line-height: 1.4;
    font-weight: 800;
    margin: 8px 0 10px;
}

.catalog-plan .plan-description {
    color: var(--text-dim);
    font-size: .9rem;
    line-height: 1.7;
    min-height: 54px;
}

.catalog-plan ul {
    list-style: none;
    padding: 0;
    margin: 19px 0 24px;
}

.catalog-plan li {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    color: var(--text-dim);
    font-size: .87rem;
    line-height: 1.6;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
}

.catalog-plan li i {
    color: var(--accent);
    margin-top: 4px;
}

.catalog-plan .btn {
    margin-top: auto;
    justify-content: center;
}

.catalog-ribbon {
    position: absolute;
    top: 14px;
    right: 14px;
    border-radius: 999px;
    background: var(--accent-glow);
    color: var(--accent);
    border: 1px solid var(--border-acc);
    padding: 5px 9px;
    font-size: .72rem;
    font-weight: 700;
}

.catalog-info {
    margin-top: 28px;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px 26px;
    background: var(--bg-card);
}

.catalog-info h3 {
    font-family: var(--font-head);
    margin: 0 0 12px;
}

.catalog-info p {
    color: var(--text-dim);
    font-size: .9rem;
    line-height: 1.8;
    margin: 7px 0;
}

.catalog-terms {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 34px;
}

.catalog-term {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
}

.catalog-term i {
    color: var(--accent);
    font-size: 1.2rem;
    margin-top: 3px;
}

.catalog-term h3 {
    font-size: .98rem;
    margin: 0 0 6px;
}

.catalog-term p {
    color: var(--text-dim);
    font-size: .86rem;
    line-height: 1.7;
    margin: 0;
}

.catalog-disclaimer {
    max-width: 850px;
    margin: 24px auto 0;
    text-align: center;
    color: var(--text-muted);
    font-size: .82rem;
    line-height: 1.75;
}

.catalog-final-cta {
    text-align: center;
    padding: 76px 0;
    border-top: 1px solid var(--border);
}

.catalog-final-cta p {
    color: var(--text-dim);
    line-height: 1.8;
    max-width: 650px;
    margin: 0 auto 24px;
}

/* Atalho flutuante de WhatsApp, alinhado com as páginas individuais de serviços. */
.catalog-whatsapp-float {
    position: fixed;
    right: 24px;
    bottom: 100px;
    z-index: 1050;
    display: flex;
    align-items: center;
    gap: 7px;
    max-width: calc(100vw - 28px);
    padding: 5px 7px 5px 15px;
    border-radius: 999px;
    background: #25d366;
    box-shadow: 0 8px 26px rgba(0, 0, 0, .24);
}

.catalog-whatsapp-float a {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    text-decoration: none;
    font-size: .91rem;
    font-weight: 700;
    line-height: 1.2;
}

.catalog-whatsapp-float a i {
    font-size: 1.5rem;
}

.catalog-whatsapp-close {
    width: 25px;
    height: 25px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, .16);
    color: #fff;
    cursor: pointer;
}

.catalog-whatsapp-close:hover {
    background: rgba(0, 0, 0, .28);
}

@media(max-width:650px) {
    .catalog-whatsapp-float {
        right: 14px;
        bottom: 86px;
        padding-left: 12px;
    }

    .catalog-whatsapp-float a {
        font-size: .84rem;
    }
}

@media(max-width:992px) {

    .catalog-grid,
    .catalog-pricing-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .catalog-plan:last-child {
        grid-column: auto;
    }
}

@media(max-width:650px) {
    .catalog-hero {
        padding: calc(var(--nav-h, 70px) + 48px) 0 52px;
    }

    .catalog-section {
        padding: 54px 0;
    }

    .catalog-grid,
    .catalog-pricing-grid,
    .catalog-terms {
        grid-template-columns: 1fr;
    }

    .catalog-card,
    .catalog-plan {
        padding: 24px 21px;
    }

    .catalog-hero p {
        font-size: 1rem;
    }
}
</style>
</head>

<body>
    <?php if ($whatsapp !== ''): ?>
    <div class="catalog-whatsapp-float" id="catalogWhatsAppFloat">
        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
            aria-label="Conversar com José Mbenga pelo WhatsApp">
            <i class="fab fa-whatsapp" aria-hidden="true"></i><span>Vamos conversar?</span>
        </a>
        <button class="catalog-whatsapp-close" type="button" onclick="this.closest('.catalog-whatsapp-float').remove()"
            aria-label="Fechar atalho do WhatsApp">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main id="main-content">
        <section class="catalog-hero">
            <div class="container catalog-hero-inner">
                <span class="catalog-kicker"><i class="fas fa-layer-group" aria-hidden="true"></i> Serviços
                    JMbenga</span>
                <h1>Soluções digitais com <span>escopo e preço claros</span></h1>
                <p>Explore os serviços de desenvolvimento, design e manutenção. As referências de preço ajudam a planear
                    o investimento; o valor final é confirmado depois de entendermos o que precisa de ser feito.</p>
                <div class="catalog-actions">
                    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/contact?service=Or%C3%A7amento+de+Projecto"><i
                            class="fas fa-paper-plane" aria-hidden="true"></i> Pedir orçamento</a>
                    <a class="btn btn-outline" href="#catalogo-servicos"><i class="fas fa-arrow-down"
                            aria-hidden="true"></i> Explorar serviços</a>
                </div>
            </div>
        </section>

        <section class="catalog-section" id="catalogo-servicos">
            <div class="container">
                <div class="section-head reveal">
                    <span class="tag">Áreas de trabalho</span>
                    <h2 class="section-title">Encontre o serviço <span class="text-gradient">adequado ao projecto</span>
                    </h2>
                    <p class="section-sub">Cada serviço tem uma página própria com mais detalhes. Os preços apresentados
                        são referências em kwanzas (Kz), não uma substituição do orçamento definido para o seu caso.</p>
                </div>
                <div class="catalog-grid">
                    <?php foreach ($services as $service): ?>
                    <article class="catalog-card reveal">
                        <div class="catalog-icon"><i class="fas <?= e($service['icon']) ?>" aria-hidden="true"></i>
                        </div>
                        <h3><?= e($service['title']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="catalog-price"><?= e($service['price']) ?></div>
                        <div class="catalog-note"><?= e($service['note']) ?></div>
                        <a class="catalog-link" href="<?= e($service['href']) ?>">Ver detalhes <i
                                class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="catalog-section catalog-alt" id="pacotes-web">
            <div class="container">
                <div class="section-head reveal">
                    <span class="tag">Desenvolvimento Web</span>
                    <h2 class="section-title">Pacotes <span class="text-gradient">para websites</span></h2>
                    <p class="section-sub">Escolha uma referência inicial. O conteúdo, as funcionalidades, o prazo e os
                        limites de cada entrega ficam registados no escopo acordado.</p>
                </div>
                <div class="catalog-pricing-grid">
                    <?php foreach ($webPackages as $package): ?>
                    <article class="catalog-plan reveal <?= $package['featured'] ? 'is-featured' : '' ?>">
                        <?php if ($package['featured']): ?><span class="catalog-ribbon">Mais
                            completo</span><?php endif; ?>
                        <h3><?= e($package['name']) ?></h3>
                        <div class="plan-price"><?= e($package['price']) ?></div>
                        <p class="plan-description"><?= e($package['description']) ?></p>
                        <ul>
                            <?php foreach ($package['features'] as $feature): ?>
                            <li><i class="fas fa-check" aria-hidden="true"></i><span><?= e($feature) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn <?= $package['featured'] ? 'btn-primary' : 'btn-outline' ?>"
                            href="<?= e(BASE_URL) ?>/contact?service=Desenvolvimento+Web&amp;package=<?= rawurlencode($package['name']) ?>">Pedir
                            este pacote</a>
                    </article>
                    <?php endforeach; ?>
                </div>
                <p class="catalog-disclaimer">O domínio, alojamento (hosting), licenças e serviços de terceiros não
                    estão incluídos, salvo indicação expressa no orçamento. Funcionalidades fora do escopo-base podem
                    alterar o preço e o prazo.</p>
            </div>
        </section>

        <section class="catalog-section" id="manutencao">
            <div class="container">
                <div class="section-head reveal">
                    <span class="tag">Acompanhamento contínuo</span>
                    <h2 class="section-title">Planos de <span class="text-gradient">manutenção</span></h2>
                    <p class="section-sub">Para manter um site existente acompanhado depois da entrega, com limites
                        mensais de trabalho definidos.</p>
                </div>
                <div class="catalog-pricing-grid">
                    <?php foreach ($carePackages as $plan): ?>
                    <article class="catalog-plan reveal">
                        <h3><?= e($plan['name']) ?></h3>
                        <div class="plan-price"><?= e($plan['price']) ?><span
                                style="font-size:.85rem;color:var(--text-muted);font-weight:400"><?= e($plan['period']) ?></span>
                        </div>
                        <p class="plan-description"><?= e($plan['description']) ?></p>
                        <ul>
                            <?php foreach ($plan['features'] as $feature): ?>
                            <li><i class="fas fa-check" aria-hidden="true"></i><span><?= e($feature) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn btn-outline"
                            href="<?= e(BASE_URL) ?>/contact?service=Manuten%C3%A7%C3%A3o+de+Site+%2F+App">Consultar
                            plano</a>
                    </article>
                    <?php endforeach; ?>
                </div>
                <div class="catalog-info reveal">
                    <h3>Condições dos planos de manutenção</h3>
                    <p>As horas indicadas correspondem ao limite mensal de trabalho incluído e não transitam
                        automaticamente para o mês seguinte. Novas funcionalidades, projectos adicionais e tarefas que
                        ultrapassem o plano são orçamentados separadamente.</p>
                    <p>Backups, monitorização, actualizações e testes dependem do acesso e das capacidades do alojamento
                        e da infraestrutura existentes; o que será efectuado fica definido no plano contratado.</p>
                </div>
            </div>
        </section>

        <section class="catalog-section catalog-alt" id="condicoes-comerciais">
            <div class="container">
                <div class="section-head reveal">
                    <span class="tag">Antes de começar</span>
                    <h2 class="section-title">Condições <span class="text-gradient">comerciais</span></h2>
                    <p class="section-sub">O projecto começa com um entendimento comum sobre entregas, responsabilidades
                        e critérios de conclusão.</p>
                </div>
                <div class="catalog-terms">
                    <article class="catalog-term reveal"><i class="fas fa-file-signature" aria-hidden="true"></i>
                        <div>
                            <h3>Escopo por escrito</h3>
                            <p>Funcionalidades, páginas, materiais a fornecer, prazo estimado e exclusões ficam
                                acordados antes do início.</p>
                        </div>
                    </article>
                    <article class="catalog-term reveal"><i class="fas fa-wallet" aria-hidden="true"></i>
                        <div>
                            <h3>Pagamento</h3>
                            <p>Regra-base: 50% para iniciar e 50% antes da entrega final ou publicação. Projectos
                                maiores podem usar pagamentos por etapas acordadas.</p>
                        </div>
                    </article>
                    <article class="catalog-term reveal"><i class="fas fa-pen-to-square" aria-hidden="true"></i>
                        <div>
                            <h3>Revisões</h3>
                            <p>Duas rondas de revisão do trabalho dentro do escopo-base. Alterações de requisitos ou
                                novas funcionalidades podem exigir orçamento adicional.</p>
                        </div>
                    </article>
                    <article class="catalog-term reveal"><i class="fas fa-shield-halved" aria-hidden="true"></i>
                        <div>
                            <h3>Correcções após entrega</h3>
                            <p>O orçamento deve especificar a janela de garantia correctiva, normalmente de 15 a 30 dias
                                para problemas reproduzíveis relacionados com o trabalho entregue. Suporte contínuo é
                                separado.</p>
                        </div>
                    </article>
                </div>
                <p class="catalog-disclaimer">Os prazos começam a contar após a entrada acordada e a recepção dos
                    conteúdos, acessos e decisões necessários. Preços, impostos aplicáveis e condições finais devem ser
                    confirmados no orçamento.</p>
            </div>
        </section>

        <section class="catalog-final-cta">
            <div class="container">
                <h2 class="section-title">Tem um projecto em mente?</h2>
                <p>Explique o que pretende construir ou melhorar. Com essa informação, será possível avaliar o escopo e
                    indicar a solução mais adequada.</p>
                <div class="catalog-actions">
                    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/contact?service=Or%C3%A7amento+de+Projecto"><i
                            class="fas fa-paper-plane" aria-hidden="true"></i> Solicitar orçamento</a>
                    <?php if ($whatsapp !== ''): ?>
                    <a class="btn btn-outline" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><i
                            class="fab fa-whatsapp" aria-hidden="true"></i> Conversar pelo WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <?php
    $inline_js = '';
    $page_scripts = [];
    include __DIR__ . '/../includes/footer.php';
    include __DIR__ . '/../includes/bottom-nav.php';
    ?>
</body>

</html>