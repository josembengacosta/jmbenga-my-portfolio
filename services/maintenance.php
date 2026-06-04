<?php
// ══════════════════════════════════════════════════════════════
// maintenance.php — JMbenga Portfolio v3.0
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

$page_title   = 'Manutenção de Sites e Apps — José Mbenga | Full Stack Developer';
$page_desc    = 'Manutenção preventiva e corretiva para sites e aplicativos. Suporte técnico, atualizações, segurança e backup. Especialista em Manutenção Angola.';
$page_section = 'services';
?>
<?php

include '../includes/header.php';
?>


<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/services/maintenance.css">

<style>
    /* ════════════════════════════════════════════════════════════
   TOKENS — Dark (padrão) e Light 
════════════════════════════════════════════════════════════ */
    :root {
        --accent: <?= e($accent) ?>;
        --accent-dim: #1d4ed8;
        --accent-glow: <?= e($accent) ?>26;
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
        background: radial-gradient(circle, <?= e($accent) ?>22 0%, transparent 65%);
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
                    <span class="tag">Serviço Contínuo</span>
                    <h1>Manutenção de Sites & Apps <span>Preventiva</span></h1>
                    <p class="lead">Mantenha seu site ou aplicativo sempre seguro, rápido e atualizado. Evite problemas
                        e
                        foque no que realmente importa: o seu negócio.</p>
                    <div style="display:flex;gap:14px;flex-wrap:wrap">
                        <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" class="btn btn-primary"><i
                                class="fas fa-headset"></i>Solicitar
                            Manutenção</a>
                        <?php if ($whatsapp): ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-outline"><i
                                    class="fab fa-whatsapp"></i>Suporte Emergencial</a>
                        <?php endif; ?>
                    </div>
                    <!-- Painel de Monitoramento Mock -->
                    <div class="monitor-panel reveal">
                        <div class="monitor-grid">
                            <div class="monitor-item">
                                <div class="val" style="color:#10b981"><span class="status-dot"></span>99.9%</div>
                                <div class="lbl">Uptime</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val">0.8s</div>
                                <div class="lbl">Tempo de Carregamento</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val">0</div>
                                <div class="lbl">Vulnerabilidades</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val"><i class="fas fa-check-circle" style="color:#10b981"></i></div>
                                <div class="lbl">Backup Diário OK</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="service-hero-visual reveal" style="transition-delay:.15s">
                    <div class="service-icon-big"><i class="fas fa-tools"></i></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Riscos vs Benefícios -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Por que Manutenção?</span>
                <h2 class="section-title">Seu Site Precisa de Cuidados <span class="text-gradient">Constantes</span>
                </h2>
                <p class="section-sub">Como qualquer ativo do seu negócio, o seu site ou app exige manutenção regular
                    para
                    continuar a gerar valor.</p>
            </div>
            <div class="risk-grid reveal">
                <!-- Riscos -->
                <div class="risk-box danger">
                    <h3><i class="fas fa-exclamation-triangle"></i> Riscos Sem Manutenção</h3>
                    <ul>
                        <li><i class="fas fa-bug"></i> Hackers exploram vulnerabilidades antigas</li>
                        <li><i class="fas fa-snail"></i> Site fica lento e perde posição no Google</li>
                        <li><i class="fas fa-exclamation-circle"></i> Funcionalidades param de funcionar</li>
                        <li><i class="fas fa-database-crash"></i> Perda de dados por falta de backup</li>
                        <li><i class="fas fa-frown"></i> Clientes insatisfeitos com experiência ruim</li>
                        <li><i class="fas fa-money-bill-slash"></i> Prejuízo financeiro com downtime</li>
                    </ul>
                </div>
                <!-- Benefícios -->
                <div class="risk-box success">
                    <h3><i class="fas fa-check-circle"></i> Benefícios Com Manutenção</h3>
                    <ul>
                        <li><i class="fas fa-shield-alt"></i> Site sempre seguro contra ataques</li>
                        <li><i class="fas fa-rocket"></i> Performance otimizada continuamente</li>
                        <li><i class="fas fa-sync-alt"></i> Funcionalidades sempre atualizadas</li>
                        <li><i class="fas fa-save"></i> Backups regulares garantem seus dados</li>
                        <li><i class="fas fa-headset"></i> Suporte técnico rápido quando precisar</li>
                        <li><i class="fas fa-chart-line"></i> Foco no seu negócio, não em problemas técnicos</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Serviços Incluídos -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">O Que Está Incluído</span>
                <h2 class="section-title">Serviços Completos de <span class="text-gradient">Manutenção</span></h2>
                <p class="section-sub">Tudo o que o seu projecto digital precisa para se manter saudável e performático.
                </p>
            </div>
            <div class="services-grid reveal">
                <?php
                $services = [
                    ['icon' => 'fa-shield-alt', 'title' => 'Segurança', 'desc' => 'Firewall, SSL, proteção contra hackers, scans de vulnerabilidade e atualizações de segurança.'],
                    ['icon' => 'fa-tachometer-alt', 'title' => 'Performance', 'desc' => 'Otimização de velocidade, cache, compressão de imagens e monitoramento de performance.'],
                    ['icon' => 'fa-sync-alt', 'title' => 'Atualizações', 'desc' => 'WordPress, plugins, frameworks, bibliotecas e dependências sempre atualizadas.'],
                    ['icon' => 'fa-save', 'title' => 'Backup', 'desc' => 'Backups diários automáticos, armazenamento seguro e plano de recuperação de desastres.'],
                    ['icon' => 'fa-eye', 'title' => 'Monitoramento', 'desc' => 'Uptime 24/7, alertas de downtime, métricas de performance e relatórios mensais.'],
                    ['icon' => 'fa-bug', 'title' => 'Correção de Bugs', 'desc' => 'Identificação e correção de erros, problemas de compatibilidade e funcionalidades quebradas.'],
                    ['icon' => 'fa-headset', 'title' => 'Suporte', 'desc' => 'Suporte técnico via email, WhatsApp, pequenas alterações e consultoria.'],
                    ['icon' => 'fa-search', 'title' => 'SEO Técnico', 'desc' => 'Otimização para motores de busca, sitemaps, robots.txt e performance mobile.'],
                ];
                foreach ($services as $svc): ?>
                    <div class="service-card reveal">
                        <i class="fas <?= $svc['icon'] ?>"></i>
                        <h4><?= $svc['title'] ?></h4>
                        <p><?= $svc['desc'] ?></p>
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
                <h2 class="section-title">Como Funciona a <span class="text-gradient">Manutenção</span></h2>
                <p class="section-sub">Processo transparente e organizado para a sua tranquilidade.</p>
            </div>
            <div class="process-steps reveal">
                <?php
                $steps = [
                    ['num' => 1, 'title' => 'Análise Inicial', 'desc' => 'Avaliação completa do seu site/app: segurança, performance, backups e estado geral. Relatório detalhado.'],
                    ['num' => 2, 'title' => 'Plano de Ação', 'desc' => 'Definição de prioridades, correções críticas, otimizações e cronograma de manutenção.'],
                    ['num' => 3, 'title' => 'Implementação', 'desc' => 'Execução das melhorias, atualizações e configuração dos sistemas de monitoramento.'],
                    ['num' => 4, 'title' => 'Monitoramento 24/7', 'desc' => 'Sistema de alertas ativo, backups automáticos e verificações regulares de saúde do sistema.'],
                    ['num' => 5, 'title' => 'Relatórios Mensais', 'desc' => 'Relatórios detalhados com métricas, ações realizadas e recomendações. Transparência total.'],
                    ['num' => 6, 'title' => 'Suporte Contínuo', 'desc' => 'Atendimento rápido para dúvidas, pequenas alterações e emergências. Canal direto de comunicação.'],
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

    <!-- Emergência -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Urgente</span>
                <h2 class="section-title">Suporte de <span class="text-gradient">Emergência</span></h2>
                <p class="section-sub">Seu site caiu? Foi hackeado? Algo parou de funcionar? Não entre em pânico!</p>
            </div>
            <div class="emergency-box reveal">
                <div>
                    <div class="emergency-features">
                        <div class="emergency-feat">
                            <i class="fas fa-clock"></i>
                            <h5>Resposta em até 2h</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Horário comercial</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-undo"></i>
                            <h5>Restauração de Backup</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Recuperação rápida</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-biohazard"></i>
                            <h5>Limpeza de Malware</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Remoção completa</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-shield-virus"></i>
                            <h5>Proteção Futura</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Prevenção de ataques</p>
                        </div>
                    </div>
                </div>
                <div style="text-align:center">
                    <h3 style="font-family:var(--font-head);margin-bottom:16px">Atendimento Prioritário</h3>
                    <p style="margin-bottom:20px">Preencha o formulário para atendimento de emergência prioritário.</p>
                    <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" class="btn btn-primary"><i
                            class="fas fa-exclamation-triangle"></i>Solicitar Suporte de Emergência</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Planos -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Investimento</span>
                <h2 class="section-title">Planos de <span class="text-gradient">Manutenção</span></h2>
                <p class="section-sub">Escolha o plano ideal para as necessidades do seu projecto. Todos incluem
                    correção de
                    bugs críticos.</p>
            </div>
            <div class="pricing-grid reveal">
                <!-- Básico -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Básico</h3>
                    <div class="price">$99<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Monitoramento de uptime</li>
                        <li><i class="fas fa-check"></i>Backup semanal</li>
                        <li><i class="fas fa-check"></i>Atualizações de segurança</li>
                        <li><i class="fas fa-check"></i>Suporte por email</li>
                        <li><i class="fas fa-check"></i>Relatório mensal</li>
                        <li><i class="fas fa-check"></i>Otimização de performance</li>
                        <li><i class="fas fa-check"></i>Suporte WhatsApp</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
                <!-- Profissional -->
                <div class="pricing-card featured">
                    <div class="plan-badge">MAIS POPULAR</div>
                    <h3 style="font-family:var(--font-head)">Profissional</h3>
                    <div class="price">$199<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Monitoramento 24/7</li>
                        <li><i class="fas fa-check"></i>Backup diário</li>
                        <li><i class="fas fa-check"></i>Atualizações automáticas</li>
                        <li><i class="fas fa-check"></i>Suporte WhatsApp</li>
                        <li><i class="fas fa-check"></i>Otimização de performance</li>
                        <li><i class="fas fa-check"></i>Scan de segurança semanal</li>
                        <li><i class="fas fa-check"></i>SEO técnico</li>
                        <li><i class="fas fa-check"></i>Suporte emergencial</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-primary" style="width:100%">Começar</a>
                </div>
                <!-- Enterprise -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">Enterprise</h3>
                    <div class="price">$399<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Tudo do Profissional</li>
                        <li><i class="fas fa-check"></i>Backup em tempo real</li>
                        <li><i class="fas fa-check"></i>SEO técnico mensal</li>
                        <li><i class="fas fa-check"></i>Suporte emergencial 24/7</li>
                        <li><i class="fas fa-check"></i>Firewall avançado</li>
                        <li><i class="fas fa-check"></i>Relatórios detalhados</li>
                        <li><i class="fas fa-check"></i>Consultoria mensal</li>
                        <li><i class="fas fa-check"></i>SLA 2 horas resposta</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact" class="btn btn-outline" style="width:100%">Começar</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                * Primeiro mês com <strong style="color:var(--accent)">30% de desconto</strong> para novos clientes.
                Horas
                extras a $40/h para demandas não incluídas. <a
                    href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" style="color:var(--accent)">Sem
                    fidelidade, cancele a qualquer momento.</a>
            </p>
        </div>
    </section>

    <!-- FAQ Manutenção -->
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Dúvidas</span>
                <h2 class="section-title">Perguntas <span class="text-gradient">Frequentes</span></h2>
            </div>
            <div class="faq-list reveal">
                <?php
                $faqs = [
                    ['q' => 'Posso cancelar a qualquer momento?', 'a' => 'Sim, todos os planos são mensais sem fidelidade. Basta avisar com 7 dias de antecedência. O acesso aos backups realizados durante o período contratado é mantido por 30 dias após o cancelamento.'],
                    ['q' => 'E se eu precisar de algo fora do plano?', 'a' => 'Trabalho com horas extras a $40/hora para demandas não incluídas no plano. Sempre consulto antes de realizar qualquer trabalho extra e forneço orçamento prévio.'],
                    ['q' => 'Como são feitos os backups?', 'a' => 'Os backups incluem banco de dados, arquivos, emails e configurações. São armazenados em servidores seguros com criptografia. Mantemos versões dos últimos 30 dias no plano Profissional e 90 dias no Enterprise.'],
                    ['q' => 'Você trabalha com qual tipo de site?', 'a' => 'Trabalho com WordPress, HTML/CSS/JS estático, React, Vue, Node.js, Laravel, e-commerce (Magento, WooCommerce), e aplicativos mobile. Se o seu site usa uma tecnologia diferente, entre em contacto para avaliar.'],
                    ['q' => 'E se meu site for hackeado?', 'a' => 'No plano Profissional e Enterprise, a limpeza de malware está incluída. Restauramos a versão limpa do backup mais recente, removemos todo código malicioso e fortalecemos a segurança para prevenir futuros ataques.'],
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

    <!-- Clientes que Confiam -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="tag">Clientes</span>
                <h2 class="section-title">Empresas que <span class="text-gradient">Já Confiam</span></h2>
                <p class="section-sub">Histórias reais de quem passou a dormir tranquilo após contratar a manutenção.
                </p>
            </div>
            <div class="testi-grid reveal">
                <?php
                $testimonials = [
                    ['quote' => 'Meu e-commerce foi hackeado e o José recuperou tudo em menos de 4 horas. Desde então, contrato a manutenção mensal e nunca mais tive problemas. A paz de espírito não tem preço.', 'author' => 'Ana Costa', 'role' => 'Loja Moda Angolana'],
                    ['quote' => 'Tinha constantes problemas com meu site institucional lento e com erros. Desde que contrato a manutenção, o site carrega 3x mais rápido e nunca mais tive downtime. O ROI é evidente.', 'author' => 'Miguel Pereira', 'role' => 'Advocacia & Associados'],
                    ['quote' => 'Como startup, não podíamos ter um desenvolvedor em tempo integral. A manutenção do José é a solução perfeita: custo-benefício excelente e qualidade profissional. Nosso app nunca esteve tão estável.', 'author' => 'Teresa Santos', 'role' => 'CEO, TechStartup AO'],
                ];
                foreach ($testimonials as $t): ?>
                    <div class="testi-card">
                        <div class="quote">"</div>
                        <p class="body"><?= $t['quote'] ?></p>
                        <div class="author"><?= $t['author'] ?></div>
                        <div class="role"><?= $t['role'] ?></div>
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
                Comece a <span class="text-gradient">Dormir Tranquilo</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Deixe a parte técnica comigo e foque no que realmente importa: crescer o seu negócio.
            </p>
            <div
                style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:500px;margin:0 auto 32px;text-align:center">
                <div><i class="fas fa-search" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">Análise Gratuita</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Do seu site atual</div>
                </div>
                <div><i class="fas fa-tag" style="font-size:1.4rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-size:.8rem;color:var(--text-dim)">30% Desconto 1º Mês</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Novos clientes</div>
                </div>
            </div>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" class="btn btn-primary"><i
                        class="fas fa-headset"></i>Começar
                    Agora</a>
                <?php if ($whatsapp): ?>
                    <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                            class="fab fa-whatsapp"></i>Suporte de Emergência</a>
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