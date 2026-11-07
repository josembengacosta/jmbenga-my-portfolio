<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Auditoria (Pesquisa Detalhada)
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

// ── Opções disponíveis para os filtros ──────────────────────
$actions = $db->query("SELECT DISTINCT action FROM _audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entities = $db->query("SELECT DISTINCT entity FROM _audit_log WHERE entity IS NOT NULL ORDER BY entity")->fetchAll(PDO::FETCH_COLUMN);

// ── Filtros validados ───────────────────────────────────────
$rawAction = $_GET['action'] ?? 'all';
$rawEntity = $_GET['entity'] ?? 'all';
$filterAction = is_string($rawAction) ? trim($rawAction) : 'all';
$filterEntity = is_string($rawEntity) ? trim($rawEntity) : 'all';

if ($filterAction !== 'all' && !in_array($filterAction, $actions, true)) {
    $filterAction = 'all';
}
if ($filterEntity !== 'all' && !in_array($filterEntity, $entities, true)) {
    $filterEntity = 'all';
}

$rawDateFrom = $_GET['date_from'] ?? '';
$rawDateTo   = $_GET['date_to'] ?? '';
$dateErrors  = [];

$validateDate = static function ($value): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return '';
    }
    return $value;
};

$dateFrom = $validateDate($rawDateFrom);
$dateTo   = $validateDate($rawDateTo);

if (is_string($rawDateFrom) && $rawDateFrom !== '' && $dateFrom === '') {
    $dateErrors[] = 'A data inicial é inválida.';
}
if (is_string($rawDateTo) && $rawDateTo !== '' && $dateTo === '') {
    $dateErrors[] = 'A data final é inválida.';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $dateErrors[] = 'A data inicial não pode ser posterior à data final.';
    $dateTo = '';
}

$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 120) : '';

// ── Query dinâmica ──────────────────────────────────────────
$where  = [];
$params = [];

if ($filterAction !== 'all') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterEntity !== 'all') {
    $where[]  = 'al.entity = ?';
    $params[] = $filterEntity;
}
if ($dateFrom !== '') {
    $where[]  = 'al.creat_log >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]  = 'al.creat_log < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $dateTo;
}
if ($search !== '') {
    $where[]  = '(al.ip_address LIKE ? OR al.action LIKE ? OR al.entity LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql = "SELECT al.*, COALESCE(e.first_name, 'Sistema') AS actor
        FROM _audit_log al
        LEFT JOIN _employees e ON al.id_employees = e.id_employees";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY al.creat_log DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── Estatísticas ────────────────────────────────────────────
$totalAll = (int) $db->query("SELECT COUNT(*) FROM _audit_log")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<link rel="stylesheet"
    href="<?= BASE_URL ?>/assets/css/jmbenga-security.css?v=<?= is_file(ROOT_PATH . '/assets/css/jmbenga-security.css') ? (int)filemtime(ROOT_PATH . '/assets/css/jmbenga-security.css') : time() ?>">
</head>

<body class="audit-page">
    <?php include __DIR__ . '/../../include/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>
        <div class="content">

            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/security">Segurança</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Auditoria</span>
                    </div>
                    <h1 class="page-title">Auditoria Completa</h1>
                    <p class="page-sub"><?= $totalAll ?> eventos registados no log de auditoria.</p>
                </div>
                <a href="<?= BASE_URL ?>/jm-panel/security" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                    Voltar</a>
            </div>

            <!-- Filtros -->
            <?php if (!empty($dateErrors)): ?>
            <div class="filter-validation" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= e(implode(' ', $dateErrors)) ?></span>
            </div>
            <?php endif; ?>
            <form class="filter-bar" method="get">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Pesquisar…" value="<?= e($search) ?>" maxlength="120">
                    <?php if ($search): ?><a href="?action=all&entity=all" class="filter-clear"><i
                            class="fas fa-times"></i></a><?php endif; ?>
                </div>
                <select name="action" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterAction === 'all' ? 'selected' : '' ?>>Todas acções</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="entity" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?= $filterEntity === 'all' ? 'selected' : '' ?>>Todas entidades</option>
                    <?php foreach ($entities as $e): ?>
                    <option value="<?= e($e) ?>" <?= $filterEntity === $e ? 'selected' : '' ?>><?= e($e) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="filter-date"
                    onchange="this.form.submit()" title="De">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="filter-date"
                    onchange="this.form.submit()" title="Até">
                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem"><i class="fas fa-filter"></i>
                    Filtrar</button>
                <?php if ($filterAction !== 'all' || $filterEntity !== 'all' || $dateFrom || $dateTo || $search): ?>
                <a href="<?= BASE_URL ?>/jm-panel/security/audit" class="btn btn-secondary"
                    style="padding:.45rem .9rem"><i class="fas fa-times"></i> Limpar</a>
                <?php endif; ?>
                <span class="filter-result"><i class="fas fa-info-circle"></i> <?= count($logs) ?> resultado(s)</span>
            </form>

            <!-- Tabela -->
            <div class="table-card">
                <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-search"></i></div>
                    <h3>Nenhum evento encontrado</h3>
                </div>
                <?php else: ?>
                <table id="auditTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Acção</th>
                            <th>Entidade</th>
                            <th>ID</th>
                            <th>Utilizador</th>
                            <th>IP</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?></td>
                            <td><?= e(ucfirst(str_replace('_',' ',$log['action']))) ?></td>
                            <td><?= e($log['entity'] ?: '—') ?></td>
                            <td><?= e((string)($log['entity_id'] ?? '—')) ?></td>
                            <td><?= e($log['actor']) ?></td>
                            <td><code><?= e($log['ip_address'] ?: '—') ?></code></td>
                            <td>
                                <button class="btn-icon view-audit-detail" data-old="<?= e($log['old_value'] ?? '') ?>"
                                    data-new="<?= e($log['new_value'] ?? '') ?>" title="Ver alterações">
                                    <i class="fas fa-search"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <script>
    $(document).ready(function() {
        if ($('#auditTable').length) {
            $('#auditTable').DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
                },
                order: [
                    [0, 'desc']
                ],
                dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
                columnDefs: [{
                    orderable: false,
                    targets: [6]
                }]
            });
        }
    });

    // Detalhes da auditoria: conteúdo da base de dados é tratado como texto.
    function formatAuditValue(raw) {
        if (!raw) return '(vazio)';
        try {
            return JSON.stringify(JSON.parse(raw), null, 2);
        } catch (error) {
            return String(raw);
        }
    }

    function escapeAuditHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [char]));
    }

    document.querySelectorAll('.view-audit-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const oldVal = escapeAuditHtml(formatAuditValue(this.dataset.old));
            const newVal = escapeAuditHtml(formatAuditValue(this.dataset.new));

            Swal.fire({
                title: 'Detalhes da Alteração',
                html: `<div class="audit-detail-content">
                    <strong>Antes:</strong>
                    <pre>${oldVal}</pre>
                    <strong>Depois:</strong>
                    <pre>${newVal}</pre>
                </div>`,
                width: '600px',
                background: 'var(--bg-card)',
                color: 'var(--text)',
                showCloseButton: true,
                confirmButtonText: 'Fechar',
                confirmButtonColor: 'var(--accent)'
            });
        });
    });
    </script>
</body>

</html>