<?php
// ══════════════════════════════════════════
// JMbenga Portfolio — db.php
// PDO singleton — usar: require_once 'db.php'
// $pdo fica disponível globalmente
// ══════════════════════════════════════════

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo)) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            die('DB Error: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/status/503');
        exit;
    }
}
