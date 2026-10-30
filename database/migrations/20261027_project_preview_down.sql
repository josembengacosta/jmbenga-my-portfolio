-- Rollback for 20261027_project_preview_up.sql.
ALTER TABLE `_projects`
  DROP COLUMN `preview_enabled`,
  DROP COLUMN `preview_url`,
  DROP COLUMN `preview_mode`;
