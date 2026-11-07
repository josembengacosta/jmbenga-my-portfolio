<?php
// ══════════════════════════════════════════════════════════════
// web-development.php — JMbenga Portfolio v3.0
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

// Valida a cor antes de a inserir no CSS e converte #RGB para #RRGGBB.
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
    static fn (int $channel): string => str_pad(dechex((int)round($channel * 0.78)), 2, '0', STR_PAD_LEFT),
    $accentChannels
));

// Só aceita URLs HTTP(S) absolutas para ligações externas provenientes da configuração.
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

$page_title = 'Serviços de Desenvolvimento Web — José Mbenga | Full Stack Developer';
$page_desc = 'Criação de websites e sistemas web profissionais, responsivos e personalizados, desde páginas institucionais a aplicações web.';
// Compatibilidade com cabeçalhos que usam $meta_desc em vez de $page_desc.
$meta_desc = $page_desc;
$page_section = 'services';

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/web-development.css">

<style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: <?=e($accentDim) ?>;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: <?=$accentRgb ?>;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(<?=$accentRgb ?>, .35);
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
    background: radial-gradient(circle, rgba(<?=$accentRgb ?>, .14) 0%, transparent 65%);
    animation: blob 14s ease-in-out infinite alternate;
    pointer-events: none;
}

@media (max-width: 768px) {
    .service-hero-text h1 {
        font-size: 1.8rem !important;
        line-height: 1.3 !important;
        letter-spacing: normal !important;
        margin-bottom: 12px !important;
    }
}
</style>

</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($whatsapp !== ''): ?>
    <div class=" whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsappContactUrl) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
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
                    <h1>Desenvolvimento Web <span>Profissional</span></h1>
                    <p class="lead">Crio sites e sistemas web modernos, performáticos e escaláveis que convertem
                        visitantes
                        em clientes. Soluções personalizadas para cada necessidade.</p>
                    <div style="display:flex;gap:14px;flex-wrap:wrap">
                        <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline"><i class="fab fa-whatsapp"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-globe"></i></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Serviços -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">O Que Ofereço</span>
                <h2 class="section-title">Soluções Web Completas para Impulsionar o Seu <span
                        class="text-gradient">Negócio
                        Digital</span></h2>
            </div>
            <div class="services-grid">
                <?php
                $services = [
                    ['icon' => 'fa-building', 'title' => 'Sites Institucionais', 'desc' => 'Websites profissionais que transmitem credibilidade e apresentam a sua empresa ao mundo, com design moderno e responsivo.'],
                    ['icon' => 'fa-shopping-cart', 'title' => 'E‑commerce', 'desc' => 'Lojas online completas com carrinho de compras, checkout seguro, gestão de produtos e integração com meios de pagamento.'],
                    ['icon' => 'fa-cogs', 'title' => 'Sistemas Web', 'desc' => 'Aplicações web personalizadas para gestão empresarial, dashboards analíticos, automação de processos e muito mais.'],
                    ['icon' => 'fa-rocket', 'title' => 'Landing Pages', 'desc' => 'Páginas de alta conversão otimizadas para campanhas de marketing, com copywriting persuasivo e um design que gera resultados.'],
                    ['icon' => 'fa-blog', 'title' => 'Blogs & Portais', 'desc' => 'Plataformas de conteúdo com CMS intuitivo, otimização SEO e integração com redes sociais para maximizar o alcance.'],
                    ['icon' => 'fa-tools', 'title' => 'Manutenção & Suporte', 'desc' => 'Planos contínuos de manutenção, atualizações de segurança, backups e suporte técnico para manter o seu site sempre no ar.'],
                ];
                foreach ($services as $svc): ?>
                <div class="service-card reveal">
                    <div class="icon-circle"><i class="fas <?= e($svc['icon']) ?>"></i></div>
                    <h3><?= e($svc['title']) ?></h3>
                    <p><?= e($svc['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Processo -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Metodologia</span>
                <h2 class="section-title">Como <span class="text-gradient">Trabalho</span></h2>
                <p class="section-sub">Um processo ágil e transparente que garante entregas de qualidade, dentro do
                    prazo e
                    do orçamento.</p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Discovery', 'desc' => 'Análise detalhada do projecto, definição de requisitos e planeamento estratégico.'],
                    ['num' => 2, 'title' => 'UI/UX Design', 'desc' => 'Criação de wireframes, protótipos interativos e design visual focado na experiência do utilizador.'],
                    ['num' => 3, 'title' => 'Desenvolvimento', 'desc' => 'Codificação limpa e componentizada, utilizando as melhores práticas e padrões da indústria.'],
                    ['num' => 4, 'title' => 'Testes & QA', 'desc' => 'Testes rigorosos de funcionalidade, performance, segurança, responsividade e acessibilidade.'],
                    ['num' => 5, 'title' => 'Lançamento', 'desc' => 'Deploy em produção, configuração de servidores, monitorização e suporte pós‑lançamento.'],
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
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Stack Tecnológico</span>
                <h2 class="section-title">Ferramentas <span class="text-gradient">Modernas</span></h2>
                <p class="section-sub">Utilizo as melhores tecnologias para garantir performance, segurança e
                    escalabilidade.</p>
            </div>
            <div class="tech-grid reveal">
                <?php
                $techs = [
                    ['icon' => 'fab fa-html5', 'name' => 'HTML5 Semântico'],
                    ['icon' => 'fab fa-css3-alt', 'name' => 'CSS3 / Tailwind / Bootstrap'],
                    ['icon' => 'fab fa-js', 'name' => 'JavaScript ES6+'],
                    ['icon' => 'fab fa-react', 'name' => 'React.js'],
                    ['icon' => 'fab fa-vuejs', 'name' => 'Vue.js'],
                    ['icon' => 'fab fa-php', 'name' => 'PHP 8.2'],
                    ['icon' => 'fas fa-database', 'name' => 'MySQL / MariaDB'],
                    ['icon' => 'fab fa-node-js', 'name' => 'Node.js'],
                    ['icon' => 'fab fa-git-alt', 'name' => 'Git & GitHub'],
                    ['icon' => 'fas fa-server', 'name' => 'Apache / Nginx'],
                ];
                foreach ($techs as $tech): ?>
                <div class="tech-card reveal">
                    <i class="<?= e($tech['icon']) ?>"></i>
                    <h4><?= e($tech['name']) ?></h4>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pacotes e preços de referência JMbenga -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Pacotes <span class="text-gradient">JMbenga</span></h2>
                <p class="section-sub">
                    Valores de referência para projectos com o âmbito descrito. O orçamento final é confirmado
                    depois de alinharmos os requisitos, os conteúdos e as funcionalidades.
                </p>
            </div>

            <div class="pricing-grid reveal">
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">JMbenga Start</h3>
                    <div class="price">75.000–100.000 <span>Kz</span></div>
                    <p>Para profissionais individuais e pequenos negócios que precisam de uma presença digital inicial.
                    </p>
                    <ul>
                        <li><i class="fas fa-check"></i>Uma página com secções organizadas</li>
                        <li><i class="fas fa-check"></i>Design adaptado a telemóveis</li>
                        <li><i class="fas fa-check"></i>Contactos, redes sociais e WhatsApp</li>
                        <li><i class="fas fa-check"></i>SEO técnico básico</li>
                        <li><i class="fas fa-check"></i>Publicação e testes</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web&amp;package=JMbenga+Start"
                        class="btn btn-outline" style="width:100%">Pedir este pacote</a>
                </div>

                <div class="pricing-card featured">
                    <div
                        style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:4px 16px;border-radius:999px;font-size:.72rem;font-weight:600">
                        RECOMENDADO</div>
                    <h3 style="font-family:var(--font-head)">JMbenga Business</h3>
                    <div class="price">180.000–280.000 <span>Kz</span></div>
                    <p>Para pequenas empresas que precisam de apresentar os seus serviços profissionalmente.</p>
                    <ul>
                        <li><i class="fas fa-check"></i>Até cinco páginas</li>
                        <li><i class="fas fa-check"></i>Design personalizado e responsivo</li>
                        <li><i class="fas fa-check"></i>Formulário de contacto e WhatsApp</li>
                        <li><i class="fas fa-check"></i>Galeria ou portfólio</li>
                        <li><i class="fas fa-check"></i>SEO inicial, publicação e testes</li>
                        <li><i class="fas fa-check"></i>Duas rondas de revisões</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web&amp;package=JMbenga+Business"
                        class="btn btn-primary" style="width:100%">Pedir este pacote</a>
                </div>

                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">JMbenga Custom</h3>
                    <div class="price">A partir de 300.000 <span>Kz</span></div>
                    <p>Para projectos que exigem funcionalidades próprias, gestão de dados ou automação.</p>
                    <ul>
                        <li><i class="fas fa-check"></i>Análise de requisitos</li>
                        <li><i class="fas fa-check"></i>Interface adaptada ao negócio</li>
                        <li><i class="fas fa-check"></i>Base de dados, quando necessária</li>
                        <li><i class="fas fa-check"></i>Autenticação e permissões, se contratadas</li>
                        <li><i class="fas fa-check"></i>Testes e documentação básica</li>
                        <li><i class="fas fa-check"></i>Entrega por etapas, conforme o projecto</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web&amp;package=JMbenga+Custom"
                        class="btn btn-outline" style="width:100%">Pedir orçamento</a>
                </div>
            </div>

            <div style="max-width:850px;margin:28px auto 0;color:var(--text-dim);font-size:.9rem;line-height:1.7">
                <p><strong>Referência para loja virtual:</strong> 300.000–650.000 Kz, dependendo do catálogo,
                    pagamentos,
                    gestão de encomendas e integrações necessárias.</p>
                <p><strong>Condições comerciais:</strong> os pacotes Start e Business não incluem automaticamente
                    domínio,
                    alojamento ou serviços pagos externos. Estes custos serão discriminados no orçamento. Como
                    regra-base,
                    o pagamento é de 50% para iniciar e 50% antes da entrega final ou publicação; projectos maiores
                    podem
                    ter pagamentos por etapas.</p>
                <p>O prazo começa após a recepção do sinal, dos conteúdos e dos acessos necessários. O pacote Business
                    inclui duas rondas de revisões dentro do âmbito aprovado; funcionalidades novas ou alterações
                    estruturais podem exigir orçamento adicional.</p>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Dúvidas Frequentes</span>
                <h2 class="section-title">Perguntas <span class="text-gradient">Frequentes</span></h2>
            </div>
            <div class="faq-list reveal">
                <?php
                $faqs = [
                    ['q' => 'Quanto tempo leva para desenvolver um site?', 'a' => 'O prazo depende do âmbito, dos conteúdos e das integrações. Depois de levantar os requisitos, apresento uma estimativa e as etapas previstas.'],
                    ['q' => 'Fornece manutenção após o lançamento?', 'a' => 'Sim! Ofereço planos de manutenção mensal que incluem atualizações de segurança, backups regulares, monitorização e suporte técnico contínuo.'],
                    ['q' => 'Posso gerir o conteúdo do site sozinho?', 'a' => 'Quando a gestão autónoma de conteúdo faz parte dos requisitos, pode ser incluído um CMS ou painel de administração. Essa funcionalidade fica especificada no orçamento.'],
                    ['q' => 'O site será responsivo (mobile‑friendly)?', 'a' => 'Absolutamente. Todos os projectos são desenvolvidos com design responsivo, garantindo uma experiência perfeita em qualquer dispositivo.'],
                    ['q' => 'Faz integração com meios de pagamento em Angola?', 'a' => 'As integrações são avaliadas caso a caso, de acordo com o provedor, a documentação técnica, a disponibilidade no país e os requisitos da conta comercial.'],
                ];
                foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <h4><i class="fas fa-question-circle"
                            style="color:var(--accent);margin-right:8px"></i><?= e($faq['q']) ?>
                    </h4>
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
                Vamos Criar Algo <span class="text-gradient">Incrível</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Pronto para transformar a sua ideia em realidade? Envie os detalhes do projecto para avaliarmos os
                requisitos e os próximos passos.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
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