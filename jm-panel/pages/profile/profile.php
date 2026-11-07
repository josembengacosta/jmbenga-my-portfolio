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
           s.invited_by
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
$auditCountStmt = $db->prepare('SELECT COUNT(*) FROM _audit_log WHERE id_employees = ?');
$auditCountStmt->execute([$adminId]);
$totalAudit = (int) $auditCountStmt->fetchColumn();

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
<?php
$profileCssPath = ROOT_PATH . '/assets/css/jmbenga-profile.css';
$profileCssVersion = is_file($profileCssPath) ? (string) filemtime($profileCssPath) : '1.0.0';
?>
<link rel="stylesheet"
    href="<?= e(rtrim(BASE_URL, '/') . '/assets/css/jmbenga-profile.css') ?>?v=<?= e($profileCssVersion) ?>">
</head>


<body class="profile-page">
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
                                        <?php if (($admin['role'] ?? '') === 'super_admin' && (int)$admin['login_attempts'] > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="resetAttempts()">
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
                                        style="background:var(--bg);padding:2px 7px;border-radius:5px;font-family:var(--font-mono)"><?= !empty($admin['access_code']) ? str_repeat('●', 6) : '—' ?></code>
                                </div>
                                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.8rem">
                                    Altera o PIN de 6 dígitos abaixo:
                                </div>
                                <div class="pin-form">
                                    <input type="password" id="newPinInput" class="pin-input" maxlength="6"
                                        minlength="6" inputmode="numeric" placeholder="••••••"
                                        autocomplete="new-password">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="savePin()">
                                        <i class="fas fa-floppy-disk"></i> Guardar PIN
                                    </button>
                                    <button type="button" class="icon-btn" onclick="togglePinVis()"
                                        title="Mostrar/ocultar PIN">
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
                                        <button type="button" class="icon-btn" onclick="toggleRecovery()"
                                            title="Mostrar/ocultar">
                                            <i class="fas fa-eye" id="recoveryEyeIcon"></i>
                                        </button>
                                        <button type="button" class="icon-btn" onclick="copyRecovery()" title="Copiar">
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
    const BASE_URL = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CSRF_TOKEN =
        <?= json_encode($_SESSION['admin_csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const MAX_PROFILE_PHOTO_BYTES = <?= defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 2 * 1024 * 1024 ?>;

    // ── Toast ────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icons = {
            success: 'check-circle',
            error: 'circle-xmark',
            info: 'circle-info'
        };
        const icon = document.createElement('i');
        icon.className = `fas fa-${icons[type] || 'circle-info'}`;
        icon.setAttribute('aria-hidden', 'true');
        toast.appendChild(icon);
        toast.appendChild(document.createTextNode(' ' + String(msg ?? '')));
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 350);
        }, 4000);
    }

    async function parseJsonResponse(response) {
        const body = await response.text();
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.toLowerCase().includes('application/json')) {
            console.error('[Profile] Resposta não JSON', response.status, body.slice(0, 500));
            if (response.redirected) {
                throw new Error(
                    'A sessão expirou ou foi redireccionada. Actualiza a página e inicia sessão novamente.');
            }
            throw new Error(
                `Resposta inesperada do servidor (HTTP ${response.status}). Verifica a rota e o log PHP.`);
        }
        try {
            return JSON.parse(body);
        } catch (error) {
            console.error('[Profile] JSON inválido', response.status, body.slice(0, 500));
            throw new Error('O servidor devolveu uma resposta inválida. Verifica o log PHP.');
        }
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
        if (file) {
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
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            selectedAvatarFile = null;
            showToast('Formato não suportado. Usa JPEG, PNG ou WebP.', 'error');
            return;
        }
        if (file.size > MAX_PROFILE_PHOTO_BYTES) {
            selectedAvatarFile = null;
            showToast(`A foto excede o limite de ${Math.round(MAX_PROFILE_PHOTO_BYTES / 1024 / 1024 * 10) / 10} MB.`,
                'error');
            return;
        }
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
        window.location.reload();
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
            const data = await parseJsonResponse(res);
            if (data.success) {
                showToast(data.message || 'Perfil actualizado!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                (data.errors || [data.message]).forEach(m => showToast(m || 'Erro desconhecido.', 'error'));
            }
        } catch (error) {
            showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
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
        } catch (error) {
            el.checked = !enabled;
            showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
        }
    }

    // ── Guardar PIN ──────────────────────────────────────────────
    async function savePin() {
        const pin = document.getElementById('newPinInput').value.trim();
        if (!/^\d{6}$/.test(pin)) {
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
        } catch (error) {
            showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
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
        } catch (error) {
            showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
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
    const countryInput = document.querySelector('[name="country_employees"]');
    if (countryInput) countryInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    </script>
</body>

</html>