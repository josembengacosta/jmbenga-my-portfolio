<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Ligação PDO centralizada
// A falha de BD nunca apresenta detalhes técnicos em produção.
// ══════════════════════════════════════════════════════════════

declare(strict_types=1);

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => 10,
        ]);
    } catch (PDOException $exception) {
        // Os detalhes completos ficam apenas no log privado do servidor.
        $exceptionMessage = preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) ?? 'Erro PDO sem mensagem.';
        $sqlState = isset($exception->errorInfo[0])
            ? (string) $exception->errorInfo[0]
            : (string) $exception->getCode();
        $environment = defined('APP_ENV') ? (string) APP_ENV : 'unknown';

        error_log(sprintf(
            '[JMbenga][database connection][%s][SQLSTATE %s] %s: %s',
            $environment,
            $sqlState,
            $exception::class,
            $exceptionMessage
        ));

        // O diagnóstico só pode ser apresentado quando APP_ENV é development
        // E o pedido chega por um host local/privado. Assim, um APP_ENV mal
        // configurado como development num domínio público não expõe detalhes.
        $httpHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $requestHost = parse_url('http://' . $httpHost, PHP_URL_HOST);
        $requestHost = is_string($requestHost) ? rtrim($requestHost, '.') : '';
        $isLoopbackHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
        $isPrivateIPv4 = filter_var($requestHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && (
                str_starts_with($requestHost, '10.')
                || str_starts_with($requestHost, '192.168.')
                || (bool) preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $requestHost)
            );
        $showDevelopmentDetails = defined('APP_ENV')
            && APP_ENV === 'development'
            && ($isLoopbackHost || $isPrivateIPv4);

        // Pedidos API/AJAX recebem JSON, mantendo o contrato dos formulários do painel.
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? $requestPath : '/';
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        $isApiOrAjax = str_contains($requestPath, '/api/')
            || str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $requestedWith === 'xmlhttprequest';

        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if ($isApiOrAjax) {
            header('Content-Type: application/json; charset=utf-8');

            $message = $showDevelopmentDetails
                ? sprintf('Falha na ligação PDO (SQLSTATE %s): %s', $sqlState, $exceptionMessage)
                : 'Serviço temporariamente indisponível. Tenta novamente dentro de instantes.';

            echo json_encode([
                'success' => false,
                'message' => $message,
                'code' => 'database_unavailable',
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        // A página 503 é autónoma: não carrega config.php, db.php nem o layout
        // normal do site, pelo que continua disponível mesmo com a BD desligada.
        $GLOBALS['jmbenga_db_unavailable'] = true;
        $GLOBALS['jmbenga_show_db_diagnostics'] = $showDevelopmentDetails;
        $GLOBALS['jmbenga_db_diagnostic'] = $showDevelopmentDetails
            ? [
                'environment' => 'development',
                'sql_state' => $sqlState,
                'exception' => $exception::class,
                'message' => $exceptionMessage,
            ]
            : null;
        $GLOBALS['jmbenga_503_retry_after'] = 60;

        require dirname(__DIR__) . '/status/503.php';
        exit;
    }
}