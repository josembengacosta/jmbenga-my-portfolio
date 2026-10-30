<?php
declare(strict_types=1);

/**
 * JMbenga Portfolio — Protecção das páginas autenticadas do ADMIN.
 * Ficheiro: jm-panel/include/auth-guard.php
 *
 * Usar no início das páginas protegidas do painel.
 * NÃO incluir nas páginas de login, recuperação/reset de senha ou lockscreen.
 * Utiliza o modelo de sessão centralizado em functions_admin.php.
 */

require_once __DIR__ . '/functions_admin.php';

// Inicia a sessão administrativa correcta (jm_panel_sess), cria o CSRF
// token, e tenta restaurar uma sessão válida através do cookie remember-me.
startAdminSession();
checkAdminRememberMe();

// Revalida a conta na base de dados e redirecciona para o login se necessário.
requireAdminLogin();

// Páginas protegidas não podem ser usadas enquanto a lockscreen estiver activa.
requireNoLockscreen();

// Disponibiliza os dados completos do administrador para as páginas que
// precisam de $currentAdmin, sem depender de uma segunda sessão PHPSESSID.
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM _employees
         WHERE id_employees = :id
           AND status_employees = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $adminId]);
    $currentAdmin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($currentAdmin === null) {
        logoutAdmin();
        redirect('/jm-panel/entrar');
    }
} catch (Throwable $e) {
    error_log('[ADMIN AUTH GUARD] Não foi possível carregar o administrador: ' . $e->getMessage());
    logoutAdmin();
    redirect('/jm-panel/entrar');
}

// Variáveis de apresentação usadas pelo cabeçalho partilhado.
$adminName = $adminName ?? trim(
    (string) ($currentAdmin['first_name'] ?? '') . ' ' . (string) ($currentAdmin['second_name'] ?? '')
);
if ($adminName === '') {
    $adminName = 'Administrador';
}

$adminPhoto = $adminPhoto ?? ($currentAdmin['photo_employees'] ?? null);

if (!isset($adminInitial) || !is_string($adminInitial) || $adminInitial === '') {
    $firstName = trim((string) ($currentAdmin['first_name'] ?? 'A'));
    $adminInitial = function_exists('mb_substr')
        ? mb_substr($firstName, 0, 1, 'UTF-8')
        : substr($firstName, 0, 1);
}