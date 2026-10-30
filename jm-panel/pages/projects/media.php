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

// Limite efectivo do upload: respeita a configuração da aplicação e os limites do PHP.
$iniBytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($unit) {
        case 'g': $number *= 1024; // fall through
        case 'm': $number *= 1024; // fall through
        case 'k': $number *= 1024;
    }
    return (int) $number;
};
$uploadLimits = [
    defined('MAX_UPLOAD_SIZE') ? (int) MAX_UPLOAD_SIZE : 0,
    $iniBytes((string) ini_get('upload_max_filesize')),
    $iniBytes((string) ini_get('post_max_size')),
];
$uploadLimits = array_values(array_filter($uploadLimits, static fn (int $bytes): bool => $bytes > 0));
$maxUploadSize = $uploadLimits ? min($uploadLimits) : 5 * 1024 * 1024;


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

        <div class="content project-media-page">

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
                        <span id="mediaCount"><?= count($mediaItems) ?> imagem(ns)</span>
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
                        <p>Formatos aceites: JPEG, PNG, WebP. Máximo:
                            <?= number_format($maxUploadSize / 1024 / 1024, 1, ',', '') ?> MB.</p>
                    </div>
                </div>
                <form id="uploadForm" enctype="multipart/form-data" novalidate
                    data-max-bytes="<?= (int) $maxUploadSize ?>">
                    <input type="hidden" name="csrf_token" id="csrf_token"
                        value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">
                    <input type="hidden" name="id_project" value="<?= $projectId ?>">

                    <div class="upload-row">
                        <div class="form-group" style="flex:1">
                            <label class="form-label">Imagem</label>
                            <div class="upload-area-small" id="uploadAreaSmall" role="button" tabindex="0"
                                aria-label="Escolher imagem para carregar">
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

    <script>
    const BASE_URL = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const CSRF = document.getElementById('csrf_token')?.value || '';

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const icon = document.createElement('i');
        icon.className =
            `fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-times-circle' : 'fa-info-circle'}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = String(message ?? 'Ocorreu um erro.');
        toast.append(icon, text);
        container.appendChild(toast);
        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            window.setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
        return container;
    }

    async function readJsonResponse(response) {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (error) {
            console.error('Resposta inesperada do servidor:', response.status, raw.slice(0, 500));
            if (response.redirected || /<!doctype html|<html/i.test(raw)) {
                throw new Error(
                    'A sessão pode ter expirado. Actualiza a página, inicia sessão novamente e tenta outra vez.'
                    );
            }
            throw new Error('O servidor devolveu uma resposta inválida. Consulta a consola do navegador.');
        }
    }

    const uploadAreaSmall = document.getElementById('uploadAreaSmall');
    const mediaFile = document.getElementById('mediaFile');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const uploadPreviewSmall = document.getElementById('uploadPreviewSmall');
    const previewImg = document.getElementById('previewImg');
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    const maxUploadBytes = Number(uploadForm?.dataset.maxBytes || 0);

    uploadAreaSmall.addEventListener('click', () => mediaFile.click());
    uploadAreaSmall.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            mediaFile.click();
        }
    });

    mediaFile.addEventListener('change', function() {
        const file = this.files?. [0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.value = '';
            showToast('Formato não permitido. Escolhe JPEG, PNG ou WebP.', 'error');
            return;
        }
        if (maxUploadBytes > 0 && file.size > maxUploadBytes) {
            this.value = '';
            showToast(`A imagem excede o limite de ${(maxUploadBytes / 1024 / 1024).toFixed(1)} MB.`, 'error');
            return;
        }
        const reader = new FileReader();
        reader.onload = event => {
            previewImg.src = event.target.result;
            uploadPlaceholder.style.display = 'none';
            uploadPreviewSmall.style.display = 'block';
        };
        reader.onerror = () => showToast('Não foi possível pré-visualizar esta imagem.', 'error');
        reader.readAsDataURL(file);
    });

    uploadForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (!mediaFile.files?. [0]) {
            showToast('Selecciona uma imagem.', 'error');
            return;
        }
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> A enviar…';
        try {
            const response = await fetch(BASE_URL + '/jm-panel/projects/media-process', {
                method: 'POST',
                body: new FormData(uploadForm),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF
                },
                credentials: 'same-origin'
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.success) {
                const messages = Array.isArray(data.errors) ? data.errors : [data.message ||
                    'Erro ao fazer upload.'
                ];
                messages.forEach(message => showToast(message, 'error'));
                return;
            }
            showToast(data.message || 'Imagem adicionada com sucesso.', 'success');
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            console.error('Falha no upload da imagem:', error);
            showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload" aria-hidden="true"></i> Fazer Upload';
        }
    });

    document.querySelectorAll('.delete-media').forEach(button => {
        button.addEventListener('click', async function() {
            const mediaId = this.dataset.id;
            if (!mediaId || !window.confirm('Eliminar esta imagem?')) return;
            this.disabled = true;
            try {
                const response = await fetch(BASE_URL + '/jm-panel/projects/media-delete?id=' +
                    encodeURIComponent(mediaId), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': CSRF
                        },
                        credentials: 'same-origin'
                    });
                const data = await readJsonResponse(response);
                if (!response.ok || !data.success) {
                    const messages = Array.isArray(data.errors) ? data.errors : [data.message ||
                        'Erro ao remover a imagem.'
                    ];
                    messages.forEach(message => showToast(message, 'error'));
                    return;
                }
                const card = document.getElementById('media-' + mediaId);
                if (card) {
                    card.style.transition = 'opacity .3s, transform .3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.96)';
                    window.setTimeout(() => {
                        card.remove();
                        const grid = document.getElementById('mediaGrid');
                        if (grid && grid.children.length === 0) {
                            const empty = document.createElement('div');
                            empty.className = 'empty-state';
                            empty.innerHTML =
                                '<div class="empty-icon"><i class="fas fa-image" aria-hidden="true"></i></div><h3>Nenhuma imagem</h3><p>Este projecto já não tem imagens adicionais. Faz upload acima para adicionar novas.</p>';
                            grid.replaceWith(empty);
                        }
                        const mediaCount = document.getElementById('mediaCount');
                        if (mediaCount) mediaCount.textContent =
                            `${document.querySelectorAll('.media-card').length} imagem(ns)`;
                    }, 300);
                }
                showToast(data.message || 'Imagem removida.', 'success');
            } catch (error) {
                console.error('Falha ao eliminar imagem:', error);
                showToast(error.message || 'Não foi possível contactar o servidor.', 'error');
            } finally {
                this.disabled = false;
            }
        });
    });
    </script>
</body>

</html>