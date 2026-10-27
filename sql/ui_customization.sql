-- ============================================================================
-- PawAdopt — UI CUSTOMIZATION MIGRATION
-- Adds the customization columns used by api/ui-settings.php.
-- Safe to re-run (MySQL 8 / MariaDB 10.5+ support ADD COLUMN IF NOT EXISTS;
-- on older versions re-running prints a harmless duplicate-column warning).
-- ============================================================================

ALTER TABLE user_preferences
  ADD COLUMN IF NOT EXISTS ui_theme          VARCHAR(32) NOT NULL DEFAULT 'cozy_warmth',
  ADD COLUMN IF NOT EXISTS accent_color_hex  VARCHAR(7)  NOT NULL DEFAULT '#E07A5F',
  ADD COLUMN IF NOT EXISTS font_family       VARCHAR(32) NOT NULL DEFAULT 'Nunito',
  ADD COLUMN IF NOT EXISTS base_font_size    VARCHAR(10) NOT NULL DEFAULT '8pt',
  ADD COLUMN IF NOT EXISTS header_scale      DECIMAL(3,2) NOT NULL DEFAULT 1.25,
  ADD COLUMN IF NOT EXISTS modal_bg_color    VARCHAR(7)  NOT NULL DEFAULT '#FFFFFF',
  ADD COLUMN IF NOT EXISTS overlay_backdrop  VARCHAR(30) NOT NULL DEFAULT 'rgba(0, 0, 0, 0.5)',
  ADD COLUMN IF NOT EXISTS border_radius_px  INT NOT NULL DEFAULT 16,
  ADD COLUMN IF NOT EXISTS shadow_elevation  VARCHAR(20) NOT NULL DEFAULT 'soft',
  ADD COLUMN IF NOT EXISTS grid_density      VARCHAR(20) NOT NULL DEFAULT 'standard',
  ADD COLUMN IF NOT EXISTS show_contact_phone  TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_contact_email  TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_contact_address TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_badges      TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS high_contrast     TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS disable_animations TINYINT(1) NOT NULL DEFAULT 0;
