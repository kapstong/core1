-- Add inactivity_timeout column to users table for per-user preferences
-- This allows each user to have their own timeout setting that persists

-- Add column if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS inactivity_timeout INT DEFAULT 30
COMMENT 'User-specific inactivity timeout in minutes (0 = disabled, NULL = use global default)';

-- Verify the column was added
DESCRIBE users;
