<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Página de Manutenção
// Ficheiro: status/maintenance.php
// ══════════════════════════════════════════════════════════════
http_response_code(503);
header('Retry-After: 3600');

$site_name     = 'JMbenga';
$accent_color  = '#2563eb';
$contact_email = 'josembengadacosta@gmail.com';
$platform_ver  = '1.0';
$maint_msg     = 'Estamos a realizar melhorias técnicas para lhe oferecer a melhor experiência.';
$maint_end_ts  = null;
$maint_start_ts = null;
$services       = [];
$cfg            = [];

// ── Tentar carregar configurações da BD ──────────────────────
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';

    if (isset($pdo)) {
        // Ler _platform
        $pq = $pdo->query("SELECT * FROM _platform WHERE id_platform = 1 LIMIT 1");
        $platform = $pq->fetch(PDO::FETCH_ASSOC);
        if ($platform) {
            // Se não estiver em manutenção, redirecionar para home
            if ($platform['status'] !== 'maintenance') {
                header('Location: ' . (BASE_URL ?? '/'));
                exit;
            }
            $platform_ver  = htmlspecialchars($platform['version'] ?? '1.0');
            if (!empty($platform['maintenance_msg'])) {
                $maint_msg = htmlspecialchars($platform['maintenance_msg']);
            }
            if (!empty($platform['maintenance_end'])) {
                $maint_end_ts = strtotime($platform['maintenance_end']);
            }
            if (!empty($platform['maintenance_start'])) {
                $maint_start_ts = strtotime($platform['maintenance_start']);
            }
            if (!empty($platform['maintenance_services'])) {
                $decoded = json_decode($platform['maintenance_services'], true);
                if (is_array($decoded)) $services = $decoded;
            }
        }

        // Ler _site_config
        $cq = $pdo->query("SELECT config_key, config_value FROM _site_config WHERE config_key IN ('site_name','accent_color','email_contact')");
        $configs = $cq->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name     = htmlspecialchars($configs['site_name']     ?? 'JMbenga');
        $accent_color  = $configs['accent_color']  ?? '#2563eb';
        $contact_email = htmlspecialchars($configs['email_contact'] ?? 'josembengadacosta@gmail.com');
        $cfg = $configs;
    }
} catch (Throwable $e) {
    // BD indisponível — manter defaults
    error_log('[maintenance] ' . $e->getMessage());
}

// ── Calcular countdown em segundos ───────────────────────────
$now = time();
$seconds_remaining = 0;
$seconds_total     = 0;
if ($maint_end_ts && $maint_end_ts > $now) {
    $seconds_remaining = $maint_end_ts - $now;
}
if ($maint_start_ts && $maint_end_ts) {
    $seconds_total = max(1, $maint_end_ts - $maint_start_ts);
} elseif ($seconds_remaining > 0) {
    $seconds_total = $seconds_remaining;
}

$maint_end_fmt = ($maint_end_ts && $maint_end_ts > $now)
    ? date('d/m/Y \à\s H:i', $maint_end_ts) . ' (WAT)'
    : 'Em breve';

// ── Serviços padrão ──────────────────────────────────────────
if (empty($services)) {
    $services = [
        'auth'          => 'ok',
        'projects'      => 'working',
        'contact'       => 'ok',
        'database'      => 'working',
        'api'           => 'ok',
        'notifications' => 'pending',
    ];
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
    'ok'      => ['dot' => 's-ok',      'badge' => 'badge-ok',      'icon' => 'fa-check',        'text' => 'Online'],
    'working' => ['dot' => 's-working', 'badge' => 'badge-working', 'icon' => 'fa-sync-alt',     'text' => 'A actualizar'],
    'pending' => ['dot' => 's-pending', 'badge' => 'badge-pending', 'icon' => 'fa-hourglass-half','text' => 'Pendente'],
    'down'    => ['dot' => 's-down',    'badge' => 'badge-down',    'icon' => 'fa-times',        'text' => 'Indisponível'],
];

// ── Processar AJAX para registo de e-mail ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'notify_register') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            echo json_encode(['ok' => false, 'message' => 'E-mail inválido.']);
            exit;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            // Verificar se já existe
            $check = $pdo->prepare("SELECT id_notify FROM _maintenance_notify WHERE email_notify = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                echo json_encode(['ok' => true, 'message' => 'Este e-mail já está na lista de avisos.']);
                exit;
            }
            // Inserir
            $ins = $pdo->prepare("INSERT INTO _maintenance_notify (email_notify, ip_notify) VALUES (?, ?)");
            $ins->execute([$email, $ip]);
            echo json_encode(['ok' => true, 'message' => 'Registado! Serás avisado quando o site regressar.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Erro ao registar. Tenta novamente.']);
        }
        exit;
    }

    if ($action === 'notify_count') {
        try {
            $cnt = (int) $pdo->query("SELECT COUNT(*) FROM _maintenance_notify WHERE sent = 0")->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $cnt]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Acção inválida.']);
    exit;
}

$back_url = BASE_URL ?? '/';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0d14">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <title>Em Manutenção — <?= $site_name ?></title>
    <link rel="icon" type="image/x-icon" href="<?= $back_url ?>assets/img/favicon.ico">
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
        <a class="brand" href="<?= $back_url ?>">
            <span class="brand-dot"></span>
            <?= $site_name ?>
        </a>

        <div class="card">
            <div class="icon-wrap"><i class="fas fa-tools"></i></div>
            <div class="badge"><span class="live-dot"></span> Manutenção em curso</div>
            <h1 class="title">Melhorias em <span>Progresso</span></h1>
            <p class="desc"><?= $maint_msg ?></p>

            <div class="endtime">
                <i class="far fa-clock"></i>
                Regresso previsto: <strong style="color:var(--text-dim)"><?= $maint_end_fmt ?></strong>
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
                $state = array_key_exists($state, $state_map) ? $state : 'ok';
                $s = $state_map[$state];
            ?>
                <li>
                    <span class="s-dot <?= $s['dot'] ?>"></span>
                    <?= $label ?>
                    <span class="s-badge <?= $s['badge'] ?>"><i
                            class="fas <?= $s['icon'] ?> me-1"></i><?= $s['text'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <!-- Formulário de notificação -->
            <label class="notify-lbl" for="notifyEmail"><i class="far fa-envelope"></i> Queres ser avisado quando
                regressarmos?</label>
            <div class="notify-group">
                <input type="email" id="notifyEmail" class="notify-input" placeholder="O teu e-mail"
                    autocomplete="email">
                <button class="btn-notify" id="btnNotify" type="button"><i class="fas fa-bell"></i> Avisar-me</button>
            </div>
            <div class="notify-fb" id="notifyFb"></div>
            <div class="notify-count" id="notifyCount"></div>

            <!-- Botões -->
            <div class="action-row">
                <a href="<?= $back_url ?>" class="btn btn-ghost"><i class="fas fa-home"></i> Tentar Aceder</a>
            </div>
        </div>

        <div class="footer">
            &copy; <?= date('Y') ?> <?= $site_name ?> · Luanda, Angola · v<?= $platform_ver ?><br>
            <a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a>
        </div>
    </div>

    <script>
    // ── Dados vindos do PHP ──────────────────────────────────────
    var SECONDS_REMAINING = <?= (int)$seconds_remaining ?>;
    var SECONDS_TOTAL = <?= (int)max(1, $seconds_total ?: $seconds_remaining ?: 1) ?>;
    var BASE_URL = '<?= $back_url ?>';

    // ── Tema ─────────────────────────────────────────────────────
    (function() {
        var html = document.documentElement,
            toggle = document.getElementById('themeToggle'),
            icon = document.getElementById('themeIcon'),
            saved = localStorage.getItem('jm_theme') || 'dark';

        function apply(t) {
            html.dataset.theme = t;
            if (icon) icon.className = t === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            localStorage.setItem('jm_theme', t);
        }
        apply(saved);
        toggle.addEventListener('click', function() {
            apply(html.dataset.theme === 'dark' ? 'light' : 'dark');
        });
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

        function tick() {
            if (remaining < 0) return;
            var h = Math.floor(remaining / 3600),
                m = Math.floor((remaining % 3600) / 60),
                s = remaining % 60;
            elH.textContent = pad(h);
            elM.textContent = pad(m);
            elS.textContent = pad(s);
            var pct = SECONDS_TOTAL > 0 ? (remaining / SECONDS_TOTAL) * 100 : 0,
                elapsed = SECONDS_TOTAL - remaining;
            elFill.style.width = Math.max(0, pct).toFixed(1) + '%';
            elEl.textContent = formatElapsed(Math.max(0, elapsed));
            elPct.textContent = Math.round(100 - pct) + '% concluído';
            if (remaining === 0) {
                clearInterval(timer);
                location.reload();
            }
            remaining--;
        }
        tick();
        timer = setInterval(tick, 1000);
    })();

    // ── Auto-verificação periódica ───────────────────────────────
    setInterval(function() {
        fetch(window.location.pathname, {
                method: 'GET',
                redirect: 'follow'
            })
            .then(function(r) {
                if (r.redirected && r.url.indexOf('maintenance') === -1) location.href = r.url;
            }).catch(function() {});
    }, 5 * 60 * 1000);

    // ── Formulário de notificação ────────────────────────────────
    (function() {
        var btn = document.getElementById('btnNotify'),
            input = document.getElementById('notifyEmail'),
            fb = document.getElementById('notifyFb'),
            cntEl = document.getElementById('notifyCount');

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
            fetch(window.location.pathname, {
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
            if (!email) {
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
            fetch(window.location.pathname, {
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