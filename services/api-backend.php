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

$accent = $cfg('accent_color', '#2563eb');
if (!is_string($accent) || !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent)) {
    $accent = '#2563eb';
}

$whatsapp = trim($cfg('whatsapp_url'));
$whatsapp_parts = $whatsapp !== '' ? parse_url($whatsapp) : false;
if (!is_array($whatsapp_parts) || strtolower($whatsapp_parts['scheme'] ?? '') !== 'https' || empty($whatsapp_parts['host'])) {
    $whatsapp = '';
}
$wa = $whatsapp; // Compatibilidade com o bloco flutuante desta página.

$page_title   = 'APIs e Backend — José Mbenga | JMbenga';
$page_desc    = 'Desenvolvimento e integração de APIs, funcionalidades backend, bases de dados e sistemas personalizados. Orçamento em kwanzas conforme os requisitos.';
$meta_desc    = $page_desc; // Compatibilidade com cabeçalhos que utilizem esta variável.
$page_section = 'services';

include __DIR__ . '/../includes/header.php';
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

@media (max-width: 768px) {
    .service-hero-text h1 {
        font-size: 1.6rem !important;
        line-height: 1.3 !important;
        letter-spacing: normal !important;
        margin-bottom: 12px !important;
    }
}
</style>

</head>

<body>

    <!-- WhatsApp Float -->
    <?php if ($wa): ?>
    <div class=" whatsapp-float" id="whatsappFloat">
        <a href="<?= e($wa . (str_contains($wa, '?') ? '&' : '?') . 'text=' . rawurlencode('Olá José, vim do seu portfólio')) ?>"
            target="_blank" rel="noopener noreferrer">
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
                    <h1>APIs & Backend <span>de Alta Performance</span></h1>
                    <p class="lead">Construo APIs robustas, seguras e escaláveis que alimentam apps, sites e sistemas
                        empresariais. Arquitetura pensada para crescer com o seu negócio.</p>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:28px">
                        <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-primary"><i
                                class="fas fa-paper-plane"></i>Solicitar Orçamento</a>
                        <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline"><i class="fab fa-whatsapp"></i>WhatsApp</a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stats-row" aria-label="Princípios do serviço">
                        <div class="hero-stat-mini">
                            <div class="num">APIs</div>
                            <div class="lbl">Integrações planeadas</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">Dados</div>
                            <div class="lbl">Modelação adequada</div>
                        </div>
                        <div class="hero-stat-mini">
                            <div class="num">QA</div>
                            <div class="lbl">Testes no escopo acordado</div>
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
                    <div class="icon-circle"><i class="fas <?= e($svc['icon']) ?>"></i></div>
                    <h3><?= e($svc['title']) ?></h3>
                    <p><?= e($svc['desc']) ?></p>
                    <div class="tags">
                        <?php foreach ($svc['tags'] as $t): ?>
                        <span class="tag-sm"><?= e($t) ?></span>
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
                    <i class="fas <?= e($arch['icon']) ?>"></i>
                    <h4><?= e($arch['title']) ?></h4>
                    <p><?= e($arch['desc']) ?></p>
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
                    <div class="step-number"><?= (int)$step['num'] ?></div>
                    <h4><?= e($step['title']) ?></h4>
                    <p><?= e($step['desc']) ?></p>
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
                    <i class="<?= e($tech['icon']) ?>"></i>
                    <h4><?= e($tech['name']) ?></h4>
                    <small><?= e($tech['sub']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Garantias -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Critérios de trabalho</span>
                <h2 class="section-title">Princípios do <span class="text-gradient">Desenvolvimento</span></h2>
                <p class="section-sub">Os critérios técnicos são definidos conforme o escopo, os riscos e a
                    infraestrutura de cada projecto.</p>
            </div>
            <div class="guarantees-grid reveal">
                <?php
                $guarantees = [
                    ['icon' => 'fa-tachometer-alt', 'title' => 'Performance Otimizada', 'desc' => 'Consultas, cache e estrutura técnica ajustados às necessidades reais da solução.'],
                    ['icon' => 'fa-shield-alt', 'title' => 'Segurança por Padrão', 'desc' => 'Validação de dados e medidas de segurança adequadas ao risco e ao escopo contratado.'],
                    ['icon' => 'fa-book', 'title' => 'Documentação Completa', 'desc' => 'Documentação e exemplos de utilização definidos no orçamento.'],
                    ['icon' => 'fa-rocket', 'title' => 'Escalabilidade', 'desc' => 'Decisões de arquitectura que consideram o crescimento esperado e os limites do projecto.'],
                ];
                foreach ($guarantees as $g): ?>
                <div class="guarantee-card">
                    <i class="fas <?= e($g['icon']) ?>"></i>
                    <h4><?= e($g['title']) ?></h4>
                    <p><?= e($g['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Preços de referência em Kz -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Referências de <span class="text-gradient">Preço em Kz</span></h2>
                <p class="section-sub">O valor final depende dos requisitos, da complexidade técnica e dos serviços
                    externos necessários.</p>
            </div>
            <div class="pricing-grid reveal">
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Integração com API externa</h3>
                    <div class="price">30.000–100.000 <span>Kz</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Integração conforme a API do fornecedor</li>
                        <li><i class="fas fa-check"></i>Tratamento de respostas e erros no escopo acordado</li>
                        <li><i class="fas fa-check"></i>Testes de funcionamento acordados</li>
                        <li><i class="fas fa-info-circle"></i>Autenticação complexa ou múltiplos serviços podem alterar
                            o preço</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-outline"
                        style="width:100%">Pedir orçamento</a>
                </div>
                <div class="pricing-card featured">
                    <h3 style="font-family:var(--font-head)">Sistema CRUD com base de dados</h3>
                    <div class="price">300.000–550.000 <span>Kz</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Criação, consulta, actualização e eliminação de registos</li>
                        <li><i class="fas fa-check"></i>Modelo de dados de acordo com os requisitos</li>
                        <li><i class="fas fa-check"></i>Validação e tratamento de erros no escopo definido</li>
                        <li><i class="fas fa-info-circle"></i>Permissões, relatórios e integrações podem aumentar o
                            custo</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-primary"
                        style="width:100%">Pedir orçamento</a>
                </div>
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Aplicação web complexa</h3>
                    <div class="price">A partir de 700.000 <span>Kz</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Análise técnica inicial</li>
                        <li><i class="fas fa-check"></i>Arquitectura definida segundo os requisitos</li>
                        <li><i class="fas fa-check"></i>Implementação faseada quando apropriado</li>
                        <li><i class="fas fa-info-circle"></i>Preço final sob análise do projecto</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=APIs+Backend" class="btn btn-outline"
                        style="width:100%">Pedir orçamento</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                Como referência adicional, uma funcionalidade extra num sistema começa em 25.000 Kz.
                Domínio, alojamento, licenças, APIs pagas e outros serviços externos são discriminados à parte,
                salvo indicação expressa na proposta. O preço definitivo é confirmado por escrito após a análise do
                escopo.
                <a href="<?= BASE_URL ?>/contact" style="color:var(--accent)">Contactar para orçamento</a>.
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
                    ['q' => 'REST ou GraphQL — qual escolher?', 'a' => 'Depende dos consumidores da API, dos dados e dos requisitos de consulta. A escolha será recomendada durante a análise e descrita no escopo aprovado.'],
                    ['q' => 'Quais tecnologias podem ser usadas?', 'a' => 'A escolha depende dos requisitos e da infraestrutura existente. PHP, Node.js e bases de dados relacionais podem ser avaliados durante a análise técnica; a tecnologia final é indicada na proposta.'],
                    ['q' => 'Como é tratada a segurança?', 'a' => 'As medidas dependem do tipo de dados e dos riscos da aplicação. O orçamento deve indicar as medidas incluídas, os testes previstos e quaisquer auditorias especializadas contratadas à parte.'],
                    ['q' => 'O deploy e a manutenção estão incluídos?', 'a' => 'Dependem do escopo contratado. A proposta deve especificar publicação, configuração do servidor, monitorização e eventual manutenção recorrente; custos de infraestrutura e serviços externos são separados salvo indicação expressa.'],
                    ['q' => 'Preciso de um servidor dedicado?', 'a' => 'Nem sempre. A infraestrutura adequada depende da carga esperada, dos dados e dos requisitos de disponibilidade. Os custos do servidor são avaliados separadamente e apresentados em kwanzas com base no fornecedor escolhido.'],
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