<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Visualizar Projecto (Ficha Ultra Completa)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

$db = $GLOBALS['pdo'];

// ── ID do projecto ────────────────────────────────────────────
$projectId = (int)($_GET['id'] ?? 0);
if ($projectId <= 0) {
    redirect('/jm-panel/projects');
}

$stmt = $db->prepare("SELECT * FROM _projects WHERE id_project = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/jm-panel/projects?msg=notfound');
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

// ── Galeria de media ─────────────────────────────────────────
$mediaStmt = $db->prepare("
    SELECT * FROM _projects_media
    WHERE id_project = ?
    ORDER BY display_order ASC, creat_media DESC
");
$mediaStmt->execute([$projectId]);
$mediaItems = $mediaStmt->fetchAll();

// ── CORRECÇÃO: Contagem de visualizações ────────────────────
$viewStmt = $db->prepare("
    SELECT COUNT(*) FROM _visitor_pageview
    WHERE page_url LIKE ?
");
$viewStmt->execute(['%/project/' . $project['slug_project'] . '%']);
$viewCount = (int) $viewStmt->fetchColumn();

// ── Visitantes únicos que viram este projecto ────────────────
$uniqueVisitorsStmt = $db->prepare("
    SELECT COUNT(DISTINCT id_visitor) FROM _visitor_pageview
    WHERE page_url LIKE ?
");
$uniqueVisitorsStmt->execute(['%/project/' . $project['slug_project'] . '%']);
$uniqueVisitors = (int) $uniqueVisitorsStmt->fetchColumn();

// ── Tempo médio na página (em segundos) ──────────────────────
$avgTimeStmt = $db->prepare("
    SELECT AVG(time_on_page) FROM _visitor_pageview
    WHERE page_url LIKE ? AND time_on_page IS NOT NULL
");
$avgTimeStmt->execute(['%/project/' . $project['slug_project'] . '%']);
$avgTime = round((float) $avgTimeStmt->fetchColumn(), 1);

// ── Visitas nos últimos 30 dias (para o gráfico) ─────────────
$dailyVisitsStmt = $db->prepare("
    SELECT DATE(creat_pageview) AS visit_date, COUNT(*) AS total
    FROM _visitor_pageview
    WHERE page_url LIKE ? AND creat_pageview >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(creat_pageview)
    ORDER BY visit_date ASC
");
$dailyVisitsStmt->execute(['%/project/' . $project['slug_project'] . '%']);
$dailyVisits = $dailyVisitsStmt->fetchAll();

// ── Dispositivos usados ──────────────────────────────────────
$deviceStmt = $db->prepare("
    SELECT v.device_type, COUNT(*) AS total
    FROM _visitor_pageview vp
    JOIN _visitor v ON vp.id_visitor = v.id_visitor
    WHERE vp.page_url LIKE ?
    GROUP BY v.device_type
    ORDER BY total DESC
");
$deviceStmt->execute(['%/project/' . $project['slug_project'] . '%']);
$devices = $deviceStmt->fetchAll();

// ── Histórico de auditoria ──────────────────────────────────
$auditStmt = $db->prepare("
    SELECT al.*, COALESCE(e.first_name, 'Sistema') AS actor
    FROM _audit_log al
    LEFT JOIN _employees e ON al.id_employees = e.id_employees
    WHERE al.entity = '_projects' AND al.entity_id = ?
    ORDER BY al.creat_log DESC
    LIMIT 30
");
$auditStmt->execute([$projectId]);
$auditLogs = $auditStmt->fetchAll();

// ── Testemunhos / Depoimentos (se existir tabela) ────────────
$testimonials = [];
if ($db->query("SHOW TABLES LIKE '_testimonials'")->rowCount() > 0) {
    $testStmt = $db->prepare("
        SELECT * FROM _testimonials
        WHERE body_testimonial LIKE ? OR company_testimonial LIKE ?
        ORDER BY creat_testimonial DESC
        LIMIT 10
    ");
    $testStmt->execute(['%' . $project['title_project'] . '%', '%' . $project['title_project'] . '%']);
    $testimonials = $testStmt->fetchAll();
}

// ── Mensagens de contacto relacionadas ───────────────────────
$relatedMessages = $db->prepare("
    SELECT * FROM _contact_message
    WHERE subject_msg LIKE ? OR message_msg LIKE ?
    ORDER BY created_at DESC
    LIMIT 10
");
$relatedMessages->execute(['%' . $project['title_project'] . '%', '%' . $project['title_project'] . '%']);
$relatedMessages = $relatedMessages->fetchAll();

// ── Cores por categoria ──────────────────────────────────────
function categoryColor(string $cat): string {
    $map = [
        'web'       => '#2563eb', 'mobile' => '#10b981', 'design' => '#8b5cf6',
        'backend'   => '#f59e0b', 'frontend' => '#14b8a6', 'fullstack' => '#ec4899',
        'devops'    => '#f97316', 'other' => '#64748b',
    ];
    return $map[strtolower($cat)] ?? '#64748b';
}
$catColor = categoryColor($project['category_project']);

// ── Traduções de estado ──────────────────────────────────────
$statusMap = [
    'published' => ['label' => 'Publicado', 'icon' => 'fa-check-circle', 'class' => 'badge-published'],
    'draft'     => ['label' => 'Rascunho',  'icon' => 'fa-clock',        'class' => 'badge-draft'],
    'archived'  => ['label' => 'Arquivado', 'icon' => 'fa-archive',      'class' => 'badge-archived'],
];
$statusInfo = $statusMap[$project['status_project']] ?? $statusMap['draft'];

// ── Tech stack ───────────────────────────────────────────────
$techArray = json_decode($project['tech_stack'] ?? '[]', true) ?: [];

// ── Preparar dados para o gráfico (JavaScript) ────────────────
$chartLabels = [];
$chartData   = [];
foreach ($dailyVisits as $day) {
    $chartLabels[] = date('d/m', strtotime($day['visit_date']));
    $chartData[]   = (int) $day['total'];
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
<!-- Chart.js para o gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <?php include __DIR__ . '/../../include/sidebar.php'; ?>

    <div class="main">
        <?php include __DIR__ . '/../../include/header.php'; ?>

        <div class="content">

            <!-- ═══ CABEÇALHO ════════════════════════════════════════════ -->
            <div class="page-top">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/jm-panel/home"><i class="fas fa-home"></i></a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= BASE_URL ?>/jm-panel/projects">Projectos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Visualizar</span>
                    </div>
                    <h1 class="page-title"><?= e($project['title_project']) ?></h1>
                    <p class="page-sub">
                        <span class="badge-status <?= $statusInfo['class'] ?>">
                            <i class="fas <?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
                        </span>
                        &nbsp;·&nbsp;
                        Criado em <?= date('d/m/Y H:i', strtotime($project['creat_project'])) ?>
                        &nbsp;·&nbsp;
                        <?= $viewCount ?> visualização(ões) &nbsp;·&nbsp;
                        <?= $uniqueVisitors ?> visitantes únicos
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <?php if ($project['status_project'] === 'published'): ?>
                    <a href="<?= BASE_URL ?>/project/<?= e($project['slug_project']) ?>" target="_blank"
                        class="btn btn-secondary">
                        <i class="fas fa-external-link-alt"></i> Ver no site
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/jm-panel/projects/edit?id=<?= $projectId ?>" class="btn btn-primary">
                        <i class="fas fa-pen"></i> Editar
                    </a>
                </div>
            </div>

            <!-- ═══ KPI MINI CARDS ══════════════════════════════════════ -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(37,99,235,.12);color:var(--accent)"><i
                            class="fas fa-eye"></i></span>
                    <span class="kpi-val"><?= $viewCount ?></span>
                    <span class="kpi-lbl">Visualizações</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--success)"><i
                            class="fas fa-users"></i></span>
                    <span class="kpi-val"><?= $uniqueVisitors ?></span>
                    <span class="kpi-lbl">Visitantes Únicos</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(245,158,11,.12);color:var(--warning)"><i
                            class="fas fa-clock"></i></span>
                    <span class="kpi-val"><?= $avgTime ?>s</span>
                    <span class="kpi-lbl">Tempo Médio</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(139,92,246,.12);color:var(--purple)"><i
                            class="fas fa-image"></i></span>
                    <span class="kpi-val"><?= count($mediaItems) ?></span>
                    <span class="kpi-lbl">Imagens</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i
                            class="fas fa-history"></i></span>
                    <span class="kpi-val"><?= count($auditLogs) ?></span>
                    <span class="kpi-lbl">Eventos</span>
                </div>
            </div>

            <div class="view-grid">
                <!-- ═══ COLUNA PRINCIPAL ══════════════════════════════════ -->
                <div class="view-main">

                    <!-- Capa -->
                    <?php if (!empty($project['cover_project'])): ?>
                    <div class="view-cover">
                        <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($project['cover_project']) ?>" alt="Capa">
                    </div>
                    <?php endif; ?>

                    <!-- Descrição completa -->
                    <?php if (!empty($project['body_project'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-align-left"></i></span>
                            <div>
                                <h3>Descrição Completa</h3>
                            </div>
                        </div>
                        <div class="view-body">
                            <?= nl2br(e($project['body_project'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Gráfico de visitas (últimos 30 dias) -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-chart-line"></i></span>
                            <div>
                                <h3>Visitas nos Últimos 30 Dias</h3>
                            </div>
                        </div>
                        <div style="height:260px">
                            <canvas id="visitsChart"></canvas>
                        </div>
                    </div>

                    <!-- Dispositivos -->
                    <?php if (!empty($devices)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-mobile-alt"></i></span>
                            <div>
                                <h3>Dispositivos Utilizados</h3>
                            </div>
                        </div>
                        <div class="device-grid">
                            <?php
                            $totalDeviceViews = array_sum(array_column($devices, 'total'));
                            $deviceColors = ['desktop' => '#2563eb', 'mobile' => '#10b981', 'tablet' => '#f59e0b', 'unknown' => '#64748b'];
                            foreach ($devices as $dev):
                                $pct = $totalDeviceViews > 0 ? round(($dev['total'] / $totalDeviceViews) * 100) : 0;
                                $color = $deviceColors[$dev['device_type']] ?? '#64748b';
                            ?>
                            <div class="device-item">
                                <div class="device-bar-bg">
                                    <div class="device-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>">
                                    </div>
                                </div>
                                <div class="device-info">
                                    <span><?= e(ucfirst($dev['device_type'])) ?></span>
                                    <span><?= $dev['total'] ?> (<?= $pct ?>%)</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Galeria de imagens -->
                    <?php if (!empty($mediaItems)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-images"></i></span>
                            <div>
                                <h3>Galeria (<?= count($mediaItems) ?> imagens)</h3>
                            </div>
                            <a href="<?= BASE_URL ?>/jm-panel/projects/media?id=<?= $projectId ?>"
                                class="btn btn-secondary" style="margin-left:auto">
                                <i class="fas fa-edit"></i> Gerir
                            </a>
                        </div>
                        <div class="view-gallery">
                            <?php foreach ($mediaItems as $img): ?>
                            <div class="gallery-item">
                                <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($img['url_media']) ?>"
                                    alt="<?= e($img['caption_media'] ?? '') ?>">
                                <?php if (!empty($img['caption_media'])): ?>
                                <div class="gallery-caption"><?= e($img['caption_media']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Testemunhos relacionados -->
                    <?php if (!empty($testimonials)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-star"></i></span>
                            <div>
                                <h3>Depoimentos Relacionados</h3>
                            </div>
                        </div>
                        <div class="testi-list">
                            <?php foreach ($testimonials as $t): ?>
                            <div class="testi-item">
                                <div class="testi-stars">
                                    <?php for ($s=0; $s<5; $s++): ?>
                                    <i class="fas fa-star"
                                        style="color:<?= $s < ($t['rating_testimonial']??5) ? 'var(--warning)' : 'var(--border)' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="testi-body">"<?= e($t['body_testimonial']) ?>"</p>
                                <div class="testi-author">— <?= e($t['name_testimonial']) ?>,
                                    <?= e($t['company_testimonial'] ?? '') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Mensagens de contacto relacionadas -->
                    <?php if (!empty($relatedMessages)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-envelope"></i></span>
                            <div>
                                <h3>Mensagens Relacionadas</h3>
                            </div>
                        </div>
                        <div class="msg-list">
                            <?php foreach ($relatedMessages as $msg): ?>
                            <div class="msg-item">
                                <div class="msg-header">
                                    <strong><?= e($msg['name_msg']) ?></strong>
                                    <span
                                        class="badge-status <?= $msg['status_msg'] === 'new' ? 'badge-draft' : 'badge-published' ?>"><?= $msg['status_msg'] === 'new' ? 'Nova' : 'Lida' ?></span>
                                </div>
                                <div class="msg-subject"><?= e($msg['subject_msg']) ?></div>
                                <div class="msg-date"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Histórico de auditoria -->
                    <?php if (!empty($auditLogs)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-history"></i></span>
                            <div>
                                <h3>Histórico de Actividades</h3>
                            </div>
                        </div>
                        <div class="audit-list">
                            <?php foreach ($auditLogs as $log): ?>
                            <div class="audit-item">
                                <div class="audit-dot"
                                    style="background:<?= strpos($log['action'],'delete')!==false ? 'var(--danger)' : (strpos($log['action'],'create')!==false ? 'var(--success)' : 'var(--accent)') ?>">
                                </div>
                                <div class="audit-content">
                                    <div class="audit-action"><?= e(ucfirst(str_replace('_', ' ', $log['action']))) ?>
                                    </div>
                                    <div class="audit-meta">
                                        <?= e($log['actor']) ?> · <?= date('d/m/Y H:i', strtotime($log['creat_log'])) ?>
                                        <?php if (!empty($log['ip_address'])): ?> · IP:
                                        <?= e($log['ip_address']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- ═══ SIDEBAR DE DETALHES ═══════════════════════════════ -->
                <div class="view-sidebar">

                    <!-- Informações gerais -->
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-info-circle"></i></span>
                            <div>
                                <h3>Informações</h3>
                            </div>
                        </div>
                        <div class="info-list">
                            <div class="info-row"><span>Slug</span><code><?= e($project['slug_project']) ?></code></div>
                            <div class="info-row"><span>Categoria</span><span class="cat-tag"
                                    style="--cat-color:<?= $catColor ?>"><?= e(ucfirst($project['category_project'])) ?></span>
                            </div>
                            <div class="info-row"><span>Estado</span><span
                                    class="badge-status <?= $statusInfo['class'] ?>"><i
                                        class="fas <?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?></span>
                            </div>
                            <div class="info-row">
                                <span>Destaque</span><span><?= $project['is_featured'] ? '⭐ Sim' : 'Não' ?></span>
                            </div>
                            <div class="info-row"><span>Ordem</span><span><?= (int)$project['display_order'] ?></span>
                            </div>
                            <div class="info-row"><span>Criado
                                    em</span><span><?= date('d/m/Y H:i', strtotime($project['creat_project'])) ?></span>
                            </div>
                            <div class="info-row"><span>Modificado
                                    em</span><span><?= date('d/m/Y H:i', strtotime($project['modif_project'])) ?></span>
                            </div>
                            <div class="info-row"><span>Visualizações</span><span><?= $viewCount ?></span></div>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <?php if (!empty($project['summary_project'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-file-alt"></i></span>
                            <div>
                                <h3>Resumo</h3>
                            </div>
                        </div>
                        <p style="color:var(--text-dim);font-size:.9rem;line-height:1.7">
                            <?= e($project['summary_project']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Tech Stack -->
                    <?php if (!empty($techArray)): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-code"></i></span>
                            <div>
                                <h3>Tech Stack</h3>
                            </div>
                        </div>
                        <div class="tags-wrap">
                            <?php foreach ($techArray as $tag): ?>
                            <span class="tag-item"><i class="fas fa-code"></i> <?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Links -->
                    <?php if ($project['url_demo'] || $project['url_github'] || $project['url_live']): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon"><i class="fas fa-link"></i></span>
                            <div>
                                <h3>Links</h3>
                            </div>
                        </div>
                        <div class="link-list">
                            <?php if (!empty($project['url_demo'])): ?>
                            <a href="<?= e($project['url_demo']) ?>" target="_blank" class="link-item"><i
                                    class="fas fa-external-link-alt"></i> Demo</a>
                            <?php endif; ?>
                            <?php if (!empty($project['url_github'])): ?>
                            <a href="<?= e($project['url_github']) ?>" target="_blank" class="link-item"><i
                                    class="fab fa-github"></i> GitHub</a>
                            <?php endif; ?>
                            <?php if (!empty($project['url_live'])): ?>
                            <a href="<?= e($project['url_live']) ?>" target="_blank" class="link-item"><i
                                    class="fas fa-globe"></i> Site ao vivo</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div><!-- /.content -->
    </div><!-- /.main -->

    <?php include __DIR__ . '/../../include/modal_logout.php'; ?>

    <style>
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

    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem
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
        flex-wrap: wrap
    }

    .kpi-row {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem
    }

    .kpi-card {
        flex: 1;
        min-width: 120px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .75rem 1rem;
        display: flex;
        align-items: center;
        gap: .65rem;
        transition: all .22s
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--border-acc);
        box-shadow: 0 6px 24px rgba(0, 0, 0, .25)
    }

    .kpi-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        flex-shrink: 0
    }

    .kpi-val {
        font-family: var(--font-head);
        font-size: 1.3rem;
        font-weight: 800;
        letter-spacing: -.02em;
        line-height: 1
    }

    .kpi-lbl {
        font-size: .65rem;
        color: var(--text-muted);
        margin-top: 1px;
        white-space: nowrap
    }

    .view-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 1.2rem;
        align-items: start
    }

    @media(max-width:1024px) {
        .view-grid {
            grid-template-columns: 1fr
        }
    }

    .view-cover {
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: 1px solid var(--border);
        margin-bottom: 1.2rem
    }

    .view-cover img {
        width: 100%;
        display: block
    }

    .form-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.2rem
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1rem
    }

    .section-icon {
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

    .section-header h3 {
        font-family: var(--font-head);
        font-size: .95rem;
        font-weight: 700
    }

    .view-body {
        color: var(--text-dim);
        font-size: .9rem;
        line-height: 1.8;
        white-space: pre-wrap
    }

    .view-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: .75rem
    }

    .gallery-item {
        border-radius: var(--radius);
        overflow: hidden;
        border: 1px solid var(--border);
        background: var(--bg)
    }

    .gallery-item img {
        width: 100%;
        display: block;
        aspect-ratio: 16/9;
        object-fit: cover
    }

    .gallery-caption {
        padding: .4rem .6rem;
        font-size: .72rem;
        color: var(--text-muted);
        text-align: center
    }

    .info-list {
        display: flex;
        flex-direction: column;
        gap: 0
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .55rem 0;
        border-bottom: 1px solid var(--border);
        font-size: .82rem
    }

    .info-row:last-child {
        border-bottom: none
    }

    .info-row span:first-child {
        color: var(--text-muted);
        font-size: .75rem
    }

    .info-row code {
        font-family: var(--font-mono);
        font-size: .75rem;
        background: var(--bg);
        padding: 2px 6px;
        border-radius: 4px;
        color: var(--accent)
    }

    .cat-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: .7rem;
        font-weight: 700;
        background: color-mix(in srgb, var(--cat-color) 12%, transparent);
        color: var(--cat-color);
        border: 1px solid color-mix(in srgb, var(--cat-color) 25%, transparent)
    }

    .tags-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem
    }

    .tag-item {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .3rem .65rem;
        background: rgba(37, 99, 235, .1);
        border: 1px solid rgba(37, 99, 235, .2);
        border-radius: 99px;
        font-size: .76rem;
        color: var(--accent);
        font-family: var(--font-mono)
    }

    .tag-item i {
        font-size: .6rem;
        opacity: .6
    }

    .link-list {
        display: flex;
        flex-direction: column;
        gap: .5rem
    }

    .link-item {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .55rem .8rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--accent);
        text-decoration: none;
        font-size: .82rem;
        transition: all .2s
    }

    .link-item:hover {
        background: var(--accent-glow);
        border-color: var(--accent)
    }

    .audit-list {
        display: flex;
        flex-direction: column;
        gap: 0
    }

    .audit-item {
        display: flex;
        gap: .75rem;
        padding: .6rem 0;
        border-bottom: 1px solid var(--border)
    }

    .audit-item:last-child {
        border-bottom: none
    }

    .audit-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px
    }

    .audit-content {
        flex: 1
    }

    .audit-action {
        font-size: .82rem;
        font-weight: 600
    }

    .audit-meta {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: 2px
    }

    .testi-list {
        display: flex;
        flex-direction: column;
        gap: 1rem
    }

    .testi-item {
        padding: .8rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius)
    }

    .testi-stars {
        margin-bottom: .4rem
    }

    .testi-body {
        font-size: .85rem;
        color: var(--text-dim);
        font-style: italic;
        line-height: 1.6
    }

    .testi-author {
        font-size: .75rem;
        color: var(--text-muted);
        margin-top: .4rem
    }

    .msg-list {
        display: flex;
        flex-direction: column;
        gap: .75rem
    }

    .msg-item {
        padding: .8rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius)
    }

    .msg-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: .3rem
    }

    .msg-subject {
        font-size: .82rem;
        color: var(--accent)
    }

    .msg-date {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: .3rem
    }

    .device-grid {
        display: flex;
        flex-direction: column;
        gap: .6rem
    }

    .device-bar-bg {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: .15rem
    }

    .device-bar-fill {
        height: 100%;
        border-radius: 3px
    }

    .device-info {
        display: flex;
        justify-content: space-between;
        font-size: .75rem;
        color: var(--text-dim)
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1.1rem;
        border-radius: 8px;
        font-family: var(--font-body);
        font-size: .83rem;
        font-weight: 500;
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
        background: #1d4ed8;
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
    </style>

    <script>
    // ── Gráfico de visitas ──────────────────────────────────────
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartData = <?= json_encode($chartData) ?>;
    if (chartLabels.length > 0) {
        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Visitas',
                    data: chartData,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.08)',
                    borderWidth: 3,
                    fill: true,
                    tension: .4,
                    pointBackgroundColor: '#2563eb',
                    pointRadius: 4,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255,255,255,.06)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255,255,255,.06)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
    </script>
</body>

</html>