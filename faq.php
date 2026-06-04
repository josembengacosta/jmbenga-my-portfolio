<?php
// ══════════════════════════════════════════════════════════════
// faq.php — JMbenga Portfolio v3.0 (com secção de Feedback)
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

// ── Buscar todas as FAQs visíveis da BD ──────────────────────
$faqs_raw = $pdo->query("
    SELECT * FROM _faq
    WHERE status_faq = 'visible'
    ORDER BY category_faq, display_order ASC
")->fetchAll();

// Agrupar por categoria
$faqs = [];
foreach ($faqs_raw as $f) {
    $cat = $f['category_faq'] ?: 'Geral';
    $faqs[$cat][] = $f;
}

// Categorias únicas para os filtros
$categories = array_keys($faqs);
$current_cat = $_GET['cat'] ?? ($categories[0] ?? '');
if (!in_array($current_cat, $categories)) {
    $current_cat = $categories[0] ?? '';
}

$page_title = 'FAQ — José Mbenga | Full Stack Developer';
$page_desc  = 'Perguntas frequentes sobre os serviços de desenvolvimento web, mobile, UI/UX design e consultoria de José Mbenga.';
$page_section = 'faq';

?>
<?php include 'includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/faq.css">

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

/* ═══ SECÇÃO DE FEEDBACK (NOVO) ═══ */
.feedback-section {
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.feedback-section::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: radial-gradient(ellipse 60% 60% at 50% 50%, var(--accent-glow) 0%, transparent 70%);
}

.feedback-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    max-width: 550px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
}

.feedback-card .section-title {
    text-align: center;
    margin-bottom: 1.5rem;
}

.feedback-card .form-group {
    margin-bottom: 1.2rem;
}

.feedback-card .form-label {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-dim);
    margin-bottom: .4rem;
}

.feedback-card .form-control {
    width: 100%;
    padding: .75rem 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: var(--font-body);
    font-size: .9rem;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}

.feedback-card .form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.feedback-card .form-control::placeholder {
    color: var(--text-muted);
}

.feedback-card textarea.form-control {
    resize: vertical;
    min-height: 110px;
}

.feedback-card .form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%238899b4' d='M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    padding-right: 40px;
    cursor: pointer;
}

.feedback-card .btn-submit {
    width: 100%;
    padding: .85rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-family: var(--font-body);
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    box-shadow: 0 0 20px var(--accent-glow);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
}

.feedback-card .btn-submit:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 8px 30px var(--accent-glow);
}

.feedback-card .btn-submit:disabled {
    opacity: .6;
    cursor: not-allowed;
    transform: none;
}

.feedback-card .form-status {
    padding: .75rem 1rem;
    border-radius: var(--radius);
    font-size: .85rem;
    margin-top: 1rem;
    display: none;
    text-align: center;
}

.feedback-card .form-status.success {
    background: rgba(16, 185, 129, .1);
    border: 1px solid rgba(16, 185, 129, .3);
    color: #34d399;
    display: block;
    margin-bottom: 1rem;
}

.feedback-card .form-status.error {
    background: rgba(239, 68, 68, .1);
    border: 1px solid rgba(239, 68, 68, .3);
    color: #f87171;
    display: block;
    margin-bottom: 1rem;
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
    <section id="faq-hero">
        <div class="container faq-hero-inner">
            <span class="tag">Dúvidas Frequentes</span>
            <h1>Perguntas <span>Frequentes</span></h1>
            <p>Respostas às dúvidas mais comuns sobre os meus serviços, processos e formas de trabalho.</p>
        </div>
    </section>

    <!-- Filtros por categoria -->
    <div class="container">
        <div class="filters-bar reveal">
            <?php foreach ($categories as $cat): ?>
            <a href="?cat=<?= urlencode($cat) ?>" class="filter-btn <?= $current_cat === $cat ? 'active' : '' ?>">
                <?= e($cat) ?> <span class="filter-count">(<?= count($faqs[$cat]) ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Grelha de FAQs -->
    <section class="container">
        <?php if (!empty($faqs[$current_cat])): ?>
        <div class="faq-grid">
            <?php foreach ($faqs[$current_cat] as $i => $f): ?>
            <div class="faq-card reveal" style="transition-delay:<?= min($i * 0.05, 0.5) ?>s">
                <div class="faq-card-header">
                    <div class="faq-icon-wrap">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3 class="faq-question"><?= e($f['question']) ?></h3>
                    <button class="faq-toggle" onclick="toggleFaq(this)" aria-label="Expandir resposta">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        <?= nl2br(e($f['answer'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="text-align:center;padding:80px 20px;color:var(--text-muted)">
            <i class="fas fa-question-circle"
                style="font-size:3rem;margin-bottom:16px;display:block;color:var(--accent);opacity:.35"></i>
            <h3 style="font-family:var(--font-head);margin-bottom:8px;color:var(--text-dim)">Nenhuma FAQ nesta categoria
            </h3>
            <p>As perguntas frequentes estão a ser actualizadas. Volte em breve!</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- CTA Final -->
    <div class="cta-section"
        style="background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:100px 0;text-align:center;position:relative;overflow:hidden">
        <div class="cta-inner reveal" style="position:relative;z-index:1;max-width:640px;margin:0 auto">
            <h2 class="cta-title"
                style="font-family:var(--font-head);font-size:clamp(2rem,4vw,3.2rem);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px">
                Ainda tem <span class="text-gradient">Dúvidas?</span>
            </h2>
            <p class="cta-sub" style="font-size:1rem;color:var(--text-dim);margin-bottom:32px;line-height:1.7">
                Não encontrou a resposta que procurava? Entre em contacto directamente comigo.
            </p>
            <div class="cta-actions" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/contact?subject=Dúvida sobre serviços" class="btn btn-primary"><i
                        class="fas fa-paper-plane"></i>Enviar Mensagem</a>
                <?php if ($whatsapp): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-secondary"><i
                        class="fab fa-whatsapp"></i>Conversar no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══ SECÇÃO DE FEEDBACK (NOVA) ═══ -->
    <section class="feedback-section">
        <div class="container">
            <div class="feedback-card reveal">
                <h2 class="section-title">Deixe o seu <span class="text-gradient">Feedback</span></h2>
                <p style="text-align:center;color:var(--text-dim);font-size:.9rem;margin-bottom:1.5rem">A sua opinião é
                    importante! Sugestões, elogios ou críticas são sempre bem-vindos.</p>
                <form id="feedback-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="text" name="_hp" style="display:none" tabindex="-1" autocomplete="off">
                    <input type="hidden" name="page" value="<?= e($_SERVER['REQUEST_URI']) ?>">

                    <div class="form-group">
                        <label class="form-label" for="feedback-name">O seu Nome <span
                                style="color:var(--danger)">*</span></label>
                        <input type="text" id="feedback-name" name="name" class="form-control"
                            placeholder="Como se chama?" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="feedback-subject">Tipo de Feedback <span
                                style="color:var(--danger)">*</span></label>
                        <select id="feedback-subject" name="subject" class="form-control form-select" required>
                            <option value="">Seleccione uma opção…</option>
                            <option value="Sugestão">💡 Sugestão</option>
                            <option value="Elogio">🌟 Elogio</option>
                            <option value="Crítica">📝 Crítica Construtiva</option>
                            <option value="Bug">🐞 Reportar um Problema</option>
                            <option value="Outro">💬 Outro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="feedback-message">Mensagem <span
                                style="color:var(--danger)">*</span></label>
                        <textarea id="feedback-message" name="message" class="form-control" rows="4"
                            placeholder="Escreva aqui o seu feedback…" required></textarea>
                    </div>

                    <div id="form-status" class="form-status"></div>

                    <button type="submit" class="btn-submit" id="submit-btn">
                        <i class="fas fa-paper-plane"></i> Enviar Feedback
                    </button>
                </form>
            </div>
        </div>
    </section>

    <script>
    // Toggle FAQ (accordion)
    function toggleFaq(btn) {
        const card = btn.closest('.faq-card');
        const answer = card.querySelector('.faq-answer');
        const icon = btn.querySelector('i');

        // Fechar outras FAQs abertas
        document.querySelectorAll('.faq-card.open').forEach(other => {
            if (other !== card) {
                other.classList.remove('open');
                const otherIcon = other.querySelector('.faq-toggle i');
                if (otherIcon) otherIcon.className = 'fas fa-chevron-down';
            }
        });

        // Alternar esta FAQ
        card.classList.toggle('open');
        icon.className = card.classList.contains('open') ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    // ═══ FORMULÁRIO DE FEEDBACK (AJAX) ═══
    (function() {
        const form = document.getElementById('feedback-form');
        if (!form) return;
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
                    status.textContent = '✓ Feedback enviado! Obrigado pela contribuição.';
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
    })();
    </script>

    <?php
    $page_scripts = [];
    include 'includes/footer.php';
    include 'includes/bottom-nav.php';
    ?>

</body>

</html>