-- Add created_by_admin_id column to users table
ALTER TABLE users ADD COLUMN created_by_admin_id INT NULL;
ALTER TABLE users ADD CONSTRAINT fk_created_by_admin FOREIGN KEY (created_by_admin_id) REFERENCES users(id);

-- Update existing admin user to show as manually created (self-reference)
UPDATE users SET created_by_admin_id = 1 WHERE username = 'admin';

-- Add created_by_admin_id column to redemption_logs table
ALTER TABLE redemption_logs ADD COLUMN created_by_admin_id INT NULL;
ALTER TABLE redemption_logs ADD CONSTRAINT fk_redemption_created_by FOREIGN KEY (created_by_admin_id) REFERENCES users(id);
