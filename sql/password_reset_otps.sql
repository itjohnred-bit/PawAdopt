CREATE TABLE IF NOT EXISTS password_reset_otps (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_ip VARCHAR(64) NULL,
    INDEX idx_reset_email (email),
    INDEX idx_reset_user (user_id),
    INDEX idx_reset_expiry (expires_at),
    INDEX idx_reset_request_ip (request_ip)
);
