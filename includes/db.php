<?php
// ══════════════════════════════════════════
// JMbenga Portfolio — db.php
// Ligação PDO reutilizável; $pdo fica disponível globalmente.
// ══════════════════════════════════════════

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo) || !$pdo instanceof PDO) {
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
            PDO::ATTR_TIMEOUT             => 10,
        ]);
    } catch (PDOException $exception) {
        error_log('[database connection] ' . $exception->getMessage());
        http_response_code(503);

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $isApiRequest = str_contains($requestPath, '/api/')
            || str_contains($accept, 'application/json');

        if ($isApiRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Serviço temporariamente indisponível. Tente novamente mais tarde.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            if (defined('APP_ENV') && APP_ENV === 'development') {
                echo "Não foi possível ligar à base de dados. Consulte o registo de erros do PHP.";
            } else {
                echo 'Serviço temporariamente indisponível. Tente novamente mais tarde.';
            }
        }
        exit;
    }
}