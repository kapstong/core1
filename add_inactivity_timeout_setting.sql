-- Add inactivity_timeout setting to settings table
-- Run this on your database to ensure the setting exists

-- Check if setting exists, if not insert it
INSERT INTO settings (setting_key, setting_value, description, category, is_system, created_at, updated_at)
SELECT 'inactivity_timeout', '30', 'Auto-logout after inactivity (minutes, 0 = disabled)', 'security', 0, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE setting_key = 'inactivity_timeout'
);

-- Verify the setting was added
SELECT * FROM settings WHERE setting_key = 'inactivity_timeout';
