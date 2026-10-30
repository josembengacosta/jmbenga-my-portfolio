<?php
/**
 * JMbenga Portfolio — configuração global por ambiente.
 *
 * Mantém os nomes das constantes legadas para não quebrar os includes existentes.
 * Em desenvolvimento lê .env; em produção lê .env.production.
 * Variáveis reais definidas pelo servidor têm prioridade sobre os ficheiros.
 */

declare(strict_types=1);

/* --------------------------------------------------------------------------
 | Leitura de variáveis de ambiente
 |------------------------------------------------------------------------- */
if (!function_exists('jmbenga_env')) {
    function jmbenga_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('jmbenga_load_env_file')) {
    function jmbenga_load_env_file(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            error_log('[config] Não foi possível ler o ficheiro de ambiente.');
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $name = $matches[1];
            $value = trim($matches[2]);

            // Não substituir configurações injectadas pelo servidor.
            if (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
                continue;
            }

            // Remover comentários inline apenas em valores não citados.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = substr($value, -1);
                $quoted = ($first === '"' && $last === '"') || ($first === "'" && $last === "'");
                if ($quoted) {
                    $value = substr($value, 1, -1);
                    if ($first === '"') {
                        $value = str_replace(
                            ['\\n', '\\r', '\\t', '\\"', '\\\\'],
                            ["\n", "\r", "\t", '"', '\\'],
                            $value
                        );
                    }
                } else {
                    $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
                }
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

if (!function_exists('jmbenga_env_file_value')) {
    function jmbenga_env_file_value(string $file, string $key): ?string
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[1]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                return substr($value, 1, -1);
            }
            return preg_replace('/\s+#.*$/', '', $value) ?? $value;
        }

        return null;
    }
}

/* --------------------------------------------------------------------------
 | Detectar ambiente antes de escolher o ficheiro .env
 |------------------------------------------------------------------------- */
$projectRoot = dirname(__DIR__);
$processAppEnv = getenv('APP_ENV');
$hasExplicitEnvironment = $processAppEnv !== false;
$explicitEnvironment = $hasExplicitEnvironment ? strtolower(trim($processAppEnv)) : '';

if ($hasExplicitEnvironment && !in_array($explicitEnvironment, ['development', 'production'], true)) {
    throw new RuntimeException('APP_ENV deve ser development ou production.');
}

$httpHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$parsedHost = parse_url('http://' . $httpHost, PHP_URL_HOST);
$parsedHost = is_string($parsedHost) ? rtrim($parsedHost, '.') : '';
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$localHosts = ['localhost', '127.0.0.1', '::1'];
$isLocalHost = in_array($parsedHost, $localHosts, true);
if (!$isLocalHost && filter_var($parsedHost, FILTER_VALIDATE_IP) !== false) {
    // Permite XAMPP aberto através de um IP privado na rede local.
    $isPrivateIPv4 = filter_var($parsedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && (str_starts_with($parsedHost, '10.')
            || str_starts_with($parsedHost, '192.168.')
            || preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $parsedHost));
    $isLocalHost = (bool) $isPrivateIPv4;
}
if ($parsedHost === '' && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
    $isLocalHost = true;
}

$hostEnvironment = $isLocalHost ? 'development' : 'production';

/* --------------------------------------------------------------------------
 | Escolher e carregar o ficheiro certo
 |------------------------------------------------------------------------- */
$envFileOverride = trim((string) jmbenga_env('JM_ENV_FILE', ''));
if ($envFileOverride !== '') {
    $envFile = $envFileOverride;
    $isAbsolutePath = str_starts_with($envFile, '/')
        || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $envFile);
    if (!$isAbsolutePath) {
        $envFile = $projectRoot . DIRECTORY_SEPARATOR . $envFile;
    }
} else {
    $envFileName = ($hasExplicitEnvironment ? $explicitEnvironment : $hostEnvironment) === 'production'
        ? '.env.production'
        : '.env';
    $envFile = $projectRoot . DIRECTORY_SEPARATOR . $envFileName;
}

if ($envFileOverride !== '' && (!is_file($envFile) || !is_readable($envFile))) {
    error_log('[config] O ficheiro JM_ENV_FILE não existe ou não pode ser lido.');
    http_response_code(503);
    exit('A configuração do ambiente está indisponível.');
}

jmbenga_load_env_file($envFile);

$fileEnvironmentValue = jmbenga_env_file_value($envFile, 'APP_ENV');
$hasFileEnvironment = $fileEnvironmentValue !== null;
$declaredEnvironment = $hasFileEnvironment ? strtolower(trim($fileEnvironmentValue)) : '';
if ($hasFileEnvironment && !in_array($declaredEnvironment, ['development', 'production'], true)) {
    throw new RuntimeException('APP_ENV no ficheiro de ambiente deve ser development ou production.');
}
$selectedEnvironment = $hasExplicitEnvironment
    ? $explicitEnvironment
    : ($envFileOverride !== '' && $hasFileEnvironment ? $declaredEnvironment : $hostEnvironment);

if ($hasFileEnvironment && $declaredEnvironment !== $selectedEnvironment) {
    error_log('[config] O APP_ENV do ficheiro de ambiente não corresponde ao ambiente detectado.');
    http_response_code(503);
    exit('A configuração do ambiente está inconsistente.');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', $selectedEnvironment);
}

/* --------------------------------------------------------------------------
 | Definições — nomes compatíveis com a configuração original
 |------------------------------------------------------------------------- */
$environmentDefaults = APP_ENV === 'development'
    ? [
        'BASE_URL' => 'http://localhost/portfolio-jmbenga',
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'portfolio_db',
        'DB_USER' => 'root',
        'DB_PASS' => '',
    ]
    : [
        // Em produção, as credenciais da base de dados têm de estar explícitas.
        'BASE_URL' => 'https://jmbenga.freedev.app',
        'DB_HOST' => '',
        'DB_NAME' => '',
        'DB_USER' => '',
        'DB_PASS' => '',
    ];

$settings = array_merge($environmentDefaults, [
    'ADMIN_PATH' => '/jm-panel',
    'DB_CHARSET' => 'utf8mb4',
    'SESSION_LIFETIME' => '3600',
    'DEEPSEEK_API_KEY' => '',
    'GEMINI_API_KEY' => '',
    'GEMINI_DEFAULT_MODEL' => 'gemini-2.5-flash',
    'MAIL_HOST' => 'smtp.gmail.com',
    'MAIL_PORT' => '587',
    'MAIL_SECURE' => 'tls',
    'MAIL_USER' => '',
    'MAIL_PASS' => '',
    'MAIL_FROM' => '',
    'MAIL_FROM_NAME' => 'JMbenga Portfolio',
]);

$values = [];
foreach ($settings as $name => $default) {
    $values[$name] = defined($name) ? constant($name) : (string) jmbenga_env($name, (string) $default);
}
$values['BASE_URL'] = rtrim((string) $values['BASE_URL'], '/');
$values['ADMIN_PATH'] = '/' . trim((string) $values['ADMIN_PATH'], '/');
$values['SESSION_LIFETIME'] = max(60, (int) $values['SESSION_LIFETIME']);
$values['MAIL_PORT'] = max(1, min(65535, (int) $values['MAIL_PORT']));

if (!defined('BASE_URL')) define('BASE_URL', (string) $values['BASE_URL']);
if (!defined('ADMIN_PATH')) define('ADMIN_PATH', (string) $values['ADMIN_PATH']);
if (!defined('DB_HOST')) define('DB_HOST', (string) $values['DB_HOST']);
if (!defined('DB_NAME')) define('DB_NAME', (string) $values['DB_NAME']);
if (!defined('DB_USER')) define('DB_USER', (string) $values['DB_USER']);
if (!defined('DB_PASS')) define('DB_PASS', (string) $values['DB_PASS']);
if (!defined('DB_CHARSET')) define('DB_CHARSET', (string) $values['DB_CHARSET']);
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', (int) $values['SESSION_LIFETIME']);
if (!defined('DEEPSEEK_API_KEY')) define('DEEPSEEK_API_KEY', (string) $values['DEEPSEEK_API_KEY']);
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', (string) $values['GEMINI_API_KEY']);
if (!defined('GEMINI_DEFAULT_MODEL')) define('GEMINI_DEFAULT_MODEL', (string) $values['GEMINI_DEFAULT_MODEL']);
if (!defined('MAIL_HOST')) define('MAIL_HOST', (string) $values['MAIL_HOST']);
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int) $values['MAIL_PORT']);
if (!defined('MAIL_SECURE')) define('MAIL_SECURE', (string) $values['MAIL_SECURE']);
if (!defined('MAIL_USER')) define('MAIL_USER', (string) $values['MAIL_USER']);
if (!defined('MAIL_PASS')) define('MAIL_PASS', (string) $values['MAIL_PASS']);
if (!defined('MAIL_FROM')) define('MAIL_FROM', (string) $values['MAIL_FROM']);
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', (string) $values['MAIL_FROM_NAME']);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $projectRoot);
}

// Endereços de proxy confiáveis, separados por vírgula. Só usar se forem conhecidos.
$trustedProxyIps = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) jmbenga_env('TRUSTED_PROXY_IPS', ''))
), static fn(string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false));
if (!defined('TRUSTED_PROXY_IPS')) {
    define('TRUSTED_PROXY_IPS', $trustedProxyIps);
}

/* --------------------------------------------------------------------------
 | Validar configuração de produção sem expor segredos
 |------------------------------------------------------------------------- */
if (APP_ENV === 'production') {
    $missing = [];
    foreach (['BASE_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $required) {
        if (trim((string) constant($required)) === '') {
            $missing[] = $required;
        }
    }

    $baseScheme = strtolower((string) parse_url(BASE_URL, PHP_URL_SCHEME));
    $baseHost = (string) parse_url(BASE_URL, PHP_URL_HOST);
    if ($baseScheme !== 'https' || $baseHost === '') {
        $missing[] = 'BASE_URL (deve utilizar HTTPS e um host válido)';
    }

    if ($missing !== []) {
        error_log('[config] Configuração de produção incompleta: ' . implode(', ', $missing));
        http_response_code(503);
        exit('O serviço está temporariamente indisponível devido à configuração.');
    }
}

/* --------------------------------------------------------------------------
 | Sessão e compatibilidade com o painel administrativo legado
 |------------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps = strtolower((string) parse_url(BASE_URL, PHP_URL_SCHEME)) === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    session_set_cookie_params([
        'lifetime' => (int) SESSION_LIFETIME,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
