<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Adicionar Projecto (AJAX)
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../include/functions_admin.php';

// Usa MAX_UPLOAD_SIZE quando configurado; caso contrário, respeita os limites PHP.
if (!defined('MAX_UPLOAD_SIZE')) {
    $jmbengaIniSizeToBytes = static function (string $value): int {
        $value = trim($value);
        if ($value === '' || !preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)B?$/i', $value, $matches)) {
            return 0;
        }
        $number = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');
        $factor = match ($unit) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };
        return (int) min(PHP_INT_MAX, round($number * $factor));
    };
    $uploadLimit = $jmbengaIniSizeToBytes((string) ini_get('upload_max_filesize'));
    $postLimit = $jmbengaIniSizeToBytes((string) ini_get('post_max_size'));
    // Reserva margem para os campos multipart do formulário.
    if ($postLimit > 262144) {
        $postLimit -= 262144;
    }
    $limits = array_values(array_filter([$uploadLimit, $postLimit], static fn (int $size): bool => $size > 0));
    define('MAX_UPLOAD_SIZE', $limits ? min($limits) : 5 * 1024 * 1024);
}

startAdminSession();
// O formulário chama checkAdminRememberMe(); o endpoint AJAX deve fazer o mesmo.
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$db = $GLOBALS['pdo'];

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$tokenCandidate = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$token = is_string($tokenCandidate) ? $tokenCandidate : '';
if (!validateAdminCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

// ── Função auxiliar para retornar erros ───────────────────────
$jsonError = function (array $errors, int $status = 422) {
    http_response_code($status);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
};

// ── Capturar e sanitizar campos ───────────────────────────────
$postString = static function (string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
};

$title        = $postString('title_project');
$slug         = $postString('slug_project');
$category     = $postString('category_project', 'web');
$status       = $postString('status_project', 'draft');
$summary      = $postString('summary_project');
$body         = $postString('body_project');
$objectives   = $postString('objectives_project');
$features     = $postString('features_project');
$contribution = $postString('contribution_project');
$techStack    = $postString('tech_stack', '[]');
$urlDemo      = $postString('url_demo');
$urlGithub    = $postString('url_github');
$urlLive      = $postString('url_live');
$previewMode  = $postString('preview_mode', 'disabled');
$previewUrl   = $postString('preview_url');
$previewEnabled = isset($_POST['preview_enabled']) ? 1 : 0;
$isFeatured     = isset($_POST['is_featured']) ? 1 : 0;
$displayOrderRaw = $postString('display_order', '0');
$rawDisplayOrder = filter_var($displayOrderRaw, FILTER_VALIDATE_INT);
$displayOrder = $rawDisplayOrder === false ? 0 : max(0, $rawDisplayOrder);

// ── Validações básicas ────────────────────────────────────────
$errors = [];

if (mb_strlen($body) > 20000 || mb_strlen($objectives) > 10000 || mb_strlen($features) > 10000 || mb_strlen($contribution) > 10000) {
    $errors[] = 'O conteúdo do estudo de caso excede o limite permitido.';
}

if (mb_strlen($title) < 2 || mb_strlen($title) > 200) {
    $errors[] = 'O título deve ter entre 2 e 200 caracteres.';
}
if (mb_strlen($summary) > 300) {
    $errors[] = 'O resumo não pode exceder 300 caracteres.';
}
if (mb_strlen($slug) < 2 || mb_strlen($slug) > 200) {
    $errors[] = 'O slug deve ter entre 2 e 200 caracteres.';
}
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    $errors[] = 'O slug só pode conter letras minúsculas, números e hífens.';
}
if (!in_array($previewMode, ['external_embed','external_link','internal','gallery','disabled'], true)) $errors[] = 'Modo de preview inválido.';
if (in_array($previewMode, ['external_embed','external_link'], true)) {
    $parts = parse_url($previewUrl);
    if (!filter_var($previewUrl, FILTER_VALIDATE_URL) || !is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http','https'], true) || isset($parts['user']) || isset($parts['pass'])) $errors[] = 'A URL externa do preview deve ser HTTP(S) válida.';
} elseif ($previewMode === 'internal' && (!preg_match('#\A/demos/[a-zA-Z0-9/_-]+(?:\.html)?\z#', $previewUrl) || str_contains($previewUrl, '..'))) {
    $errors[] = 'A demo interna deve apontar para um ficheiro estático em /demos/. PHP não é permitido.';
}
if (mb_strlen($previewUrl) > 500) $errors[] = 'O caminho do preview excede 500 caracteres.';
foreach (['URL Demo' => $urlDemo, 'URL GitHub' => $urlGithub, 'URL Live' => $urlLive] as $label => $url) {
    if ($url === '') {
        continue;
    }
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])) {
        $errors[] = $label . ' deve ser uma URL HTTP(S) válida.';
    }
}
if ($rawDisplayOrder === false || $rawDisplayOrder < 0) {
    $errors[] = 'A ordem de exibição deve ser um número inteiro igual ou superior a zero.';
}
if ($previewMode === 'disabled') $previewEnabled = 0;
if (!in_array($category, ['web','mobile','api','desktop','design','other'], true)) {
    $errors[] = 'Categoria inválida.';
}
if (!in_array($status, ['published','draft','archived'], true)) {
    $errors[] = 'Estado inválido.';
}

// ── Unicidade do slug ─────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM _projects WHERE slug_project = ?");
$stmt->execute([$slug]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = 'Este slug já está em uso. Escolhe um diferente.';
}

// ── Validação da imagem de capa (obrigatória) ─────────────────
$coverFile = $_FILES['cover_project'] ?? null;
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = null;

if (!is_array($coverFile) || !isset($coverFile['error'])) {
    $errors[] = 'A imagem de capa é obrigatória.';
} else {
    $uploadError = (int) $coverFile['error'];
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'A imagem de capa é obrigatória.';
    } elseif ($uploadError !== UPLOAD_ERR_OK) {
        $errors[] = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite de upload do servidor (' . round(MAX_UPLOAD_SIZE / 1024 / 1024, 2) . ' MB).',
            UPLOAD_ERR_PARTIAL => 'O upload da imagem ficou incompleto. Tenta novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor não tem uma pasta temporária para receber uploads.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu escrever o ficheiro enviado.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload da imagem.',
            default => 'Não foi possível receber a imagem. Tenta novamente.',
        };
    } else {
        $fileSize = isset($coverFile['size']) ? (int) $coverFile['size'] : 0;
        $tmpName = isset($coverFile['tmp_name']) && is_string($coverFile['tmp_name']) ? $coverFile['tmp_name'] : '';

        if ($fileSize <= 0 || $fileSize > MAX_UPLOAD_SIZE) {
            $errors[] = 'A imagem deve ter entre 1 byte e ' . round(MAX_UPLOAD_SIZE / 1024 / 1024, 2) . ' MB.';
        }
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $errors[] = 'O ficheiro de imagem recebido não é válido.';
        } else {
            $finfo = false;
            if (!function_exists('finfo_open')) {
                $errors[] = 'A validação de imagens não está disponível no servidor.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
            }
            if (function_exists('finfo_open') && $finfo === false) {
                $errors[] = 'Não foi possível validar o formato da imagem no servidor.';
            } elseif (function_exists('finfo_open') && $finfo !== false) {
                $detectedMime = finfo_file($finfo, $tmpName);
                unset($finfo); // finfo_close() is deprecated as of PHP 8.5.
                if (!is_string($detectedMime) || !array_key_exists($detectedMime, $allowedTypes)) {
                    $errors[] = 'Formato de imagem não permitido. Usa JPEG, PNG ou WebP.';
                } else {
                    $mime = $detectedMime;
                }
            }
        }
    }
}

// ── Validação do tech_stack (JSON válido) ────────────────────
$techArray = json_decode($techStack, true);
if (!is_array($techArray) || array_is_list($techArray) === false) {
    $errors[] = 'Formato inválido para o Tech Stack.';
} else {
    if (count($techArray) > 100) {
        $errors[] = 'O Tech Stack não pode conter mais de 100 tecnologias.';
    }
    foreach ($techArray as $technology) {
        if (!is_string($technology) || trim($technology) === '' || mb_strlen($technology) > 80) {
            $errors[] = 'Cada tecnologia deve ser um texto entre 1 e 80 caracteres.';
            break;
        }
    }
}

if (!empty($errors)) {
    $jsonError($errors);
}

// ── Upload da imagem ──────────────────────────────────────────
$ext       = $allowedTypes[(string) $mime];
$coverName = 'project_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest      = ROOT_PATH . '/assets/img/projects/' . $coverName;

if (!move_uploaded_file($coverFile['tmp_name'], $dest)) {
    $errors[] = 'Falha ao guardar a imagem. Verifica as permissões da pasta.';
    $jsonError($errors);
}

// ── Inserir na base de dados ──────────────────────────────────
try {
    $stmt = $db->prepare("
        INSERT INTO _projects
        (title_project, slug_project, summary_project, body_project, objectives_project, features_project, contribution_project,
         category_project, tech_stack, cover_project,
         url_demo, url_github, url_live, preview_mode, preview_url, preview_enabled,
         is_featured, display_order, status_project)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title, $slug, $summary, $body, $objectives, $features, $contribution,
        $category, $techStack, $coverName,
        $urlDemo, $urlGithub, $urlLive,
        $previewMode, $previewUrl !== '' ? $previewUrl : null, $previewEnabled,
        $isFeatured, $displayOrder, $status,
    ]);

    $newId = $db->lastInsertId();

    logAudit(
        (int)$_SESSION['admin_id'], null,
        'project.create', '_projects', (int)$newId,
        null,
        ['title' => $title, 'slug' => $slug, 'category' => $category, 'status' => $status]
    );

    echo json_encode(['success' => true, 'message' => 'Projecto criado com sucesso.']);
    exit;

} catch (Throwable $e) {
    // Remover imagem em caso de erro
    if (file_exists($dest)) unlink($dest);
    error_log('[ADD PROJECT] ' . $e->getMessage());
    $jsonError(['Erro interno ao guardar o projecto. Tenta novamente.'], 500);
}
