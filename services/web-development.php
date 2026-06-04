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

$accent = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');
$email = $cfg('email_contact');

$page_title = 'Serviços de Desenvolvimento Web — José Mbenga | Full Stack Developer';
$page_desc  = 'Criação de websites e sistemas web profissionais, performáticos e personalizados. De landing pages a plataformas complexas.';
$page_section = 'services';

?>
<?php
include '../includes/header.php';

?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services.css">

<style>
/* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
:root {
    --accent: <?=e($accent) ?>;
    --accent-dim: #1d4ed8;
    --accent-glow: <?=e($accent) ?>26;
    --accent-rgb: 37, 99, 235;

    --bg: #0a0d14;
    --bg-2: #0f1623;
    --bg-card: #131b2e;
    --bg-glass: rgba(13, 22, 35, .8);
    --border: rgba(255, 255, 255, .06);
    --border-acc: rgba(37, 99, 235, .35);
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
    <?php if ($wa = $cfg('whatsapp_url')): ?>
    <div class=" whatsapp-float" id="whatsappFloat">
        <a href="<?= e($wa) ?>?text=Olá%20José,%20vim%20do%20seu%20portfólio" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include  '../includes/navbar.php'; ?>

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
                        <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-outline"><i
                                class="fab fa-whatsapp"></i>WhatsApp</a>
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
                    <div class="icon-circle"><i class="fas <?= $svc['icon'] ?>"></i></div>
                    <h3><?= $svc['title'] ?></h3>
                    <p><?= $svc['desc'] ?></p>
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
                    <div class="step-number"><?= $step['num'] ?></div>
                    <h4><?= $step['title'] ?></h4>
                    <p><?= $step['desc'] ?></p>
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
                    ['icon' => 'fab fa-css3-alt', 'name' => 'CSS3 / Tailwind'],
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
                    <i class="<?= $tech['icon'] ?>"></i>
                    <h4><?= $tech['name'] ?></h4>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Preços -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Planos <span class="text-gradient">Transparentes</span></h2>
                <p class="section-sub">Sem surpresas. Escolha o plano ideal para o seu projecto ou solicite um orçamento
                    personalizado.</p>
            </div>
            <div class="pricing-grid reveal">
                <!-- Básico -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Site Básico</h3>
                    <div class="price">$1,500<span>/projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Até 5 páginas</li>
                        <li><i class="fas fa-check"></i>Design responsivo</li>
                        <li><i class="fas fa-check"></i>SEO básico</li>
                        <li><i class="fas fa-check"></i>Formulário de contacto</li>
                        <li><i class="fas fa-check"></i>Hospedagem gratuita (1º ano)</li>
                        <li><i class="fas fa-check"></i>Suporte por 30 dias</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
                <!-- Profissional -->
                <div class="pricing-card featured">
                    <div
                        style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:4px 16px;border-radius:999px;font-size:.72rem;font-weight:600">
                        MAIS POPULAR</div>
                    <h3 style="font-family:var(--font-head)">Profissional</h3>
                    <div class="price">$3,500<span>/projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Até 10 páginas</li>
                        <li><i class="fas fa-check"></i>Design premium personalizado</li>
                        <li><i class="fas fa-check"></i>SEO avançado</li>
                        <li><i class="fas fa-check"></i>Sistema de blog / notícias</li>
                        <li><i class="fas fa-check"></i>Painel de administração</li>
                        <li><i class="fas fa-check"></i>Integração com redes sociais</li>
                        <li><i class="fas fa-check"></i>Otimização de performance</li>
                        <li><i class="fas fa-check"></i>Suporte por 60 dias</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-primary" style="width:100%">Começar</a>
                </div>
                <!-- E-commerce -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">E‑commerce</h3>
                    <div class="price">$5,000<span>/projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Loja online completa</li>
                        <li><i class="fas fa-check"></i>Carrinho & checkout</li>
                        <li><i class="fas fa-check"></i>Gestão de produtos</li>
                        <li><i class="fas fa-check"></i>Pagamentos online (multicaixa, referência, etc.)</li>
                        <li><i class="fas fa-check"></i>Painel do cliente</li>
                        <li><i class="fas fa-check"></i>Relatórios de vendas</li>
                        <li><i class="fas fa-check"></i>Integração com redes sociais</li>
                        <li><i class="fas fa-check"></i>Suporte prioritário</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                Os preços podem variar conforme a complexidade do projecto. <a href="<?= BASE_URL ?>/contact"
                    style="color:var(--accent)">Entre em contacto</a> para um orçamento personalizado.
            </p>
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
                    ['q' => 'Quanto tempo leva para desenvolver um site?', 'a' => 'O tempo varia conforme a complexidade. Um site básico leva de 2 a 3 semanas, sites profissionais de 4 a 6 semanas e e‑commerces de 8 a 12 semanas.'],
                    ['q' => 'Fornece manutenção após o lançamento?', 'a' => 'Sim! Ofereço planos de manutenção mensal que incluem atualizações de segurança, backups regulares, monitorização e suporte técnico contínuo.'],
                    ['q' => 'Posso gerir o conteúdo do site sozinho?', 'a' => 'Sim. Todos os sites incluem um CMS (Sistema de Gestão de Conteúdo) intuitivo para que possa atualizar textos, imagens, produtos e outras informações sem conhecimentos técnicos.'],
                    ['q' => 'O site será responsivo (mobile‑friendly)?', 'a' => 'Absolutamente. Todos os projectos são desenvolvidos com design responsivo, garantindo uma experiência perfeita em qualquer dispositivo.'],
                    ['q' => 'Faz integração com meios de pagamento em Angola?', 'a' => 'Sim. Tenho experiência com integrações de pagamentos locais como Multicaixa Express, Referência Multicaixa, e também gateways internacionais como Stripe e PayPal.'],
                ];
                foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <h4><i class="fas fa-question-circle"
                            style="color:var(--accent);margin-right:8px"></i><?= $faq['q'] ?>
                    </h4>
                    <p><?= $faq['a'] ?></p>
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
                Pronto para transformar a sua ideia em realidade? Preencha o formulário e entrarei em contacto em até 24
                horas.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Desenvolvimento+Web" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Solicitar
                    Orçamento</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $inline_js = '';
    $page_scripts = [];
    include '../includes/footer.php';
    include '../includes/bottom-nav.php';
    ?>

</body>

</html>