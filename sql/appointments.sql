

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id      INT AUTO_INCREMENT PRIMARY KEY,
    application_id      INT NOT NULL,
    pet_id              INT NOT NULL,
    adopter_id          INT NOT NULL,
    veterinary_id       INT NOT NULL,
    appointment_date    DATE NOT NULL,
    appointment_time    TIME NOT NULL,
    appointment_type    VARCHAR(80) NOT NULL DEFAULT 'Veterinary Consultation',
    notes               TEXT NULL,
    status              ENUM('SCHEDULED','CONFIRMED','COMPLETED','CANCELLED','RESCHEDULED')
                        NOT NULL DEFAULT 'SCHEDULED',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_appt_adopter     (adopter_id),
    INDEX idx_appt_vet         (veterinary_id),
    INDEX idx_appt_application (application_id),
    INDEX idx_appt_when        (appointment_date, appointment_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* In-app notifications — only creates if missing. Use your existing model if you have one. */
CREATE TABLE IF NOT EXISTS notifications (
    notification_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    type                VARCHAR(40) NOT NULL DEFAULT 'info',
    title               VARCHAR(200) NOT NULL,
    body                TEXT NULL,
    link_url            VARCHAR(255) NULL,
    is_read             TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user     (user_id),
    INDEX idx_notif_user_new (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* Vet/clinic contact number — enables "Call Vet" / "Message Vet" from adopter side. */
ALTER TABLE shelter_profiles
    ADD COLUMN IF NOT EXISTS phone         VARCHAR(30)  NULL,
    ADD COLUMN IF NOT EXISTS contact_email VARCHAR(150) NULL;
