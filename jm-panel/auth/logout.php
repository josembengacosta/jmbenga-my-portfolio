<?php
declare(strict_types=1);

// JMbenga Portfolio — Encerramento seguro da sessão do ADMIN.
// Ficheiro: jm-panel/auth/logout.php

require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// O logout altera o estado da sessão; por isso, só aceita POST.
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo 'Método não permitido. Volte ao painel e utilize a opção Terminar Sessão.';
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !validateAdminCsrf($csrfToken)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo 'Pedido inválido ou expirado. Atualize a página do painel e tente novamente.';
    exit;
}

// A confirmação só é válida para uma sessão administrativa autenticada.
if (!isAdminLoggedIn()) {
    redirect('/jm-panel/entrar');
}

// Audita a saída, invalida o cookie remember-me e termina a sessão.
logoutAdmin();

// Impede que páginas autenticadas sejam reutilizadas a partir da cache.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

redirect('/jm-panel/entrar?msg=logout');