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

$accent   = trim($cfg('accent_color', '#2563eb'));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
    $accent = '#2563eb';
}

$whatsapp = trim($cfg('whatsapp_url'));
$whatsappUrl = '';
if ($whatsapp !== '' && filter_var($whatsapp, FILTER_VALIDATE_URL)) {
    $parts = parse_url($whatsapp);
    if (is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)) {
        $whatsappUrl = $whatsapp;
    }
}
$whatsappContactUrl = $whatsappUrl !== ''
    ? $whatsappUrl . (str_contains($whatsappUrl, '?') ? '&' : '?') . 'text=' . rawurlencode('Olá José, preciso de informações sobre manutenção do meu site ou aplicação.')
    : '';

$page_title   = 'Manutenção de Sites e Aplicações — JMbenga | Angola';
$page_desc    = 'Planos de manutenção de sites e aplicações em Angola: verificações, suporte técnico, actualizações e backups conforme o âmbito contratado.';
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
    <?php if ($whatsappContactUrl !== ''): ?>
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsappContactUrl) ?>" target="_blank" rel="noopener noreferrer">
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
                    <p class="lead">Com um plano de manutenção adequado, o seu site ou aplicação recebe verificações e
                        melhorias planeadas. Reduza riscos técnicos e concentre-se no que importa: o seu negócio.</p>
                    <div style="display:flex;gap:14px;flex-wrap:wrap">
                        <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" class="btn btn-primary"><i
                                class="fas fa-headset"></i>Solicitar
                            Manutenção</a>
                        <?php if ($whatsappContactUrl !== ''): ?>
                        <a href="<?= e($whatsappContactUrl) ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline"><i class="fab fa-whatsapp"></i>Contactar por WhatsApp</a>
                        <?php endif; ?>
                    </div>
                    <!-- O âmbito da manutenção é definido por projecto e plano. -->
                    <div class="monitor-panel reveal">
                        <div class="monitor-grid">
                            <div class="monitor-item">
                                <div class="val"><i class="fas fa-search"></i></div>
                                <div class="lbl">Verificações acordadas</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val"><i class="fas fa-tools"></i></div>
                                <div class="lbl">Suporte com limite definido</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val"><i class="fas fa-database"></i></div>
                                <div class="lbl">Backups conforme infraestrutura</div>
                            </div>
                            <div class="monitor-item">
                                <div class="val"><i class="fas fa-file-alt"></i></div>
                                <div class="lbl">Registo do trabalho realizado</div>
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
                        <li><i class="fas fa-shield-alt"></i> Redução de riscos através de verificações e actualizações
                            acordadas</li>
                        <li><i class="fas fa-rocket"></i> Oportunidades de melhoria de desempenho</li>
                        <li><i class="fas fa-sync-alt"></i> Actualizações planeadas conforme compatibilidade e âmbito
                        </li>
                        <li><i class="fas fa-save"></i> Verificação de backups quando suportados e contratados</li>
                        <li><i class="fas fa-headset"></i> Apoio técnico dentro dos limites do plano</li>
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
                    ['icon' => 'fa-shield-alt', 'title' => 'Segurança', 'desc' => 'Revisão de configurações de segurança, certificados SSL quando aplicável e actualizações de segurança incluídas no âmbito acordado.'],
                    ['icon' => 'fa-tachometer-alt', 'title' => 'Performance', 'desc' => 'Análise de desempenho e aplicação de melhorias de velocidade adequadas à tecnologia e ao plano contratado.'],
                    ['icon' => 'fa-sync-alt', 'title' => 'Atualizações', 'desc' => 'Actualizações de CMS, plugins, frameworks, bibliotecas e dependências, após avaliação de compatibilidade e dentro do âmbito acordado.'],
                    ['icon' => 'fa-save', 'title' => 'Backups', 'desc' => 'Verificação e planeamento de cópias de segurança, quando a infraestrutura e o acesso técnico o permitem.'],
                    ['icon' => 'fa-eye', 'title' => 'Verificações', 'desc' => 'Verificações de disponibilidade e desempenho de acordo com o plano contratado e as ferramentas disponíveis.'],
                    ['icon' => 'fa-bug', 'title' => 'Correção de Bugs', 'desc' => 'Identificação e correção de erros, problemas de compatibilidade e funcionalidades quebradas.'],
                    ['icon' => 'fa-headset', 'title' => 'Suporte', 'desc' => 'Suporte técnico pelos canais acordados e pequenas alterações dentro das horas incluídas no plano.'],
                    ['icon' => 'fa-search', 'title' => 'SEO Técnico', 'desc' => 'Otimização para motores de busca, sitemaps, robots.txt e performance mobile.'],
                ];
                foreach ($services as $svc): ?>
                <div class="service-card reveal">
                    <i class="fas <?= e($svc['icon']) ?>"></i>
                    <h4><?= e($svc['title']) ?></h4>
                    <p><?= e($svc['desc']) ?></p>
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
                    ['num' => 4, 'title' => 'Verificações periódicas', 'desc' => 'A frequência das verificações e eventuais alertas depende do plano, das ferramentas e da infraestrutura do cliente.'],
                    ['num' => 5, 'title' => 'Registo e recomendações', 'desc' => 'Resumo do trabalho realizado e recomendações, com a periodicidade definida no plano.'],
                    ['num' => 6, 'title' => 'Suporte definido', 'desc' => 'Atendimento dentro das horas incluídas, do horário combinado e das condições indicadas na proposta.'],
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
                            <h5>Avaliação prioritária</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Sujeita à disponibilidade</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-undo"></i>
                            <h5>Revisão de recuperação</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Se houver backup acessível</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-biohazard"></i>
                            <h5>Análise de segurança</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Âmbito após diagnóstico</p>
                        </div>
                        <div class="emergency-feat">
                            <i class="fas fa-shield-virus"></i>
                            <h5>Plano de prevenção</h5>
                            <p style="font-size:.72rem;color:var(--text-muted)">Medidas recomendadas</p>
                        </div>
                    </div>
                </div>
                <div style="text-align:center">
                    <h3 style="font-family:var(--font-head);margin-bottom:16px">Atendimento Prioritário</h3>
                    <p style="margin-bottom:20px">Descreva o problema para avaliarmos a urgência, a viabilidade da
                        recuperação e o orçamento aplicável. O atendimento depende da disponibilidade e das condições
                        acordadas.</p>
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
                <p class="section-sub">Planos mensais de referência com limites de trabalho definidos. O âmbito final
                    depende da tecnologia, dos acessos e da infraestrutura do projecto.</p>
            </div>
            <div class="pricing-grid reveal">
                <!-- JMbenga Care Basic -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">JMbenga Care Basic</h3>
                    <div class="price">15.000 Kz<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Verificação mensal de funcionamento</li>
                        <li><i class="fas fa-check"></i>Até 1 hora de suporte ou pequenas alterações</li>
                        <li><i class="fas fa-check"></i>Verificação de links e formulários</li>
                        <li><i class="fas fa-check"></i>Relatório mensal breve</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App&amp;plan=Care+Basic"
                        class="btn btn-outline" style="width:100%">Pedir este plano</a>
                </div>
                <!-- JMbenga Care Business -->
                <div class="pricing-card featured">
                    <div class="plan-badge">RECOMENDADO</div>
                    <h3 style="font-family:var(--font-head)">JMbenga Care Business</h3>
                    <div class="price">35.000 Kz<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Verificações periódicas</li>
                        <li><i class="fas fa-check"></i>Até 3 horas de trabalho mensal</li>
                        <li><i class="fas fa-check"></i>Actualizações simples de conteúdo</li>
                        <li><i class="fas fa-check"></i>Verificação de backups, quando suportados</li>
                        <li><i class="fas fa-check"></i>Prioridade normal no atendimento</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App&amp;plan=Care+Business"
                        class="btn btn-primary" style="width:100%">Pedir este plano</a>
                </div>
                <!-- JMbenga Care Pro -->
                <div class="pricing-card">
                    <h3 style="font-family:var(--font-head)">JMbenga Care Pro</h3>
                    <div class="price">70.000 Kz<span>/mês</span></div>
                    <ul>
                        <li><i class="fas fa-check"></i>Até 6 horas de trabalho mensal</li>
                        <li><i class="fas fa-check"></i>Monitorização conforme a infraestrutura</li>
                        <li><i class="fas fa-check"></i>Actualizações e testes controlados</li>
                        <li><i class="fas fa-check"></i>Apoio prioritário</li>
                        <li><i class="fas fa-check"></i>Relatório técnico mensal</li>
                    </ul>
                    <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App&amp;plan=Care+Pro"
                        class="btn btn-outline" style="width:100%">Pedir este plano</a>
                </div>
            </div>
            <p style="text-align:center;margin-top:24px;color:var(--text-muted);font-size:.85rem">
                Os preços são referências iniciais. As horas incluídas não transitam automaticamente para o mês
                seguinte.
                Funcionalidades novas, horas adicionais, domínio, alojamento, licenças e serviços de terceiros são
                orçamentados à parte, salvo indicação escrita em contrário.
                O horário, o tempo de resposta, a duração e as condições de cancelamento serão definidos na proposta.
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
                    ['q' => 'Como funciona a duração e o cancelamento?', 'a' => 'A periodicidade de cobrança, o aviso prévio e as condições de cancelamento ficam definidos na proposta ou acordo antes do início do serviço.'],
                    ['q' => 'E se eu precisar de algo fora do plano?', 'a' => 'Funcionalidades novas e trabalho que exceda as horas incluídas são avaliados e orçamentados separadamente. Nenhum extra deve avançar sem aprovação prévia do cliente.'],
                    ['q' => 'Como são feitos os backups?', 'a' => 'A possibilidade, frequência, retenção e local de armazenamento dependem da infraestrutura e dos acessos disponíveis. O serviço exacto de backup será descrito na proposta; não se presume backup automático quando este não existe.'],
                    ['q' => 'Que tecnologias consegue manter?', 'a' => 'A compatibilidade é avaliada caso a caso, com base na tecnologia, na versão, nos acessos e no estado actual do projecto. Após a análise inicial, confirmo o que pode ser incluído no plano.'],
                    ['q' => 'E se o meu site for atacado ou ficar indisponível?', 'a' => 'Primeiro será avaliado o problema, a disponibilidade de backups e o acesso à infraestrutura. Recuperação, limpeza de malware, incidentes críticos ou intervenção de emergência podem exigir orçamento próprio, salvo se estiverem expressamente incluídos no plano.'],
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

    <!-- A secção de testemunhos foi retirada: só devem ser publicados testemunhos reais e autorizados. -->

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
            <p style="font-size:.9rem;color:var(--text-dim);margin-bottom:28px">Antes de apresentar uma proposta, avalio
                a tecnologia, os acessos, as necessidades e os limites do serviço pretendido.</p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?service=Manutenção+de+Site+App" class="btn btn-primary"><i
                        class="fas fa-headset"></i>Começar
                    Agora</a>
                <?php if ($whatsappContactUrl !== ''): ?>
                <a href="<?= e($whatsappContactUrl) ?>" target="_blank" rel="noopener noreferrer"
                    class="btn btn-secondary"><i class="fab fa-whatsapp"></i>Contactar por WhatsApp</a>
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