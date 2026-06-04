<?php
// ══════════════════════════════════════════
// JMbenga Portfolio — functions.php
// Helpers globais
// ══════════════════════════════════════════

/**
 * Saída segura (escapa HTML)
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirecionar
 */
function redirect(string $path, int $code = 302): void {
    header("Location: " . BASE_URL . $path, true, $code);
    exit;
}

/**
 * Retornar JSON e terminar execução
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verificar se é pedido POST
 */
function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Obter IP real do visitante
 */
function get_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Gerar slug a partir de um título
 */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $text));
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

/**
 * Obter configuração do site da BD
 */
function get_config(PDO $pdo, string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("SELECT config_value FROM _site_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $cache[$key] = $stmt->fetchColumn() ?: $default;
    }
    return $cache[$key];
}

/**
 * Resolve a foto publica do perfil.
 * Prioridade: foto do admin em _employees, depois photo_profile nas configuracoes.
 */
function get_public_profile_photo(PDO $pdo, string $fallback = ''): ?array {
    $candidates = [];

    try {
        $stmt = $pdo->query("
            SELECT photo_employees
            FROM _employees
            WHERE photo_employees IS NOT NULL
              AND photo_employees <> ''
              AND status_employees = 'active'
            ORDER BY (role = 'super_admin') DESC, id_employees ASC
            LIMIT 1
        ");
        $dbPhoto = $stmt->fetchColumn();
        if ($dbPhoto) {
            $candidates[] = (string) $dbPhoto;
        }
    } catch (Throwable $e) {
        error_log('[profile photo] ' . $e->getMessage());
    }

    if ($fallback !== '') {
        $candidates[] = $fallback;
    }

    foreach ($candidates as $candidate) {
        $file = basename(str_replace('\\', '/', trim($candidate)));
        if ($file === '') {
            continue;
        }

        $path = ROOT_PATH . '/assets/img/profile/' . $file;
        if (is_file($path)) {
            return [
                'file' => $file,
                'path' => $path,
                'url'  => BASE_URL . '/assets/img/profile/' . rawurlencode($file),
            ];
        }
    }

    return null;
}

/**
 * Verificar token CSRF
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Verificar modo de manutenção / bloqueio
 * 
 * Consulta a tabela _platform.
 * - 'maintenance' → redireciona para /status/maintenance
 * - 'blocked'     → redireciona para /status/blocked
 * 
 * @param PDO $pdo Conexão à base de dados.
 * @return void
 */
function check_maintenance_mode(PDO $pdo): void {
    // Ignorar para páginas de erro/manutenção/bloqueio
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($current_path, '/status/') !== false) {
        return;
    }

    try {
        $stmt = $pdo->query("SELECT status FROM _platform WHERE id_platform = 1 LIMIT 1");
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$platform) return;

        if ($platform['status'] === 'maintenance') {
            redirect('/status/maintenance');
        } elseif ($platform['status'] === 'blocked') {
            redirect('/status/blocked');
        }
    } catch (PDOException $e) {
        // Se a BD estiver indisponível, não bloquear o acesso
        error_log('[maintenance check] ' . $e->getMessage());
    }
}