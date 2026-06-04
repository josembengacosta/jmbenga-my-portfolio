-- ══════════════════════════════════════════════════════════════════════
-- JMbenga Portfolio — schema.sql
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- Convenção: prefixo _, ids: id_tabela, datas: creat_/modif_
-- Sem tabela de utilizadores — portfólio pessoal com admin único
-- ══════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = 'Africa/Luanda';

-- ──────────────────────────────────────────────────────────────────────
-- 1. PLATAFORMA & CONFIGURAÇÃO
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_platform` (
  `id_platform`           int(11)       NOT NULL AUTO_INCREMENT,
  `status`                enum('active','maintenance','blocked') NOT NULL DEFAULT 'active',
  `maintenance_msg`       text          DEFAULT NULL  COMMENT 'Mensagem exibida em manutenção',
  `maintenance_start`     datetime      DEFAULT NULL,
  `maintenance_end`       datetime      DEFAULT NULL,
  `allow_contact`         tinyint(1)    NOT NULL DEFAULT 1  COMMENT 'Abrir/fechar formulário de contacto',
  `allow_feedback`        tinyint(1)    NOT NULL DEFAULT 1,
  `version`               varchar(10)   NOT NULL DEFAULT '1.0',
  `creat_platform`        timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_platform`        timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `_platform` (`status`, `version`) VALUES ('active', '1.0');


-- ── Configurações chave-valor do site (bio, redes sociais, SEO…) ──────

CREATE TABLE `_site_config` (
  `id_config`     int(11)       NOT NULL AUTO_INCREMENT,
  `config_key`    varchar(100)  NOT NULL  COMMENT 'Ex: hero_title, github_url, meta_description',
  `config_value`  text          DEFAULT NULL,
  `config_group`  varchar(50)   NOT NULL DEFAULT 'general'  COMMENT 'general|social|seo|contact|appearance',
  `is_public`     tinyint(1)    NOT NULL DEFAULT 1  COMMENT '1 = visível no frontend via PHP',
  `modif_config`  timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `uq_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `_site_config` (`config_key`, `config_value`, `config_group`) VALUES
  ('site_name',           'JMbenga Portfolio',                         'general'),
  ('hero_title',          'Desenvolvedor Full Stack',                  'general'),
  ('hero_subtitle',       'Transformando ideias em experiências digitais', 'general'),
  ('about_text',          NULL,                                        'general'),
  ('cv_file',             NULL,                                        'general'),
  ('years_experience',    '3',                                         'general'),
  ('projects_count',      '50',                                        'general'),
  ('clients_count',       '100',                                       'general'),
  ('meta_description',    NULL,                                        'seo'),
  ('meta_keywords',       NULL,                                        'seo'),
  ('og_image',            NULL,                                        'seo'),
  ('email_contact',       'josembengadacosta@gmail.com',               'contact'),
  ('phone_contact',       '+244 922 030 116',                          'contact'),
  ('location',            'Luanda, Angola',                            'contact'),
  ('github_url',          'https://github.com/josembengacosta',        'social'),
  ('linkedin_url',        'https://linkedin.com/in/josembengadacosta', 'social'),
  ('whatsapp_url',        'https://wa.me/244922030116',                'social'),
  ('twitter_url',         NULL,                                        'social'),
  ('instagram_url',       NULL,                                        'social'),
  ('accent_color',        '#2563eb',                                   'appearance'),
  ('dark_mode_default',   '1',                                         'appearance');


-- ──────────────────────────────────────────────────────────────────────
-- 2. ADMIN (EMPLOYEES)
-- Sem tabela de utilizadores — apenas o próprio José (e possíveis colaboradores futuros)
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_employees` (
  `id_employees`           int(11)       NOT NULL AUTO_INCREMENT,
  `first_name`             varchar(50)   NOT NULL,
  `second_name`            varchar(80)   DEFAULT NULL,
  `user_employees`         varchar(60)   DEFAULT NULL  COMMENT 'Username de login',
  `gender`                 enum('M','F') NOT NULL DEFAULT 'M',
  `tel_employees`          varchar(20)   DEFAULT NULL,
  `email_employees`        varchar(255)  NOT NULL,
  `photo_employees`        varchar(255)  DEFAULT NULL,
  `password_employees`     varchar(255)  NOT NULL,
  `about_employees`        text          DEFAULT NULL,
  `country_employees`      varchar(2)    DEFAULT 'AO'  COMMENT 'ISO 3166-1 alpha-2',
  `city_employees`         varchar(80)   DEFAULT 'Luanda',
  `role`                   enum('super_admin','admin','editor') NOT NULL DEFAULT 'super_admin',
  `status_employees`       enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `creat_employees`        timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_employees`        timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_employees`),
  UNIQUE KEY `uq_email_emp` (`email_employees`),
  UNIQUE KEY `uq_user_emp`  (`user_employees`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `_employees_security` (
  `id_sec_emp`              int(11)       NOT NULL AUTO_INCREMENT,
  `id_employees`            int(11)       NOT NULL,
  `recovery_key`            varchar(60)   NOT NULL,
  `remember_token`          varchar(255)  DEFAULT NULL,
  `reset_password_token`    varchar(100)  DEFAULT NULL,
  `reset_password_expires`  datetime      DEFAULT NULL,
  `login_attempts`          int(11)       NOT NULL DEFAULT 0,
  `block_until`             datetime      DEFAULT NULL,
  `block_level`             tinyint(1)    NOT NULL DEFAULT 0  COMMENT '0=livre, 1=5min, 2=15min, 3=30min',
  `last_login_at`           datetime      DEFAULT NULL,
  `last_login_ip`           varchar(45)   DEFAULT NULL,
  `lockscreen`              tinyint(1)    NOT NULL DEFAULT 0,
  `access_code`             varchar(6)    DEFAULT NULL  COMMENT 'PIN de lockscreen',
  `invite_token`            varchar(64)   DEFAULT NULL,
  `invite_token_expires`    datetime      DEFAULT NULL,
  `invite_used`             tinyint(1)    NOT NULL DEFAULT 0,
  `invited_by`              int(11)       DEFAULT NULL  COMMENT 'id_employees',
  `creat_sec_emp`           timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_sec_emp`           timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_sec_emp`),
  UNIQUE KEY `uq_recovery_key` (`recovery_key`),
  UNIQUE KEY `uq_invite_token` (`invite_token`),
  KEY `fk_esec_emp` (`id_employees`),
  CONSTRAINT `fk_esec_emp` FOREIGN KEY (`id_employees`)
    REFERENCES `_employees` (`id_employees`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────
-- 3. SEGURANÇA DO PAINEL
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_admin_ip_whitelist` (
  `id_ip`         int(11)       NOT NULL AUTO_INCREMENT,
  `ip_address`    varchar(45)   NOT NULL  COMMENT 'IPv4 ou IPv6',
  `label`         varchar(80)   DEFAULT NULL  COMMENT 'Ex: Casa, Escritório, VPN',
  `added_by`      int(11)       DEFAULT NULL  COMMENT 'FK _employees',
  `active`        tinyint(1)    NOT NULL DEFAULT 1,
  `creat_ip`      timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_ip`      timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ip`),
  UNIQUE KEY `uq_ip_address` (`ip_address`),
  KEY `fk_ip_emp` (`added_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `_admin_access_log` (
  `id_log`        int(11)       NOT NULL AUTO_INCREMENT,
  `ip_address`    varchar(45)   NOT NULL,
  `path_tried`    varchar(255)  DEFAULT NULL,
  `reason`        varchar(60)   DEFAULT NULL  COMMENT 'ip_blocked | login_fail | brute_force',
  `user_agent`    varchar(255)  DEFAULT NULL,
  `creat_log`     timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_ip`    (`ip_address`),
  KEY `idx_creat` (`creat_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `_audit_log` (
  `id_log`        bigint(20)    NOT NULL AUTO_INCREMENT,
  `id_employees`  int(11)       DEFAULT NULL,
  `action`        varchar(100)  NOT NULL  COMMENT 'Ex: project.create, skill.delete, config.update',
  `entity`        varchar(50)   DEFAULT NULL  COMMENT 'Tabela/entidade afectada',
  `entity_id`     int(11)       DEFAULT NULL,
  `old_value`     longtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value`     longtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address`    varchar(45)   DEFAULT NULL,
  `user_agent`    text          DEFAULT NULL,
  `creat_log`     timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_employees`   (`id_employees`),
  KEY `idx_action`     (`action`),
  KEY `idx_creat_log`  (`creat_log`),
  CONSTRAINT `fk_al_emp` FOREIGN KEY (`id_employees`)
    REFERENCES `_employees` (`id_employees`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────
-- 4. CONTEÚDO DO PORTFÓLIO
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_projects` (
  `id_project`      int(11)       NOT NULL AUTO_INCREMENT,
  `title_project`   varchar(200)  NOT NULL,
  `slug_project`    varchar(200)  NOT NULL  COMMENT 'URL amigável: meu-projecto',
  `summary_project` varchar(300)  DEFAULT NULL  COMMENT 'Resumo curto (cards)',
  `body_project`    longtext      DEFAULT NULL  COMMENT 'Descrição completa (modal/página detalhe)',
  `category_project` enum('web','mobile','api','desktop','design','other') NOT NULL DEFAULT 'web',
  `tech_stack`      longtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                    COMMENT 'JSON array: ["PHP","MySQL","JS"]' CHECK (json_valid(`tech_stack`)),
  `cover_project`   varchar(255)  DEFAULT NULL  COMMENT 'Imagem principal',
  `url_demo`        varchar(255)  DEFAULT NULL,
  `url_github`      varchar(255)  DEFAULT NULL,
  `url_live`        varchar(255)  DEFAULT NULL,
  `is_featured`     tinyint(1)    NOT NULL DEFAULT 0  COMMENT '1 = aparece no hero/destaque',
  `display_order`   int(11)       NOT NULL DEFAULT 0,
  `status_project`  enum('published','draft','archived') NOT NULL DEFAULT 'draft',
  `creat_project`   timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_project`   timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_project`),
  UNIQUE KEY `uq_slug_project` (`slug_project`),
  KEY `idx_category`   (`category_project`),
  KEY `idx_status`     (`status_project`),
  KEY `idx_featured`   (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Projectos do portfólio';


CREATE TABLE `_projects_media` (
  `id_media`      int(11)       NOT NULL AUTO_INCREMENT,
  `id_project`    int(11)       NOT NULL,
  `url_media`     varchar(255)  NOT NULL,
  `caption_media` varchar(255)  DEFAULT NULL,
  `is_cover`      tinyint(1)    NOT NULL DEFAULT 0,
  `display_order` int(11)       NOT NULL DEFAULT 0,
  `creat_media`   timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_media`),
  KEY `fk_pm_project` (`id_project`),
  CONSTRAINT `fk_pm_project` FOREIGN KEY (`id_project`)
    REFERENCES `_projects` (`id_project`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Screenshots e media de cada projecto';


CREATE TABLE `_skills` (
  `id_skill`        int(11)           NOT NULL AUTO_INCREMENT,
  `name_skill`      varchar(100)      NOT NULL,
  `percentage_skill` tinyint(3) unsigned NOT NULL DEFAULT 80  COMMENT '0 a 100',
  `category_skill`  enum('frontend','backend','devops','design','other') NOT NULL DEFAULT 'frontend',
  `icon_skill`      varchar(100)      DEFAULT NULL  COMMENT 'Classe Font Awesome ou URL do SVG',
  `color_skill`     varchar(7)        DEFAULT NULL  COMMENT 'Hex: #2563eb',
  `display_order`   int(11)           NOT NULL DEFAULT 0,
  `is_visible`      tinyint(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_skill`),
  KEY `idx_category_skill` (`category_skill`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Habilidades técnicas exibidas no portfólio';

INSERT INTO `_skills` (`name_skill`, `percentage_skill`, `category_skill`, `icon_skill`, `display_order`) VALUES
  ('HTML5',      95, 'frontend', 'fab fa-html5',       1),
  ('CSS3',       90, 'frontend', 'fab fa-css3-alt',    2),
  ('JavaScript', 85, 'frontend', 'fab fa-js',          3),
  ('PHP',        88, 'backend',  'fab fa-php',         4),
  ('MySQL',      82, 'backend',  'fas fa-database',    5),
  ('Git',        80, 'devops',   'fab fa-git-alt',     6);


CREATE TABLE `_testimonials` (
  `id_testimonial`    int(11)       NOT NULL AUTO_INCREMENT,
  `name_testimonial`  varchar(100)  NOT NULL,
  `role_testimonial`  varchar(100)  DEFAULT NULL  COMMENT 'Cargo / Função',
  `company_testimonial` varchar(100) DEFAULT NULL,
  `body_testimonial`  text          NOT NULL,
  `photo_testimonial` varchar(255)  DEFAULT NULL,
  `rating_testimonial` tinyint(1)  NOT NULL DEFAULT 5  COMMENT '1 a 5 estrelas',
  `status_testimonial` enum('visible','hidden') NOT NULL DEFAULT 'visible',
  `display_order`     int(11)       NOT NULL DEFAULT 0,
  `creat_testimonial` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_testimonial`),
  KEY `idx_status_test` (`status_testimonial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Depoimentos de clientes';


CREATE TABLE `_faq` (
  `id_faq`        int(11)       NOT NULL AUTO_INCREMENT,
  `category_faq`  varchar(100)  DEFAULT 'Geral',
  `question`      varchar(500)  NOT NULL,
  `answer`        longtext      NOT NULL,
  `status_faq`    enum('visible','hidden') NOT NULL DEFAULT 'visible',
  `display_order` int(11)       NOT NULL DEFAULT 0,
  `creat_faq`     timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_faq`     timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_faq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'FAQs exibidas no portfólio';


-- ──────────────────────────────────────────────────────────────────────
-- 5. COMUNICAÇÃO
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_contact_message` (
  `id`            int(11)       NOT NULL AUTO_INCREMENT,
  `name_msg`      varchar(120)  NOT NULL,
  `email_msg`     varchar(120)  NOT NULL,
  `phone_msg`     varchar(30)   DEFAULT NULL,
  `subject_msg`   varchar(120)  NOT NULL,
  `message_msg`   text          NOT NULL,
  `ip_address`    varchar(45)   DEFAULT NULL,
  `user_agent`    varchar(512)  DEFAULT NULL,
  `status_msg`    enum('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `replied_at`    datetime      DEFAULT NULL,
  `is_starred`    tinyint(1)    NOT NULL DEFAULT 0,
  `created_at`    datetime      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_msg`  (`status_msg`),
  KEY `idx_email_msg`   (`email_msg`),
  KEY `idx_created_msg` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Mensagens recebidas via formulário de contacto';


CREATE TABLE `_feedback` (
  `id`            int(11)       NOT NULL AUTO_INCREMENT,
  `name_fb`       varchar(120)  NOT NULL,
  `subject_fb`    varchar(80)   NOT NULL DEFAULT 'Sugestão',
  `message_fb`    text          NOT NULL,
  `page_origin`   varchar(255)  DEFAULT NULL  COMMENT 'Página de onde veio o feedback',
  `ip_address`    varchar(45)   DEFAULT NULL,
  `user_agent`    varchar(512)  DEFAULT NULL,
  `status_fb`     enum('new','read','archived') NOT NULL DEFAULT 'new',
  `is_starred`    tinyint(1)    NOT NULL DEFAULT 0,
  `created_at`    datetime      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_fb`  (`status_fb`),
  KEY `idx_created_fb` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Feedbacks recebidos via modal do site';


-- ──────────────────────────────────────────────────────────────────────
-- 6. TRACKING DE VISITANTES
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_visitor` (
  `id_visitor`        bigint(20)    NOT NULL AUTO_INCREMENT,
  `ip_address`        varchar(45)   NOT NULL  COMMENT 'IPv4 ou IPv6',
  `ip_version`        enum('v4','v6') NOT NULL DEFAULT 'v4',
  `country_code`      char(2)       DEFAULT NULL  COMMENT 'ISO 3166-1 alpha-2',
  `country_name`      varchar(100)  DEFAULT NULL,
  `city`              varchar(100)  DEFAULT NULL,
  `region`            varchar(100)  DEFAULT NULL,
  `latitude`          decimal(10,7) DEFAULT NULL,
  `longitude`         decimal(10,7) DEFAULT NULL,
  `timezone`          varchar(50)   DEFAULT NULL,
  `isp`               varchar(255)  DEFAULT NULL,
  `user_agent`        text          DEFAULT NULL,
  `browser`           varchar(50)   DEFAULT NULL  COMMENT 'chrome, firefox, safari, edge',
  `browser_version`   varchar(20)   DEFAULT NULL,
  `os`                varchar(50)   DEFAULT NULL  COMMENT 'Windows, macOS, Android, iOS',
  `os_version`        varchar(20)   DEFAULT NULL,
  `device_type`       enum('desktop','mobile','tablet','bot','unknown') NOT NULL DEFAULT 'unknown',
  `device_brand`      varchar(50)   DEFAULT NULL,
  `screen_resolution` varchar(20)   DEFAULT NULL,
  `is_bot`            tinyint(1)    NOT NULL DEFAULT 0,
  `bot_name`          varchar(100)  DEFAULT NULL,
  `page_entry`        varchar(500)  DEFAULT NULL  COMMENT 'Primeira página visitada',
  `page_exit`         varchar(500)  DEFAULT NULL  COMMENT 'Última página antes de sair',
  `pages_viewed`      int(11)       NOT NULL DEFAULT 1,
  `session_duration`  int(11)       DEFAULT NULL  COMMENT 'Duração da sessão em segundos',
  `referrer`          varchar(500)  DEFAULT NULL,
  `utm_source`        varchar(100)  DEFAULT NULL,
  `utm_medium`        varchar(100)  DEFAULT NULL,
  `utm_campaign`      varchar(100)  DEFAULT NULL,
  `session_id`        varchar(128)  DEFAULT NULL,
  `is_online`         tinyint(1)    NOT NULL DEFAULT 0,
  `last_seen`         datetime      DEFAULT current_timestamp(),
  `visit_count`       int(11)       NOT NULL DEFAULT 1  COMMENT 'Total de visitas deste IP',
  `status_visitor`    enum('active','blocked','suspicious') NOT NULL DEFAULT 'active',
  `block_type`        enum('temporary','permanent') DEFAULT NULL,
  `block_reason`      enum('spam','bot','suspicious','other') DEFAULT NULL,
  `block_notes`       text          DEFAULT NULL,
  `block_until`       datetime      DEFAULT NULL  COMMENT 'NULL = permanente',
  `blocked_by`        int(11)       DEFAULT NULL  COMMENT 'id_employees',
  `blocked_at`        datetime      DEFAULT NULL,
  `creat_visitor`     timestamp     NOT NULL DEFAULT current_timestamp(),
  `modif_visitor`     timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_visitor`),
  UNIQUE KEY `uq_session_id`       (`session_id`),
  KEY `idx_ip_address`             (`ip_address`),
  KEY `idx_status_visitor`         (`status_visitor`),
  KEY `idx_country_code`           (`country_code`),
  KEY `idx_device_type`            (`device_type`),
  KEY `idx_is_online`              (`is_online`),
  KEY `idx_last_seen`              (`last_seen`),
  KEY `fk_vis_emp`                 (`blocked_by`),
  CONSTRAINT `fk_vis_emp` FOREIGN KEY (`blocked_by`)
    REFERENCES `_employees` (`id_employees`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Registo de visitantes únicos (por sessão/IP)';


CREATE TABLE `_visitor_pageview` (
  `id_pageview`   bigint(20)    NOT NULL AUTO_INCREMENT,
  `id_visitor`    bigint(20)    NOT NULL,
  `page_url`      varchar(500)  NOT NULL,
  `page_title`    varchar(255)  DEFAULT NULL,
  `time_on_page`  int(11)       DEFAULT NULL  COMMENT 'Segundos na página',
  `creat_pageview` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pageview`),
  KEY `fk_vp_visitor`      (`id_visitor`),
  KEY `idx_creat_pageview`  (`creat_pageview`),
  CONSTRAINT `fk_vp_visitor` FOREIGN KEY (`id_visitor`)
    REFERENCES `_visitor` (`id_visitor`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Páginas visitadas por cada visitante';


-- ──────────────────────────────────────────────────────────────────────
-- 7. MANUTENÇÃO / NOTIFICAÇÕES
-- ──────────────────────────────────────────────────────────────────────

CREATE TABLE `_maintenance_notify` (
  `id_notify`     int(11)       NOT NULL AUTO_INCREMENT,
  `email_notify`  varchar(255)  NOT NULL,
  `ip_notify`     varchar(45)   DEFAULT NULL,
  `sent`          tinyint(1)    NOT NULL DEFAULT 0  COMMENT '1 = já notificado quando voltou',
  `creat_notify`  timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notify`),
  UNIQUE KEY `uq_email_notify` (`email_notify`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;

-- ══════════════════════════════════════════════════════════════════════
-- FIM — JMbenga Portfolio schema.sql
-- ══════════════════════════════════════════════════════════════════════
