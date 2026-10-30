<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Editar FAQ
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Verificar se o ID foi passado e a FAQ existe ────────────
$editId = (int)($_GET['id'] ?? 0);
if ($editId <= 0) {
    redirect('/jm-panel/faq');
}

$stmt = $db->prepare("SELECT * FROM _faq WHERE id_faq = ?");
$stmt->execute([$editId]);
$faq = $stmt->fetch();

if (!$faq) {
    redirect('/jm-panel/faq?msg=notfound');
}

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = $_SESSION['admin_email'] ?? '';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;

// ── Contadores globais ─────────────────────────────────────
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();

// ── Sessão & cliente ───────────────────────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$clientIP    = $_SERVER['REMOTE_ADDR'] ?? '—';
$clientUA    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browser     = parseBrowserFromUA($clientUA);
$os          = parseOSFromUA($clientUA);

// ── Categorias existentes (para sugestão) ──────────────────
$existingCategories = $db->query("SELECT DISTINCT category_faq FROM _faq ORDER BY category_faq")->fetchAll(PDO::FETCH_COLUMN);
if (empty($existingCategories)) {
    $existingCategories = ['Geral', 'Serviços', 'Orçamento', 'Suporte'];
}

// ── Recuperar dados do formulário em caso de erro ───────────
$formData   = $_SESSION['form_data']   ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

// Se não houve erro, usar dados da FAQ
if (empty($formData)) {
    $formData = $faq;
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/jmbenga-faq.css?v=1.0.0">
</head>

<body class="faq-form-page">

    <?php include __DIR__ . '/../../include/sidebar.php'; ?>

    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>

        <div class="content">

            <!-- ═══ CABEÇALHO + BREADCRUMB ════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/faq">FAQ</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Editar FAQ</span>
                    </div>
                    <h1 class="page-title">Editar FAQ</h1>
                    <p class="page-sub">Altera os campos abaixo para actualizar a FAQ.</p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/faq" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" form="faqForm" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Actualizar FAQ
                    </button>
                </div>
            </div>

            <!-- ═══ ALERTAS DE ERRO ═══════════════════════════════════════ -->
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Corrige os seguintes erros:</strong>
                    <ul style="margin:.3rem 0 0 1.2rem">
                        <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>

            <!-- ═══ FORMULÁRIO ════════════════════════════════════════════ -->
            <form id="faqForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/faq/edit-process"
                data-action-label="Actualizar FAQ" data-busy-label="A actualizar…"
                data-success-url="<?= BASE_URL ?>/jm-panel/faq?msg=updated">

                <input type="hidden" name="csrf_token" id="csrf_token"
                    value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">
                <input type="hidden" name="id_faq" value="<?= $editId ?>">

                <!-- ═══ SECÇÃO 1 — PERGUNTA E RESPOSTA ══════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-question-circle"></i></span>
                        <div>
                            <h3>Pergunta &amp; Resposta</h3>
                            <p>Define a pergunta e a resposta que serão exibidas na página de FAQ.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Pergunta -->
                        <div class="form-group full-width">
                            <label class="form-label" for="question">
                                Pergunta <span class="required">*</span>
                            </label>
                            <input type="text" id="question" name="question" class="form-control"
                                value="<?= e($formData['question'] ?? '') ?>"
                                placeholder="Ex: Quanto tempo leva para desenvolver um site?" maxlength="500" required
                                autofocus>
                            <span class="form-hint" id="questionCount">0/500 caracteres</span>
                        </div>

                        <!-- Resposta -->
                        <div class="form-group full-width">
                            <label class="form-label" for="answer">
                                Resposta <span class="required">*</span>
                            </label>
                            <textarea id="answer" name="answer" class="form-control textarea-lg" rows="6"
                                placeholder="Escreve a resposta completa…" maxlength="5000"
                                required><?= e($formData['answer'] ?? '') ?></textarea>
                            <span class="form-hint" id="answerCount">0/5000 caracteres</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 2 — CATEGORIA & ORDENAÇÃO ═══════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-tags"></i></span>
                        <div>
                            <h3>Categoria &amp; Ordenação</h3>
                            <p>Agrupa a FAQ numa categoria e define a sua ordem de exibição.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <!-- Categoria -->
                        <div class="form-group">
                            <label class="form-label" for="category_faq">
                                Categoria <span class="required">*</span>
                            </label>
                            <input type="text" id="category_faq" name="category_faq" class="form-control"
                                value="<?= e($formData['category_faq'] ?? 'Geral') ?>"
                                placeholder="Ex: Serviços, Orçamento, Suporte" maxlength="100" required
                                list="categoryList">
                            <datalist id="categoryList">
                                <?php foreach ($existingCategories as $cat): ?>
                                <option value="<?= e($cat) ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <span class="form-hint">Podes escrever uma nova ou escolher uma existente.</span>
                        </div>

                        <!-- Ordem -->
                        <div class="form-group">
                            <label class="form-label" for="display_order">Ordem de Exibição</label>
                            <div class="number-input">
                                <button type="button" class="num-btn" onclick="changeOrder(-1)">−</button>
                                <input type="number" id="display_order" name="display_order"
                                    class="form-control num-field" value="<?= $formData['display_order'] ?? 0 ?>"
                                    min="0">
                                <button type="button" class="num-btn" onclick="changeOrder(1)">+</button>
                            </div>
                            <span class="form-hint">Números menores aparecem primeiro.</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECÇÃO 3 — ESTADO ═══════════════════════════════════ -->
                <div class="form-section">
                    <div class="section-header">
                        <span class="section-icon"><i class="fas fa-eye"></i></span>
                        <div>
                            <h3>Estado</h3>
                            <p>Define se a FAQ está visível ou oculta no site.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="status_faq" value="visible"
                                <?= ($formData['status_faq'] ?? 'visible') === 'visible' ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                            <span class="switch-label">
                                <?= ($formData['status_faq'] ?? 'visible') === 'visible' ? 'Visível' : 'Oculta' ?>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- ═══ BOTÕES DE ACÇÃO ═══════════════════════════════════ -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/jm-panel/faq" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn2">
                        <i class="fas fa-save"></i> Actualizar FAQ
                    </button>
                </div>
            </form>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    (() => {
        'use strict';

        const form = document.getElementById('faqForm');
        if (!form) return;

        const submitButtons = Array.from(document.querySelectorAll('#submitBtn, #submitBtn2'));
        const actionLabel = form.dataset.actionLabel || 'Guardar FAQ';
        const busyLabel = form.dataset.busyLabel || 'A guardar…';
        const successUrl = form.dataset.successUrl;
        let submissionSucceeded = false;

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icon = document.createElement('i');
            icon.className =
                `fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}`;
            const text = document.createElement('span');
            text.textContent = String(message ?? 'Ocorreu um erro.');
            toast.append(icon, text);
            container.appendChild(toast);
            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                window.setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText =
                'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
            document.body.appendChild(container);
            return container;
        }

        async function readJsonResponse(response) {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (_) {
                throw new Error(`Resposta inválida do servidor (HTTP ${response.status}).`);
            }
        }

        function setBusy(isBusy) {
            submitButtons.forEach(button => {
                button.disabled = isBusy;
                button.innerHTML = isBusy ?
                    `<i class="fas fa-spinner fa-spin"></i> ${busyLabel}` :
                    `<i class="fas fa-save"></i> ${actionLabel}`;
            });
        }

        const questionInput = document.getElementById('question');
        const answerInput = document.getElementById('answer');

        function updateCounter(input, counter, max, threshold) {
            if (!input || !counter) return;
            const length = input.value.length;
            counter.textContent = `${length}/${max} caracteres`;
            counter.style.color = length > threshold ? 'var(--danger)' : 'var(--text-muted)';
        }

        if (questionInput) {
            const counter = document.getElementById('questionCount');
            questionInput.addEventListener('input', () => updateCounter(questionInput, counter, 500, 480));
            updateCounter(questionInput, counter, 500, 480);
        }
        if (answerInput) {
            const counter = document.getElementById('answerCount');
            answerInput.addEventListener('input', () => updateCounter(answerInput, counter, 5000, 4800));
            updateCounter(answerInput, counter, 5000, 4800);
        }

        window.changeOrder = function(delta) {
            const field = document.getElementById('display_order');
            if (!field) return;
            const current = Number.parseInt(field.value, 10);
            field.value = Math.max(0, (Number.isFinite(current) ? current : 0) + delta);
        };

        const statusCheckbox = form.querySelector('input[name="status_faq"]');
        const switchLabel = form.querySelector('.switch-label');
        if (statusCheckbox && switchLabel) {
            statusCheckbox.addEventListener('change', () => {
                switchLabel.textContent = statusCheckbox.checked ? 'Visível' : 'Oculta';
            });
        }

        form.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('input', () => field.classList.remove('error'));
            field.addEventListener('change', () => field.classList.remove('error'));
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.submitting === '1') return;

            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                const isEmpty = !String(field.value ?? '').trim();
                field.classList.toggle('error', isEmpty);
                if (isEmpty) valid = false;
            });

            const orderField = document.getElementById('display_order');
            if (orderField && (!Number.isInteger(Number(orderField.value)) || Number(orderField.value) <
                    0)) {
                orderField.classList.add('error');
                valid = false;
            }

            if (!valid) {
                showToast('Verifica os campos obrigatórios e a ordem de exibição.', 'error');
                return;
            }

            form.dataset.submitting = '1';
            setBusy(true);

            try {
                const csrfField = document.getElementById('csrf_token');
                const response = await fetch(form.dataset.api, {
                    method: 'POST',
                    body: new window.FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfField ? csrfField.value : ''
                    },
                    credentials: 'same-origin'
                });
                const data = await readJsonResponse(response);

                if (!response.ok || !data.success) {
                    const errors = Array.isArray(data.errors) ? data.errors : [data.message ||
                        'Não foi possível guardar a FAQ.'
                    ];
                    errors.forEach(message => showToast(message, 'error'));
                    return;
                }

                submissionSucceeded = true;
                showToast(data.message || 'FAQ guardada com sucesso.', 'success');
                window.setTimeout(() => {
                    if (successUrl) window.location.href = successUrl;
                }, 700);
            } catch (error) {
                showToast(error.message || 'Erro de rede. Tenta novamente.', 'error');
            } finally {
                // A navegação após sucesso ocorre pouco depois; em falha, os botões ficam disponíveis.
                if (!submissionSucceeded) {
                    setBusy(false);
                    delete form.dataset.submitting;
                }
            }
        });
    })();
    </script>
</body>

</html>