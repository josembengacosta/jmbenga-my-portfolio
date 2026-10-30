-- Rollback for 20261027_project_case_study_up.sql.
ALTER TABLE `_projects`
  DROP COLUMN `contribution_project`,
  DROP COLUMN `features_project`,
  DROP COLUMN `objectives_project`;
