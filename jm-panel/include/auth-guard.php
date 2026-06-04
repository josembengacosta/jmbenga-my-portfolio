<?php
// ══════════════════════════════════════════
// JMbenga Portfolio — auth-guard.php
// Incluir no TOPO de cada página do painel
// ══════════════════════════════════════════

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_name(SESSION_NAME);
session_start();

// Se não estiver autenticado → login
if (empty($_SESSION['id_employees'])) {
    header('Location: ' . BASE_URL . '/jm-panel/login');
    exit;
}

// Verificar lockscreen
if (!empty($_SESSION['lockscreen'])) {
    header('Location: ' . BASE_URL . '/jm-panel/lockscreen');
    exit;
}

// Carregar dados do admin autenticado
$stmt = $pdo->prepare("SELECT * FROM _employees WHERE id_employees = ? AND status_employees = 'active'");
$stmt->execute([$_SESSION['id_employees']]);
$currentAdmin = $stmt->fetch();

if (!$currentAdmin) {
    session_destroy();
    header('Location: ' . BASE_URL . '/jm-panel/login');
    exit;
}
