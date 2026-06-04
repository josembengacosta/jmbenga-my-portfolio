<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Responder Mensagem (v2)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) redirect('/jm-panel/messages');

$stmt = $db->prepare("SELECT * FROM _contact_message WHERE id = ?");
$stmt->execute([$id]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) redirect('/jm-panel/messages?msg=notfound');

// Marcar como lida automaticamente ao abrir para responder
if ($msg['status_msg'] === 'new') {
    $db->prepare("UPDATE _contact_message SET status_msg = 'read' WHERE id = ?")->execute([$id]);
    $msg['status_msg'] = 'read';
}

// Dados do admin
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = $_SESSION['admin_email'] ?? '';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;

// Initials do remetente
$senderInitials = strtoupper(mb_substr($msg['name_msg'], 0, 1));
$senderName2    = explode(' ', trim($msg['name_msg']));
if (count($senderName2) > 1) {
    $senderInitials .= strtoupper(mb_substr(end($senderName2), 0, 1));
}

// Resposta anterior (se já foi respondida e a coluna existir)
$previousReply = !empty($msg['reply_body']) ? $msg['reply_body'] : null;
$previousSubject = !empty($msg['reply_subject']) ? $msg['reply_subject'] : null;
$repliedAt     = !empty($msg['replied_at']) ? $msg['replied_at'] : null;

// Sugestão de assunto
$suggestedSubject = 'Re: ' . $msg['subject_msg'];
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<style>
/* ── Layout ──────────────────────────────────────────────────── */
.page-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .78rem;
    color: var(--text-muted);
    margin-bottom: .5rem
}

.breadcrumb a {
    color: var(--text-dim);
    text-decoration: none;
    transition: color .2s
}

.breadcrumb a:hover {
    color: var(--accent)
}

.breadcrumb i {
    font-size: .6rem
}

.page-title {
    font-family: var(--font-head);
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -.02em;
    margin-bottom: .2rem
}

.page-sub {
    font-size: .8rem;
    color: var(--text-muted)
}

.reply-grid {
    display: grid;
    grid-template-columns: 1fr 480px;
    gap: 1.25rem;
    align-items: start
}

@media(max-width:1100px) {
    .reply-grid {
        grid-template-columns: 1fr
    }
}

/* ── Cards ───────────────────────────────────────────────────── */
.rcard {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.2rem
}

.rcard:last-child {
    margin-bottom: 0
}

.rcard-head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, .015)
}

.rcard-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: .85rem;
    flex-shrink: 0
}

.rcard-icon.green {
    background: rgba(16, 185, 129, .08);
    border-color: rgba(16, 185, 129, .2);
    color: #34d399
}

.rcard-icon.warn {
    background: rgba(245, 158, 11, .08);
    border-color: rgba(245, 158, 11, .2);
    color: #fbbf24
}

.rcard-head h3 {
    font-family: var(--font-head);
    font-size: .9rem;
    font-weight: 700
}

.rcard-head p {
    font-size: .73rem;
    color: var(--text-muted);
    margin-top: .1rem
}

.rcard-body {
    padding: 1.4rem
}

/* ── Sender card ─────────────────────────────────────────────── */
.sender-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border)
}

.sender-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-head);
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: -.04em;
    user-select: none
}

.sender-info strong {
    display: block;
    font-size: .95rem;
    font-weight: 700
}

.sender-info p {
    font-size: .78rem;
    color: var(--text-muted);
    margin-top: .15rem
}

.sender-meta {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    margin-top: .4rem
}

.sender-tag {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2px 8px;
    font-size: .7rem;
    color: var(--text-dim)
}

.sender-tag i {
    color: var(--accent);
    font-size: .68rem
}

.sender-date {
    margin-left: auto;
    text-align: right;
    font-size: .72rem;
    color: var(--text-muted);
    flex-shrink: 0
}

/* ── Message body ────────────────────────────────────────────── */
.msg-text {
    font-size: .88rem;
    line-height: 1.85;
    color: var(--text-dim);
    white-space: pre-wrap;
    word-break: break-word;
    padding: 1.25rem 1.4rem
}

.msg-text.compact {
    max-height: 200px;
    overflow: hidden;
    position: relative
}

.msg-text.compact::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(transparent, var(--bg-card))
}

.expand-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    padding: .5rem;
    border-top: 1px solid var(--border);
    font-size: .78rem;
    color: var(--accent);
    cursor: pointer;
    background: none;
    border-left: none;
    border-right: none;
    border-bottom: none;
    width: 100%;
    transition: background .2s
}

.expand-btn:hover {
    background: var(--accent-glow)
}

/* ── Previous reply block ────────────────────────────────────── */
.prev-reply {
    border-left: 3px solid var(--accent);
    margin: 0 1.4rem 1.2rem;
    padding: .8rem 1rem;
    background: rgba(37, 99, 235, .04);
    border-radius: 0 8px 8px 0;
    font-size: .82rem;
    color: var(--text-dim);
    line-height: 1.7
}

.prev-reply-hd {
    font-size: .73rem;
    color: var(--text-muted);
    margin-bottom: .4rem
}

.prev-reply-hd strong {
    color: var(--accent)
}

/* ── Status badge ────────────────────────────────────────────── */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700
}

.s-new {
    background: rgba(37, 99, 235, .1);
    color: var(--accent);
    border: 1px solid rgba(37, 99, 235, .2)
}

.s-read {
    background: rgba(148, 163, 184, .1);
    color: var(--text-dim);
    border: 1px solid var(--border)
}

.s-replied {
    background: rgba(16, 185, 129, .1);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, .2)
}

.s-archived {
    background: rgba(148, 163, 184, .1);
    color: var(--text-muted);
    border: 1px solid var(--border)
}

/* ── Form ────────────────────────────────────────────────────── */
.form-group {
    display: flex;
    flex-direction: column;
    gap: .3rem;
    margin-bottom: 1rem
}

.form-group:last-child {
    margin-bottom: 0
}

.form-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text-dim)
}

.req {
    color: var(--danger)
}

.form-control {
    padding: .62rem .85rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font-body);
    font-size: .87rem;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    width: 100%
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow)
}

.form-control::placeholder {
    color: var(--text-muted)
}

.form-control:disabled {
    opacity: .6;
    cursor: not-allowed
}

.form-control.error {
    border-color: var(--danger)
}

textarea.form-control {
    resize: vertical;
    min-height: 220px;
    line-height: 1.75
}

/* ── Templates ───────────────────────────────────────────────── */
.template-bar {
    display: flex;
    gap: .4rem;
    flex-wrap: wrap;
    margin-bottom: .75rem
}

.template-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .7rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: .72rem;
    color: var(--text-dim);
    cursor: pointer;
    transition: all .2s;
    font-family: var(--font-body)
}

.template-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--accent-glow)
}

.template-btn i {
    font-size: .68rem
}

/* ── Char counter ────────────────────────────────────────────── */
.textarea-wrap {
    position: relative
}

.char-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .7rem;
    color: var(--text-muted);
    margin-top: .3rem
}

.char-counter .char-warn {
    color: #fbbf24
}

.char-counter .char-ok {
    color: #34d399
}

/* ── Preview ─────────────────────────────────────────────────── */
.preview-box {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    font-size: .85rem;
    line-height: 1.8;
    color: var(--text-dim);
    min-height: 120px;
    white-space: pre-wrap;
    word-break: break-word;
    display: none
}

.preview-box.visible {
    display: block
}

/* ── To field ────────────────────────────────────────────────── */
.to-field {
    display: flex;
    align-items: center;
    gap: .6rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .45rem .85rem
}

.to-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0
}

.to-name {
    font-size: .85rem;
    font-weight: 600
}

.to-email {
    font-size: .75rem;
    color: var(--text-muted);
    margin-left: .3rem
}

/* ── Buttons ─────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem 1.1rem;
    border-radius: 8px;
    font-family: var(--font-body);
    font-size: .83rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    border: 1px solid var(--border);
    white-space: nowrap
}

.btn-primary {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent)
}

.btn-primary:hover {
    filter: brightness(1.12);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px var(--accent-glow)
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-dim)
}

.btn-secondary:hover {
    background: var(--bg-hover);
    border-color: var(--border-acc);
    color: var(--text)
}

.btn-success {
    background: rgba(16, 185, 129, .1);
    border-color: rgba(16, 185, 129, .3);
    color: #34d399
}

.btn-success:hover {
    background: rgba(16, 185, 129, .2)
}

.btn-lg {
    padding: .68rem 1.4rem;
    font-size: .9rem
}

.btn:disabled {
    opacity: .55;
    pointer-events: none
}

/* ── Toast ───────────────────────────────────────────────────── */
#toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: .5rem;
    pointer-events: none
}

.toast {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .7rem 1.1rem;
    border-radius: 9px;
    font-size: .82rem;
    font-weight: 500;
    pointer-events: all;
    animation: toastIn .25s ease;
    transition: opacity .3s, transform .3s;
    max-width: 320px;
    backdrop-filter: blur(12px)
}

.toast-success {
    background: rgba(16, 185, 129, .15);
    border: 1px solid rgba(16, 185, 129, .35);
    color: #34d399
}

.toast-error {
    background: rgba(239, 68, 68, .15);
    border: 1px solid rgba(239, 68, 68, .35);
    color: #f87171
}

.toast-info {
    background: rgba(59, 130, 246, .15);
    border: 1px solid rgba(59, 130, 246, .35);
    color: #93c5fd
}

.toast-warning {
    background: rgba(245, 158, 11, .15);
    border: 1px solid rgba(245, 158, 11, .35);
    color: #fbbf24
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateX(20px)
    }

    to {
        opacity: 1;
        transform: translateX(0)
    }
}

/* ── Send actions row ────────────────────────────────────────── */
.send-row {
    display: flex;
    gap: .6rem;
    align-items: center;
    margin-top: 1.1rem;
    padding-top: 1.1rem;
    border-top: 1px solid var(--border)
}

.send-row .spacer {
    flex: 1
}

.signature-toggle {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .75rem;
    color: var(--text-muted);
    cursor: pointer
}

.signature-toggle input {
    accent-color: var(--accent)
}

/* ── Replied banner ──────────────────────────────────────────── */
.replied-banner {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .9rem 1.1rem;
    background: rgba(16, 185, 129, .06);
    border: 1px solid rgba(16, 185, 129, .2);
    border-radius: 10px;
    margin-bottom: 1.2rem;
    font-size: .82rem;
    color: #34d399
}

.replied-banner i {
    margin-top: .1rem;
    flex-shrink: 0
}
</style>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <!-- Cabeçalho -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/messages">Mensagens</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Responder</span>
                    </div>
                    <h1 class="page-title">Responder a <?= e($msg['name_msg']) ?></h1>
                    <p class="page-sub">
                        Assunto: <strong><?= e($msg['subject_msg']) ?></strong>
                        &nbsp;·&nbsp;
                        <span class="status-badge <?= match($msg['status_msg']) {
                        'new'      => 's-new',
                        'read'     => 's-read',
                        'replied'  => 's-replied',
                        'archived' => 's-archived',
                        default    => 's-read',
                    } ?>">
                            <i class="fas fa-<?= match($msg['status_msg']) {
                            'new'      => 'envelope',
                            'read'     => 'check',
                            'replied'  => 'reply',
                            'archived' => 'box-archive',
                            default    => 'circle'
                        } ?>"></i>
                            <?= match($msg['status_msg']) {
                            'new'      => 'Nova',
                            'read'     => 'Lida',
                            'replied'  => 'Respondida',
                            'archived' => 'Arquivada',
                            default    => ucfirst($msg['status_msg'])
                        } ?>
                        </span>
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/messages" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>

            <!-- Banner se já foi respondida -->
            <?php if ($msg['status_msg'] === 'replied' && $repliedAt): ?>
            <div class="replied-banner">
                <i class="fas fa-circle-check"></i>
                <div>
                    <strong>Mensagem já respondida</strong> em <?= date('d/m/Y \à\s H:i', strtotime($repliedAt)) ?>.
                    Podes enviar uma nova resposta abaixo.
                </div>
            </div>
            <?php endif; ?>

            <div class="reply-grid">

                <!-- ═══ COLUNA ESQUERDA: Mensagem original ═══════════════ -->
                <div>
                    <!-- Remetente -->
                    <div class="rcard">
                        <div class="sender-row">
                            <div class="sender-avatar"><?= e($senderInitials) ?></div>
                            <div class="sender-info" style="flex:1;min-width:0">
                                <strong><?= e($msg['name_msg']) ?></strong>
                                <p><?= e($msg['email_msg']) ?></p>
                                <div class="sender-meta">
                                    <?php if (!empty($msg['phone_msg'])): ?>
                                    <span class="sender-tag"><i
                                            class="fas fa-phone"></i><?= e($msg['phone_msg']) ?></span>
                                    <?php endif; ?>
                                    <span class="sender-tag"><i
                                            class="fas fa-envelope"></i><?= e($msg['email_msg']) ?></span>
                                </div>
                            </div>
                            <div class="sender-date">
                                <div><?= date('d/m/Y', strtotime($msg['created_at'])) ?></div>
                                <div style="font-size:.7rem;margin-top:.15rem">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>

                        <!-- Assunto -->
                        <div
                            style="padding:.75rem 1.4rem;border-bottom:1px solid var(--border);background:rgba(255,255,255,.01)">
                            <span
                                style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Assunto</span>
                            <div style="font-size:.9rem;font-weight:700;margin-top:.2rem"><?= e($msg['subject_msg']) ?>
                            </div>
                        </div>

                        <!-- Corpo da mensagem -->
                        <div class="msg-text <?= mb_strlen($msg['message_msg']) > 500 ? 'compact' : '' ?>" id="msgBody">
                            <?= nl2br(e($msg['message_msg'])) ?></div>
                        <?php if (mb_strlen($msg['message_msg']) > 500): ?>
                        <button class="expand-btn" id="expandBtn" onclick="expandMsg()">
                            <i class="fas fa-chevron-down" id="expandIcon"></i> Ver mensagem completa
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Resposta anterior (se existir) -->
                    <?php if ($previousReply): ?>
                    <div class="rcard">
                        <div class="rcard-head">
                            <div class="rcard-icon green"><i class="fas fa-reply-all"></i></div>
                            <div>
                                <h3>Resposta Anterior</h3>
                                <p>Enviada <?= $repliedAt ? date('d/m/Y H:i', strtotime($repliedAt)) : '' ?></p>
                            </div>
                        </div>
                        <div class="prev-reply">
                            <div class="prev-reply-hd">
                                <strong><?= e($adminName) ?></strong> &lt;<?= e($adminEmail) ?>&gt;
                                <?php if ($previousSubject): ?> · <?= e($previousSubject) ?><?php endif; ?>
                            </div>
                            <?= nl2br(e($previousReply)) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ COLUNA DIREITA: Formulário de resposta ══════════ -->
                <div>
                    <div class="rcard">
                        <div class="rcard-head">
                            <div class="rcard-icon"><i class="fas fa-paper-plane"></i></div>
                            <div>
                                <h3>Escrever Resposta</h3>
                                <p>Será enviada por email a <?= e($msg['email_msg']) ?></p>
                            </div>
                        </div>
                        <div class="rcard-body">

                            <form id="replyForm" novalidate data-api="<?= BASE_URL ?>/jm-panel/messages/reply-process">
                                <input type="hidden" name="csrf_token" id="csrf_token"
                                    value="<?= $_SESSION['admin_csrf_token'] ?>">
                                <input type="hidden" name="id_message" value="<?= $id ?>">

                                <!-- Para: -->
                                <div class="form-group">
                                    <label class="form-label">Para</label>
                                    <div class="to-field">
                                        <div class="to-avatar"><?= e($senderInitials) ?></div>
                                        <span class="to-name"><?= e($msg['name_msg']) ?></span>
                                        <span class="to-email">&lt;<?= e($msg['email_msg']) ?>&gt;</span>
                                    </div>
                                </div>

                                <!-- Assunto -->
                                <div class="form-group">
                                    <label class="form-label" for="reply_subject">Assunto</label>
                                    <input type="text" id="reply_subject" name="reply_subject" class="form-control"
                                        value="<?= e($suggestedSubject) ?>" required>
                                </div>

                                <!-- Templates rápidos -->
                                <div class="form-group">
                                    <label class="form-label">Templates Rápidos</label>
                                    <div class="template-bar">
                                        <button type="button" class="template-btn" onclick="useTemplate('obrigado')">
                                            <i class="fas fa-heart"></i> Obrigado
                                        </button>
                                        <button type="button" class="template-btn" onclick="useTemplate('recebido')">
                                            <i class="fas fa-check"></i> Recebido
                                        </button>
                                        <button type="button" class="template-btn" onclick="useTemplate('contato')">
                                            <i class="fas fa-phone"></i> Contacto em breve
                                        </button>
                                        <button type="button" class="template-btn" onclick="useTemplate('proposta')">
                                            <i class="fas fa-file-lines"></i> Proposta
                                        </button>
                                    </div>
                                </div>

                                <!-- Corpo da resposta -->
                                <div class="form-group">
                                    <div
                                        style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.3rem">
                                        <label class="form-label" style="margin:0">Mensagem <span
                                                class="req">*</span></label>
                                        <div style="display:flex;gap:.4rem">
                                            <button type="button" class="template-btn" id="previewToggle"
                                                onclick="togglePreview()">
                                                <i class="fas fa-eye"></i> Pré-visualizar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="textarea-wrap">
                                        <textarea id="reply_body" name="reply_body" class="form-control" rows="9"
                                            placeholder="Olá <?= e($msg['name_msg']) ?>,&#10;&#10;Obrigado pelo teu contacto.&#10;&#10;"
                                            required oninput="updateCounter(this)"></textarea>
                                        <div class="preview-box" id="previewBox"></div>
                                    </div>
                                    <div class="char-counter">
                                        <span id="charCount">0 caracteres</span>
                                        <span id="charStatus"></span>
                                    </div>
                                </div>

                                <!-- Assinatura -->
                                <label class="signature-toggle">
                                    <input type="checkbox" id="addSignature" checked onchange="updateSignature()">
                                    Incluir assinatura automática
                                </label>

                                <!-- Acções de envio -->
                                <div class="send-row">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearForm()"
                                        title="Limpar formulário">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                    <div class="spacer"></div>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="fas fa-paper-plane"></i> Enviar Resposta
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>

                    <!-- Info card -->
                    <div class="rcard">
                        <div class="rcard-body" style="padding:.9rem 1.1rem">
                            <div
                                style="display:flex;flex-direction:column;gap:.5rem;font-size:.78rem;color:var(--text-muted)">
                                <div style="display:flex;justify-content:space-between">
                                    <span>Recebida em</span>
                                    <span
                                        style="color:var(--text-dim)"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between">
                                    <span>ID da mensagem</span>
                                    <code
                                        style="font-family:var(--font-mono);background:var(--bg);padding:1px 5px;border-radius:4px;color:var(--accent)">#<?= $msg['id'] ?></code>
                                </div>
                                <?php if ($repliedAt): ?>
                                <div style="display:flex;justify-content:space-between">
                                    <span>Última resposta</span>
                                    <span style="color:#34d399"><?= date('d/m/Y H:i', strtotime($repliedAt)) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="toast-container"></div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const MSG_ID = <?= $id ?>;
    const SENDER_NAME = '<?= addslashes(e($msg['name_msg'])) ?>';
    const ADMIN_NAME = '<?= addslashes(e($adminName)) ?>';

    // ── Templates rápidos ────────────────────────────────────────
    const TEMPLATES = {
        obrigado: `Olá ${SENDER_NAME},\n\nMuito obrigado pelo teu contacto e pelo interesse demonstrado.\n\nFiquei feliz em receber a tua mensagem e vou analisá-la com atenção.\n\nQualquer questão adicional, não hesites em contactar.\n\nCom os melhores cumprimentos,\n${ADMIN_NAME}`,
        recebido: `Olá ${SENDER_NAME},\n\nConfirmo que recebi a tua mensagem com sucesso.\n\nIrei analisar e responder brevemente.\n\nObrigado pelo contacto.\n\nAtenciosamente,\n${ADMIN_NAME}`,
        contato: `Olá ${SENDER_NAME},\n\nObrigado pelo teu contacto.\n\nAnalisei a tua mensagem e vou entrar em contacto contigo em breve para discutir os próximos passos.\n\nAté lá, fico à disposição para qualquer questão.\n\nCom os melhores cumprimentos,\n${ADMIN_NAME}`,
        proposta: `Olá ${SENDER_NAME},\n\nObrigado por te teres colocado em contacto.\n\nAnalisei o teu pedido com atenção e estou a preparar uma proposta personalizada para ti. Enviarei os detalhes em breve.\n\nSe tiveres alguma questão adicional, não hesites em responder a este email.\n\nAtenciosamente,\n${ADMIN_NAME}`,
    };

    function useTemplate(key) {
        const body = document.getElementById('reply_body');
        body.value = TEMPLATES[key] || '';
        updateCounter(body);
        body.focus();
        showToast('Template aplicado.', 'info');
    }

    // ── Contador de caracteres ────────────────────────────────────
    function updateCounter(el) {
        const len = el.value.length;
        const cnt = document.getElementById('charCount');
        const status = document.getElementById('charStatus');
        cnt.textContent = len + ' caracteres';
        if (len === 0) {
            status.textContent = '';
        } else if (len < 20) {
            status.className = 'char-warn';
            status.textContent = 'Muito curto';
        } else if (len < 50) {
            status.className = '';
            status.textContent = 'Pode ser mais detalhado';
        } else {
            status.className = 'char-ok';
            status.textContent = '✓ Boa extensão';
        }
        // Actualizar preview se visível
        if (document.getElementById('previewBox').classList.contains('visible')) {
            updatePreview();
        }
    }

    // ── Preview ───────────────────────────────────────────────────
    let previewMode = false;

    function togglePreview() {
        const body = document.getElementById('reply_body');
        const preview = document.getElementById('previewBox');
        const btn = document.getElementById('previewToggle');
        previewMode = !previewMode;
        if (previewMode) {
            updatePreview();
            body.style.display = 'none';
            preview.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-pen"></i> Editar';
        } else {
            body.style.display = '';
            preview.style.display = 'none';
            btn.innerHTML = '<i class="fas fa-eye"></i> Pré-visualizar';
        }
    }

    function updatePreview() {
        const val = document.getElementById('reply_body').value;
        const preview = document.getElementById('previewBox');
        preview.innerHTML = val ?
            val.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>') :
            '<em style="color:var(--text-muted)">Sem conteúdo ainda…</em>';
    }

    // ── Assinatura ────────────────────────────────────────────────
    const SIG = `\n\n--\n${ADMIN_NAME}`;

    function updateSignature() {
        const body = document.getElementById('reply_body');
        const hasSig = document.getElementById('addSignature').checked;
        if (hasSig) {
            if (!body.value.endsWith(SIG)) body.value += SIG;
        } else {
            body.value = body.value.replace(new RegExp(SIG.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'), '');
        }
        updateCounter(body);
    }

    // Inserir assinatura ao carregar
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.getElementById('reply_body');
        if (body.value.trim() === '') {
            body.value = SIG.trimStart();
            // Move cursor to beginning
            body.setSelectionRange(0, 0);
            body.scrollTop = 0;
        }
        updateCounter(body);
    });

    // ── Expandir mensagem longa ───────────────────────────────────
    function expandMsg() {
        const body = document.getElementById('msgBody');
        const btn = document.getElementById('expandBtn');
        const icon = document.getElementById('expandIcon');
        if (body.classList.contains('compact')) {
            body.classList.remove('compact');
            btn.innerHTML = '<i class="fas fa-chevron-up"></i> Recolher';
        } else {
            body.classList.add('compact');
            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Ver mensagem completa';
        }
    }

    // ── Limpar form ───────────────────────────────────────────────
    function clearForm() {
        document.getElementById('reply_body').value = '';
        updateCounter(document.getElementById('reply_body'));
        showToast('Formulário limpo.', 'info');
    }

    // ── Toast ─────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        const icons = {
            success: 'circle-check',
            error: 'circle-xmark',
            info: 'circle-info',
            warning: 'triangle-exclamation'
        };
        t.innerHTML = `<i class="fas fa-${icons[type] || 'circle-info'}"></i> ${msg}`;
        c.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(20px)';
            setTimeout(() => t.remove(), 350);
        }, 4500);
    }

    // ── Submit ────────────────────────────────────────────────────
    const form = document.getElementById('replyForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const subject = document.getElementById('reply_subject').value.trim();
        const body = document.getElementById('reply_body').value.trim();

        if (!subject) {
            showToast('O assunto é obrigatório.', 'error');
            return;
        }
        if (!body || body === SIG.trim()) {
            showToast('Escreve a mensagem de resposta.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar…';

        try {
            const fd = new FormData(form);
            const apiUrl = form.dataset.api;

            console.log('📤 Enviando para:', apiUrl);
            console.log('📦 Dados:', {
                id_message: fd.get('id_message'),
                reply_subject: fd.get('reply_subject'),
                reply_body: fd.get('reply_body').substring(0, 50) + '...',
                csrf_token: fd.get('csrf_token').substring(0, 10) + '...'
            });

            const res = await fetch(apiUrl, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                }
            });

            console.log('📥 Response status:', res.status, res.statusText);

            if (!res.ok) {
                const text = await res.text();
                console.error('❌ HTTP Error Body:', text);
                showToast(`Erro HTTP ${res.status}: ${res.statusText}. Verifica a consola.`, 'error');
                return;
            }

            const data = await res.json();
            console.log('✅ Response JSON:', data);

            if (data.success) {
                if (data.sent_via_email) {
                    showToast('Resposta enviada com sucesso por email!', 'success');
                } else {
                    showToast(
                        'Resposta guardada, mas o email não foi entregue. Verifica as configurações SMTP.',
                        'warning');
                }
                setTimeout(() => {
                    window.location.href = BASE_URL + '/jm-panel/messages';
                }, 1800);
            } else {
                showToast(data.message || 'Erro ao enviar. Tenta novamente.', 'error');
            }
        } catch (err) {
            console.error('🔴 Exception:', err);
            showToast('Erro: ' + err.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Resposta';
        }
    });
    </script>
</body>

</html>