-- Reversible additive migration: project demo preview configuration.
ALTER TABLE `_projects`
  ADD COLUMN `preview_mode` enum('external_embed','external_link','internal','gallery','disabled') NOT NULL DEFAULT 'disabled' AFTER `url_live`,
  ADD COLUMN `preview_url` varchar(500) DEFAULT NULL AFTER `preview_mode`,
  ADD COLUMN `preview_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `preview_url`;
