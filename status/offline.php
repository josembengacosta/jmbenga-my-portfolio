<?php
$site_name    = 'JMbenga';
$accent_color = '#2563eb';
try {
    require_once __DIR__ . '/../includes/config.php';
    if (defined('BASE_URL')) {
        // Tenta carregar configs
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/icons/favicon.ico">
    <title>Offline | <?php echo htmlspecialchars($site_name); ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --accent: <?php echo htmlspecialchars($accent_color);
        ?>;
        --bg: #0a0d14;
        --text: #e8edf5;
        --text-dim: #8899b4;
        --font-head: 'Syne', sans-serif;
        --font-body: 'DM Sans', sans-serif
    }

    body {
        font-family: var(--font-body);
        background: var(--bg);
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        text-align: center;
        padding: 2rem
    }

    .container {
        max-width: 500px
    }

    .icon {
        font-size: 4rem;
        color: var(--accent);
        margin-bottom: 1.5rem;
        opacity: .6
    }

    h1 {
        font-family: var(--font-head);
        font-size: 2rem;
        margin-bottom: .8rem
    }

    p {
        color: var(--text-dim);
        line-height: 1.7;
        margin-bottom: 1.5rem
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: .7rem 1.5rem;
        background: var(--accent);
        color: #fff;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 500;
        transition: all .25s
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, .3)
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon"><i class="fas fa-wifi-slash"></i></div>
        <h1>Estás Offline</h1>
        <p>Parece que perdeste a ligação à internet. Algumas funcionalidades podem não estar disponíveis até recuperares
            a conexão.</p>
        <button class="btn" onclick="location.reload()"><i class="fas fa-redo"></i> Tentar Novamente</button>
    </div>
</body>

</html>