CREATE TABLE IF NOT EXISTS user_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    title VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_feedback_public (is_public, rating, created_at),
    INDEX idx_user_feedback_user (user_id, created_at)
);

CREATE TABLE IF NOT EXISTS admin_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    category VARCHAR(60) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    details TEXT NOT NULL,
    source_page VARCHAR(80) NOT NULL DEFAULT 'dashboard',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    admin_note VARCHAR(500) NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_reports_status (status, created_at),
    INDEX idx_admin_reports_role (role, created_at)
);
