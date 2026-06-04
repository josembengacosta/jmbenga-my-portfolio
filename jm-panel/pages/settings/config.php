<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Configurações do Site (Hub)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── Dados do admin ──────────────────────────────────────────
$adminName    = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$adminPhoto   = $_SESSION['admin_photo'] ?? null;
$totalProjects  = (int) $db->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
$unreadMessages = (int) $db->query("SELECT COUNT(*) FROM _contact_message WHERE status_msg = 'new'")->fetchColumn();
$onlineVisitors = (int) $db->query("SELECT COUNT(*) FROM _visitor WHERE is_online = 1")->fetchColumn();
$loginTime   = $_SESSION['admin_login_time'] ?? time();
$sessionMins = floor((time() - $loginTime) / 60);

// ── Carregar todas as configurações do site ──────────────────
$configs = $db->query("SELECT * FROM _site_config ORDER BY config_group, config_key")->fetchAll();

// Agrupar por grupo
$grouped = [];
foreach ($configs as $c) {
    $grouped[$c['config_group']][] = $c;
}

// ── Estado da plataforma ────────────────────────────────────
$platform = $db->query("SELECT * FROM _platform WHERE id_platform = 1")->fetch();
$siteStatus      = $platform['status'] ?? 'active';
$siteVersion     = $platform['version'] ?? '1.0';
$maintenanceMsg  = $platform['maintenance_msg'] ?? '';
$maintenanceEnd  = $platform['maintenance_end'] ?? null;
$allowContact    = $platform['allow_contact'] ?? 1;
$allowFeedback   = $platform['allow_feedback'] ?? 1;
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>
<style>
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

.settings-nav {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem
}

.settings-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.3rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: var(--text);
    transition: all .22s
}

.settings-card:hover {
    transform: translateY(-2px);
    border-color: var(--border-acc);
    box-shadow: 0 6px 24px rgba(0, 0, 0, .25)
}

.settings-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0
}

.settings-card h3 {
    font-family: var(--font-head);
    font-size: .95rem;
    margin-bottom: .15rem
}

.settings-card p {
    font-size: .75rem;
    color: var(--text-muted);
    line-height: 1.4
}

.settings-card .arrow {
    color: var(--text-muted);
    font-size: .8rem;
    margin-left: auto;
    flex-shrink: 0;
    transition: transform .2s
}

.settings-card:hover .arrow {
    transform: translateX(4px);
    color: var(--accent)
}

.table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem
}

.card-header h3 {
    font-family: var(--font-head);
    font-size: 1rem;
    font-weight: 700
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem
}

.config-group-title {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--accent);
    margin-bottom: .6rem;
    font-weight: 700
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .35rem 0;
    border-bottom: 1px solid var(--border);
    font-size: .78rem
}

.config-item:last-child {
    border-bottom: none
}

.config-key {
    color: var(--text-dim);
    font-size: .7rem;
    font-family: var(--font-mono)
}

.config-value {
    color: var(--text);
    text-align: right;
    max-width: 180px;
    word-break: break-word
}

.platform-info {
    display: flex;
    flex-direction: column;
    gap: .5rem
}

.platform-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .5rem 0;
    border-bottom: 1px solid var(--border);
    font-size: .82rem
}

.platform-row:last-child {
    border-bottom: none
}

.platform-row span:first-child {
    color: var(--text-dim)
}

.platform-row code {
    font-family: var(--font-mono);
    font-size: .78rem;
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--accent)
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 600
}

.badge-published {
    background: rgba(16, 185, 129, .1);
    color: var(--success)
}

.badge-draft {
    background: rgba(245, 158, 11, .1);
    color: var(--warning)
}

.badge-archived {
    background: rgba(239, 68, 68, .1);
    color: var(--danger)
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem 1.1rem;
    border-radius: 8px;
    font-size: .83rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    border: 1px solid var(--border);
    white-space: nowrap
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
</style>

<body>
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Configurações</span>
                    </div>
                    <h1 class="page-title">Configurações do Site</h1>
                    <p class="page-sub">Versão <?= e($siteVersion) ?> &nbsp;·&nbsp; Estado: <strong
                            style="color:<?= $siteStatus === 'active' ? 'var(--success)' : ($siteStatus === 'maintenance' ? 'var(--warning)' : 'var(--danger)') ?>"><?= match($siteStatus){'active'=>'Activo','maintenance'=>'Manutenção','blocked'=>'Bloqueado',default=>ucfirst($siteStatus)} ?></strong>
                    </p>
                </div>
                <div style="display:flex;gap:.6rem">
                    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-secondary"><i
                            class="fas fa-external-link-alt"></i> Ver site</a>
                </div>
            </div>

            <!-- ═══ CARDS DE NAVEGAÇÃO ═══════════════════════════════════ -->
            <div class="settings-nav">
                <a href="<?= BASE_URL ?>/jm-panel/settings/general" class="settings-card">
                    <div class="settings-icon" style="background:rgba(37,99,235,.12);color:var(--accent)"><i
                            class="fas fa-cog"></i></div>
                    <div>
                        <h3>Gerais</h3>
                        <p>Nome do site, email, telefone, localização, CV, anos de experiência</p>
                    </div>
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/settings/social" class="settings-card">
                    <div class="settings-icon" style="background:rgba(16,185,129,.12);color:var(--success)"><i
                            class="fas fa-share-alt"></i></div>
                    <div>
                        <h3>Redes Sociais</h3>
                        <p>GitHub, LinkedIn, WhatsApp, Twitter, Instagram</p>
                    </div>
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/settings/appearance" class="settings-card">
                    <div class="settings-icon" style="background:rgba(139,92,246,.12);color:var(--purple)"><i
                            class="fas fa-palette"></i></div>
                    <div>
                        <h3>Aparência</h3>
                        <p>Cor de destaque, modo escuro padrão, logo</p>
                    </div>
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/settings/seo" class="settings-card">
                    <div class="settings-icon" style="background:rgba(245,158,11,.12);color:var(--warning)"><i
                            class="fas fa-search"></i></div>
                    <div>
                        <h3>SEO &amp; Meta</h3>
                        <p>Meta description, keywords, OG image, título do site</p>
                    </div>
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
                <a href="<?= BASE_URL ?>/jm-panel/settings/maintenance" class="settings-card">
                    <div class="settings-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                            class="fas fa-tools"></i></div>
                    <div>
                        <h3>Manutenção</h3>
                        <p>Modo de manutenção, mensagem, contactos permitidos</p>
                    </div>
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
            </div>

            <!-- ═══ RESUMO DAS CONFIGURAÇÕES ACTUAIS ════════════════════ -->
            <div class="table-card" style="margin-top:1.5rem">
                <div class="card-header">
                    <h3>Resumo das Configurações Actuais</h3>
                </div>
                <div class="config-grid">
                    <?php foreach ($grouped as $group => $items): ?>
                    <div class="config-group">
                        <h4 class="config-group-title"><?= e(ucfirst($group)) ?></h4>
                        <div class="config-list">
                            <?php foreach (array_slice($items, 0, 6) as $item): ?>
                            <div class="config-item">
                                <span class="config-key"><?= e($item['config_key']) ?></span>
                                <span
                                    class="config-value"><?= !empty($item['config_value']) ? e(mb_strlen($item['config_value']) > 40 ? mb_substr($item['config_value'], 0, 40) . '…' : $item['config_value']) : '<em style="color:var(--text-muted)">vazio</em>' ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ═══ ESTADO DA PLATAFORMA ═════════════════════════════════ -->
            <div class="table-card" style="margin-top:1.5rem">
                <div class="card-header">
                    <h3>Estado da Plataforma</h3>
                </div>
                <div class="platform-info">
                    <div class="platform-row">
                        <span>Status</span>
                        <span
                            class="badge-status <?= $siteStatus === 'active' ? 'badge-published' : ($siteStatus === 'maintenance' ? 'badge-draft' : 'badge-archived') ?>">
                            <?= match($siteStatus){'active'=>'Activo','maintenance'=>'Manutenção','blocked'=>'Bloqueado',default=>ucfirst($siteStatus)} ?>
                        </span>
                    </div>
                    <div class="platform-row"><span>Versão</span><code><?= e($siteVersion) ?></code></div>
                    <div class="platform-row"><span>Formulário de
                            Contacto</span><span><?= $allowContact ? '✅ Permitido' : '❌ Fechado' ?></span></div>
                    <div class="platform-row"><span>Formulário de
                            Feedback</span><span><?= $allowFeedback ? '✅ Permitido' : '❌ Fechado' ?></span></div>
                    <?php if ($siteStatus === 'maintenance'): ?>
                    <div class="platform-row"><span>Mensagem de
                            Manutenção</span><span><?= e($maintenanceMsg ?: '—') ?></span></div>
                    <div class="platform-row"><span>Fim
                            Previsto</span><span><?= $maintenanceEnd ? date('d/m/Y H:i', strtotime($maintenanceEnd)) : 'Indeterminado' ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

</body>

</html>