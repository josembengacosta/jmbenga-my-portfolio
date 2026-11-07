<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador de Configurações de Redes Sociais
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../include/functions_admin.php';

startAdminSession();
checkAdminRememberMe();
requireAdminLogin();
requireNoLockscreen();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

/**
 * Responde sempre em JSON e termina a execução.
 */
function jmSocialJsonResponse(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jmSocialJsonResponse(false, 'Método não permitido.', 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!is_string($token) || !validateAdminCsrf($token)) {
    jmSocialJsonResponse(false, 'Token de segurança inválido ou expirado. Actualiza a página e tenta novamente.', 403);
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    jmSocialJsonResponse(false, 'Sessão administrativa inválida. Inicia sessão novamente.', 401);
}

$db = $GLOBALS['pdo'] ?? null;
if (!$db instanceof PDO) {
    error_log('[SETTINGS SOCIAL] Ligação PDO indisponível.');
    jmSocialJsonResponse(false, 'Não foi possível estabelecer ligação à base de dados.', 500);
}

// Lista permitida de chaves. Não aceitamos nomes de campos enviados livremente.
$socialFields = [
    'github_url',
    'linkedin_url',
    'whatsapp_url',
    'twitter_url',
    'instagram_url',
    'youtube_url',
    'behance_url',
    'dribbble_url',
    'medium_url',
    'devto_url',
    'facebook_url',
    'telegram_url',
    'tiktok_url',
    'discord_url',
    'stackoverflow_url',
    'codepen_url',
];

/**
 * Valida URLs públicas e impede esquemas não web, credenciais embutidas
 * e valores excessivamente longos.
 */
function jmSocialIsValidHttpUrl(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

$values = [];
$errors = [];

foreach ($socialFields as $field) {
    $rawValue = $_POST[$field] ?? null;

    // A página envia sempre os 16 campos. Um pedido parcial não deve apagar
    // acidentalmente links que já estejam guardados.
    if (!is_string($rawValue)) {
        $errors[] = 'O campo "' . ucfirst(str_replace('_url', '', $field)) . '" está ausente ou tem um formato inválido.';
        continue;
    }

    $value = trim($rawValue);

    if (strlen($value) > 2048) {
        $errors[] = 'A URL do campo "' . ucfirst(str_replace('_url', '', $field)) . '" ultrapassa o limite de 2048 caracteres.';
        continue;
    }

    if ($value !== '' && !jmSocialIsValidHttpUrl($value)) {
        $errors[] = 'A URL do campo "' . ucfirst(str_replace('_url', '', $field)) . '" deve ser um endereço HTTP ou HTTPS válido.';
        continue;
    }

    $values[$field] = $value;
}

if ($errors !== []) {
    jmSocialJsonResponse(false, 'Existem campos que precisam de correcção.', 422, ['errors' => $errors]);
}

try {
    // _site_config possui uma chave única global em config_key.
    // O UPSERT evita a sequência SELECT/INSERT vulnerável a concorrência.
    // Mantém as 16 chaves no grupo social e publicamente disponíveis.
    $db->beginTransaction();

    $upsert = $db->prepare("
        INSERT INTO _site_config
            (config_key, config_value, config_group, is_public)
        VALUES
            (?, ?, 'social', 1)
        ON DUPLICATE KEY UPDATE
            config_value = VALUES(config_value),
            config_group = 'social',
            is_public = 1
    ");

    foreach ($socialFields as $field) {
        $upsert->execute([$field, $values[$field]]);
    }

    $db->commit();

    // A auditoria ocorre depois do commit: uma falha no log não deve
    // transformar uma gravação concluída numa resposta de erro enganadora.
    logAudit(
        $adminId,
        null,
        'settings.social.update',
        '_site_config',
        null,
        null,
        ['updated_keys' => $socialFields, 'config_group' => 'social']
    );

    jmSocialJsonResponse(
        true,
        'Redes sociais actualizadas com sucesso.',
        200,
        ['updated_count' => count($socialFields)]
    );
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[SETTINGS SOCIAL] ' . $exception->getMessage());
    jmSocialJsonResponse(false, 'Ocorreu um erro ao guardar as redes sociais. Nenhuma alteração foi confirmada.', 500);
}