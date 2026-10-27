CREATE TABLE IF NOT EXISTS account_deletion_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    requested_by_role VARCHAR(20) NOT NULL DEFAULT 'ADOPTER',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_for DATETIME NOT NULL,
    cancelled_at DATETIME NULL,
    processed_at DATETIME NULL,
    note VARCHAR(255) NULL,
    INDEX idx_account_deletion_user (user_id, status),
    INDEX idx_account_deletion_schedule (status, scheduled_for)
);
