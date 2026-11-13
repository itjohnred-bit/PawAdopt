-- ============================================================================
-- PawAdopt — SIMPLIFIED UI CUSTOMIZATION (MySQL 8 AND MariaDB safe)
-- Adds only the columns the simplified Customize UI needs.
-- Uses a stored procedure so it is idempotent on BOTH MySQL 8 and MariaDB.
-- (MySQL 8 does not support "ADD COLUMN IF NOT EXISTS".)
-- ============================================================================

DROP PROCEDURE IF EXISTS pa_add_column;

DELIMITER $$
CREATE PROCEDURE pa_add_column(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Background -------------------------------------------------------------
CALL pa_add_column('user_preferences', 'bg_color',        "VARCHAR(7)  NOT NULL DEFAULT '#134E43'");
CALL pa_add_column('user_preferences', 'bg_image_url',    "VARCHAR(255) DEFAULT NULL");
CALL pa_add_column('user_preferences', 'bg_blur',         "TINYINT(1)  NOT NULL DEFAULT 0");
CALL pa_add_column('user_preferences', 'bg_blur_strength',"INT         NOT NULL DEFAULT 14");
CALL pa_add_column('user_preferences', 'bg_image_fit',    "VARCHAR(16) NOT NULL DEFAULT 'cover'");

-- Typography -------------------------------------------------------------
CALL pa_add_column('user_preferences', 'font_family',     "VARCHAR(32) NOT NULL DEFAULT 'Nunito'");
CALL pa_add_column('user_preferences', 'large_text',      "TINYINT(1)  NOT NULL DEFAULT 0");

-- Header / sidebar -------------------------------------------------------
CALL pa_add_column('user_preferences', 'header_color',    "VARCHAR(7)  NOT NULL DEFAULT '#FFFFFF'");
CALL pa_add_column('user_preferences', 'header_texture',  "VARCHAR(16) NOT NULL DEFAULT 'none'");
CALL pa_add_column('user_preferences', 'sidebar_color',   "VARCHAR(7)  NOT NULL DEFAULT '#FFFFFF'");
CALL pa_add_column('user_preferences', 'sidebar_texture', "VARCHAR(16) NOT NULL DEFAULT 'none'");

DROP PROCEDURE IF EXISTS pa_add_column;

-- Make sure every existing user still has a preferences row.
INSERT INTO user_preferences (user_id, theme, accent_color)
SELECT u.user_id, 'cozy_warmth', '#0d9488'
FROM users u
LEFT JOIN user_preferences up ON up.user_id = u.user_id
WHERE up.user_id IS NULL;

-- Verify:
--   SHOW COLUMNS FROM user_preferences;
-- ============================================================================
