-- ============================================================================
-- PawAdopt — COMMUNITY MODULE (posts, comments, reports)
-- MySQL 8 / MariaDB 10.6+ · InnoDB · utf8mb4 · idempotent (CREATE IF NOT EXISTS)
--
-- Privacy: these tables store NO email, phone, or address. Author identity is
-- only user_id -> users.user_id, and the UI only ever shows `username` + role.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `community_posts` (
  `post_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11)      NOT NULL,
  `post_type`     ENUM('EXPERIENCE','INQUIRY','ANNOUNCEMENT','SYSTEM') NOT NULL DEFAULT 'INQUIRY',
  `title`         VARCHAR(160) NOT NULL,
  `body`          TEXT         NOT NULL,
  `mention_all`   TINYINT(1)   NOT NULL DEFAULT 0,
  `is_pinned`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_hidden`     TINYINT(1)   NOT NULL DEFAULT 0,
  `comment_count` INT(11)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `idx_cp_created` (`created_at`),
  KEY `idx_cp_type` (`post_type`),
  KEY `idx_cp_user` (`user_id`),
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `community_comments` (
  `comment_id`  INT(11)    NOT NULL AUTO_INCREMENT,
  `post_id`     INT(11)    NOT NULL,
  `user_id`     INT(11)    NOT NULL,
  `body`        TEXT       NOT NULL,
  `is_hidden`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_cc_post` (`post_id`),
  CONSTRAINT `fk_cc_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`post_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `community_reports` (
  `report_id`    INT(11)    NOT NULL AUTO_INCREMENT,
  `post_id`      INT(11)    DEFAULT NULL,
  `comment_id`   INT(11)    DEFAULT NULL,
  `reporter_id`  INT(11)    NOT NULL,
  `reason`       VARCHAR(500) NOT NULL,
  `status`       ENUM('OPEN','RESOLVED','DISMISSED') NOT NULL DEFAULT 'OPEN',
  `created_at`   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_by`  INT(11)    DEFAULT NULL,
  `resolved_at`  DATETIME   DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_cr_status` (`status`),
  CONSTRAINT `fk_cr_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
--   SHOW TABLES LIKE 'community%';
-- ============================================================================
