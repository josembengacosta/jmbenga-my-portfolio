<?php
/**
 * ============================================================
 *  Wasom Upfy v2.0 — wu-panel / seed_admin.php
 *  Executa UMA única vez: cria as tabelas + insere o super_admin
 *  Apaga ou bloqueia este ficheiro após execução!
 * ============================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ============================================================
//  DADOS DO SUPER ADMIN — edita apenas aqui
// ============================================================
define('ADMIN_FIRST_NAME', 'José Mbenga');
define('ADMIN_EMAIL',      'josembengadacosta@gmail.com');
define('ADMIN_USERNAME',   'josembengadacosta');
define('ADMIN_PASSWORD',   'Amoreterno...1');   // ← troca pela password real
// ============================================================

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log = [];
$errors = [];

function step(string $msg, array &$log): void {
    $log[] = '✅ ' . $msg;
}

function fail(string $msg, array &$errors): void {
    $errors[] = '❌ ' . $msg;
}

try {
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET foreign_key_checks = 0");

    // ----------------------------------------------------------
    //  1. Criar tabela _employees
    // ----------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `_employees` (
          `id_employees`       int(11)       NOT NULL AUTO_INCREMENT,
          `first_name`         varchar(50)   NOT NULL,
          `second_name`        varchar(80)   DEFAULT NULL,
          `user_employees`     varchar(60)   DEFAULT NULL COMMENT 'Username de login',
          `gender`             enum('M','F') NOT NULL DEFAULT 'M',
          `tel_employees`      varchar(20)   DEFAULT NULL,
          `email_employees`    varchar(255)  NOT NULL,
          `photo_employees`    varchar(255)  DEFAULT NULL,
          `password_employees` varchar(255)  NOT NULL,
          `about_employees`    text          DEFAULT NULL,
          `country_employees`  varchar(2)    DEFAULT 'AO',
          `city_employees`     varchar(80)   DEFAULT 'Luanda',
          `role`               enum('super_admin','admin','editor') NOT NULL DEFAULT 'super_admin',
          `status_employees`   enum('active','inactive','blocked')  NOT NULL DEFAULT 'active',
          `creat_employees`    timestamp     NOT NULL DEFAULT current_timestamp(),
          `modif_employees`    timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id_employees`),
          UNIQUE KEY `uq_email_emp` (`email_employees`),
          UNIQUE KEY `uq_user_emp`  (`user_employees`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    step('Tabela _employees OK', $log);

    // ----------------------------------------------------------
    //  2. Criar tabela _employees_security
    // ----------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `_employees_security` (
          `id_sec_emp`             int(11)      NOT NULL AUTO_INCREMENT,
          `id_employees`           int(11)      NOT NULL,
          `recovery_key`           varchar(60)  NOT NULL,
          `remember_token`         varchar(255) DEFAULT NULL,
          `reset_password_token`   varchar(100) DEFAULT NULL,
          `reset_password_expires` datetime     DEFAULT NULL,
          `login_attempts`         int(11)      NOT NULL DEFAULT 0,
          `block_until`            datetime     DEFAULT NULL,
          `block_level`            tinyint(1)   NOT NULL DEFAULT 0,
          `last_login_at`          datetime     DEFAULT NULL,
          `last_login_ip`          varchar(45)  DEFAULT NULL,
          `lockscreen`             tinyint(1)   NOT NULL DEFAULT 0,
          `access_code`            varchar(6)   DEFAULT NULL,
          `invite_token`           varchar(64)  DEFAULT NULL,
          `invite_token_expires`   datetime     DEFAULT NULL,
          `invite_used`            tinyint(1)   NOT NULL DEFAULT 0,
          `invited_by`             int(11)      DEFAULT NULL,
          `creat_sec_emp`          timestamp    NOT NULL DEFAULT current_timestamp(),
          `modif_sec_emp`          timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id_sec_emp`),
          UNIQUE KEY `uq_recovery_key` (`recovery_key`),
          UNIQUE KEY `uq_invite_token` (`invite_token`),
          KEY `fk_esec_emp` (`id_employees`),
          CONSTRAINT `fk_esec_emp` FOREIGN KEY (`id_employees`)
            REFERENCES `_employees` (`id_employees`)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    step('Tabela _employees_security OK', $log);

    // ----------------------------------------------------------
    //  3. Verificar se o admin já existe
    // ----------------------------------------------------------
    $check = $pdo->prepare("SELECT id_employees FROM _employees WHERE email_employees = ? OR user_employees = ? LIMIT 1");
    $check->execute([ADMIN_EMAIL, ADMIN_USERNAME]);

    if ($check->fetchColumn()) {
        fail('Super admin já existe na base de dados. Seed ignorado.', $errors);
    } else {

        // ----------------------------------------------------------
        //  4. Inserir super_admin
        // ----------------------------------------------------------
        $hash = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("
            INSERT INTO _employees
                (first_name, email_employees, user_employees, password_employees, role, status_employees)
            VALUES
                (?, ?, ?, ?, 'super_admin', 'active')
        ");
        $stmt->execute([ADMIN_FIRST_NAME, ADMIN_EMAIL, ADMIN_USERNAME, $hash]);

        $adminId = (int) $pdo->lastInsertId();
        step("Super admin inserido — ID: $adminId", $log);

        // ----------------------------------------------------------
        //  5. Inserir registo de segurança
        // ----------------------------------------------------------
        $recoveryKey = bin2hex(random_bytes(16)); // 32 hex chars

        $stmt2 = $pdo->prepare("
            INSERT INTO _employees_security
                (id_employees, recovery_key)
            VALUES (?, ?)
        ");
        $stmt2->execute([$adminId, $recoveryKey]);
        step("Segurança criada — recovery_key: <strong>$recoveryKey</strong> (guarda isto!)", $log);
    }

    $pdo->exec("SET foreign_key_checks = 1");

} catch (PDOException $e) {
    fail('Erro PDO: ' . $e->getMessage(), $errors);
}

// ============================================================
//  OUTPUT
// ============================================================
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Seed Admin — Wasom Upfy</title>
    <style>
    body {
        font-family: monospace;
        background: #0f0f0f;
        color: #e0e0e0;
        padding: 2rem;
    }

    h2 {
        color: #7c3aed;
    }

    .ok {
        color: #22c55e;
        margin: .4rem 0;
    }

    .err {
        color: #ef4444;
        margin: .4rem 0;
    }

    .warn {
        margin-top: 2rem;
        padding: 1rem;
        border: 1px solid #f59e0b;
        color: #f59e0b;
        border-radius: 6px;
    }
    </style>
</head>

<body>
    <h2>🔧 Wasom Upfy — Seed Admin</h2>

    <?php foreach ($log    as $l): ?><p class="ok"><?= $l ?></p><?php endforeach; ?>
    <?php foreach ($errors as $e): ?><p class="err"><?= $e ?></p><?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="warn">
        ⚠️ <strong>Segurança:</strong> apaga ou renomeia este ficheiro agora.<br>
        Nunca o deixes acessível publicamente no servidor.
    </div>
    <?php endif; ?>

</body>

</html>