-- ============================================================================
-- PawAdopt — Veterinary approval + Privacy consent persistence
-- Run on the production DB (Aiven MySQL 8 / local MySQL). Idempotent:
-- each ALTER only runs when the change is missing. Safe to re-run.
-- ============================================================================

-- 0) users.role must accept VETERINARY (original enum only had
--    ADOPTER/SHELTER/ADMIN, which made veterinary signups fail silently).
SET @def := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'role');
SET @sql := IF(@def NOT LIKE '%VETERINARY%',
  'ALTER TABLE `users` MODIFY COLUMN `role` ENUM(''ADOPTER'',''SHELTER'',''VETERINARY'',''ADMIN'') NOT NULL DEFAULT ''ADOPTER''',
  'SELECT 1 AS role_enum_ok');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1) users.approval_status — veterinary accounts start PENDING and cannot log
--    in until an admin approves them. Existing rows default to APPROVED so
--    current users are unaffected.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'approval_status');
SET @sql := IF(@col = 0,
  'ALTER TABLE `users` ADD COLUMN `approval_status` ENUM(''APPROVED'',''PENDING'',''REJECTED'') NOT NULL DEFAULT ''APPROVED'' AFTER `role`',
  'SELECT 1 AS approval_status_exists');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) users.privacy_accepted_at — timestamp set once when the user accepts the
--    Privacy & Data Notice, so the notice is not shown again.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'privacy_accepted_at');
SET @sql := IF(@col = 0,
  'ALTER TABLE `users` ADD COLUMN `privacy_accepted_at` DATETIME NULL DEFAULT NULL AFTER `approval_status`',
  'SELECT 1 AS privacy_col_exists');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verify (expect 2 new rows):
-- SHOW COLUMNS FROM users LIKE 'approval%';
-- SHOW COLUMNS FROM users LIKE 'privacy%';
