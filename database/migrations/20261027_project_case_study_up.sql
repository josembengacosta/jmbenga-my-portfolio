-- Reversible additive migration: project case study sections.
ALTER TABLE `_projects`
  ADD COLUMN `objectives_project` longtext DEFAULT NULL AFTER `body_project`,
  ADD COLUMN `features_project` longtext DEFAULT NULL AFTER `objectives_project`,
  ADD COLUMN `contribution_project` longtext DEFAULT NULL AFTER `features_project`;
