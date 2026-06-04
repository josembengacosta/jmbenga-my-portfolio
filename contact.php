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

$email      = $cfg('email_contact');
$phone      = $cfg('phone_contact');
$whatsapp   = $cfg('whatsapp_url');
$location   = $cfg('location', 'Luanda, Angola');
$accent     = $cfg('accent_color', '#2563eb');
// ── Capturar parâmetros de URL ──────────────────────────────
$subject_from_url = '';
if (!empty($_GET['subject']))           $subject_from_url = trim($_GET['subject']);
elseif (!empty($_GET['service']))       $subject_from_url = 'Serviço: ' . trim($_GET['service']);
elseif (!empty($_GET['type']))          $subject_from_url = 'Pedido de ' . trim($_GET['type']);
elseif (!empty($_GET['from']))          $subject_from_url = 'Vim da página ' . trim($_GET['from']);

// Lista de assuntos
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

// Determinar seleção baseada na URL
$selected_subject = '';
$free_text_subject = '';
if (!empty($subject_from_url)) {
    foreach ($subject_options as $label => $value) {
        if ($value === '__other__') continue;
        if (stripos($subject_from_url, $value) !== false) {
            $selected_subject = $value;
            break;
        }
    }
    if ($selected_subject === '') {
        $selected_subject = '__other__';
        $free_text_subject = $subject_from_url;
    }
}

// Fallback: se nada foi definido, usar o primeiro item da lista (não "Outro")
if ($selected_subject === '' && $free_text_subject === '') {
    foreach ($subject_options as $val) {
        if ($val !== '__other__') {
            $selected_subject = $val;
            break;
        }
    }
}

$page_title = 'Contacto — José Mbenga | Full Stack Developer';
$page_desc  = 'Entre em contacto com José Mbenga para discutir o seu próximo projecto. Resposta em até 24 horas.';
$page_section = 'contact';

?>
<?php


include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/contact.css">

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
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero da página -->
    <section id="contact-hero">
        <div class="container contact-hero-inner">
            <span class="tag">Contacto</span>
            <h1>Vamos <span>Conversar</span></h1>
            <p>Tem um projecto em mente? Gostaria de trocar ideias ou simplesmente dizer olá? Preencha o formulário ou
                utilize um dos canais abaixo. Responderei em até <strong style="color:var(--accent)">24 horas</strong>.
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
                            <?php if ($cfg('github_url')): ?>
                            <a href="<?= e($cfg('github_url')) ?>" target="_blank" class="social-link-item"
                                aria-label="GitHub"><i class="fab fa-github"></i></a>
                            <?php endif; ?>
                            <?php if ($cfg('linkedin_url')): ?>
                            <a href="<?= e($cfg('linkedin_url')) ?>" target="_blank" class="social-link-item"
                                aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <?php endif; ?>
                            <?php if ($whatsapp): ?>
                            <a href="<?= e($whatsapp) ?>" target="_blank" class="social-link-item"
                                aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulário -->
                <div class="reveal" style="transition-delay:.15s">
                    <h2 class="section-title" style="margin-top:14px;margin-bottom:24px">Envie uma <span
                            class="text-gradient">Mensagem</span></h2>
                    <form id="contact-form" class="contact-form" novalidate>

                        <input type="hidden" name="_hp" style="display:none" tabindex="-1" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="_hp" style="display:none" tabindex="-1" autocomplete="off">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="name" class="form-control" placeholder="José Silva" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" placeholder="jose@exemplo.com"
                                    required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+244 9xx xxx xxx">
                        </div>

                        <!-- Assunto (select + campo livre para "Outro") -->
                        <div class="form-group">
                            <label class="form-label">Assunto *</label>
                            <select id="subject-select" class="form-select">
                                <?php foreach ($subject_options as $label => $value): ?>
                                <option value="<?= e($value) ?>" <?= $selected_subject === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="custom-subject-wrap"
                            <?= $selected_subject === '__other__' ? 'style="display:block"' : '' ?>>
                            <label class="form-label">Especifique o assunto</label>
                            <input type="text" id="custom-subject" class="form-control"
                                placeholder="Descreva o assunto em poucas palavras..."
                                value="<?= e($free_text_subject) ?>">
                        </div>
                        <!-- Campo hidden que será enviado com o assunto final -->
                        <input type="hidden" name="subject" id="subject-hidden"
                            value="<?= e($selected_subject === '__other__' ? $free_text_subject : $selected_subject) ?>">

                        <div class="form-group">
                            <label class="form-label">Mensagem *</label>
                            <textarea name="message" class="form-control" rows="5"
                                placeholder="Descreva o seu projecto ou ideia..." required></textarea>
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
            <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-primary"
                style="font-size:1.1rem;padding:16px 36px">
                <i class="fab fa-whatsapp"></i> Iniciar Conversa no WhatsApp
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
$page_scripts = ['assets/js/contact-form.js'];
$inline_js = <<<JS
// ── Lógica do select de assunto ────────────────────────────
(function() {
  const select = document.getElementById('subject-select');
  const customWrap = document.getElementById('custom-subject-wrap');
  const customInput = document.getElementById('custom-subject');
  const hiddenSubject = document.getElementById('subject-hidden');

  function updateSubject() {
    const val = select.value;
    if (val === '__other__') {
      customWrap.classList.add('show');
      customInput.setAttribute('required', 'required');
      hiddenSubject.value = customInput.value;
    } else {
      customWrap.classList.remove('show');
      customInput.removeAttribute('required');
      hiddenSubject.value = val;
    }
  }

  select.addEventListener('change', updateSubject);
  customInput.addEventListener('input', function() {
    if (select.value === '__other__') {
      hiddenSubject.value = this.value;
    }
  });

  // Inicializar (caso já venha pré‑selecionado "Outro")
  updateSubject();
})();
JS;

    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>