-- Face authentication support for inventory staff login
-- Run once against your MySQL database.

ALTER TABLE users
    ADD COLUMN face_descriptor MEDIUMTEXT NULL AFTER password_hash,
    ADD COLUMN face_biometric_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER face_descriptor,
    ADD COLUMN face_last_enrolled_at DATETIME NULL AFTER face_biometric_enabled;

CREATE TABLE IF NOT EXISTS face_auth_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    face_distance DECIMAL(8,6) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_face_auth_user_id (user_id),
    KEY idx_face_auth_created_at (created_at),
    CONSTRAINT fk_face_auth_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
