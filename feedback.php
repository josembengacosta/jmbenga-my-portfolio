<?php
// ══════════════════════════════════════════════════════════════
// feedback.php — JMbenga Portfolio v3.0
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visitor.php';

check_maintenance_mode($pdo);

$cfg = function (string $key, string $default = ''): string {
    global $pdo;
    return get_config($pdo, $key, $default);
};

$accent   = $cfg('accent_color', '#2563eb');
$whatsapp = $cfg('whatsapp_url');

$page_title = 'Feedback — José Mbenga | Full Stack Developer';
$page_desc  = 'Envie o seu feedback, sugestão ou crítica sobre o portfólio de José Mbenga. A sua opinião é importante!';
$page_section = 'feedback';

?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/feedback.css">

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
    <div class="whatsapp-float" id="whatsappFloat">
        <a href="<?= e($wa) ?>?text=Olá%20José,%20vim%20do%20seu%20portfólio" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> <span>Vamos conversar?</span>
        </a>
        <button class="close-whatsapp" onclick="this.parentElement.style.display='none'" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero -->
    <section id="feedback-hero">
        <div class="container feedback-hero-inner">
            <span class="tag">A Sua Opinião</span>
            <h1>Envie o seu <span>Feedback</span></h1>
            <p>A sua opinião é fundamental para melhorar continuamente este portfólio. Sugestões, elogios e críticas são
                sempre bem-vindos!</p>
        </div>
    </section>

    <!-- Formulário -->
    <div class="container" style="max-width:600px;margin:3rem auto 5rem">
        <div class="feedback-card reveal">
            <h2 class="section-title">Formulário de <span class="text-gradient">Feedback</span></h2>
            <form id="feedback-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="text" name="_hp" style="display:none" tabindex="-1" autocomplete="off">

                <div class="form-group">
                    <label class="form-label" for="name_fb">O seu Nome <span
                            style="color:var(--danger)">*</span></label>
                    <input type="text" id="name_fb" name="name_fb" class="form-control" placeholder="Como se chama?"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject_fb">Tipo de Feedback <span
                            style="color:var(--danger)">*</span></label>
                    <select id="subject_fb" name="subject_fb" class="form-control form-select" required>
                        <option value="">Seleccione uma opção…</option>
                        <option value="Sugestão">💡 Sugestão</option>
                        <option value="Elogio">🌟 Elogio</option>
                        <option value="Crítica">📝 Crítica Construtiva</option>
                        <option value="Bug">🐞 Reportar um Problema</option>
                        <option value="Outro">💬 Outro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="message_fb">Mensagem <span
                            style="color:var(--danger)">*</span></label>
                    <textarea id="message_fb" name="message_fb" class="form-control" rows="5"
                        placeholder="Descreva o seu feedback aqui…" required></textarea>
                </div>

                <div id="form-status" class="form-status"></div>

                <button type="submit" class="btn btn-primary" id="submit-btn"
                    style="width:100%;padding:.9rem;font-size:.95rem">
                    <i class="fas fa-paper-plane"></i> Enviar Feedback
                </button>
            </form>
        </div>
    </div>

    <script>
    const form = document.getElementById('feedback-form');
    const status = document.getElementById('form-status');
    const submit = document.getElementById('submit-btn');
    const BASE = document.documentElement.dataset.baseUrl || '';

    form.querySelectorAll('.form-control').forEach(f => {
        f.addEventListener('input', () => f.classList.remove('error'));
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        let ok = true;
        form.querySelectorAll('[required]').forEach(f => {
            if (!f.value.trim()) {
                f.classList.add('error');
                ok = false;
            }
        });
        if (!ok) {
            status.className = 'form-status error';
            status.textContent = 'Por favor, preencha todos os campos obrigatórios.';
            return;
        }

        const honey = form.querySelector('[name="_hp"]');
        if (honey && honey.value) return;

        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar…';
        status.className = 'form-status';

        try {
            const res = await fetch(BASE + '/api/feedback', {
                method: 'POST',
                body: new FormData(form)
            });
            const data = await res.json();

            if (data.success) {
                status.className = 'form-status success';
                status.textContent =
                '✓ Feedback enviado com sucesso! Muito obrigado pela sua contribuição.';
                form.reset();
            } else {
                throw new Error(data.message || 'Erro ao enviar.');
            }
        } catch (err) {
            status.className = 'form-status error';
            status.textContent = '✗ ' + err.message;
        } finally {
            submit.disabled = false;
            submit.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Feedback';
        }
    });
    </script>

    <?php
    $page_scripts = [];
    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>