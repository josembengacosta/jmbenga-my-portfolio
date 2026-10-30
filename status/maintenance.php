<?php
// JMbenga Portfolio — Página de manutenção
// Ficheiro: status/maintenance.php
http_response_code(503);

$site_name       = 'JMbenga';
$contact_email   = 'josembengadacosta@gmail.com';
$platform_ver    = '1.0';
$maint_msg       = 'Estamos a realizar melhorias técnicas para lhe oferecer a melhor experiência.';
$maint_end_ts    = null;
$maint_start_ts  = null;
$services        = [];
$pdo             = null;
$base_url        = '/';

// Normaliza BASE_URL para ficar com uma única barra final. Se a configuração
// não estiver disponível, tenta reconhecer a instalação local em /status/.
$resolveBaseUrl = static function (): string {
    if (defined('BASE_URL') && is_string(BASE_URL) && BASE_URL !== '') {
        $candidate = BASE_URL;
    } else {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('~^(.*?)/status/maintenance\.php$~i', $scriptName, $matches)) {
            $candidate = ($matches[1] !== '' ? $matches[1] : '') . '/';
        } else {
            $candidate = '/';
        }
    }

    $candidate = rtrim($candidate, '/') . '/';
    return $candidate === '//' ? '/' : $candidate;
};

// Converte datas guardadas na BD em timestamps, interpretando datas sem fuso
// explícito no horário local de Angola.
$parseMaintenanceDate = static function ($value): ?int {
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('Africa/Luanda'));
        return $date->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
};

try {
    require_once __DIR__ . '/../includes/config.php';
    $base_url = $resolveBaseUrl();
    require_once __DIR__ . '/../includes/db.php';

    if ($pdo instanceof PDO) {
        $platformQuery = $pdo->query(
            'SELECT status, version, maintenance_msg, maintenance_start, maintenance_end, maintenance_services
             FROM _platform WHERE id_platform = 1 LIMIT 1'
        );
        $platform = $platformQuery->fetch(PDO::FETCH_ASSOC);

        if (is_array($platform)) {
            // Se a manutenção foi desativada, regressa ao site normal.
            if (strtolower(trim((string) ($platform['status'] ?? ''))) !== 'maintenance') {
                header('Location: ' . $base_url, true, 302);
                exit;
            }

            $platform_ver = (string) ($platform['version'] ?? '1.0');
            if (isset($platform['maintenance_msg']) && is_string($platform['maintenance_msg'])
                && trim($platform['maintenance_msg']) !== '') {
                $maint_msg = $platform['maintenance_msg'];
            }
            $maint_end_ts = $parseMaintenanceDate($platform['maintenance_end'] ?? null);
            $maint_start_ts = $parseMaintenanceDate($platform['maintenance_start'] ?? null);

            if (!empty($platform['maintenance_services']) && is_string($platform['maintenance_services'])) {
                $decodedServices = json_decode($platform['maintenance_services'], true);
                if (is_array($decodedServices)) {
                    $services = $decodedServices;
                }
            }
        }

        $configQuery = $pdo->query(
            "SELECT config_key, config_value FROM _site_config
             WHERE config_key IN ('site_name', 'email_contact')"
        );
        $siteConfigs = $configQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name = (string) ($siteConfigs['site_name'] ?? 'JMbenga');
        $configuredEmail = (string) ($siteConfigs['email_contact'] ?? 'josembengadacosta@gmail.com');
        if (filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)) {
            $contact_email = $configuredEmail;
        }
    }
} catch (Throwable $e) {
    // Se a BD estiver indisponível, a página continua a mostrar a mensagem
    // de manutenção com valores de reserva; o detalhe fica apenas no log.
    error_log('[maintenance] ' . $e->getMessage());
}

$base_url = $resolveBaseUrl();

// O cabeçalho deve sugerir uma nova tentativa coerente com a previsão, quando
// esta existe. Sem previsão válida, utiliza uma hora como intervalo indicativo.
$now = time();
$seconds_remaining = ($maint_end_ts !== null && $maint_end_ts > $now)
    ? $maint_end_ts - $now
    : 0;
$seconds_total = 0;
if ($maint_start_ts !== null && $maint_end_ts !== null && $maint_end_ts > $maint_start_ts) {
    $seconds_total = $maint_end_ts - $maint_start_ts;
} elseif ($seconds_remaining > 0) {
    $seconds_total = $seconds_remaining;
}
header('Retry-After: ' . max(1, $seconds_remaining > 0 ? $seconds_remaining : 3600));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$luandaTimezone = new DateTimeZone('Africa/Luanda');
if ($maint_end_ts !== null && $maint_end_ts > $now) {
    $maint_end_fmt = (new DateTimeImmutable('@' . $maint_end_ts))
        ->setTimezone($luandaTimezone)
        ->format('d/m/Y \\à\\s H:i') . ' (WAT)';
} elseif ($maint_end_ts !== null && $maint_end_ts <= $now) {
    $maint_end_fmt = 'Prazo previsto ultrapassado';
} else {
    $maint_end_fmt = 'Sem previsão definida';
}

$service_labels = [
    'auth'          => 'Autenticação',
    'projects'      => 'Projectos',
    'contact'       => 'Formulário de Contacto',
    'database'      => 'Base de Dados',
    'api'           => 'API',
    'notifications' => 'Notificações',
];
$state_map = [
    'ok'      => ['dot' => 's-ok',      'badge' => 'badge-ok',      'icon' => 'fa-check',         'text' => 'Online'],
    'working' => ['dot' => 's-working', 'badge' => 'badge-working', 'icon' => 'fa-sync-alt',      'text' => 'A actualizar'],
    'pending' => ['dot' => 's-pending', 'badge' => 'badge-pending', 'icon' => 'fa-hourglass-half', 'text' => 'Pendente'],
    'down'    => ['dot' => 's-down',    'badge' => 'badge-down',    'icon' => 'fa-times',          'text' => 'Indisponível'],
];

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$sendJson = static function (array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

// Endpoints AJAX são tratados nesta página para preservar a instalação atual.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = is_string($_POST['action']) ? $_POST['action'] : '';

    if (!($pdo instanceof PDO)) {
        $sendJson(['ok' => false, 'message' => 'O registo está temporariamente indisponível. Tenta novamente mais tarde.'], 503);
    }

    if ($action === 'notify_register') {
        $emailInput = $_POST['email'] ?? '';
        $emailInput = is_string($emailInput) ? trim($emailInput) : '';
        if ($emailInput === '' || strlen($emailInput) > 254 || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $sendJson(['ok' => false, 'message' => 'E-mail inválido.'], 422);
        }

        $email = strtolower($emailInput);
        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : '0.0.0.0';

        try {
            $check = $pdo->prepare('SELECT id_notify FROM _maintenance_notify WHERE email_notify = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch(PDO::FETCH_ASSOC)) {
                $sendJson(['ok' => true, 'message' => 'Este e-mail já está na lista de avisos.']);
            }

            $insert = $pdo->prepare('INSERT INTO _maintenance_notify (email_notify, ip_notify) VALUES (?, ?)');
            $insert->execute([$email, $ip]);
            $sendJson(['ok' => true, 'message' => 'Registado! Serás avisado quando o site regressar.']);
        } catch (Throwable $e) {
            error_log('[maintenance-notify-register] ' . $e->getMessage());
            $sendJson(['ok' => false, 'message' => 'Erro ao registar. Tenta novamente.'], 500);
        }
    }

    if ($action === 'notify_count') {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM _maintenance_notify WHERE sent = 0')->fetchColumn();
            $sendJson(['ok' => true, 'count' => max(0, $count)]);
        } catch (Throwable $e) {
            error_log('[maintenance-notify-count] ' . $e->getMessage());
            $sendJson(['ok' => false, 'count' => 0], 500);
        }
    }

    $sendJson(['ok' => false, 'message' => 'Acção inválida.'], 400);
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <link rel="icon" href="<?= $escape($base_url . 'assets/img/icons/favicon.ico') ?>">
    <title>Em Manutenção — <?= $escape($site_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --accent: #f59e0b;
        --accent-glow: rgba(245, 158, 11, .2);
        --bg: #0a0d14;
        --bg-2: #0f1623;
        --bg-card: #131b2e;
        --border: rgba(255, 255, 255, .06);
        --text: #e8edf5;
        --text-dim: #8899b4;
        --text-muted: #4a5878;
        --font-head: 'Syne', sans-serif;
        --font-body: 'DM Sans', sans-serif;
        --font-mono: 'DM Mono', monospace;
        --radius: 12px;
        --radius-lg: 20px;
    }

    [data-theme="light"] {
        --bg: #f0f4fb;
        --bg-2: #e4eaf6;
        --bg-card: #ffffff;
        --border: rgba(0, 0, 0, .07);
        --text: #0f172a;
        --text-dim: #475569;
        --text-muted: #94a3b8;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0
    }

    html,
    body {
        height: 100%;
        margin: 0;
    }

    body {
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        /* deixa o conteúdo começar no topo */
        min-height: 100vh;
        /* garante que o body ocupa pelo menos a altura total */
        overflow-y: auto;
        /* scroll vertical quando necessário */
        overflow-x: hidden;
        transition: background .35s, color .35s;
        padding: 2rem 1rem;
    }

    /* Orbs de fundo */
    .bg-orbs {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden
    }

    .bg-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: .12;
        animation: floatOrb 16s ease-in-out infinite
    }

    .bg-orb:nth-child(1) {
        width: 520px;
        height: 520px;
        background: var(--accent);
        top: -160px;
        left: -110px;
        animation-delay: 0s
    }

    .bg-orb:nth-child(2) {
        width: 400px;
        height: 400px;
        background: #7c3aed;
        bottom: -120px;
        right: -90px;
        animation-delay: -6s
    }

    .bg-orb:nth-child(3) {
        width: 300px;
        height: 300px;
        background: #2563eb;
        top: 42%;
        left: 56%;
        animation-delay: -11s
    }

    @keyframes floatOrb {

        0%,
        100% {
            transform: translate(0, 0) scale(1)
        }

        33% {
            transform: translate(28px, -38px) scale(1.05)
        }

        66% {
            transform: translate(-18px, 22px) scale(.96)
        }
    }

    .page-wrap {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 580px;
        text-align: center
    }

    /* Logo */
    .brand {
        font-family: var(--font-head);
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--text);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 2rem
    }

    .brand span {
        color: var(--accent)
    }

    .brand-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 10px var(--accent);
        animation: pulseDot 2s ease-in-out infinite
    }

    @keyframes pulseDot {

        0%,
        100% {
            box-shadow: 0 0 6px var(--accent)
        }

        50% {
            box-shadow: 0 0 18px var(--accent), 0 0 32px var(--accent-glow)
        }
    }

    /* Card */
    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 2.5rem 2rem;
        backdrop-filter: blur(20px);
        box-shadow: 0 0 60px var(--accent-glow), 0 24px 64px rgba(0, 0, 0, .4);
    }

    .icon-wrap {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: rgba(245, 158, 11, .1);
        border: 1px solid rgba(245, 158, 11, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        color: var(--accent);
        animation: iconAnim 3.5s ease-in-out infinite
    }

    @keyframes iconAnim {

        0%,
        100% {
            transform: translateY(0) rotate(0deg)
        }

        30% {
            transform: translateY(-8px) rotate(-6deg)
        }

        60% {
            transform: translateY(-3px) rotate(4deg)
        }
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(245, 158, 11, .1);
        border: 1px solid rgba(245, 158, 11, .2);
        border-radius: 999px;
        padding: 4px 14px;
        font-family: var(--font-head);
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--accent);
        margin-bottom: 1rem
    }

    .badge .live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulseDot 1.4s ease-in-out infinite
    }

    .title {
        font-family: var(--font-head);
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: .5rem
    }

    .title span {
        color: var(--accent)
    }

    .desc {
        font-size: .9rem;
        color: var(--text-dim);
        line-height: 1.7;
        margin-bottom: .5rem
    }

    .endtime {
        font-size: .75rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px
    }

    .endtime i {
        color: var(--accent)
    }

    /* Countdown */
    .cd-wrap {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 1.5rem
    }

    .cd-block {
        background: rgba(255, 255, 255, .04);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .7rem 1rem;
        min-width: 64px;
        text-align: center
    }

    .cd-num {
        font-family: var(--font-mono);
        font-size: 1.6rem;
        font-weight: 500;
        color: var(--accent);
        line-height: 1;
        display: block;
        margin-bottom: .2rem
    }

    .cd-lbl {
        font-size: .6rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .08em
    }

    .prog-wrap {
        margin-bottom: 1.5rem
    }

    .prog-track {
        height: 4px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .06);
        overflow: hidden;
        margin-bottom: .3rem
    }

    .prog-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--accent), #f97316);
        transition: width 1s linear;
        box-shadow: 0 0 10px rgba(245, 158, 11, .4)
    }

    .prog-meta {
        display: flex;
        justify-content: space-between;
        font-size: .65rem;
        color: var(--text-muted)
    }

    /* Serviços */
    .services-title {
        text-align: left;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-muted);
        margin-bottom: .5rem
    }

    .service-list {
        list-style: none;
        text-align: left;
        margin-bottom: 1.5rem
    }

    .service-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: .45rem .5rem;
        border-radius: 8px;
        font-size: .8rem;
        color: var(--text-dim);
        border-bottom: 1px solid rgba(255, 255, 255, .03)
    }

    .service-list li:last-child {
        border-bottom: none
    }

    .s-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0
    }

    .s-ok {
        background: #22c55e;
        box-shadow: 0 0 6px rgba(34, 197, 94, .5)
    }

    .s-working {
        background: var(--accent);
        box-shadow: 0 0 6px var(--accent-glow);
        animation: pulseDot 1.6s ease-in-out infinite
    }

    .s-pending {
        background: #fbbf24;
        box-shadow: 0 0 6px rgba(251, 191, 36, .4)
    }

    .s-down {
        background: #f87171;
        box-shadow: 0 0 6px rgba(248, 113, 113, .4)
    }

    .s-badge {
        margin-left: auto;
        font-size: .65rem;
        font-weight: 700;
        padding: .15rem .5rem;
        border-radius: 999px
    }

    .badge-ok {
        background: rgba(34, 197, 94, .1);
        color: #22c55e
    }

    .badge-working {
        background: rgba(245, 158, 11, .1);
        color: var(--accent)
    }

    .badge-pending {
        background: rgba(251, 191, 36, .1);
        color: #fbbf24
    }

    .badge-down {
        background: rgba(248, 113, 113, .1);
        color: #f87171
    }

    /* Formulário */
    .notify-lbl {
        text-align: left;
        font-size: .75rem;
        color: var(--text-dim);
        display: block;
        margin-bottom: .4rem
    }

    .notify-group {
        display: flex;
        gap: 8px;
        margin-bottom: .5rem
    }

    .notify-input {
        flex: 1;
        background: rgba(255, 255, 255, .04);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: .55rem .8rem;
        color: var(--text);
        font-family: var(--font-body);
        font-size: .82rem;
        outline: none;
        transition: border-color .2s
    }

    .notify-input:focus {
        border-color: var(--accent)
    }

    .btn-notify {
        background: var(--accent);
        color: #000;
        border: none;
        border-radius: var(--radius);
        padding: .55rem 1rem;
        font-family: var(--font-body);
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
        white-space: nowrap
    }

    .btn-notify:hover {
        background: #fbbf24;
        transform: translateY(-1px)
    }

    .btn-notify:disabled {
        opacity: .6;
        cursor: not-allowed
    }

    .notify-fb {
        font-size: .72rem;
        text-align: left;
        display: none
    }

    .notify-fb.ok {
        color: #22c55e
    }

    .notify-fb.err {
        color: #f87171
    }

    .notify-count {
        font-size: .68rem;
        color: var(--text-muted);
        text-align: right;
        margin-top: .2rem
    }

    /* Botões */
    .action-row {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 1.5rem
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: .65rem 1.4rem;
        border-radius: var(--radius);
        font-family: var(--font-body);
        font-size: .85rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all .25s
    }

    .btn-primary {
        background: var(--accent);
        color: #000;
        box-shadow: 0 0 20px var(--accent-glow)
    }

    .btn-primary:hover {
        transform: translateY(-2px)
    }

    .btn-ghost {
        background: transparent;
        color: var(--text);
        border: 1px solid var(--border)
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent)
    }

    /* Footer */
    .footer {
        font-size: .7rem;
        color: var(--text-muted);
        margin-top: 2rem
    }

    .footer a {
        color: var(--accent);
        text-decoration: none
    }

    /* Tema */
    .theme-toggle {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 38px;
        height: 38px;
        border-radius: 9px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        font-size: .95rem;
        transition: all .25s
    }

    .theme-toggle:hover {
        border-color: var(--accent);
        color: var(--accent)
    }

    /* Animações */
    .fade-in {
        opacity: 0;
        animation: fadeUp .5s ease forwards
    }

    .fade-in:nth-child(1) {
        animation-delay: .05s
    }

    .fade-in:nth-child(2) {
        animation-delay: .13s
    }

    .fade-in:nth-child(3) {
        animation-delay: .21s
    }

    .fade-in:nth-child(4) {
        animation-delay: .29s
    }

    .fade-in:nth-child(5) {
        animation-delay: .37s
    }

    .fade-in:nth-child(6) {
        animation-delay: .45s
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(18px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    @media(max-width:480px) {
        .card {
            padding: 1.8rem 1.2rem
        }

        .notify-group {
            flex-direction: column
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
            scroll-behavior: auto !important;
        }
    }
    </style>
</head>

<body>

    <div class="bg-orbs">
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
    </div>

    <button class="theme-toggle" id="themeToggle" title="Alternar tema"><i class="fas fa-moon"
            id="themeIcon"></i></button>

    <div class="page-wrap">
        <a class="brand" href="<?= $escape($base_url) ?>">
            <span class="brand-dot"></span>
            <?= $escape($site_name) ?>
        </a>

        <div class="card">
            <div class="icon-wrap"><i class="fas fa-tools"></i></div>
            <div class="badge"><span class="live-dot"></span> Manutenção em curso</div>
            <h1 class="title">Melhorias em <span>Progresso</span></h1>
            <p class="desc"><?= $escape($maint_msg) ?></p>

            <div class="endtime">
                <i class="far fa-clock"></i>
                Regresso previsto: <strong style="color:var(--text-dim)"><?= $escape($maint_end_fmt) ?></strong>
            </div>

            <!-- Countdown -->
            <?php if ($seconds_remaining > 0): ?>
            <div class="cd-wrap">
                <div class="cd-block"><span class="cd-num" id="cdHours">--</span><span class="cd-lbl">Horas</span></div>
                <div class="cd-block"><span class="cd-num" id="cdMinutes">--</span><span class="cd-lbl">Minutos</span>
                </div>
                <div class="cd-block"><span class="cd-num" id="cdSeconds">--</span><span class="cd-lbl">Segundos</span>
                </div>
            </div>
            <div class="prog-wrap">
                <div class="prog-track">
                    <div class="prog-fill" id="progFill" style="width:100%"></div>
                </div>
                <div class="prog-meta"><span id="progElapsed">—</span><span id="progPct">—</span></div>
            </div>
            <?php endif; ?>

            <!-- Estado dos Serviços -->
            <?php if (!empty($services)): ?>
            <div class="services-title"><i class="fas fa-hdd"></i> Estado dos Serviços</div>
            <ul class="service-list">
                <?php foreach ($services as $key => $state):
                $label = $service_labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                $state = is_string($state) && array_key_exists($state, $state_map) ? $state : 'pending';
                $s = $state_map[$state];
            ?>
                <li>
                    <span class="s-dot <?= $s['dot'] ?>"></span>
                    <?= $escape($label) ?>
                    <span class="s-badge <?= $escape($s['badge']) ?>"><i
                            class="fas <?= $escape($s['icon']) ?> me-1"></i><?= $escape($s['text']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <!-- Formulário de notificação -->
            <label class="notify-lbl" for="notifyEmail"><i class="far fa-envelope"></i> Queres ser avisado quando
                regressarmos?</label>
            <div class="notify-group">
                <input type="email" id="notifyEmail" class="notify-input" placeholder="O teu e-mail"
                    autocomplete="email" maxlength="254" required>
                <button class="btn-notify" id="btnNotify" type="button"><i class="fas fa-bell"></i> Avisar-me</button>
            </div>
            <div class="notify-fb" id="notifyFb" aria-live="polite" role="status"></div>
            <div class="notify-count" id="notifyCount"></div>

            <!-- Botões -->
            <div class="action-row">
                <a href="<?= $escape($base_url) ?>" class="btn btn-ghost"><i class="fas fa-home"></i> Tentar Aceder</a>
            </div>
        </div>

        <div class="footer">
            &copy; <?= date('Y', time()) ?> <?= $escape($site_name) ?> · Luanda, Angola ·
            v<?= $escape($platform_ver) ?><br>
            <a href="mailto:<?= $escape($contact_email) ?>"><?= $escape($contact_email) ?></a>
        </div>
    </div>

    <script>
    // ── Dados vindos do PHP ──────────────────────────────────────
    var SECONDS_REMAINING = <?= (int)$seconds_remaining ?>;
    var SECONDS_TOTAL = <?= (int)max(1, $seconds_total ?: $seconds_remaining ?: 1) ?>;
    var BASE_URL = <?= json_encode($base_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // ── Tema ─────────────────────────────────────────────────────
    (function() {
        var html = document.documentElement,
            toggle = document.getElementById('themeToggle'),
            icon = document.getElementById('themeIcon'),
            saved = 'dark';
        try {
            saved = localStorage.getItem('jm_theme') || 'dark';
        } catch (e) {
            // O tema padrão continua funcional quando o armazenamento é bloqueado.
        }

        function apply(t) {
            if (t !== 'dark' && t !== 'light') t = 'dark';
            html.dataset.theme = t;
            if (icon) icon.className = t === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            try {
                localStorage.setItem('jm_theme', t);
            } catch (e) {
                // Não impedir a página de funcionar por falha do localStorage.
            }
        }
        apply(saved);
        if (toggle) {
            toggle.setAttribute('aria-label', 'Alternar tema claro/escuro');
            toggle.addEventListener('click', function() {
                apply(html.dataset.theme === 'dark' ? 'light' : 'dark');
            });
        }
    })();

    // ── Countdown ────────────────────────────────────────────────
    (function() {
        if (SECONDS_REMAINING <= 0) return;
        var remaining = SECONDS_REMAINING,
            elH = document.getElementById('cdHours'),
            elM = document.getElementById('cdMinutes'),
            elS = document.getElementById('cdSeconds'),
            elFill = document.getElementById('progFill'),
            elEl = document.getElementById('progElapsed'),
            elPct = document.getElementById('progPct'),
            timer;

        function pad(n) {
            return String(n).padStart(2, '0');
        }

        function formatElapsed(secs) {
            var h = Math.floor(secs / 3600),
                m = Math.floor((secs % 3600) / 60),
                s = secs % 60;
            if (h > 0) return h + 'h ' + pad(m) + 'm ' + pad(s) + 's decorridos';
            if (m > 0) return pad(m) + 'm ' + pad(s) + 's decorridos';
            return s + 's decorridos';
        }

        if (!elH || !elM || !elS || !elFill || !elEl || !elPct) return;

        function tick() {
            if (remaining <= 0) {
                clearInterval(timer);
                location.reload();
                return;
            }
            var h = Math.floor(remaining / 3600),
                m = Math.floor((remaining % 3600) / 60),
                s = remaining % 60;
            elH.textContent = pad(h);
            elM.textContent = pad(m);
            elS.textContent = pad(s);
            var pct = SECONDS_TOTAL > 0 ? Math.min(100, Math.max(0, (remaining / SECONDS_TOTAL) * 100)) : 0,
                elapsed = SECONDS_TOTAL - remaining;
            elFill.style.width = pct.toFixed(1) + '%';
            elEl.textContent = formatElapsed(Math.max(0, elapsed));
            elPct.textContent = Math.round(100 - pct) + '% concluído';
            remaining--;
        }
        tick();
        timer = setInterval(tick, 1000);
    })();

    // ── Auto-verificação periódica ───────────────────────────────
    setInterval(function() {
        fetch(window.location.href, {
                method: 'GET',
                redirect: 'follow',
                cache: 'no-store',
                headers: {
                    'Accept': 'text/html'
                }
            })
            .then(function(r) {
                if (r.redirected) window.location.assign(r.url);
            }).catch(function() {});
    }, 5 * 60 * 1000);

    // ── Formulário de notificação ────────────────────────────────
    (function() {
        var btn = document.getElementById('btnNotify'),
            input = document.getElementById('notifyEmail'),
            fb = document.getElementById('notifyFb'),
            cntEl = document.getElementById('notifyCount');
        if (!btn || !input || !fb || !cntEl) return;

        function showFb(msg, ok) {
            fb.textContent = msg;
            fb.className = 'notify-fb ' + (ok ? 'ok' : 'err');
            fb.style.display = 'block';
        }

        function isValidEmail(e) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
        }

        function loadCount() {
            var fd = new FormData();
            fd.append('action', 'notify_count');
            fetch(window.location.href, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (d.ok && d.count > 0) cntEl.textContent = d.count + ' pessoa' + (d.count !== 1 ? 's' :
                        '') + ' aguardam o regresso';
                }).catch(function() {});
        }
        btn.addEventListener('click', function() {
            var email = input.value.trim();
            if (!email || email.length > 254) {
                showFb('Introduz o teu e-mail.', false);
                return;
            }
            if (!isValidEmail(email)) {
                showFb('E-mail inválido.', false);
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A registar…';
            var fd = new FormData();
            fd.append('action', 'notify_register');
            fd.append('email', email);
            fetch(window.location.href, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (d.ok) {
                        showFb(d.message, true);
                        input.value = '';
                        btn.innerHTML = '<i class="fas fa-check"></i> Registado';
                        loadCount();
                    } else {
                        showFb(d.message, false);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-bell"></i> Avisar-me';
                    }
                }).catch(function() {
                    showFb('Erro de rede. Tenta novamente.', false);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-bell"></i> Avisar-me';
                });
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') btn.click();
        });
        loadCount();
    })();
    </script>
</body>

</html>