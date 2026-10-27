

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_preferences (
    pref_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    theme          VARCHAR(20) NOT NULL DEFAULT 'light',
    accent_color   VARCHAR(20) NOT NULL DEFAULT '#0d9488',
    density        VARCHAR(20) NOT NULL DEFAULT 'comfortable',
    show_greeting  TINYINT(1) NOT NULL DEFAULT 1,
    -- dashboard widget ordering (only consumed by role=dashboard pages)
    layout_json    TEXT NULL,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_pref_user (user_id),
    CONSTRAINT fk_user_pref_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backward-compat: migrate any rows from a previous per-page customizer.
-- Only runs when the legacy dashboard_preferences table still exists
-- (fresh installs do not have it — the INSERT is skipped safely).
SET @has_legacy := (SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'dashboard_preferences');
SET @sql := IF(@has_legacy > 0,
    'INSERT INTO user_preferences (user_id, theme, accent_color, density, show_greeting, layout_json) 
     SELECT d.user_id, d.theme, d.accent_color, d.density, d.show_greeting, d.layout_json 
     FROM dashboard_preferences d 
     WHERE NOT EXISTS (SELECT 1 FROM user_preferences up WHERE up.user_id = d.user_id) 
     ON DUPLICATE KEY UPDATE 
         theme         = d.theme, 
         accent_color  = d.accent_color, 
         density       = d.density, 
         show_greeting = d.show_greeting, 
         layout_json   = d.layout_json',
    'SELECT ''legacy dashboard_preferences table not present - migration skipped'' AS info');
PREPARE pa_stmt FROM @sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

COMMIT;
