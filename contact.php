<?php
// ══════════════════════════════════════════════════════════════
// contact.php — JMbenga Portfolio v3.0
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visitor.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$cfg = function (string $key, string $default = ''): string {
    global $pdo;
    return get_config($pdo, $key, $default);
};

$email    = $cfg('email_contact');
$phone    = $cfg('phone_contact');
$location = $cfg('location', 'Luanda, Angola');

// Validar valores usados directamente em CSS ou como destinos de links.
$accent = $cfg('accent_color', '#2563eb');
if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent)) {
    $accent = '#2563eb';
}

$safe_http_url = static function (?string $url): string {
    $url = trim((string)$url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
};
$whatsapp = $safe_http_url($cfg('whatsapp_url'));
$whatsapp_message_url = $whatsapp !== ''
    ? $whatsapp . (str_contains($whatsapp, '?') ? '&' : '?') . 'text=' . rawurlencode('Olá José, vim do seu portfólio')
    : '';
$github_url = $safe_http_url($cfg('github_url'));
$linkedin_url = $safe_http_url($cfg('linkedin_url'));

// Ler apenas parâmetros escalares e limitar o tamanho para evitar avisos e entradas excessivas.
$query_value = static function (string $key): string {
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 120, 'UTF-8');
    }
    return substr($value, 0, 120);
};

// Valores comerciais permitidos. O pacote só se aplica a Desenvolvimento Web.
$package_options = [
    'JMbenga Start'    => 'JMbenga Start',
    'JMbenga Business' => 'JMbenga Business',
    'JMbenga Custom'   => 'JMbenga Custom',
];

// Normalizar acentos, barras, &, hífenes e prefixos antigos antes de comparar.
$normalize_subject = static function (string $value): string {
    $value = trim($value);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    $value = preg_replace('/^(?:servico(?: de)?|pedido de|contacto via pagina|vim da pagina)\s+/', '', $value) ?? $value;
    return trim($value);
};

$raw_subject  = $query_value('subject');
$raw_service  = $query_value('service');
$raw_type     = $query_value('type');
$raw_from     = $query_value('from');
$raw_package  = $query_value('package');

$subject_options = [
    'Desenvolvimento Web'        => 'Desenvolvimento Web',
    'Aplicativo Mobile'          => 'Aplicativo Mobile',
    'UI/UX Design'               => 'UI/UX Design',
    'APIs & Backend'             => 'APIs & Backend',
    'Manutenção de Site / App'   => 'Manutenção de Site / App',
    'Consultoria Técnica'        => 'Consultoria Técnica',
    'Orçamento de Projecto'      => 'Orçamento de Projecto',
    'Parceria / Colaboração'     => 'Parceria / Colaboração',
    'Outro (especificar abaixo)' => '__other__',
];

$subject_aliases = [
    'Desenvolvimento Web' => ['desenvolvimento web', 'web development', 'website', 'web site'],
    'Aplicativo Mobile' => ['aplicativo mobile', 'aplicativos mobile', 'app mobile', 'apps mobile', 'aplicacao mobile', 'aplicacoes moveis'],
    'UI/UX Design' => ['ui ux design', 'ui ux', 'design ui ux', 'design de interface'],
    'APIs & Backend' => ['apis backend', 'api backend', 'apis e backend', 'api e backend', 'api backend development'],
    'Manutenção de Site / App' => ['manutencao de site app', 'manutencao de sites e apps', 'manutencao de site', 'manutencao de sites', 'manutencao de app', 'manutencao'],
    'Consultoria Técnica' => ['consultoria tecnica', 'consultoria'],
    'Orçamento de Projecto' => ['orcamento', 'orcamento de projecto', 'orcamento de projeto', 'pedido de orcamento'],
    'Parceria / Colaboração' => ['parceria', 'colaboracao', 'parceria colaboracao'],
];

// Reconhecer o pacote apenas por uma correspondência exacta com a lista aprovada.
$matched_package = '';
$package_candidate = $raw_package;
$legacy_package_in_service = false;
if ($package_candidate === '' && $raw_service !== '') {
    $package_candidate = $raw_service; // Compatibilidade com links antigos ?service=JMbenga+Business
    $legacy_package_in_service = true;
}
foreach ($package_options as $package_value) {
    if ($package_candidate !== '' && $normalize_subject($package_candidate) === $normalize_subject($package_value)) {
        $matched_package = $package_value;
        break;
    }
}

// Determinar o serviço. Os links antigos que passavam o pacote como serviço
// são convertidos para Desenvolvimento Web + pacote, em vez de caírem em "Outro".
if ($raw_subject !== '') {
    $subject_from_url = $raw_subject;
} elseif ($legacy_package_in_service && $matched_package !== '') {
    $subject_from_url = 'Desenvolvimento Web';
} elseif ($raw_service !== '') {
    $subject_from_url = $raw_service;
} elseif ($raw_type !== '') {
    $subject_from_url = 'Pedido de ' . $raw_type;
} elseif ($raw_from !== '') {
    $subject_from_url = 'Contacto via página: ' . $raw_from;
} elseif ($raw_package !== '' && $matched_package !== '') {
    // Se só vier o pacote, inferir a categoria à qual pertence.
    $subject_from_url = 'Desenvolvimento Web';
} else {
    $subject_from_url = '';
}

$selected_subject = '';
$free_text_subject = '';
if ($subject_from_url !== '') {
    $normalized_request = $normalize_subject($subject_from_url);
    foreach ($subject_aliases as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            $normalized_alias = $normalize_subject($alias);
            if ($normalized_request === $normalized_alias) {
                $selected_subject = $canonical;
                break 2;
            }
        }
    }

    // Se não houver correspondência conhecida, preservar o pedido em "Outro".
    if ($selected_subject === '') {
        $selected_subject = '__other__';
        $free_text_subject = $subject_from_url;
    }
}

// Não associar um pacote Web a outro serviço. O pacote só é retido se a
// categoria seleccionada for Desenvolvimento Web e o valor constar da lista aprovada.
$selected_package = ($selected_subject === 'Desenvolvimento Web') ? $matched_package : '';
$message_prefill = '';
if ($selected_package !== '') {
    $message_prefill = 'Olá, tenho interesse no pacote ' . $selected_package
        . ' de Desenvolvimento Web. Gostaria de solicitar um orçamento.';
}

$page_title = 'Contacto — José Mbenga | Full Stack Developer';
$page_desc  = 'Entre em contacto com José Mbenga para discutir o seu próximo projecto, solicitar um orçamento ou esclarecer dúvidas sobre os serviços.';
$page_section = 'contact';

?>
<?php


include 'includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/contact.css">

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
    <?php if ($whatsapp !== ''): ?>
    <div class=" whatsapp-float" id="whatsappFloat">
        <a href="<?= e($whatsapp_message_url) ?>" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero da página -->
    <section id="contact-hero">
        <div class="container contact-hero-inner">
            <span class="tag">Contacto</span>
            <h1>Vamos <span>Conversar</span></h1>
            <p>Tem um projecto em mente? Gostaria de trocar ideias ou simplesmente dizer olá? Preencha o formulário ou
                utilize um dos canais abaixo. Entrarei em contacto assim que possível.
            </p>
        </div>
    </section>

    <!-- Conteúdo principal -->
    <section class="section">
        <div class="container">
            <div class="contact-grid">
                <!-- Informações -->
                <div class="reveal">
                    <div class="contact-info">
                        <h2 class="section-title" style="margin-top:14px">Informações de <span
                                class="text-gradient">Contacto</span></h2>
                        <p class="section-sub" style="margin-bottom:0">Estou disponível para novos projectos, parcerias
                            e
                            consultoria.</p>

                        <div class="contact-links">
                            <?php if ($email): ?>
                            <a href="mailto:<?= e($email) ?>" class="contact-link-item">
                                <div class="contact-link-icon"><i class="fas fa-envelope"></i></div>
                                <span><?= e($email) ?></span>
                            </a>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="contact-link-item">
                                <div class="contact-link-icon"><i class="fas fa-phone"></i></div>
                                <span><?= e($phone) ?></span>
                            </a>
                            <?php endif; ?>
                            <div class="contact-link-item">
                                <div class="contact-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <span><?= e($location) ?></span>
                            </div>
                            <?php if ($whatsapp): ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="contact-link-item">
                                <div class="contact-link-icon"><i class="fab fa-whatsapp"></i></div>
                                <span>Conversar no WhatsApp</span>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Redes sociais -->
                        <div class="social-links">
                            <?php if ($github_url !== ''): ?>
                            <a href="<?= e($github_url) ?>" target="_blank" rel="noopener noreferrer"
                                class="social-link-item" aria-label="GitHub"><i class="fab fa-github"></i></a>
                            <?php endif; ?>
                            <?php if ($linkedin_url !== ''): ?>
                            <a href="<?= e($linkedin_url) ?>" target="_blank" rel="noopener noreferrer"
                                class="social-link-item" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <?php endif; ?>
                            <?php if ($whatsapp): ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
                                class="social-link-item" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulário -->
                <div class="reveal" style="transition-delay:.15s">
                    <h2 class="section-title" style="margin-top:14px;margin-bottom:24px">Envie uma <span
                            class="text-gradient">Mensagem</span></h2>
                    <form id="contact-form" class="contact-form" novalidate>

                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="text" name="_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true"
                            style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="contact-name">Nome *</label>
                                <input id="contact-name" type="text" name="name" class="form-control"
                                    placeholder="José Silva" autocomplete="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact-email">Email *</label>
                                <input id="contact-email" type="email" name="email" class="form-control"
                                    placeholder="jose@exemplo.com" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact-phone">Telefone</label>
                            <input id="contact-phone" type="tel" name="phone" class="form-control"
                                placeholder="+244 9xx xxx xxx" autocomplete="tel">
                        </div>

                        <!-- Assunto (select + campo livre para "Outro") -->
                        <div class="form-group">
                            <label class="form-label" for="subject-select">Assunto *</label>
                            <select id="subject-select" class="form-select" required>
                                <option value="" <?= $selected_subject === '' ? 'selected' : '' ?> disabled>Seleccione
                                    um assunto</option>
                                <?php foreach ($subject_options as $label => $value): ?>
                                <option value="<?= e($value) ?>" <?= $selected_subject === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="custom-subject-wrap"
                            style="display:<?= $selected_subject === '__other__' ? 'block' : 'none' ?>">
                            <label class="form-label" for="custom-subject">Especifique o assunto</label>
                            <input type="text" id="custom-subject" class="form-control"
                                placeholder="Descreva o assunto em poucas palavras..."
                                value="<?= e($free_text_subject) ?>" maxlength="120">
                        </div>
                        <!-- Campo hidden que será enviado com o assunto final -->
                        <input type="hidden" name="subject" id="subject-hidden"
                            value="<?= e($selected_subject === '__other__' ? $free_text_subject : $selected_subject) ?>">

                        <div class="form-group" id="package-wrap"
                            style="display:<?= $selected_subject === 'Desenvolvimento Web' ? 'block' : 'none' ?>">
                            <label class="form-label" for="package-select">Pacote pretendido (opcional)</label>
                            <select id="package-select" name="package" class="form-select">
                                <option value="" <?= $selected_package === '' ? 'selected' : '' ?>>Ainda não escolhi um
                                    pacote</option>
                                <?php foreach ($package_options as $package_label => $package_value): ?>
                                <option value="<?= e($package_value) ?>"
                                    <?= $selected_package === $package_value ? 'selected' : '' ?>>
                                    <?= e($package_label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-help">Pode escolher um pacote ou pedir uma proposta
                                personalizada.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-message">Mensagem *</label>
                            <textarea id="contact-message" name="message" class="form-control" rows="5"
                                data-package-autofilled="<?= $message_prefill !== '' ? 'true' : 'false' ?>"
                                placeholder="Descreva o seu projecto ou ideia..."
                                required><?= e($message_prefill) ?></textarea>
                        </div>

                        <div id="form-status" class="form-status"></div>

                        <button type="submit" class="btn btn-primary" id="form-submit" style="align-self:flex-start">
                            <i class="fas fa-paper-plane"></i>
                            <span>Enviar Mensagem</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA adicional -->
    <div class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:80px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Prefere uma <span class="text-gradient">Conversa Directa?</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Estou disponível no WhatsApp para uma conversa rápida e sem compromisso.
            </p>
            <?php if ($whatsapp): ?>
            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary"
                style="font-size:1.1rem;padding:16px 36px">
                <i class="fab fa-whatsapp"></i> Iniciar Conversa no WhatsApp
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
$page_scripts = ['assets/js/contact-form.js'];
$inline_js = ''; // A sincronização do formulário é gerida por assets/js/contact-form.js.

    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>