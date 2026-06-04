<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Perfil do Admin (v2 — Completo)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db      = $GLOBALS['pdo'];
$adminId = (int) $_SESSION['admin_id'];

// ── Dados completos do admin ──────────────────────────────────
$stmt = $db->prepare("
    SELECT e.*,
           s.recovery_key, s.login_attempts, s.block_until, s.block_level,
           s.last_login_at, s.last_login_ip, s.lockscreen, s.access_code,
           s.invite_token, s.invite_token_expires, s.invite_used, s.invited_by,
           s.remember_token, s.creat_sec_emp, s.modif_sec_emp
    FROM _employees e
    LEFT JOIN _employees_security s ON s.id_employees = e.id_employees
    WHERE e.id_employees = ?
");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) redirect('/jm-panel/logout');

$adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['second_name'] ?? ''));
if ($adminName === '') {
    $adminName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
}
$adminEmail = $admin['email_employees'] ?? $_SESSION['admin_email'] ?? '';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
if ($adminInitial === '') $adminInitial = 'A';
$adminPhoto = !empty($admin['photo_employees']) && file_exists(ROOT_PATH . '/assets/img/profile/' . $admin['photo_employees'])
    ? $admin['photo_employees']
    : null;

// ── Quem o convidou? ─────────────────────────────────────────
$invitedByName = null;
if (!empty($admin['invited_by'])) {
    $iStmt = $db->prepare("SELECT CONCAT(first_name,' ',COALESCE(second_name,'')) FROM _employees WHERE id_employees = ?");
    $iStmt->execute([$admin['invited_by']]);
    $invitedByName = trim($iStmt->fetchColumn() ?: '');
}

// ── Últimas 20 actividades (auditoria) ───────────────────────
$auditStmt = $db->prepare("
    SELECT * FROM _audit_log
    WHERE id_employees = ?
    ORDER BY creat_log DESC
    LIMIT 20
");
$auditStmt->execute([$adminId]);
$auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Contadores globais ────────────────────────────────────────
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$totalAudit     = (int) $db->query("SELECT COUNT(*) FROM _audit_log WHERE id_employees = $adminId")->fetchColumn();

// ── Sessão ────────────────────────────────────────────────────
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);
$sessionHr   = floor($sessionMins / 60);
$sessionMin  = $sessionMins % 60;
$sessionStr  = $sessionHr > 0 ? "{$sessionHr}h {$sessionMin}m" : "{$sessionMin}m";

// ── Feedback de operações ─────────────────────────────────────
$formData    = $_SESSION['form_data']    ?? [];
$formErrors  = $_SESSION['form_errors']  ?? [];
$formSuccess = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['form_success']);
if (empty($formData)) $formData = $admin;

// ── Labels ────────────────────────────────────────────────────
$statusLabel = match ($admin['status_employees']) {
    'active'   => ['label' => 'Activo',    'class' => 'badge-success'],
    'inactive' => ['label' => 'Inactivo',  'class' => 'badge-warning'],
    'blocked'  => ['label' => 'Bloqueado', 'class' => 'badge-danger'],
    default    => ['label' => 'Desconhecido', 'class' => 'badge-muted'],
};
$roleLabel = match ($admin['role'] ?? 'admin') {
    'super_admin' => ['label' => 'Super Admin', 'class' => 'role-super', 'icon' => 'fa-crown'],
    'admin'       => ['label' => 'Admin',        'class' => 'role-admin', 'icon' => 'fa-shield-alt'],
    'editor'      => ['label' => 'Editor',       'class' => 'role-editor', 'icon' => 'fa-pen-nib'],
    default       => ['label' => 'Staff',        'class' => 'role-editor', 'icon' => 'fa-user'],
};
$blockLabel = match ((int) $admin['block_level']) {
    1 => '5 minutos',
    2 => '15 minutos',
    3 => '30 minutos',
    default => 'Sem bloqueio',
};

// Iniciais para avatar placeholder
$initials = strtoupper(mb_substr($admin['first_name'], 0, 1) . mb_substr($admin['second_name'] ?? '', 0, 1));
if (mb_strlen($initials) < 1) $initials = '??';

$hasPhoto    = !empty($admin['photo_employees']) && file_exists(ROOT_PATH . '/assets/img/profile/' . $admin['photo_employees']);
$photoUrl    = $hasPhoto ? BASE_URL . '/assets/img/profile/' . e($admin['photo_employees']) : null;
$memberSince = !empty($admin['creat_employees']) ? date('d/m/Y', strtotime($admin['creat_employees'])) : '—';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>
<style>
/* ── Reset / Variables ───────────────────────────────────── */
:root {
    --profile-radius: 14px;
    --tab-h: 44px;
}

/* ── Layout ──────────────────────────────────────────────── */
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

.profile-layout {
    display: grid;
    grid-template-columns: 290px 1fr;
    gap: 1.5rem;
    align-items: start
}

@media(max-width:960px) {
    .profile-layout {
        grid-template-columns: 1fr
    }
}

/* ── Cards ───────────────────────────────────────────────── */
.pcard {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--profile-radius);
    padding: 1.5rem;
    margin-bottom: 1.25rem
}

.pcard:last-child {
    margin-bottom: 0
}

.pcard-title {
    font-family: var(--font-head);
    font-size: .85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem
}

.pcard-title i {
    color: var(--accent);
    font-size: .8rem
}

/* ── Sidebar ─────────────────────────────────────────────── */
.avatar-zone {
    position: relative;
    width: 96px;
    height: 96px;
    margin: 0 auto 1rem;
    cursor: pointer
}

.avatar-zone input[type=file] {
    display: none
}

.avatar-ring {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    overflow: hidden;
    border: 2.5px solid var(--border-acc);
    transition: border-color .25s
}

.avatar-zone:hover .avatar-ring {
    border-color: var(--accent)
}

.avatar-zone:hover .avatar-overlay {
    opacity: 1
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-head);
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    user-select: none
}

.avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, .55);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .2s;
    color: #fff;
    font-size: .7rem;
    gap: .2rem;
    pointer-events: none
}

.avatar-overlay i {
    font-size: 1.1rem
}

.avatar-zone.drag-over .avatar-ring {
    border-color: var(--accent);
    box-shadow: 0 0 0 4px var(--accent-glow)
}

.admin-name {
    font-family: var(--font-head);
    font-size: 1.1rem;
    font-weight: 800;
    text-align: center;
    margin-bottom: .15rem
}

.admin-email {
    font-size: .78rem;
    color: var(--text-muted);
    text-align: center;
    margin-bottom: .75rem;
    word-break: break-all
}

/* ── Badges ──────────────────────────────────────────────── */
.badge-row {
    display: flex;
    gap: .4rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 1rem
}

.badge-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .02em
}

.badge-success {
    background: rgba(16, 185, 129, .12);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, .25)
}

.badge-warning {
    background: rgba(245, 158, 11, .12);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, .25)
}

.badge-danger {
    background: rgba(239, 68, 68, .12);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, .25)
}

.badge-muted {
    background: rgba(148, 163, 184, .12);
    color: var(--text-muted);
    border: 1px solid var(--border)
}

.role-super {
    background: rgba(251, 191, 36, .1);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, .2)
}

.role-admin {
    background: rgba(59, 130, 246, .1);
    color: var(--accent);
    border: 1px solid rgba(59, 130, 246, .2)
}

.role-editor {
    background: rgba(139, 92, 246, .1);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, .2)
}

/* ── Mini stats ──────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .6rem;
    margin-bottom: 1rem
}

.stat-box {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .65rem .6rem;
    text-align: center
}

.stat-box .stat-val {
    font-family: var(--font-head);
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: .15rem
}

.stat-box .stat-lbl {
    font-size: .65rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .04em
}

.stat-box.accent .stat-val {
    color: var(--accent)
}

.stat-box.success .stat-val {
    color: var(--success)
}

.stat-box.warn .stat-val {
    color: #fbbf24
}

.stat-box.purple .stat-val {
    color: #a78bfa
}

/* ── Meta info ────────────────────────────────────────────── */
.meta-list {
    display: flex;
    flex-direction: column;
    gap: 0
}

.meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .45rem 0;
    border-bottom: 1px solid var(--border);
    font-size: .78rem;
    gap: .5rem
}

.meta-row:last-child {
    border-bottom: none
}

.meta-row .meta-key {
    color: var(--text-muted);
    flex-shrink: 0
}

.meta-row .meta-val {
    color: var(--text);
    font-weight: 500;
    text-align: right;
    word-break: break-all
}

.meta-row code {
    font-family: var(--font-mono);
    font-size: .72rem;
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--accent);
    word-break: break-all
}

/* ── Sidebar actions ─────────────────────────────────────── */
.sidebar-actions {
    display: flex;
    flex-direction: column;
    gap: .4rem
}

.slink {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .5rem .8rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: .8rem;
    transition: all .2s;
    cursor: pointer
}

.slink:hover {
    background: var(--accent-glow);
    border-color: var(--border-acc);
    color: var(--accent)
}

.slink i {
    width: 14px;
    text-align: center;
    color: var(--accent)
}

/* ── Tabs ────────────────────────────────────────────────── */
.tab-bar {
    display: flex;
    gap: 0;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 1.5rem;
    overflow-x: auto
}

.tab-btn {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem 1rem;
    border-radius: 7px;
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-dim);
    background: none;
    border: none;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
    flex-shrink: 0
}

.tab-btn:hover {
    color: var(--text);
    background: var(--bg-hover)
}

.tab-btn.active {
    background: var(--bg-card);
    color: var(--text);
    border: 1px solid var(--border);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .2)
}

.tab-btn .tab-count {
    background: var(--accent-glow);
    color: var(--accent);
    border: 1px solid var(--border-acc);
    font-size: .62rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 999px;
    min-width: 18px;
    text-align: center
}

.tab-panel {
    display: none
}

.tab-panel.active {
    display: block
}

/* ── Forms ───────────────────────────────────────────────── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.1rem
}

.form-grid .full {
    grid-column: 1/-1
}

@media(max-width:600px) {
    .form-grid {
        grid-template-columns: 1fr
    }

    .form-grid .full {
        grid-column: 1
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: .3rem
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
    padding: .6rem .85rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font-body);
    font-size: .85rem;
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

.form-control.error {
    border-color: var(--danger)
}

.form-hint {
    font-size: .7rem;
    color: var(--text-muted);
    margin-top: .15rem
}

.form-actions {
    display: flex;
    gap: .6rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border)
}

/* ── Buttons ─────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .55rem 1.1rem;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    border: 1px solid var(--border);
    white-space: nowrap;
    font-family: var(--font-body)
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

.btn-danger {
    background: rgba(239, 68, 68, .1);
    border-color: rgba(239, 68, 68, .3);
    color: #f87171
}

.btn-danger:hover {
    background: rgba(239, 68, 68, .2)
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
    padding: .65rem 1.4rem;
    font-size: .88rem
}

.btn-sm {
    padding: .35rem .7rem;
    font-size: .75rem
}

.btn:disabled {
    opacity: .55;
    cursor: not-allowed;
    pointer-events: none
}

/* ── Security panel ──────────────────────────────────────── */
.sec-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem
}

@media(max-width:660px) {
    .sec-grid {
        grid-template-columns: 1fr
    }
}

.sec-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    align-items: flex-start;
    gap: .75rem
}

.sec-block.danger-border {
    border-color: rgba(239, 68, 68, .3)
}

.sec-block.success-border {
    border-color: rgba(16, 185, 129, .3)
}

.sec-icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: var(--accent-glow);
    border: 1px solid var(--border-acc);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: .85rem;
    flex-shrink: 0
}

.sec-icon.danger {
    background: rgba(239, 68, 68, .08);
    border-color: rgba(239, 68, 68, .2);
    color: #f87171
}

.sec-icon.success {
    background: rgba(16, 185, 129, .08);
    border-color: rgba(16, 185, 129, .2);
    color: #34d399
}

.sec-icon.warn {
    background: rgba(245, 158, 11, .08);
    border-color: rgba(245, 158, 11, .2);
    color: #fbbf24
}

.sec-icon.purple {
    background: rgba(139, 92, 246, .08);
    border-color: rgba(139, 92, 246, .2);
    color: #a78bfa
}

.sec-body {
    flex: 1;
    min-width: 0
}

.sec-body strong {
    display: block;
    font-size: .82rem;
    font-weight: 700;
    margin-bottom: .2rem
}

.sec-body p {
    font-size: .77rem;
    color: var(--text-muted);
    margin: 0 0 .5rem;
    line-height: 1.4
}

/* Toggle switch */
.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem
}

.toggle {
    position: relative;
    width: 40px;
    height: 22px;
    flex-shrink: 0
}

.toggle input {
    opacity: 0;
    width: 0;
    height: 0
}

.toggle-track {
    position: absolute;
    inset: 0;
    border-radius: 11px;
    background: var(--border);
    transition: background .25s;
    cursor: pointer
}

.toggle-track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    transition: transform .25s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .3)
}

.toggle input:checked+.toggle-track {
    background: var(--accent)
}

.toggle input:checked+.toggle-track::after {
    transform: translateX(18px)
}

/* Recovery key field */
.secret-field {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-top: .4rem
}

.secret-value {
    font-family: var(--font-mono);
    font-size: .8rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .4rem .6rem;
    color: var(--accent);
    letter-spacing: .06em;
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis
}

.secret-value.hidden {
    filter: blur(5px);
    user-select: none
}

.icon-btn {
    width: 30px;
    height: 30px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-card);
    color: var(--text-dim);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: .75rem;
    transition: all .2s;
    flex-shrink: 0
}

.icon-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--accent-glow)
}

/* Dividers */
.sec-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.25rem 0
}

/* PIN form inline */
.pin-form {
    display: flex;
    gap: .4rem;
    align-items: center;
    margin-top: .5rem
}

.pin-input {
    width: 80px;
    padding: .4rem .5rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: var(--font-mono);
    font-size: .9rem;
    letter-spacing: .15em;
    text-align: center;
    outline: none
}

.pin-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow)
}

/* ── Audit log ───────────────────────────────────────────── */
.audit-wrap {
    display: flex;
    flex-direction: column;
    gap: 0
}

.audit-item {
    display: flex;
    gap: .75rem;
    padding: .75rem 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start
}

.audit-item:last-child {
    border-bottom: none
}

.audit-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    flex-shrink: 0;
    margin-top: .1rem
}

.audit-icon.c-create {
    background: rgba(16, 185, 129, .1);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, .2)
}

.audit-icon.c-delete {
    background: rgba(239, 68, 68, .1);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, .2)
}

.audit-icon.c-update {
    background: rgba(59, 130, 246, .1);
    color: var(--accent);
    border: 1px solid rgba(59, 130, 246, .2)
}

.audit-icon.c-login {
    background: rgba(139, 92, 246, .1);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, .2)
}

.audit-icon.c-default {
    background: rgba(148, 163, 184, .1);
    color: var(--text-muted);
    border: 1px solid var(--border)
}

.audit-body {
    flex: 1;
    min-width: 0
}

.audit-action {
    font-size: .82rem;
    font-weight: 600;
    margin-bottom: .1rem
}

.audit-meta {
    font-size: .7rem;
    color: var(--text-muted);
    display: flex;
    gap: .6rem;
    flex-wrap: wrap
}

.audit-entity {
    font-size: .68rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1px 5px;
    color: var(--text-dim);
    font-family: var(--font-mono)
}

/* ── Alert ───────────────────────────────────────────────── */
.alert {
    padding: .9rem 1.1rem;
    border-radius: 10px;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    font-size: .83rem
}

.alert-success {
    background: rgba(16, 185, 129, .08);
    border: 1px solid rgba(16, 185, 129, .25);
    color: #34d399
}

.alert-danger {
    background: rgba(239, 68, 68, .08);
    border: 1px solid rgba(239, 68, 68, .25);
    color: #f87171
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    opacity: .6;
    font-size: .85rem;
    padding: 0
}

.alert-close:hover {
    opacity: 1
}

/* ── Toast ───────────────────────────────────────────────── */
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

/* ── Section header ──────────────────────────────────────── */
.section-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.2rem;
    padding-bottom: .9rem;
    border-bottom: 1px solid var(--border)
}

.section-hd h3 {
    font-family: var(--font-head);
    font-size: .98rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: .5rem
}

.section-hd h3 i {
    color: var(--accent)
}

/* ── Upload drag zone ────────────────────────────────────── */
.drag-hint {
    font-size: .65rem;
    color: var(--text-muted);
    text-align: center;
    margin-top: .35rem
}
</style>

<body>
    <script>
    // Defina switchTab ANTES de qualquer HTML que a use
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
    }
    </script>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <!-- Cabeçalho da página -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Meu Perfil</span>
                    </div>
                    <h1 class="page-title">Meu Perfil</h1>
                </div>
                <div style="display:flex;gap:.6rem">
                    <a href="<?= BASE_URL ?>/jm-panel/profile/change-password" class="btn btn-secondary">
                        <i class="fas fa-lock"></i> Alterar Senha
                    </a>
                </div>
            </div>

            <!-- Alertas de sessão (fallback não-AJAX) -->
            <?php if ($formSuccess): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= e($formSuccess) ?>
                <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div><strong>Erros:</strong>
                    <ul style="margin:.3rem 0 0 1.2rem">
                        <?php foreach ($formErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grid principal -->
            <div class="profile-layout">

                <!-- ═══ SIDEBAR ESQUERDA ═══════════════════════════════ -->
                <div>

                    <!-- Avatar + nome -->
                    <div class="pcard" style="text-align:center">
                        <label class="avatar-zone" id="avatarZone" title="Clica ou arrasta para alterar foto">
                            <div class="avatar-ring">
                                <?php if ($photoUrl): ?>
                                <img src="<?= $photoUrl ?>" class="avatar-img" id="avatarImg">
                                <?php else: ?>
                                <div class="avatar-placeholder" id="avatarPlaceholder"><?= e($initials) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Alterar</span>
                            </div>
                            <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp">
                        </label>
                        <div class="drag-hint"><i class="fas fa-arrow-up-from-bracket"></i> Clica ou arrasta uma imagem
                        </div>

                        <div class="admin-name" style="margin-top:.8rem">
                            <?= e(trim($admin['first_name'] . ' ' . ($admin['second_name'] ?? ''))) ?>
                        </div>
                        <div class="admin-email"><?= e($admin['email_employees']) ?></div>

                        <div class="badge-row">
                            <span class="badge-pill <?= $roleLabel['class'] ?>">
                                <i class="fas <?= $roleLabel['icon'] ?>"></i> <?= $roleLabel['label'] ?>
                            </span>
                            <span class="badge-pill <?= $statusLabel['class'] ?>">
                                <i class="fas fa-circle" style="font-size:.4rem"></i> <?= $statusLabel['label'] ?>
                            </span>
                        </div>

                        <div class="sidebar-actions">
                            <a href="#" class="slink" onclick="switchTab('perfil'); return false">
                                <i class="fas fa-user-edit"></i> Editar Dados Pessoais
                            </a>
                            <a href="#" class="slink" onclick="switchTab('seguranca'); return false">
                                <i class="fas fa-shield-halved"></i> Gerir Segurança
                            </a>
                            <a href="#" class="slink" onclick="switchTab('actividade'); return false">
                                <i class="fas fa-timeline"></i> Ver Actividade
                            </a>
                            <a href="<?= BASE_URL ?>/jm-panel/profile/change-password" class="slink">
                                <i class="fas fa-key"></i> Alterar Senha
                            </a>
                        </div>
                    </div>

                    <!-- Mini stats -->
                    <div class="pcard">
                        <div class="pcard-title"><i class="fas fa-chart-simple"></i> Estatísticas</div>
                        <div class="stat-grid">
                            <div class="stat-box accent">
                                <div class="stat-val"><?= $totalProjects ?></div>
                                <div class="stat-lbl">Projectos</div>
                            </div>
                            <div class="stat-box success">
                                <div class="stat-val"><?= $onlineVisitors ?></div>
                                <div class="stat-lbl">Online</div>
                            </div>
                            <div class="stat-box warn">
                                <div class="stat-val"><?= $unreadMessages ?></div>
                                <div class="stat-lbl">Msgs novas</div>
                            </div>
                            <div class="stat-box purple">
                                <div class="stat-val"><?= $totalAudit ?></div>
                                <div class="stat-lbl">Actividades</div>
                            </div>
                        </div>
                    </div>

                    <!-- Conta: datas e IDs -->
                    <div class="pcard">
                        <div class="pcard-title"><i class="fas fa-info-circle"></i> Conta</div>
                        <div class="meta-list">
                            <div class="meta-row">
                                <span class="meta-key">ID</span>
                                <span class="meta-val"><code>#<?= $admin['id_employees'] ?></code></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-key">Username</span>
                                <span class="meta-val"><?= e($admin['user_employees'] ?: '—') ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-key">Membro desde</span>
                                <span class="meta-val"><?= $memberSince ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-key">Última modificação</span>
                                <span
                                    class="meta-val"><?= $admin['modif_employees'] ? date('d/m/Y H:i', strtotime($admin['modif_employees'])) : '—' ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-key">Sessão actual</span>
                                <span class="meta-val"><?= $sessionStr ?></span>
                            </div>
                            <?php if ($invitedByName): ?>
                            <div class="meta-row">
                                <span class="meta-key">Convidado por</span>
                                <span class="meta-val"><?= e($invitedByName) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /sidebar -->

                <!-- ═══ CONTEÚDO PRINCIPAL ══════════════════════════════ -->
                <div>

                    <!-- Tabs -->
                    <div class="tab-bar" role="tablist">
                        <button class="tab-btn active" onclick="switchTab('perfil')" data-tab="perfil" role="tab">
                            <i class="fas fa-user"></i> Pessoal
                        </button>
                        <button class="tab-btn" onclick="switchTab('seguranca')" data-tab="seguranca" role="tab">
                            <i class="fas fa-shield-halved"></i> Segurança
                            <?php if ((int)$admin['login_attempts'] > 0): ?>
                            <span class="tab-count"><?= (int)$admin['login_attempts'] ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="tab-btn" onclick="switchTab('actividade')" data-tab="actividade" role="tab">
                            <i class="fas fa-clock-rotate-left"></i> Actividade
                            <?php if ($totalAudit > 0): ?>
                            <span class="tab-count"><?= $totalAudit ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- ─── TAB 1: PESSOAL ──────────────────────────── -->
                    <div class="tab-panel active" id="tab-perfil">
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-user-circle"></i> Informações Pessoais</h3>
                            </div>
                            <form id="profileForm" method="post" action="<?= BASE_URL ?>/jm-panel/profile-process"
                                novalidate data-api="<?= BASE_URL ?>/jm-panel/profile-process"
                                enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                                <input type="hidden" name="photo_data" id="photoData">

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Primeiro Nome <span class="req">*</span></label>
                                        <input type="text" name="first_name" class="form-control"
                                            value="<?= e($formData['first_name'] ?? '') ?>" placeholder="José" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Apelido</label>
                                        <input type="text" name="second_name" class="form-control"
                                            value="<?= e($formData['second_name'] ?? '') ?>" placeholder="Mbenga">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email <span class="req">*</span></label>
                                        <input type="email" name="email_employees" class="form-control"
                                            value="<?= e($formData['email_employees'] ?? '') ?>"
                                            placeholder="admin@exemplo.ao" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="user_employees" class="form-control"
                                            value="<?= e($formData['user_employees'] ?? '') ?>" placeholder="jmbenga">
                                        <span class="form-hint">Utilizado no login</span>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" name="tel_employees" class="form-control"
                                            value="<?= e($formData['tel_employees'] ?? '') ?>"
                                            placeholder="+244 9XX XXX XXX">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Género</label>
                                        <select name="gender" class="form-control">
                                            <option value="M"
                                                <?= ($formData['gender'] ?? 'M') === 'M' ? 'selected' : '' ?>>Masculino
                                            </option>
                                            <option value="F"
                                                <?= ($formData['gender'] ?? 'M') === 'F' ? 'selected' : '' ?>>Feminino
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">País <span class="form-hint"
                                                style="text-transform:none;font-size:.7rem">(ISO 2)</span></label>
                                        <input type="text" name="country_employees" class="form-control"
                                            value="<?= e($formData['country_employees'] ?? 'AO') ?>" maxlength="2"
                                            placeholder="AO" style="text-transform:uppercase">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="city_employees" class="form-control"
                                            value="<?= e($formData['city_employees'] ?? 'Luanda') ?>" maxlength="80"
                                            placeholder="Luanda">
                                    </div>
                                    <div class="form-group full">
                                        <label class="form-label">Sobre mim</label>
                                        <textarea name="about_employees" class="form-control" rows="3"
                                            placeholder="Uma breve descrição sobre ti..."><?= e($formData['about_employees'] ?? '') ?></textarea>
                                        <span class="form-hint" id="aboutCounter">0 caracteres</span>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="fas fa-rotate-left"></i> Repor
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="fas fa-floppy-disk"></i> Guardar Alterações
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ─── TAB 2: SEGURANÇA ────────────────────────── -->
                    <div class="tab-panel" id="tab-seguranca">

                        <!-- Login info -->
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-right-to-bracket"></i> Sessão & Acessos</h3>
                            </div>
                            <div class="sec-grid">
                                <div class="sec-block">
                                    <div class="sec-icon"><i class="fas fa-clock"></i></div>
                                    <div class="sec-body">
                                        <strong>Último Login</strong>
                                        <p><?= $admin['last_login_at'] ? date('d/m/Y \à\s H:i', strtotime($admin['last_login_at'])) : 'Nunca registado' ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="sec-block">
                                    <div class="sec-icon"><i class="fas fa-globe"></i></div>
                                    <div class="sec-body">
                                        <strong>IP do Último Login</strong>
                                        <p><code><?= e($admin['last_login_ip'] ?? '—') ?></code></p>
                                    </div>
                                </div>
                                <div class="sec-block <?= (int)$admin['login_attempts'] > 0 ? 'danger-border' : '' ?>">
                                    <div class="sec-icon <?= (int)$admin['login_attempts'] > 0 ? 'danger' : '' ?>"><i
                                            class="fas fa-triangle-exclamation"></i></div>
                                    <div class="sec-body">
                                        <strong>Tentativas Falhadas</strong>
                                        <p><?= (int)$admin['login_attempts'] ?> tentativa(s) registada(s)</p>
                                        <?php if ((int)$admin['login_attempts'] > 0): ?>
                                        <button class="btn btn-danger btn-sm" onclick="resetAttempts()">
                                            <i class="fas fa-eraser"></i> Limpar
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div
                                    class="sec-block <?= !empty($admin['block_until']) && strtotime($admin['block_until']) > time() ? 'danger-border' : '' ?>">
                                    <div
                                        class="sec-icon <?= !empty($admin['block_until']) && strtotime($admin['block_until']) > time() ? 'danger' : 'success' ?>">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                    <div class="sec-body">
                                        <strong>Bloqueio Actual</strong>
                                        <p><?= $blockLabel ?>
                                            <?php if (!empty($admin['block_until']) && strtotime($admin['block_until']) > time()): ?>
                                            <br><small style="color:var(--danger)">Até
                                                <?= date('H:i', strtotime($admin['block_until'])) ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lockscreen -->
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-lock-open"></i> Lockscreen</h3>
                            </div>
                            <div class="sec-block" id="lockscreenBlock" style="margin-bottom:1rem">
                                <div class="sec-icon <?= $admin['lockscreen'] ? 'success' : '' ?>"><i
                                        class="fas fa-display-lock"></i></div>
                                <div class="sec-body">
                                    <div class="toggle-row">
                                        <div>
                                            <strong>Protecção por Ecrã Bloqueado</strong>
                                            <p>Exige código PIN ao retomar a sessão inactiva.</p>
                                        </div>
                                        <label class="toggle" title="Activar/Desactivar Lockscreen">
                                            <input type="checkbox" id="lockscreenToggle"
                                                <?= $admin['lockscreen'] ? 'checked' : '' ?>
                                                onchange="toggleLockscreen(this)">
                                            <span class="toggle-track"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- PIN -->
                            <div id="pinSection" <?= $admin['lockscreen'] ? '' : 'style="display:none"' ?>>
                                <div style="font-size:.82rem;color:var(--text-dim);margin-bottom:.6rem">
                                    <i class="fas fa-hashtag" style="color:var(--accent)"></i>
                                    PIN actual: <code
                                        style="background:var(--bg);padding:2px 7px;border-radius:5px;font-family:var(--font-mono)"><?= $admin['access_code'] ? str_repeat('●', strlen($admin['access_code'])) : '—' ?></code>
                                </div>
                                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.8rem">
                                    Altera o PIN de 6 dígitos abaixo:
                                </div>
                                <div class="pin-form">
                                    <input type="password" id="newPinInput" class="pin-input" maxlength="6"
                                        minlength="6" inputmode="numeric" placeholder="••••••"
                                        autocomplete="new-password">
                                    <button class="btn btn-primary btn-sm" onclick="savePin()">
                                        <i class="fas fa-floppy-disk"></i> Guardar PIN
                                    </button>
                                    <button class="icon-btn" onclick="togglePinVis()" title="Mostrar/ocultar PIN">
                                        <i class="fas fa-eye" id="pinEyeIcon"></i>
                                    </button>
                                </div>
                                <div class="form-hint" style="margin-top:.5rem"> 6 dígitos numéricos</div>
                            </div>
                        </div>

                        <!-- Chave de recuperação -->
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-key"></i> Chave de Recuperação</h3>
                            </div>
                            <div class="sec-block">
                                <div class="sec-icon warn"><i class="fas fa-vault"></i></div>
                                <div class="sec-body">
                                    <strong>Chave de Recuperação da Conta</strong>
                                    <p>Guarda esta chave num local seguro. Permite recuperar o acesso à conta caso
                                        percas a senha.</p>
                                    <div class="secret-field">
                                        <span class="secret-value hidden" id="recoveryKeyVal">
                                            <?= e($admin['recovery_key'] ?? '—') ?>
                                        </span>
                                        <button class="icon-btn" onclick="toggleRecovery()" title="Mostrar/ocultar">
                                            <i class="fas fa-eye" id="recoveryEyeIcon"></i>
                                        </button>
                                        <button class="icon-btn" onclick="copyRecovery()" title="Copiar">
                                            <i class="fas fa-copy" id="copyIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Convite -->
                        <?php if (!empty($admin['invite_token']) || $invitedByName): ?>
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-envelope-open-text"></i> Token de Convite</h3>
                            </div>
                            <div class="sec-grid">
                                <?php if ($invitedByName): ?>
                                <div class="sec-block">
                                    <div class="sec-icon purple"><i class="fas fa-user-plus"></i></div>
                                    <div class="sec-body">
                                        <strong>Convidado por</strong>
                                        <p><?= e($invitedByName) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="sec-block">
                                    <div class="sec-icon <?= $admin['invite_used'] ? 'success' : 'warn' ?>"><i
                                            class="fas fa-ticket"></i></div>
                                    <div class="sec-body">
                                        <strong>Estado do Convite</strong>
                                        <p><?= $admin['invite_used'] ? 'Já utilizado' : 'Pendente' ?></p>
                                        <?php if (!empty($admin['invite_token_expires'])): ?>
                                        <p style="font-size:.72rem">Expira:
                                            <?= date('d/m/Y H:i', strtotime($admin['invite_token_expires'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /tab-seguranca -->

                    <!-- ─── TAB 3: ACTIVIDADE ───────────────────────── -->
                    <div class="tab-panel" id="tab-actividade">
                        <div class="pcard">
                            <div class="section-hd">
                                <h3><i class="fas fa-clock-rotate-left"></i> Últimas 20 Acções</h3>
                                <span style="font-size:.75rem;color:var(--text-muted)"><?= $totalAudit ?> total</span>
                            </div>
                            <?php if (empty($auditLogs)): ?>
                            <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.85rem">
                                <i class="fas fa-inbox"
                                    style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
                                Nenhuma actividade registada ainda.
                            </div>
                            <?php else: ?>
                            <div class="audit-wrap">
                                <?php foreach ($auditLogs as $log):
                                        $act = strtolower($log['action']);
                                        if (str_contains($act, 'delete'))      $cls = 'c-delete';
                                        elseif (str_contains($act, 'create'))  $cls = 'c-create';
                                        elseif (str_contains($act, 'login'))   $cls = 'c-login';
                                        elseif (str_contains($act, 'update') || str_contains($act, 'edit')) $cls = 'c-update';
                                        else $cls = 'c-default';

                                        $icon = match (true) {
                                            str_contains($act, 'delete') => 'fa-trash',
                                            str_contains($act, 'create') => 'fa-plus',
                                            str_contains($act, 'login')  => 'fa-right-to-bracket',
                                            str_contains($act, 'update') || str_contains($act, 'edit') => 'fa-pen',
                                            str_contains($act, 'upload') => 'fa-upload',
                                            str_contains($act, 'config') => 'fa-gear',
                                            default => 'fa-circle-dot'
                                        };

                                        $newVal = null;
                                        if (!empty($log['new_value'])) {
                                            $dec = json_decode($log['new_value'], true);
                                            if (is_array($dec)) {
                                                $keys = array_keys($dec);
                                                $newVal = implode(', ', array_slice($keys, 0, 3));
                                                if (count($keys) > 3) $newVal .= '…';
                                            }
                                        }
                                    ?>
                                <div class="audit-item">
                                    <div class="audit-icon <?= $cls ?>"><i class="fas <?= $icon ?>"></i></div>
                                    <div class="audit-body">
                                        <div class="audit-action">
                                            <?= e(ucfirst(str_replace(['_', '.'], [' ', ' '], $log['action']))) ?>
                                        </div>
                                        <div class="audit-meta">
                                            <span><?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?></span>
                                            <?php if ($log['ip_address']): ?>
                                            <span>· <i class="fas fa-location-dot"></i>
                                                <?= e($log['ip_address']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($log['entity']): ?>
                                            <span
                                                class="audit-entity"><?= e($log['entity']) ?><?= $log['entity_id'] ? ' #' . $log['entity_id'] : '' ?></span>
                                            <?php endif; ?>
                                            <?php if ($newVal): ?>
                                            <span style="color:var(--text-dim)">campos: <?= e($newVal) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div><!-- /tab-actividade -->

                </div><!-- /main content -->
            </div><!-- /profile-layout -->

        </div><!-- /content -->
    </div><!-- /main -->

    <div id="toast-container"></div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const CSRF_TOKEN = '<?= $_SESSION['admin_csrf_token'] ?>';

    // ── Toast ────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        const icons = {
            success: 'check-circle',
            error: 'circle-xmark',
            info: 'circle-info'
        };
        t.innerHTML = `<i class="fas fa-${icons[type] || 'circle-info'}"></i> ${msg}`;
        c.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(20px)';
            setTimeout(() => t.remove(), 350);
        }, 4000);
    }

    // ── Avatar: click + drag & drop ──────────────────────────────
    const avatarZone = document.getElementById('avatarZone');
    const photoInput = document.getElementById('photoInput');
    let selectedAvatarFile = null;

    avatarZone.addEventListener('dragover', e => {
        e.preventDefault();
        avatarZone.classList.add('drag-over')
    });
    avatarZone.addEventListener('dragleave', () => avatarZone.classList.remove('drag-over'));
    avatarZone.addEventListener('drop', e => {
        e.preventDefault();
        avatarZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            selectedAvatarFile = file;
            previewAvatar(file);
        }
    });

    photoInput.addEventListener('change', function() {
        if (this.files[0]) {
            selectedAvatarFile = this.files[0];
            previewAvatar(this.files[0]);
        }
    });

    function previewAvatar(file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            let el = document.getElementById('avatarImg') || document.getElementById('avatarPlaceholder');
            if (!el || el.tagName !== 'IMG') {
                const img = document.createElement('img');
                img.className = 'avatar-img';
                img.id = 'avatarImg';
                el.parentElement.replaceChild(img, el);
                el = img;
            }
            el.src = ev.target.result;
            document.getElementById('photoData').value = ev.target.result;
            showToast('Foto seleccionada. Guarda para aplicar.', 'info');
        };
        reader.readAsDataURL(file);
    }

    // ── Contador de biografia ────────────────────────────────────
    const aboutArea = document.querySelector('[name="about_employees"]');
    const aboutCnt = document.getElementById('aboutCounter');
    if (aboutArea) {
        const update = () => aboutCnt.textContent = aboutArea.value.length + ' caracteres';
        aboutArea.addEventListener('input', update);
        update();
    }

    // ── Reset form ───────────────────────────────────────────────
    function resetForm() {
        document.getElementById('profileForm').reset();
        showToast('Formulário reposto.', 'info');
    }

    // ── Submit perfil ────────────────────────────────────────────
    const profileForm = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');

    profileForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        let valid = true;
        profileForm.querySelectorAll('[required]').forEach(f => {
            const ok = f.value.trim() !== '';
            f.classList.toggle('error', !ok);
            if (!ok) valid = false;
        });
        if (!valid) {
            showToast('Preenche os campos obrigatórios.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar…';

        try {
            const fd = new FormData(profileForm);
            // Anexa o ficheiro real se selecionado
            if (selectedAvatarFile) fd.append('photo_employees', selectedAvatarFile);

            const res = await fetch(profileForm.dataset.api, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Perfil actualizado!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                (data.errors || [data.message]).forEach(m => showToast(m || 'Erro desconhecido.', 'error'));
            }
        } catch {
            showToast('Erro de rede. Tenta novamente.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-floppy-disk"></i> Guardar Alterações';
        }
    });

    // ── AJAX helper ──────────────────────────────────────────────
    async function securityAction(action, extra = {}) {
        const body = new FormData();
        body.append('action', action);
        body.append('csrf_token', CSRF_TOKEN);
        for (const [k, v] of Object.entries(extra)) body.append(k, v);
        const res = await fetch(`${BASE_URL}/jm-panel/profile-process`, {
            method: 'POST',
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CSRF_TOKEN
            }
        });
        return res.json();
    }

    // ── Lockscreen toggle ────────────────────────────────────────
    async function toggleLockscreen(el) {
        const enabled = el.checked;
        try {
            const data = await securityAction('toggle_lockscreen', {
                enabled: enabled ? 1 : 0
            });
            if (data.success) {
                document.getElementById('pinSection').style.display = enabled ? '' : 'none';
                const icon = document.querySelector('#lockscreenBlock .sec-icon');
                icon.classList.toggle('success', enabled);
                showToast(data.message || (enabled ? 'Lockscreen activado.' : 'Lockscreen desactivado.'),
                    'success');
            } else {
                el.checked = !enabled; // reverter
                showToast(data.message || 'Erro ao alterar lockscreen.', 'error');
            }
        } catch {
            el.checked = !enabled;
            showToast('Erro de rede.', 'error');
        }
    }

    // ── Guardar PIN ──────────────────────────────────────────────
    async function savePin() {
        const pin = document.getElementById('newPinInput').value.trim();
        if (!/^\d{4,6}$/.test(pin)) {
            showToast('O PIN deve ter 6 dígitos numéricos.', 'error');
            return;
        }
        try {
            const data = await securityAction('change_pin', {
                new_pin: pin
            });
            if (data.success) {
                document.getElementById('newPinInput').value = '';
                showToast('PIN actualizado com sucesso.', 'success');
            } else {
                showToast(data.message || 'Erro ao guardar PIN.', 'error');
            }
        } catch {
            showToast('Erro de rede.', 'error');
        }
    }

    // ── Limpar tentativas ────────────────────────────────────────
    async function resetAttempts() {
        if (!confirm('Limpar as tentativas de login falhadas?')) return;
        try {
            const data = await securityAction('reset_attempts');
            if (data.success) {
                showToast('Tentativas limpas.', 'success');
                setTimeout(() => location.reload(), 900);
            } else showToast(data.message || 'Erro.', 'error');
        } catch {
            showToast('Erro de rede.', 'error');
        }
    }

    // ── Recovery key ─────────────────────────────────────────────
    let recoveryVisible = false;

    function toggleRecovery() {
        recoveryVisible = !recoveryVisible;
        document.getElementById('recoveryKeyVal').classList.toggle('hidden', !recoveryVisible);
        document.getElementById('recoveryEyeIcon').className = recoveryVisible ? 'fas fa-eye-slash' : 'fas fa-eye';
    }

    function copyRecovery() {
        const val = document.getElementById('recoveryKeyVal').textContent.trim();
        navigator.clipboard.writeText(val).then(() => {
            const icon = document.getElementById('copyIcon');
            icon.className = 'fas fa-check';
            showToast('Chave copiada!', 'success');
            setTimeout(() => icon.className = 'fas fa-copy', 2000);
        });
    }

    // ── PIN visibility ───────────────────────────────────────────
    function togglePinVis() {
        const inp = document.getElementById('newPinInput');
        const icon = document.getElementById('pinEyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    // ── Input country uppercase ──────────────────────────────────
    document.querySelector('[name="country_employees"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    </script>
</body>

</html>