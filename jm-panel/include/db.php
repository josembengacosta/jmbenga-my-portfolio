<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — db.php (conexão PDO única)
// ══════════════════════════════════════════════════════════════

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo)) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Conexão persistente (recomendado para shared hosting)
        if (defined('DB_PERSISTENT') && DB_PERSISTENT) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            die('Erro de conexão: ' . $e->getMessage());
        }
        // Em produção, redirecionar para página de erro 500
        header('Location: ' . BASE_URL . '/status/500');
        exit;
    }
}