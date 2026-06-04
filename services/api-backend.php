<?php
// ══════════════════════════════════════════════════════════════
// api-backend.php — JMbenga Portfolio v3.0
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

$accent   = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');
$email    = $cfg('email_contact');

$page_title   = 'APIs & Backend — José Mbenga | Full Stack Developer';
$page_desc    = 'Desenvolvimento de APIs REST, GraphQL e sistemas backend escaláveis com PHP, Node.js e MySQL. Arquitetura sólida para o seu produto digital.';
$page_section = 'services';

?>
<?php

include '../includes/header.php';
?>


<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/api.css">

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
                    <h1>APIs & Backend <span>de Alta Performance</span></h1>
                    <p class="lead">Construo APIs robustas, seguras e escaláveis que alimentam apps, sites e sistemas
                        empresariais. Arquitetura pensada para crescer com o seu negócio.</p>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-outline"><i
                                class="fab fa-whatsapp"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stats-row">
                        <div class="hero-stat-mini">
                            <div class="num">99.9%</div>
                            <div class="lbl">Uptime</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">&lt;200ms</div>
                            <div class="lbl">Latência Média</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">10k+</div>
                            <div class="lbl">Req/s Suportados</div>
                        </div>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-server"></i></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Serviços de Backend -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Serviços</span>
                <h2 class="section-title">Soluções Backend <span class="text-gradient">Completas</span></h2>
                <p class="section-sub">Do design da API à infraestrutura em produção, entrego sistemas backend prontos
                    para
                    escalar.</p>
            </div>
            <div class="services-grid reveal">
                <?php
                $services = [
                    ['icon' => 'fa-plug', 'title' => 'APIs REST & GraphQL', 'desc' => 'APIs modernas e bem documentadas que conectam o seu frontend ao mundo. Paginação, filtros, autenticação, rate limiting.', 'tags' => ['REST', 'GraphQL', 'JWT', 'OAuth2']],
                    ['icon' => 'fa-microchip', 'title' => 'Microserviços', 'desc' => 'Arquitetura distribuída para sistemas complexos que exigem escalabilidade e resiliência independentes.', 'tags' => ['Docker', 'Kubernetes', 'Message Queues']],
                    ['icon' => 'fa-database', 'title' => 'Bases de Dados', 'desc' => 'Modelagem, otimização e administração de bases de dados relacionais e NoSQL para performance máxima.', 'tags' => ['MySQL', 'PostgreSQL', 'MongoDB', 'Redis']],
                    ['icon' => 'fa-shield-alt', 'title' => 'Segurança & Autenticação', 'desc' => 'Implementação de sistemas de login seguros, OAuth2, JWT, encriptação de dados e proteção contra ataques comuns.', 'tags' => ['OAuth2', 'JWT', 'HTTPS', 'OWASP']],
                    ['icon' => 'fa-cloud-upload-alt', 'title' => 'Cloud & DevOps', 'desc' => 'Deploy automatizado, CI/CD, monitorização e gestão de servidores em cloud (AWS, DigitalOcean, VPS).', 'tags' => ['AWS', 'CI/CD', 'Nginx', 'Docker']],
                    ['icon' => 'fa-sync-alt', 'title' => 'Integrações & Webhooks', 'desc' => 'Conexão com serviços externos: gateways de pagamento, SMS, email, redes sociais, ERPs.', 'tags' => ['Stripe', 'Twilio', 'WhatsApp', 'REST']],
                ];
                foreach ($services as $svc): ?>
                <div class="service-card reveal">
                    <div class="icon-circle"><i class="fas <?= $svc['icon'] ?>"></i></div>
                    <h3><?= $svc['title'] ?></h3>
                    <p><?= $svc['desc'] ?></p>
                    <div class="tags">
                        <?php foreach ($svc['tags'] as $t): ?>
                        <span class="tag-sm"><?= $t ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Arquitetura Típica -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Arquitetura</span>
                <h2 class="section-title">Como Estruturo <span class="text-gradient">Sistemas Backend</span></h2>
                <p class="section-sub">Padrões de arquitetura testados que garantem performance, segurança e
                    manutenibilidade.</p>
            </div>
            <div class="arch-grid reveal">
                <?php
                $archs = [
                    ['icon' => 'fa-layer-group', 'title' => 'MVC / Hexagonal', 'desc' => 'Separação clara de responsabilidades. Código organizado em camadas que facilitam testes e manutenção.'],
                    ['icon' => 'fa-key', 'title' => 'Autenticação Stateless', 'desc' => 'JWT para autenticação sem estado. Escalável e compatível com múltiplos serviços.'],
                    ['icon' => 'fa-exchange-alt', 'title' => 'API Gateway', 'desc' => 'Ponto único de entrada para APIs. Rate limiting, caching, logs centralizados.'],
                    ['icon' => 'fa-bell', 'title' => 'Event-Driven', 'desc' => 'Sistemas reativos com filas e eventos para processamento assíncrono e desacoplamento.'],
                ];
                foreach ($archs as $arch): ?>
                <div class="arch-card">
                    <i class="fas <?= $arch['icon'] ?>"></i>
                    <h4><?= $arch['title'] ?></h4>
                    <p><?= $arch['desc'] ?></p>
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
                <h2 class="section-title">Meu <span class="text-gradient">Processo de Desenvolvimento</span></h2>
                <p class="section-sub">Um fluxo estruturado que garante entregas previsíveis e de qualidade.</p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Análise & Requisitos', 'desc' => 'Definição de endpoints, modelos de dados, fluxos de autenticação e regras de negócio.'],
                    ['num' => 2, 'title' => 'Design da API', 'desc' => 'Contrato da API (OpenAPI/Swagger), modelos de request/response, códigos de erro.'],
                    ['num' => 3, 'title' => 'Desenvolvimento', 'desc' => 'Codificação seguindo boas práticas: SOLID, DRY, testes unitários e de integração.'],
                    ['num' => 4, 'title' => 'Testes & QA', 'desc' => 'Testes de carga, segurança, penetração e validação de todos os endpoints.'],
                    ['num' => 5, 'title' => 'Deploy & CI/CD', 'desc' => 'Pipeline automatizado para deploy contínuo com rollback seguro e zero downtime.'],
                    ['num' => 6, 'title' => 'Documentação', 'desc' => 'Documentação completa e interativa para desenvolvedores que consumirão a API.'],
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

    <!-- Stack Tecnológico -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Stack</span>
                <h2 class="section-title">Tecnologias <span class="text-gradient">Backend</span></h2>
                <p class="section-sub">As ferramentas certas para cada desafio, sempre escolhidas com critério técnico.
                </p>
            </div>
            <div class="tech-grid reveal">
                <?php
                $techs = [
                    ['icon' => 'fab fa-php', 'name' => 'PHP 8.2', 'sub' => 'Laravel / Slim'],
                    ['icon' => 'fab fa-node-js', 'name' => 'Node.js', 'sub' => 'Express / Fastify'],
                    ['icon' => 'fas fa-database', 'name' => 'MySQL', 'sub' => 'Relacional'],
                    ['icon' => 'fas fa-leaf', 'name' => 'MongoDB', 'sub' => 'NoSQL'],
                    ['icon' => 'fas fa-memory', 'name' => 'Redis', 'sub' => 'Cache / Queue'],
                    ['icon' => 'fab fa-docker', 'name' => 'Docker', 'sub' => 'Containers'],
                    ['icon' => 'fas fa-code-branch', 'name' => 'Git', 'sub' => 'Version Control'],
                    ['icon' => 'fas fa-cloud', 'name' => 'AWS / VPS', 'sub' => 'Cloud Hosting'],
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

    <!-- Garantias -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Garantias</span>
                <h2 class="section-title">Por Que <span class="text-gradient">Trabalhar Comigo</span></h2>
                <p class="section-sub">Compromisso com qualidade, segurança e performance em cada linha de código.</p>
            </div>
            <div class="guarantees-grid reveal">
                <?php
                $guarantees = [
                    ['icon' => 'fa-tachometer-alt', 'title' => 'Performance Otimizada', 'desc' => 'Queries, cache e arquitetura pensados para latência mínima.'],
                    ['icon' => 'fa-shield-alt', 'title' => 'Segurança por Padrão', 'desc' => 'OWASP, encriptação e proteção contra ataques comuns.'],
                    ['icon' => 'fa-book', 'title' => 'Documentação Completa', 'desc' => 'API docs interativas com exemplos reais de uso.'],
                    ['icon' => 'fa-rocket', 'title' => 'Escalabilidade', 'desc' => 'Arquitetura pronta para crescimento sem reescrita.'],
                ];
                foreach ($guarantees as $g): ?>
                <div class="guarantee-card">
                    <i class="fas <?= $g['icon'] ?>"></i>
                    <h4><?= $g['title'] ?></h4>
                    <p><?= $g['desc'] ?></p>
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
                <p class="section-sub">Escolha o plano ideal ou solicite um orçamento personalizado para o seu projecto.
                </p>
            </div>
            <div class="pricing-grid reveal">
                <!-- API Simples -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">API Simples</h3>
                    <div class="price">$1,200<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Até 5 endpoints</li>
                        <li><i class="fas fa-check"></i>Autenticação JWT</li>
                        <li><i class="fas fa-check"></i>CRUD básico</li>
                        <li><i class="fas fa-check"></i>Documentação Swagger</li>
                        <li><i class="fas fa-check"></i>Deploy em VPS</li>
                        <li class="unavailable"><i class="fas fa-times"></i>CI/CD pipeline</li>
                        <li class="unavailable"><i class="fas fa-times"></i>Cache avançado</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-outline"
                        style="width:100%">Começar</a>
                </div>
                <!-- API Profissional -->
                <div class="pricing-card featured">
                    <div
                        style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:4px 16px;border-radius:999px;font-size:.72rem;font-weight:600">
                        MAIS POPULAR</div>
                    <h3 style="font-family:var(--font-head)">API Profissional</h3>
                    <div class="price">$3,500<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Até 20 endpoints</li>
                        <li><i class="fas fa-check"></i>Autenticação avançada</li>
                        <li><i class="fas fa-check"></i>Cache Redis</li>
                        <li><i class="fas fa-check"></i>Rate limiting</li>
                        <li><i class="fas fa-check"></i>CI/CD pipeline</li>
                        <li><i class="fas fa-check"></i>Testes automatizados</li>
                        <li><i class="fas fa-check"></i>Logging & monitoring</li>
                        <li class="unavailable"><i class="fas fa-times"></i>Microserviços</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-primary"
                        style="width:100%">Começar</a>
                </div>
                <!-- Backend Completo -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Backend Completo</h3>
                    <div class="price">$7,000<span>+ /projecto</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Endpoints ilimitados</li>
                        <li><i class="fas fa-check"></i>Microserviços ou monolito</li>
                        <li><i class="fas fa-check"></i>Cache em vários níveis</li>
                        <li><i class="fas fa-check"></i>Filas e jobs</li>
                        <li><i class="fas fa-check"></i>Admin dashboard</li>
                        <li><i class="fas fa-check"></i>CI/CD completo</li>
                        <li><i class="fas fa-check"></i>Load balancing</li>
                        <li><i class="fas fa-check"></i>Suporte 3 meses</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-outline"
                        style="width:100%">Começar</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                * Preços de referência. <a href="<?= BASE_URL ?>/contact" style="color:var(--accent)">Entre em
                    contacto</a>
                para um orçamento detalhado baseado nos seus requisitos específicos.
            </p>
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
                    ['q' => 'REST ou GraphQL — qual escolher?', 'a' => 'Depende do caso de uso. REST é mais simples, com caching nativo HTTP e ampla compatibilidade. GraphQL é ideal quando precisa de consultas flexíveis para múltiplos clientes (web, mobile) com diferentes necessidades de dados. Posso ajudar a decidir.'],
                    ['q' => 'Você trabalha com Laravel?', 'a' => 'Sim. Tenho experiência sólida com PHP/Laravel para APIs robustas. Também trabalho com Node.js (Express/Fastify) quando a stack exige JavaScript no backend.'],
                    ['q' => 'A API será segura?', 'a' => 'Segurança é prioridade. Implemento OAuth2/JWT para autenticação, proteção CSRF, rate limiting, validação de entrada, headers de segurança, e sigo as recomendações OWASP. Testes de penetração podem ser incluídos.'],
                    ['q' => 'Você também faz deploy e manutenção?', 'a' => 'Sim, entrego a solução completa: desenvolvimento, deploy em VPS ou cloud (AWS, DigitalOcean), configuração de Nginx/Apache, CI/CD e monitorização. Ofereço planos de manutenção contínua.'],
                    ['q' => 'Preciso de um servidor dedicado?', 'a' => 'Nem sempre. APIs pequenas rodam muito bem em VPS de $10-20/mês. Para sistemas maiores, posso recomendar a melhor infraestrutura. Ajuda-o a escolher o plano ideal para o seu orçamento.'],
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
                Vamos Construir o <span class="text-gradient">Backend do Seu Produto</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Tem um projecto ambicioso? Uma ideia que precisa de uma base sólida? Estou pronto para ajudar. Vamos
                conversar.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-primary"><i
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