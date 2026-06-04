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

$accent = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');
$email = $cfg('email_contact');

$page_title = 'Aplicativos Mobile — José Mbenga | Full Stack Developer';
$page_desc  = 'Desenvolvimento de aplicativos móveis nativos e híbridos para iOS e Android. Performance, design premium e experiência de usuário excepcional.';
$page_section = 'services';


?>
<?php

include '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/mobile-apps.css">

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
    <?php include '../includes/navbar.php'; ?>


    <!-- Hero -->
    <section id="service-hero">
        <div class="container">
            <div class="service-hero-inner">
                <div class="service-hero-text reveal">
                    <span class="tag">Serviço Premium</span>
                    <h1>Aplicativos Mobile <span>Profissionais</span></h1>
                    <p class="lead">Desenvolvo apps nativos e híbridos para iOS e Android que entregam performance
                        excepcional e experiência de usuário premium.</p>
                    <div class="hero-stats-row">
                        <div class="hero-stat-mini">
                            <div class="num">95%</div>
                            <div class="lbl">App Performance</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">4.8/5</div>
                            <div class="lbl">UX Score</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=Aplicativos+Mobile" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-outline"><i
                                class="fab fa-whatsapp"></i>WhatsApp</a>
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

    <!-- Tipos de Aplicativos -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Tipos de Aplicativos</span>
                <h2 class="section-title">Desenvolvo Diferentes Tipos de Apps Conforme a Sua <span
                        class="text-gradient">Necessidade</span></h2>
            </div>
            <div class="app-types-grid">
                <?php
                $app_types = [
                    ['icon' => 'fa-shopping-cart', 'title' => 'E-commerce Apps', 'desc' => 'Lojas mobile com carrinho, checkout, pagamentos e notificações push para uma experiência de compra fluida.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-building', 'title' => 'Apps Empresariais', 'desc' => 'Sistemas internos, CRMs, dashboards e apps de produtividade para empresas que precisam mobilidade.', 'platforms' => ['React Native']],
                    ['icon' => 'fa-heartbeat', 'title' => 'Health & Fitness', 'desc' => 'Apps de treino, nutrição, monitoramento de saúde e integração com wearables.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-graduation-cap', 'title' => 'Educacionais', 'desc' => 'Plataformas de ensino, cursos online, quiz apps e sistemas de aprendizado interativos.', 'platforms' => ['Flutter']],
                    ['icon' => 'fa-play-circle', 'title' => 'Entretenimento', 'desc' => 'Apps de streaming, redes sociais, jogos casuais e plataformas de conteúdo envolventes.', 'platforms' => ['iOS', 'Android']],
                    ['icon' => 'fa-tools', 'title' => 'Utilitários', 'desc' => 'Ferramentas, calculadoras, organizadores, apps financeiros e utilitários do dia a dia.', 'platforms' => ['Cross-platform']],
                ];
                foreach ($app_types as $type): ?>
                <div class="app-type-card reveal">
                    <div class="icon-circle"><i class="fas <?= $type['icon'] ?>"></i></div>
                    <h3><?= $type['title'] ?></h3>
                    <p><?= $type['desc'] ?></p>
                    <div class="platform-badges">
                        <?php foreach ($type['platforms'] as $p): ?>
                        <span class="platform-badge"><?= $p ?></span>
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
                    <h4><?= $app['title'] ?></h4>
                    <div class="tech-name"><?= $app['tech'] ?></div>
                    <p><?= $app['desc'] ?></p>
                    <ul>
                        <?php foreach ($app['advantages'] as $adv): ?>
                        <li><?= $adv ?></li>
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
                <h2 class="section-title">Meu <span class="text-gradient">Processo</span></h2>
                <p class="section-sub">Metodologia ágil para apps de sucesso, com entregas iterativas e feedback
                    contínuo.
                </p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Discovery & Planning', 'desc' => 'Análise de mercado, definição de funcionalidades, arquitetura técnica e planejamento do MVP.'],
                    ['num' => 2, 'title' => 'UI/UX Design Mobile', 'desc' => 'Design de interface seguindo guidelines iOS/Android, prototipação e testes de usabilidade.'],
                    ['num' => 3, 'title' => 'Development Sprint', 'desc' => 'Desenvolvimento ágil com sprints de 2 semanas, code reviews e integração contínua.'],
                    ['num' => 4, 'title' => 'Testing & QA', 'desc' => 'Testes em dispositivos reais, correção de bugs, testes de performance e segurança.'],
                    ['num' => 5, 'title' => 'App Store Submission', 'desc' => 'Preparação para publicação nas lojas, acompanhamento e otimização ASO.'],
                    ['num' => 6, 'title' => 'Maintenance & Updates', 'desc' => 'Manutenção contínua, atualizações para novas versões de iOS/Android e novas funcionalidades.'],
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
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Stack Tecnológico</span>
                <h2 class="section-title">Tecnologias <span class="text-gradient">Modernas e Robustas</span></h2>
            </div>
            <div class="tech-grid reveal">
                <?php
                $techs = [
                    ['icon' => 'fab fa-react', 'name' => 'React Native', 'sub' => 'Cross-platform'],
                    ['icon' => 'fas fa-layer-group', 'name' => 'Flutter', 'sub' => 'Google Framework'],
                    ['icon' => 'fab fa-apple', 'name' => 'Swift', 'sub' => 'iOS Native'],
                    ['icon' => 'fab fa-android', 'name' => 'Kotlin', 'sub' => 'Android Native'],
                    ['icon' => 'fas fa-database', 'name' => 'Firebase', 'sub' => 'Backend & Auth'],
                    ['icon' => 'fas fa-leaf', 'name' => 'MongoDB', 'sub' => 'Database'],
                    ['icon' => 'fas fa-cloud', 'name' => 'AWS', 'sub' => 'Cloud Services'],
                    ['icon' => 'fab fa-git-alt', 'name' => 'Git', 'sub' => 'Version Control'],
                ];
                foreach ($techs as $tech): ?>
                <div class="tech-card reveal">
                    <i class="<?= $tech['icon'] ?>"></i>
                    <h4><?= $tech['name'] ?></h4>
                    <small><?= $tech['sub'] ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Preços -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Preços <span class="text-gradient">Transparentes</span></h2>
                <p class="section-sub">Sem surpresas. Escolha o plano ideal ou solicite um orçamento personalizado.</p>
            </div>
            <div class="pricing-grid reveal">
                <!-- Básico -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">App Básico</h3>
                    <div class="price">$3,500<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>1 plataforma (iOS ou Android)</li>
                        <li><i class="fas fa-check"></i>Até 10 telas</li>
                        <li><i class="fas fa-check"></i>Design responsivo</li>
                        <li><i class="fas fa-check"></i>Integração com APIs</li>
                        <li><i class="fas fa-check"></i>Publicação na App Store</li>
                        <li><i class="fas fa-check"></i>Push notifications</li>
                        <li><i class="fas fa-check"></i>Offline mode</li>
                        <li><i class="fas fa-check"></i>Suporte por 30 dias</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
                <!-- Profissional -->
                <div class="pricing-card featured">
                    <div
                        style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:4px 16px;border-radius:999px;font-size:.72rem;font-weight:600">
                        MAIS POPULAR</div>
                    <h3 style="font-family:var(--font-head)">App Profissional</h3>
                    <div class="price">$7,000<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>iOS + Android (cross-platform)</li>
                        <li><i class="fas fa-check"></i>Até 25 telas</li>
                        <li><i class="fas fa-check"></i>Design premium</li>
                        <li><i class="fas fa-check"></i>Push notifications</li>
                        <li><i class="fas fa-check"></i>Integração com APIs externas</li>
                        <li><i class="fas fa-check"></i>Offline capabilities</li>
                        <li><i class="fas fa-check"></i>Backend personalizado</li>
                        <li><i class="fas fa-check"></i>Analytics avançado</li>
                        <li><i class="fas fa-check"></i>Suporte por 60 dias</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-primary" style="width:100%">Começar</a>
                </div>
                <!-- Empresarial -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">App Empresarial</h3>
                    <div class="price">$12,000<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>iOS + Android + PWA</li>
                        <li><i class="fas fa-check"></i>Telas ilimitadas</li>
                        <li><i class="fas fa-check"></i>UI/UX personalizado</li>
                        <li><i class="fas fa-check"></i>Backend completo</li>
                        <li><i class="fas fa-check"></i>Admin dashboard</li>
                        <li><i class="fas fa-check"></i>Analytics & Reports</li>
                        <li><i class="fas fa-check"></i>Integrações complexas</li>
                        <li><i class="fas fa-check"></i>Suporte prioritário 6 meses</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                * Inclui publicação nas lojas de aplicativos. Preços são estimativas. <a href="<?= BASE_URL ?>/contact"
                    style="color:var(--accent)">Contacte-me</a> para um orçamento preciso baseado nas suas necessidades
                específicas.
            </p>
        </div>
    </section>

    <!-- Apps Desenvolvidos -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Portfólio Mobile</span>
                <h2 class="section-title">Apps <span class="text-gradient">Desenvolvidos</span></h2>
                <p class="section-sub">Veja alguns dos aplicativos mobile que já criei.</p>
            </div>
            <div class="portfolio-grid reveal">
                <?php
                $apps = [
                    ['icon' => 'fa-heartbeat', 'title' => 'FitTrack Pro', 'desc' => 'App de fitness com tracking de exercícios, dieta e progresso.'],
                    ['icon' => 'fa-cloud-sun', 'title' => 'WeatherNow', 'desc' => 'App de previsão do tempo com alertas e radar em tempo real.'],
                    ['icon' => 'fa-shopping-bag', 'title' => 'ShopEasy Mobile', 'desc' => 'App de e-commerce com carrinho, checkout e recomendações.'],
                ];
                foreach ($apps as $app): ?>
                <div class="portfolio-card">
                    <div class="app-icon"><i class="fas <?= $app['icon'] ?>"></i></div>
                    <h4><?= $app['title'] ?></h4>
                    <p><?= $app['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
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
                    ['q' => 'Quanto tempo leva para desenvolver um aplicativo?', 'a' => 'O tempo varia conforme a complexidade. Um app básico leva 6-8 semanas, apps médios 10-14 semanas, e apps complexos 16-20 semanas. Trabalho com sprints de 2 semanas e entrego um MVP funcional rapidamente.'],
                    ['q' => 'Qual é melhor: nativo ou cross-platform?', 'a' => 'Depende do projecto. Apps nativos têm performance máxima e acesso completo às APIs, mas custam mais. Cross-platform (React Native/Flutter) tem custo menor e desenvolvimento mais rápido. Analiso cada caso para recomendar a melhor abordagem.'],
                    ['q' => 'Você cuida da publicação nas lojas?', 'a' => 'Sim, incluo a publicação na Apple App Store e Google Play Store em todos os pacotes. Também ajudo com ASO (App Store Optimization) para melhorar a visibilidade do seu app.'],
                    ['q' => 'Oferece manutenção pós-lançamento?', 'a' => 'Sim, ofereço pacotes de manutenção mensal que incluem correção de bugs, atualizações para novas versões do iOS/Android, suporte técnico e implementação de novas funcionalidades.'],
                    ['q' => 'Preciso ter conta de desenvolvedor?', 'a' => 'Para iOS, é necessário ter uma conta Apple Developer ($99/ano). Para Android, a conta Google Play Developer tem taxa única de $25. Posso ajudar no processo de criação dessas contas.'],
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
                Vamos Criar o Seu <span class="text-gradient">App</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Tem uma ideia de aplicativo? Vamos conversar sobre como transformá-la em realidade.
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