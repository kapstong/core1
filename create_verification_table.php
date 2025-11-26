<?php
require_once 'backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    $db->exec('CREATE TABLE IF NOT EXISTS verification_codes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        code VARCHAR(255) NOT NULL,
        code_type VARCHAR(50) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        is_used TINYINT DEFAULT 0,
        used_at TIMESTAMP NULL,
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_type (user_id, code_type),
        INDEX idx_code (code),
        INDEX idx_expires (expires_at)
    )');

    echo "verification_codes table created successfully!\n";
} catch(Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
