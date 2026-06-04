<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Gestão de Media do Projecto
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

// Verificar se o projecto existe
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

// ── Carregar media existente ──────────────────────────────────
$mediaItems = $db->prepare("
    SELECT * FROM _projects_media
    WHERE id_project = ?
    ORDER BY display_order ASC, creat_media DESC
");
$mediaItems->execute([$projectId]);
$mediaItems = $mediaItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark" data-base-url="<?= BASE_URL ?>">
<?php include __DIR__ . '/../../include/head.php'; ?>
</head>

<body>

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
                        <a href="<?= BASE_URL ?>/jm-panel/projects">Projectos</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Media</span>
                    </div>
                    <h1 class="page-title">Media do Projecto</h1>
                    <p class="page-sub">
                        <strong><?= e($project['title_project']) ?></strong> &nbsp;·&nbsp;
                        <?= count($mediaItems) ?> imagem(ns)
                    </p>
                </div>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <a href="<?= BASE_URL ?>/jm-panel/projects/edit?id=<?= $projectId ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar ao projecto
                    </a>
                </div>
            </div>

            <!-- ═══ UPLOAD DE NOVA IMAGEM ══════════════════════════════════ -->
            <div class="form-section">
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                    <div>
                        <h3>Adicionar Nova Imagem</h3>
                        <p>Formatos aceites: JPEG, PNG, WebP. Máximo: <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB.</p>
                    </div>
                </div>
                <form id="uploadForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['admin_csrf_token'] ?>">
                    <input type="hidden" name="id_project" value="<?= $projectId ?>">

                    <div class="upload-row">
                        <div class="form-group" style="flex:1">
                            <label class="form-label">Imagem</label>
                            <div class="upload-area-small" id="uploadAreaSmall">
                                <input type="file" id="mediaFile" name="media" accept="image/jpeg,image/png,image/webp"
                                    hidden required>
                                <div class="upload-placeholder" id="uploadPlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Clique para escolher</span>
                                </div>
                                <div class="upload-preview-small" id="uploadPreviewSmall" style="display:none">
                                    <img id="previewImg" src="" alt="Preview">
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="flex:1">
                            <label class="form-label" for="caption_media">Legenda (opcional)</label>
                            <input type="text" id="caption_media" name="caption_media" class="form-control"
                                placeholder="Ex: Tela inicial do app" maxlength="255">
                        </div>
                    </div>
                    <div style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Fazer Upload
                        </button>
                    </div>
                </form>
            </div>

            <!-- ═══ GALERIA DE IMAGENS ═════════════════════════════════════ -->
            <div class="form-section">
                <div class="section-header">
                    <span class="section-icon"><i class="fas fa-images"></i></span>
                    <div>
                        <h3>Imagens do Projecto</h3>
                        <p>Gere as screenshots e imagens associadas a este projecto.</p>
                    </div>
                </div>

                <?php if (empty($mediaItems)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-image"></i></div>
                    <h3>Nenhuma imagem</h3>
                    <p>Este projecto ainda não tem imagens adicionais. Faz upload acima.</p>
                </div>
                <?php else: ?>
                <div class="media-grid" id="mediaGrid">
                    <?php foreach ($mediaItems as $media): ?>
                    <div class="media-card" id="media-<?= $media['id_media'] ?>">
                        <div class="media-thumb">
                            <img src="<?= BASE_URL ?>/assets/img/projects/<?= e($media['url_media']) ?>"
                                alt="<?= e($media['caption_media'] ?? 'Screenshot') ?>" loading="lazy">
                            <div class="media-overlay">
                                <button class="btn-icon btn-icon-danger delete-media"
                                    data-id="<?= $media['id_media'] ?>" title="Eliminar imagem">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($media['caption_media'])): ?>
                        <div class="media-caption"><?= e($media['caption_media']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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
        color: var(--text-muted)
    }

    .form-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.8rem;
        margin-bottom: 1.2rem
    }

    .section-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.2rem;
        border-bottom: 1px solid var(--border)
    }

    .section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--accent-glow);
        border: 1px solid var(--border-acc);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-size: .95rem;
        flex-shrink: 0
    }

    .section-header h3 {
        font-family: var(--font-head);
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: .15rem
    }

    .section-header p {
        font-size: .78rem;
        color: var(--text-muted)
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: .35rem
    }

    .form-label {
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--text-dim)
    }

    .form-control {
        padding: .65rem .85rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-family: var(--font-body);
        font-size: .88rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s
    }

    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow)
    }

    .upload-row {
        display: flex;
        gap: 1.2rem;
        align-items: flex-end
    }

    @media(max-width:768px) {
        .upload-row {
            flex-direction: column
        }
    }

    .upload-area-small {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all .25s;
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .upload-area-small:hover {
        border-color: var(--accent);
        background: rgba(37, 99, 235, .03)
    }

    .upload-placeholder {
        color: var(--text-muted);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem
    }

    .upload-placeholder i {
        font-size: 1.8rem
    }

    .upload-placeholder span {
        font-size: .82rem
    }

    .upload-preview-small img {
        max-height: 150px;
        border-radius: 8px
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

    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem
    }

    .media-card {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        transition: all .2s
    }

    .media-card:hover {
        border-color: var(--border-acc);
        transform: translateY(-2px)
    }

    .media-thumb {
        position: relative;
        aspect-ratio: 16/9;
        overflow: hidden
    }

    .media-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .media-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .6);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity .2s
    }

    .media-card:hover .media-overlay {
        opacity: 1
    }

    .btn-icon-danger {
        background: var(--danger);
        border-color: var(--danger);
        color: #fff;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .2s
    }

    .btn-icon-danger:hover {
        background: #dc2626;
        transform: scale(1.1)
    }

    .media-caption {
        padding: .5rem .7rem;
        font-size: .75rem;
        color: var(--text-dim);
        text-align: center
    }

    .empty-state {
        padding: 3rem 2rem;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: var(--bg);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--text-muted);
        margin-bottom: .5rem
    }

    .empty-state h3 {
        font-family: var(--font-head);
        font-size: 1.1rem;
        font-weight: 700
    }

    .empty-state p {
        font-size: .85rem;
        color: var(--text-muted);
        max-width: 360px
    }
    </style>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const CSRF = '<?= $_SESSION['admin_csrf_token'] ?>';

    // ── Toast helper ─────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML =
            `<i class="fas fa-${type === 'success' ? 'check-circle' : 'error' ? 'times-circle' : 'info-circle'}"></i> ${message}`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
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

    // ── Preview de upload ────────────────────────────────────────
    const uploadAreaSmall = document.getElementById('uploadAreaSmall');
    const mediaFile = document.getElementById('mediaFile');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const uploadPreviewSmall = document.getElementById('uploadPreviewSmall');
    const previewImg = document.getElementById('previewImg');

    uploadAreaSmall.addEventListener('click', () => mediaFile.click());

    mediaFile.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            uploadPlaceholder.style.display = 'none';
            uploadPreviewSmall.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // ── Upload AJAX ──────────────────────────────────────────────
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!mediaFile.files[0]) {
            showToast('Seleciona uma imagem.', 'error');
            return;
        }

        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar…';

        const formData = new FormData(uploadForm);
        try {
            const res = await fetch(BASE_URL + '/jm-panel/projects/media-process', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                }
            });
            const data = await res.json();

            if (data.success) {
                showToast('Imagem adicionada!', 'success');
                // Recarregar após 1s para mostrar a nova imagem
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message || 'Erro ao fazer upload.', 'error');
            }
        } catch (err) {
            showToast('Erro de rede.', 'error');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Fazer Upload';
        }
    });

    // ── Eliminar media ───────────────────────────────────────────
    document.querySelectorAll('.delete-media').forEach(btn => {
        btn.addEventListener('click', async function() {
            const mediaId = this.dataset.id;
            if (!confirm('Eliminar esta imagem?')) return;

            try {
                const res = await fetch(BASE_URL + '/jm-panel/projects/media-delete?id=' +
                mediaId, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': CSRF
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const card = document.getElementById('media-' + mediaId);
                    if (card) {
                        card.style.transition = 'opacity .3s, transform .3s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.8)';
                        setTimeout(() => card.remove(), 300);
                    }
                    showToast('Imagem removida.', 'success');
                } else {
                    showToast(data.message || 'Erro ao remover.', 'error');
                }
            } catch (err) {
                showToast('Erro de rede.', 'error');
            }
        });
    });
    </script>
</body>

</html>