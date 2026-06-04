<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Logout do Painel
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../include/functions_admin.php';

startAdminSession();

// Executa logout (destrói sessão, apaga remember cookie, audita)
logoutAdmin();

// Redireciona para o login com mensagem opcional
redirect('/jm-panel/entrar?msg=logout');