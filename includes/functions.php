<?php
// ══════════════════════════════════════════
// JMbenga Portfolio — functions.php
// Helpers globais
// ══════════════════════════════════════════

/** Saída segura para HTML. */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Redirecciona para um caminho interno da aplicação. */
if (!function_exists('redirect')) {
    function redirect(string $path, int $code = 302): void
    {
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/config.php';
        }

        if (preg_match('/[\r\n]/', $path)) {
            throw new InvalidArgumentException('Caminho de redireccionamento inválido.');
        }

        $path = trim($path);
        if ($path === '') {
            $path = '/home';
        }
        if (str_starts_with($path, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
            throw new InvalidArgumentException('O redireccionamento deve ser interno.');
        }

        $target = rtrim((string) BASE_URL, '/') . '/' . ltrim($path, '/');
        header('Location: ' . $target, true, $code);
        exit;
    }
}

/** Devolve JSON e termina a execução. */
if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            http_response_code(500);
            echo '{"error":"Não foi possível construir a resposta."}';
            exit;
        }

        echo $json;
        exit;
    }
}

/** Verifica se o pedido HTTP é POST. */
if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }
}

/**
 * Obtém o IP do cliente.
 * Por segurança, só confia nos cabeçalhos de proxy se REMOTE_ADDR pertencer
 * à lista TRUSTED_PROXY_IPS configurada pelo administrador.
 */
if (!function_exists('get_ip')) {
    function get_ip(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        $trustedProxies = defined('TRUSTED_PROXY_IPS') && is_array(TRUSTED_PROXY_IPS)
            ? TRUSTED_PROXY_IPS
            : [];

        if (!in_array($remote, $trustedProxies, true)) {
            return $remote;
        }

        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
        ];

        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $candidates[] = trim(explode(',', $forwarded)[0]);
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $remote;
    }
}

/** Gera um slug simples e seguro a partir de um título. */
if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = trim($text);
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        $transliterated = function_exists('iconv')
            ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text)
            : false;
        if (is_string($transliterated)) {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/[\s-]+/', '-', trim($text)) ?? '';
        return trim($text, '-');
    }
}

/** Obtém uma configuração da BD com cache por conexão PDO e chave. */
if (!function_exists('get_config')) {
    function get_config(PDO $pdo, string $key, string $default = ''): string
    {
        static $cache = [];
        $cacheKey = spl_object_id($pdo) . ':' . $key;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT config_value FROM _site_config WHERE config_key = ? LIMIT 1'
            );
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            $cache[$cacheKey] = $value === false ? $default : (string) $value;
        } catch (PDOException $exception) {
            error_log('[get_config] ' . $exception->getMessage());
            $cache[$cacheKey] = $default;
        }

        return $cache[$cacheKey];
    }
}

/**
 * Resolve a fotografia pública do perfil.
 * Prioridade: foto activa do administrador, depois fallback configurado.
 */
if (!function_exists('get_public_profile_photo')) {
    function get_public_profile_photo(PDO $pdo, string $fallback = ''): ?array
    {
        $candidates = [];

        try {
            $stmt = $pdo->query(
                "SELECT photo_employees
                 FROM _employees
                 WHERE photo_employees IS NOT NULL
                   AND photo_employees <> ''
                   AND status_employees = 'active'
                 ORDER BY (role = 'super_admin') DESC, id_employees ASC
                 LIMIT 1"
            );
            $dbPhoto = $stmt->fetchColumn();
            if (is_string($dbPhoto) && $dbPhoto !== '') {
                $candidates[] = $dbPhoto;
            }
        } catch (Throwable $exception) {
            error_log('[profile photo] ' . $exception->getMessage());
        }

        if ($fallback !== '') {
            $candidates[] = $fallback;
        }

        if (!defined('ROOT_PATH') || !defined('BASE_URL')) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $file = basename(str_replace('\\', '/', trim((string) $candidate)));
            if ($file === '' || $file === '.' || $file === '..') {
                continue;
            }

            $path = ROOT_PATH . '/assets/img/profile/' . $file;
            if (is_file($path)) {
                return [
                    'file' => $file,
                    'path' => $path,
                    'url'  => rtrim(BASE_URL, '/') . '/assets/img/profile/' . rawurlencode($file),
                ];
            }
        }

        return null;
    }
}

/** Gera e devolve o token CSRF da sessão actual. */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

/** Verifica token CSRF enviado por formulário ou cabeçalho HTTP. */
if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $expected = $_SESSION['csrf_token'] ?? '';
        $provided = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        return is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && $provided !== ''
            && hash_equals($expected, $provided);
    }
}

/** Verifica modo de manutenção ou bloqueio global da plataforma. */
if (!function_exists('check_maintenance_mode')) {
    function check_maintenance_mode(PDO $pdo): void
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $currentPath = parse_url($requestUri, PHP_URL_PATH);
        $currentPath = is_string($currentPath) ? $currentPath : '/';

        if (strpos($currentPath, '/status/') !== false) {
            return;
        }

        try {
            $stmt = $pdo->query(
                'SELECT status FROM _platform WHERE id_platform = 1 LIMIT 1'
            );
            $platform = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$platform) {
                return;
            }

            if (($platform['status'] ?? '') === 'maintenance') {
                redirect('/status/maintenance');
            }

            if (($platform['status'] ?? '') === 'blocked') {
                redirect('/status/blocked');
            }
        } catch (PDOException $exception) {
            // A falha neste controlo não deve bloquear todas as páginas públicas.
            error_log('[maintenance check] ' . $exception->getMessage());
        }
    }
}