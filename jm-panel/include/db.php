<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — db.php (conexão PDO única)
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

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Ligações persistentes são opcionais. Ativar apenas se o ambiente
        // de alojamento tiver sido testado com esta opção.
        if (defined('DB_PERSISTENT') && DB_PERSISTENT === true) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // O detalhe técnico fica apenas no log do servidor.
        error_log('[JMbenga Portfolio][DB] Falha na conexão PDO: ' . $e->getMessage());
        http_response_code(500);

        if (defined('APP_ENV') && APP_ENV === 'development') {
            $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            exit('Erro de conexão com a base de dados: ' . $detail);
        }

        // Evita redirecionamentos potencialmente circulares para uma página
        // de erro que também possa depender desta conexão.
        exit('Serviço temporariamente indisponível. Tenta novamente mais tarde.');
    }
}