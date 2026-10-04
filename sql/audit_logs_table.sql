-- Create audit_logs table for HopeDrops
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(255) NOT NULL,
    category ENUM('authentication', 'user_management', 'hospital_management', 'blood_operations', 'system_admin', 'security', 'notifications') NOT NULL,
    action VARCHAR(255) NOT NULL,
    resource VARCHAR(255) NOT NULL,
    status ENUM('success', 'warning', 'error') NOT NULL DEFAULT 'success',
    ip_address VARCHAR(45) NULL,
    location VARCHAR(255) NULL,
    details TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id)
);

-- Note: All audit log data will be created dynamically through application activity
-- No static sample data is inserted for a clean production database
-- Audit logs will be automatically generated as users interact with the system

-- Create indexes for better performance
CREATE INDEX idx_audit_category_status ON audit_logs(category, status);
CREATE INDEX idx_audit_timestamp_desc ON audit_logs(timestamp DESC);
CREATE INDEX idx_audit_user_timestamp ON audit_logs(user_id, timestamp DESC);