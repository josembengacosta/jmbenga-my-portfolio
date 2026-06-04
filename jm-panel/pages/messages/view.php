<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visualizar Mensagem (v2)
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

// Marcar como lida automaticamente ao abrir
if ($msg['status_msg'] === 'new') {
    $db->prepare("UPDATE _contact_message SET status_msg = 'read' WHERE id = ?")->execute([$id]);
    $msg['status_msg'] = 'read';
}

// ── Navegação anterior / próxima ──────────────────────────────
$prevStmt = $db->prepare("SELECT id FROM _contact_message WHERE id < ? ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$id]);
$prevId = $prevStmt->fetchColumn();

$nextStmt = $db->prepare("SELECT id FROM _contact_message WHERE id > ? ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$id]);
$nextId = $nextStmt->fetchColumn();

// ── Admin ─────────────────────────────────────────────────────
$adminName  = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? '';
$csrfToken  = $_SESSION['admin_csrf_token'];

// ── Parsing UA do remetente (guardado na BD, não do admin) ────
$ua      = $msg['user_agent'] ?? '';
$browser = parseBrowserFromUA($ua);
$os      = parseOSFromUA($ua);

// ── Iniciais do remetente ─────────────────────────────────────
$parts    = explode(' ', trim($msg['name_msg']));
$initials = strtoupper(mb_substr($parts[0], 0, 1));
if (isset($parts[1])) $initials .= strtoupper(mb_substr($parts[1], 0, 1));

// ── Estado: classes consistentes com inbox.php ────────────────
function statusInfo(string $s): array {
    return match ($s) {
        'new'      => ['cls' => 'badge-new',      'icon' => 'fa-envelope',    'label' => 'Nova'],
        'read'     => ['cls' => 'badge-read',      'icon' => 'fa-check',       'label' => 'Lida'],
        'replied'  => ['cls' => 'badge-replied',   'icon' => 'fa-reply',       'label' => 'Respondida'],
        'archived' => ['cls' => 'badge-archived',  'icon' => 'fa-box-archive', 'label' => 'Arquivada'],
        default    => ['cls' => 'badge-read',      'icon' => 'fa-circle',      'label' => ucfirst($s)],
    };
}
$si = statusInfo($msg['status_msg']);
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
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
    margin-top: .25rem
}

/* ── Action bar ──────────────────────────────────────────────── */
.action-bar {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    align-items: center
}

@media(max-width:1024px) {
    .action-bar {
        width: 100%
    }
}

/* ── Nav bar (prev/next) ─────────────────────────────────────── */
.msg-nav {
    display: flex;
    align-items: center;
    gap: .4rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: .35rem .5rem;
    margin-bottom: 1.25rem
}

.msg-nav .nav-info {
    font-size: .78rem;
    color: var(--text-muted);
    flex: 1;
    text-align: center
}

.msg-nav .nav-info strong {
    color: var(--text-dim)
}

.nav-btn {
    display: flex;
    align-items: center;
    gap: .3rem;
    padding: .35rem .7rem;
    border-radius: 6px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    color: var(--text-dim);
    background: var(--bg);
    transition: all .2s;
    white-space: nowrap
}

.nav-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--accent-glow)
}

.nav-btn.disabled {
    opacity: .35;
    pointer-events: none
}

/* ── Grid ────────────────────────────────────────────────────── */
.view-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
    align-items: start
}

@media(max-width:1060px) {
    .view-grid {
        grid-template-columns: 1fr
    }
}

/* ── Cards ───────────────────────────────────────────────────── */
.vcard {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.2rem
}

.vcard:last-child {
    margin-bottom: 0
}

.vcard-head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.3rem;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, .01)
}

.vcard-icon {
    width: 33px;
    height: 33px;
    border-radius: 8px;
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: .82rem;
    flex-shrink: 0
}

.vcard-icon.green {
    background: rgba(16, 185, 129, .08);
    border-color: rgba(16, 185, 129, .2);
    color: #34d399
}

.vcard-icon.warn {
    background: rgba(245, 158, 11, .08);
    border-color: rgba(245, 158, 11, .2);
    color: #fbbf24
}

.vcard-icon.purple {
    background: rgba(139, 92, 246, .08);
    border-color: rgba(139, 92, 246, .2);
    color: #a78bfa
}

.vcard-head h3 {
    font-family: var(--font-head);
    font-size: .88rem;
    font-weight: 700
}

.vcard-head p {
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .1rem
}

.vcard-body {
    padding: 1.3rem
}

/* ── Message body ────────────────────────────────────────────── */
.msg-text {
    font-size: .9rem;
    line-height: 1.85;
    color: var(--text-dim);
    white-space: pre-wrap;
    word-break: break-word;
    padding: 1.2rem 1.3rem
}

/* ── Reply history block ─────────────────────────────────────── */
.reply-thread {
    border-left: 3px solid var(--accent);
    margin: 0 1.3rem 1.2rem;
    padding: .85rem 1rem;
    background: rgba(37, 99, 235, .04);
    border-radius: 0 8px 8px 0
}

.reply-thread-hd {
    font-size: .72rem;
    color: var(--text-muted);
    margin-bottom: .45rem;
    display: flex;
    align-items: center;
    gap: .5rem
}

.reply-thread-hd strong {
    color: var(--accent)
}

.reply-thread-body {
    font-size: .83rem;
    line-height: 1.75;
    color: var(--text-dim);
    white-space: pre-wrap
}

/* ── Sender card ─────────────────────────────────────────────── */
.sender-avatar-wrap {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.3rem;
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

.sender-name {
    font-size: .95rem;
    font-weight: 700
}

.sender-email {
    font-size: .75rem;
    color: var(--text-muted);
    margin-top: .1rem;
    word-break: break-all
}

.sender-date {
    margin-left: auto;
    text-align: right;
    font-size: .72rem;
    color: var(--text-muted);
    flex-shrink: 0
}

/* ── Info rows ───────────────────────────────────────────────── */
.info-list {
    display: flex;
    flex-direction: column
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .5rem 0;
    border-bottom: 1px solid var(--border);
    font-size: .8rem;
    gap: .5rem
}

.info-row:last-child {
    border-bottom: none
}

.info-row .ik {
    color: var(--text-muted);
    font-size: .73rem;
    flex-shrink: 0
}

.info-row .iv {
    color: var(--text-dim);
    font-weight: 500;
    text-align: right;
    word-break: break-all
}

.info-row code {
    font-family: var(--font-mono);
    font-size: .72rem;
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--accent)
}

.info-row a {
    color: var(--accent);
    text-decoration: none
}

.info-row a:hover {
    text-decoration: underline
}

/* ── Meta section (UA/IP) ────────────────────────────────────── */
.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: .7rem;
    color: var(--text-dim)
}

.meta-chip i {
    color: var(--accent);
    font-size: .65rem
}

/* ── Quick links ─────────────────────────────────────────────── */
.link-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .52rem .8rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--accent);
    text-decoration: none;
    font-size: .8rem;
    transition: all .2s
}

.link-item:hover {
    background: var(--accent-glow);
    border-color: var(--accent)
}

/* ── Badges ──────────────────────────────────────────────────── */
.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700
}

.badge-new {
    background: rgba(37, 99, 235, .1);
    color: #3b82f6;
    border: 1px solid rgba(37, 99, 235, .2)
}

.badge-read {
    background: rgba(148, 163, 184, .1);
    color: #94a3b8;
    border: 1px solid var(--border)
}

.badge-replied {
    background: rgba(16, 185, 129, .1);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, .2)
}

.badge-archived {
    background: rgba(100, 116, 139, .1);
    color: #64748b;
    border: 1px solid rgba(100, 116, 139, .2)
}

/* ── Buttons ─────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .52rem 1.05rem;
    border-radius: 8px;
    font-family: var(--font-body);
    font-size: .82rem;
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
    box-shadow: 0 4px 14px var(--accent-glow)
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

.btn-danger {
    border-color: rgba(239, 68, 68, .3);
    color: #f87171;
    background: rgba(239, 68, 68, .04)
}

.btn-danger:hover {
    background: var(--danger);
    color: #fff;
    border-color: var(--danger)
}

.btn:disabled {
    opacity: .5;
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
    gap: .5rem;
    padding: .65rem 1rem;
    border-radius: 9px;
    font-size: .82rem;
    font-weight: 500;
    pointer-events: all;
    animation: toastIn .25s ease;
    transition: opacity .3s, transform .3s;
    max-width: 320px;
    backdrop-filter: blur(10px)
}

.toast-success {
    background: rgba(16, 185, 129, .15);
    border: 1px solid rgba(16, 185, 129, .3);
    color: #34d399
}

.toast-error {
    background: rgba(239, 68, 68, .15);
    border: 1px solid rgba(239, 68, 68, .3);
    color: #f87171
}

.toast-info {
    background: rgba(59, 130, 246, .15);
    border: 1px solid rgba(59, 130, 246, .3);
    color: #93c5fd
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateX(18px)
    }

    to {
        opacity: 1;
        transform: translateX(0)
    }
}

/* ── Section divider ─────────────────────────────────────────── */
.sec-label {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-muted);
    padding: .5rem 1.3rem .3rem;
    border-top: 1px solid var(--border);
    margin-top: .2rem
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
                        <span>Visualizar</span>
                    </div>
                    <h1 class="page-title"><?= e($msg['subject_msg']) ?></h1>
                    <div class="page-sub">
                        <span>De <strong style="color:var(--text)"><?= e($msg['name_msg']) ?></strong></span>
                        <span style="color:var(--border)">·</span>
                        <span><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                        <span style="color:var(--border)">·</span>
                        <span class="badge-status <?= $si['cls'] ?>" id="statusBadge">
                            <i class="fas <?= $si['icon'] ?>"></i> <?= $si['label'] ?>
                        </span>
                        <?php if (!empty($msg['is_starred']) && $msg['is_starred']): ?>
                        <span style="color:#f59e0b;font-size:.8rem"><i class="fas fa-star"></i></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acções -->
                <div class="action-bar">
                    <a href="<?= BASE_URL ?>/jm-panel/messages/reply?id=<?= $id ?>" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Responder
                    </a>
                    <button class="btn btn-secondary" id="starBtn" data-id="<?= $id ?>"
                        data-starred="<?= (int)($msg['is_starred'] ?? 0) ?>">
                        <i class="fas fa-star"
                            style="color:<?= ($msg['is_starred'] ?? 0) ? '#f59e0b' : 'var(--text-muted)' ?>"></i>
                        <span><?= ($msg['is_starred'] ?? 0) ? 'Favorito' : 'Marcar' ?></span>
                    </button>
                    <button class="btn btn-secondary" id="readBtn" data-id="<?= $id ?>"
                        data-status="<?= $msg['status_msg'] ?>">
                        <i class="fas fa-<?= $msg['status_msg'] === 'new' ? 'check' : 'envelope' ?>"></i>
                        <span><?= $msg['status_msg'] === 'new' ? 'Marcar lida' : 'Não lida' ?></span>
                    </button>
                    <button class="btn btn-secondary" id="archiveBtn" data-id="<?= $id ?>"
                        data-archived="<?= $msg['status_msg'] === 'archived' ? '1' : '0' ?>">
                        <i class="fas fa-<?= $msg['status_msg'] === 'archived' ? 'rotate-left' : 'box-archive' ?>"></i>
                        <span><?= $msg['status_msg'] === 'archived' ? 'Restaurar' : 'Arquivar' ?></span>
                    </button>
                    <button class="btn btn-danger" id="deleteBtn" data-id="<?= $id ?>"
                        data-title="<?= e($msg['name_msg']) ?>">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>

            <!-- Navegação anterior / próxima -->
            <div class="msg-nav">
                <?php if ($prevId): ?>
                <a href="<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $prevId ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
                <?php else: ?>
                <span class="nav-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span class="nav-info">Mensagem <strong>#<?= $id ?></strong></span>

                <?php if ($nextId): ?>
                <a href="<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $nextId ?>" class="nav-btn">
                    Próxima <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="nav-btn disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>

            <!-- Grid principal -->
            <div class="view-grid">

                <!-- ═══ COLUNA ESQUERDA: Mensagem ═══════════════════════════ -->
                <div>
                    <!-- Remetente + corpo -->
                    <div class="vcard">
                        <div class="sender-avatar-wrap">
                            <div class="sender-avatar"><?= e($initials) ?></div>
                            <div style="flex:1;min-width:0">
                                <div class="sender-name"><?= e($msg['name_msg']) ?></div>
                                <div class="sender-email"><?= e($msg['email_msg']) ?></div>
                            </div>
                            <div class="sender-date">
                                <div><?= date('d/m/Y', strtotime($msg['created_at'])) ?></div>
                                <div style="font-size:.7rem;margin-top:.15rem">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>

                        <!-- Assunto -->
                        <div
                            style="padding:.65rem 1.3rem;border-bottom:1px solid var(--border);background:rgba(255,255,255,.01)">
                            <span
                                style="font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">Assunto</span>
                            <div style="font-size:.9rem;font-weight:700;margin-top:.15rem"><?= e($msg['subject_msg']) ?>
                            </div>
                        </div>

                        <!-- Corpo -->
                        <div class="msg-text"><?= nl2br(e($msg['message_msg'])) ?></div>
                    </div>

                    <!-- Resposta anterior (se existir) -->
                    <?php if (!empty($msg['reply_body'])): ?>
                    <div class="vcard">
                        <div class="vcard-head">
                            <div class="vcard-icon green"><i class="fas fa-reply-all"></i></div>
                            <div>
                                <h3>Resposta Enviada</h3>
                                <p>
                                    <?php if (!empty($msg['replied_at'])): ?>
                                    <?= date('d/m/Y \à\s H:i', strtotime($msg['replied_at'])) ?>
                                    <?php else: ?>
                                    Data não registada
                                    <?php endif; ?>
                                    <?php if (!empty($msg['reply_subject'])): ?>
                                    · <?= e($msg['reply_subject']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="margin-left:auto">
                                <a href="<?= BASE_URL ?>/jm-panel/messages/reply?id=<?= $id ?>"
                                    class="btn btn-secondary" style="padding:.3rem .7rem;font-size:.75rem">
                                    <i class="fas fa-reply"></i> Responder novamente
                                </a>
                            </div>
                        </div>
                        <div class="reply-thread">
                            <div class="reply-thread-hd">
                                <i class="fas fa-user-tie" style="color:var(--accent)"></i>
                                <strong><?= e($adminName) ?></strong>
                                <span style="color:var(--border)">·</span>
                                <span><?= e($adminEmail) ?></span>
                            </div>
                            <div class="reply-thread-body"><?= nl2br(e($msg['reply_body'])) ?></div>
                        </div>
                    </div>
                    <?php elseif ($msg['status_msg'] === 'replied'): ?>
                    <!-- Respondida mas sem corpo guardado (sistema antigo) -->
                    <div class="vcard">
                        <div class="vcard-head">
                            <div class="vcard-icon green"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <h3>Mensagem Respondida</h3>
                                <p>
                                    <?php if (!empty($msg['replied_at'])): ?>
                                    Respondida em <?= date('d/m/Y H:i', strtotime($msg['replied_at'])) ?>
                                    <?php else: ?>
                                    Resposta registada sem data
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="margin-left:auto">
                                <a href="<?= BASE_URL ?>/jm-panel/messages/reply?id=<?= $id ?>"
                                    class="btn btn-secondary" style="padding:.3rem .7rem;font-size:.75rem">
                                    <i class="fas fa-reply"></i> Responder novamente
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ COLUNA DIREITA: Sidebar ════════════════════════════ -->
                <div>

                    <!-- Info do remetente -->
                    <div class="vcard">
                        <div class="vcard-head">
                            <div class="vcard-icon"><i class="fas fa-user-circle"></i></div>
                            <div>
                                <h3>Remetente</h3>
                            </div>
                        </div>
                        <div class="vcard-body" style="padding:.8rem 1.3rem">
                            <div class="info-list">
                                <div class="info-row">
                                    <span class="ik">Nome</span>
                                    <span class="iv"><?= e($msg['name_msg']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="ik">Email</span>
                                    <span class="iv"><a
                                            href="mailto:<?= e($msg['email_msg']) ?>"><?= e($msg['email_msg']) ?></a></span>
                                </div>
                                <?php if (!empty($msg['phone_msg'])): ?>
                                <div class="info-row">
                                    <span class="ik">Telefone</span>
                                    <span class="iv"><a
                                            href="tel:<?= e(preg_replace('/\s+/', '', $msg['phone_msg'])) ?>"><?= e($msg['phone_msg']) ?></a></span>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="ik">Estado</span>
                                    <span class="badge-status <?= $si['cls'] ?>" id="infoStatus">
                                        <i class="fas <?= $si['icon'] ?>"></i> <?= $si['label'] ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="ik">Recebida</span>
                                    <span class="iv"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="ik">ID</span>
                                    <code>#<?= $msg['id'] ?></code>
                                </div>
                                <?php if (!empty($msg['replied_at'])): ?>
                                <div class="info-row">
                                    <span class="ik">Respondida</span>
                                    <span class="iv"
                                        style="color:#34d399"><?= date('d/m/Y H:i', strtotime($msg['replied_at'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Metadata técnica -->
                        <?php if (!empty($msg['ip_address']) || $browser || $os): ?>
                        <div class="sec-label">Metadata técnica</div>
                        <div class="vcard-body" style="padding:.6rem 1.3rem 1rem">
                            <div class="info-list">
                                <?php if (!empty($msg['ip_address'])): ?>
                                <div class="info-row">
                                    <span class="ik">IP</span>
                                    <code><?= e($msg['ip_address']) ?></code>
                                </div>
                                <?php endif; ?>
                                <?php if ($browser): ?>
                                <div class="info-row">
                                    <span class="ik">Browser</span>
                                    <span class="meta-chip"><i class="fas fa-globe"></i><?= e($browser) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($os): ?>
                                <div class="info-row">
                                    <span class="ik">Sistema</span>
                                    <span class="meta-chip"><i class="fas fa-desktop"></i><?= e($os) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Links rápidos -->
                        <div class="sec-label">Acções rápidas</div>
                        <div class="vcard-body"
                            style="padding:.6rem 1.3rem 1rem;display:flex;flex-direction:column;gap:.4rem">
                            <a href="mailto:<?= e($msg['email_msg']) ?>" class="link-item">
                                <i class="fas fa-envelope"></i> Enviar email directo
                            </a>
                            <?php if (!empty($msg['phone_msg'])): ?>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', $msg['phone_msg'])) ?>" class="link-item">
                                <i class="fas fa-phone"></i> Ligar
                            </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/jm-panel/messages/reply?id=<?= $id ?>" class="link-item">
                                <i class="fas fa-reply"></i> Responder por email
                            </a>
                        </div>
                    </div>

                    <!-- Card de navegação rápida -->
                    <div class="vcard">
                        <div class="vcard-head">
                            <div class="vcard-icon purple"><i class="fas fa-arrows-left-right"></i></div>
                            <div>
                                <h3>Navegar</h3>
                            </div>
                        </div>
                        <div class="vcard-body" style="display:flex;gap:.5rem">
                            <?php if ($prevId): ?>
                            <a href="<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $prevId ?>" class="btn btn-secondary"
                                style="flex:1;justify-content:center">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary" disabled style="flex:1;justify-content:center">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </button>
                            <?php endif; ?>
                            <?php if ($nextId): ?>
                            <a href="<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $nextId ?>" class="btn btn-secondary"
                                style="flex:1;justify-content:center">
                                Próxima <i class="fas fa-arrow-right"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary" disabled style="flex:1;justify-content:center">
                                Próxima <i class="fas fa-arrow-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <div id="toast-container"></div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    const BASE = '<?= BASE_URL ?>';
    const CSRF = '<?= $csrfToken ?>';
    const MSG_ID = <?= $id ?>;

    // ── Toast ─────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        const icons = {
            success: 'circle-check',
            error: 'circle-xmark',
            info: 'circle-info'
        };
        t.innerHTML = `<i class="fas fa-${icons[type] || 'circle-info'}"></i> ${msg}`;
        c.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(18px)';
            setTimeout(() => t.remove(), 320);
        }, 4200);
    }

    // ── Status map (4 cores distintas) ───────────────────────────
    const STATUS_MAP = {
        new: {
            cls: 'badge-new',
            icon: 'fa-envelope',
            label: 'Nova'
        },
        read: {
            cls: 'badge-read',
            icon: 'fa-check',
            label: 'Lida'
        },
        replied: {
            cls: 'badge-replied',
            icon: 'fa-reply',
            label: 'Respondida'
        },
        archived: {
            cls: 'badge-archived',
            icon: 'fa-box-archive',
            label: 'Arquivada'
        },
    };

    function updateStatusBadge(newStatus) {
        const s = STATUS_MAP[newStatus] || STATUS_MAP.read;
        document.querySelectorAll('#statusBadge, #infoStatus').forEach(el => {
            el.className = 'badge-status ' + s.cls;
            el.innerHTML = `<i class="fas ${s.icon}"></i> ${s.label}`;
        });
    }

    // ── AJAX helper ───────────────────────────────────────────────
    async function apiPost(endpoint) {
        const res = await fetch(BASE + endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CSRF
            }
        });
        return res.json();
    }

    // ── Favorito ──────────────────────────────────────────────────
    document.getElementById('starBtn').addEventListener('click', async function() {
        const icon = this.querySelector('i');
        const span = this.querySelector('span');
        const starred = this.dataset.starred === '1';

        // Optimistic UI
        icon.style.color = starred ? 'var(--text-muted)' : '#f59e0b';
        this.dataset.starred = starred ? '0' : '1';
        span.textContent = starred ? 'Marcar' : 'Favorito';

        try {
            const data = await apiPost('/jm-panel/messages/toggle-star?id=' + MSG_ID);
            if (!data.success) {
                icon.style.color = starred ? '#f59e0b' : 'var(--text-muted)';
                this.dataset.starred = starred ? '1' : '0';
                span.textContent = starred ? 'Favorito' : 'Marcar';
                showToast('Erro ao alterar favorito.', 'error');
            } else {
                showToast(data.is_starred ? 'Adicionado aos favoritos.' : 'Removido dos favoritos.',
                    'success');
            }
        } catch {
            icon.style.color = starred ? '#f59e0b' : 'var(--text-muted)';
            this.dataset.starred = starred ? '1' : '0';
            span.textContent = starred ? 'Favorito' : 'Marcar';
            showToast('Erro de rede.', 'error');
        }
    });

    // ── Marcar como lida / não lida ───────────────────────────────
    document.getElementById('readBtn').addEventListener('click', async function() {
        const icon = this.querySelector('i');
        const span = this.querySelector('span');
        try {
            const data = await apiPost('/jm-panel/messages/toggle-read?id=' + MSG_ID);
            if (data.success) {
                const ns = data.new_status;
                this.dataset.status = ns;
                icon.className = 'fas fa-' + (ns === 'new' ? 'check' : 'envelope');
                span.textContent = ns === 'new' ? 'Marcar lida' : 'Não lida';
                updateStatusBadge(ns);
                showToast(ns === 'read' ? 'Marcada como lida.' : 'Marcada como não lida.', 'success');
            } else {
                showToast(data.message || 'Erro ao alterar estado.', 'error');
            }
        } catch {
            showToast('Erro de rede.', 'error');
        }
    });

    // ── Arquivar / Restaurar ──────────────────────────────────────
    document.getElementById('archiveBtn').addEventListener('click', async function() {
        const icon = this.querySelector('i');
        const span = this.querySelector('span');
        const isArchived = this.dataset.archived === '1';

        // Usar endpoints distintos
        const endpoint = isArchived ?
            '/jm-panel/messages/unarchive?id=' + MSG_ID :
            '/jm-panel/messages/archive?id=' + MSG_ID;

        try {
            const data = await apiPost(endpoint);
            if (data.success) {
                const nowArchived = data.new_status === 'archived';
                this.dataset.archived = nowArchived ? '1' : '0';
                icon.className = 'fas fa-' + (nowArchived ? 'rotate-left' : 'box-archive');
                span.textContent = nowArchived ? 'Restaurar' : 'Arquivar';
                updateStatusBadge(data.new_status);
                // Sincronizar readBtn
                const readBtn = document.getElementById('readBtn');
                if (readBtn) readBtn.dataset.status = data.new_status;
                showToast(nowArchived ? 'Mensagem arquivada.' : 'Mensagem restaurada.', 'success');
            } else {
                showToast(data.message || 'Erro ao arquivar.', 'error');
            }
        } catch {
            showToast('Erro de rede.', 'error');
        }
    });

    // ── Eliminar ──────────────────────────────────────────────────
    document.getElementById('deleteBtn').addEventListener('click', function() {
        const title = this.dataset.title || 'esta mensagem';
        const doDelete = async () => {
            try {
                const data = await apiPost('/jm-panel/messages/delete?id=' + MSG_ID);
                if (data.success) {
                    showToast('Mensagem eliminada. A redirecionar…', 'success');
                    setTimeout(() => window.location.href = BASE + '/jm-panel/messages', 900);
                } else {
                    showToast(data.message || 'Erro ao eliminar.', 'error');
                }
            } catch {
                showToast('Erro de rede.', 'error');
            }
        };

        if (typeof Swal === 'undefined') {
            if (confirm('Eliminar mensagem de "' + title + '"?')) doDelete();
            return;
        }
        Swal.fire({
            title: 'Eliminar mensagem?',
            html: `<span style="color:var(--text-dim);font-size:.9rem">Vais eliminar permanentemente a mensagem de <strong style="color:var(--text)">"${title}"</strong>.<br>Esta ação não pode ser desfeita.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> Sim, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            background: 'var(--bg-card)',
            color: 'var(--text)',
        }).then(r => {
            if (r.isConfirmed) doDelete();
        });
    });

    // ── Atalhos de teclado ────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        <?php if ($prevId): ?>
        if (e.key === 'ArrowLeft') window.location.href =
            '<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $prevId ?>';
        <?php endif; ?>
        <?php if ($nextId): ?>
        if (e.key === 'ArrowRight') window.location.href =
            '<?= BASE_URL ?>/jm-panel/messages/view?id=<?= $nextId ?>';
        <?php endif; ?>
        if (e.key === 'r' || e.key === 'R') window.location.href =
            '<?= BASE_URL ?>/jm-panel/messages/reply?id=<?= $id ?>';
        if (e.key === 'Backspace') window.location.href = '<?= BASE_URL ?>/jm-panel/messages';
    });
    </script>
</body>

</html>