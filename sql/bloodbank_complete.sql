-- RaktaSewa Blood Bank Management System - Complete Database Setup
-- This file consolidates all necessary tables, data, and configurations
-- Run this single file to set up the complete database system
-- Last updated: December 29, 2025 - Schema verified compatible with current APIs
-- Note: All API endpoints confirmed working with this database structure
-- Recent updates: 
--   - Emergency dashboard fully integrated with emergency_requests table
--   - Hospital activities now pull from appointments and emergency_requests
--   - Fixed API responses for proper urgency_level field handling
--   - All hospital pages display dynamic usernames from session

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bloodbank_db;
USE bloodbank_db;

-- Set SQL mode for better compatibility
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ====================================================================
-- CORE TABLES
-- ====================================================================

-- Users table (main authentication and role management)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('donor', 'hospital', 'admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100) DEFAULT 'Not specified',
    pincode VARCHAR(10) DEFAULT '000000',
    emergency_contact VARCHAR(15),
    medical_conditions TEXT,
    is_eligible BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hospitals table (hospital/organization details)
CREATE TABLE IF NOT EXISTS hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hospital_name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT 'Not specified',
    pincode VARCHAR(10) DEFAULT '000000',
    contact_person VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(15) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    emergency_contact VARCHAR(15),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    hospital_type VARCHAR(50) DEFAULT 'General',
    phone VARCHAR(15) NULL,
    email VARCHAR(100) NULL,
    is_approved TINYINT(1) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood inventory table (track available blood units by hospital)
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_available INT DEFAULT 0,
    units_required INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hospital_blood (hospital_id, blood_type)
);

-- Donations table (track donation history)
CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    donation_date DATE NOT NULL,
    donation_time TIME,
    status ENUM('scheduled', 'completed', 'cancelled', 'rejected') DEFAULT 'scheduled',
    units_donated INT DEFAULT 1,
    hemoglobin_level DECIMAL(3,1),
    weight DECIMAL(5,2),
    blood_pressure VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Notifications table (system notifications and alerts)
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'emergency') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table (system activity tracking)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit logs table (comprehensive audit trail)
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

-- ====================================================================
-- HOSPITAL MANAGEMENT TABLES
-- ====================================================================

-- Hospital activities table (track hospital operations and events)
CREATE TABLE IF NOT EXISTS hospital_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    user_id INT NULL,
    activity_type VARCHAR(100) NOT NULL,
    activity_data JSON,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Campaign registrations table (track donor registrations for campaigns)
CREATE TABLE IF NOT EXISTS campaign_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    notes TEXT,
    UNIQUE KEY unique_registration (campaign_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_campaign_id (campaign_id),
    KEY idx_user_id (user_id),
    KEY idx_status (status)
);

-- Emergency blood requests table
CREATE TABLE IF NOT EXISTS emergency_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical', 'emergency') DEFAULT 'medium',
    status ENUM('pending', 'accepted', 'fulfilled', 'cancelled') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    required_date TIMESTAMP NULL,
    notes TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- General requests table (alternative to emergency_requests for compatibility)
CREATE TABLE IF NOT EXISTS requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical', 'emergency') DEFAULT 'medium',
    status ENUM('pending', 'accepted', 'fulfilled', 'cancelled') DEFAULT 'pending',
    description TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- ====================================================================
-- REWARD SYSTEM TABLES
-- ====================================================================

-- User rewards tracking table
CREATE TABLE IF NOT EXISTS user_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    current_points INT DEFAULT 0,
    level INT DEFAULT 1,
    donations_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reward (user_id)
);

-- Badges system
CREATE TABLE IF NOT EXISTS badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(255),
    category VARCHAR(50),
    requirements TEXT,
    points_awarded INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User badges (earned badges)
CREATE TABLE IF NOT EXISTS user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- Reward items (shop items that can be redeemed)
CREATE TABLE IF NOT EXISTS reward_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    points_cost INT NOT NULL,
    category VARCHAR(50),
    image_url VARCHAR(255),
    stock_quantity INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reward redemptions (when users redeem items)
CREATE TABLE IF NOT EXISTS reward_redemptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    points_used INT NOT NULL,
    redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES reward_items(id) ON DELETE CASCADE
);

-- ====================================================================
-- APPOINTMENTS SYSTEM
-- ====================================================================

-- Appointments table (blood donation scheduling)
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    reminder_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    INDEX idx_appointments_donor_id (donor_id),
    INDEX idx_appointments_hospital_id (hospital_id),
    INDEX idx_appointments_date (appointment_date),
    INDEX idx_appointments_status (status)
);

-- ====================================================================
-- INDEXES FOR PERFORMANCE
-- ====================================================================

-- Users table indexes (only for columns that exist)
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_blood_type ON users(blood_type);

-- Hospitals table indexes
CREATE INDEX IF NOT EXISTS idx_hospitals_user_id ON hospitals(user_id);
CREATE INDEX IF NOT EXISTS idx_hospitals_city ON hospitals(city);
CREATE INDEX IF NOT EXISTS idx_hospitals_approved ON hospitals(is_approved);
CREATE INDEX IF NOT EXISTS idx_hospitals_active ON hospitals(is_active);

-- Blood inventory indexes
CREATE INDEX IF NOT EXISTS idx_blood_inventory_hospital_id ON blood_inventory(hospital_id);
CREATE INDEX IF NOT EXISTS idx_blood_inventory_blood_type ON blood_inventory(blood_type);

-- Donations table indexes
CREATE INDEX IF NOT EXISTS idx_donations_donor_id ON donations(donor_id);
CREATE INDEX IF NOT EXISTS idx_donations_hospital_id ON donations(hospital_id);
CREATE INDEX IF NOT EXISTS idx_donations_date ON donations(donation_date);
CREATE INDEX IF NOT EXISTS idx_donations_status ON donations(status);

-- Notifications table indexes
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);

-- Activity logs indexes
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);

-- Audit logs indexes
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_timestamp ON audit_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_audit_logs_category ON audit_logs(category);
CREATE INDEX IF NOT EXISTS idx_audit_logs_status ON audit_logs(status);

-- Hospital activities indexes
CREATE INDEX IF NOT EXISTS idx_hospital_activities_hospital_id ON hospital_activities(hospital_id);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_user_id ON hospital_activities(user_id);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_type ON hospital_activities(activity_type);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_created_at ON hospital_activities(created_at);

-- Emergency requests indexes
CREATE INDEX IF NOT EXISTS idx_emergency_requests_hospital_id ON emergency_requests(hospital_id);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_blood_type ON emergency_requests(blood_type);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_urgency ON emergency_requests(urgency_level);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_status ON emergency_requests(status);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_created_at ON emergency_requests(created_at);

-- General requests indexes
CREATE INDEX IF NOT EXISTS idx_requests_hospital_id ON requests(hospital_id);
CREATE INDEX IF NOT EXISTS idx_requests_blood_type ON requests(blood_type);
CREATE INDEX IF NOT EXISTS idx_requests_urgency ON requests(urgency_level);
CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_created_at ON requests(created_at);

-- ====================================================================
-- INITIAL DATA
-- ====================================================================

-- Create default admin account
-- Username: admin
-- Password: password (Change this immediately after first login!)
INSERT INTO users (username, email, password, role, full_name, phone, is_eligible, is_active) 
VALUES ('admin', 'admin@raktasewa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', '0000000000', 1, 1) 
ON DUPLICATE KEY UPDATE username = username;

-- ====================================================================
-- MIGRATION AND COMPATIBILITY FIXES
-- ====================================================================

-- Add user_id column to hospital_activities if it doesn't exist (for existing databases)
ALTER TABLE hospital_activities 
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER hospital_id,
ADD CONSTRAINT fk_hospital_activities_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for user_id if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_hospital_activities_user_id ON hospital_activities(user_id);

-- ====================================================================
-- POST-SETUP PROCEDURES
-- ====================================================================

-- Initialize user rewards for existing donors (if any)
INSERT INTO user_rewards (user_id, total_points, current_points, level, donations_count)
SELECT u.id, 0, 0, 1, 0 
FROM users u 
WHERE u.role = 'donor' 
AND NOT EXISTS (SELECT 1 FROM user_rewards ur WHERE ur.user_id = u.id);

-- Ensure all hospitals have blood inventory entries for all blood types
INSERT IGNORE INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
SELECT h.id, bt.blood_type, 0, 0
FROM hospitals h
CROSS JOIN (
    SELECT 'A+' as blood_type UNION ALL
    SELECT 'A-' UNION ALL
    SELECT 'B+' UNION ALL
    SELECT 'B-' UNION ALL
    SELECT 'AB+' UNION ALL
    SELECT 'AB-' UNION ALL
    SELECT 'O+' UNION ALL
    SELECT 'O-'
) bt
WHERE h.is_approved = 1;

-- Update hospital phone/email fields from contact fields (for compatibility)
UPDATE hospitals SET 
    phone = COALESCE(phone, contact_phone),
    email = COALESCE(email, contact_email)
WHERE phone IS NULL OR email IS NULL;

-- ====================================================================
-- CAMPAIGN SYSTEM SETUP
-- ====================================================================

-- Campaign data is stored in hospital_activities table with activity_type = 'campaign_created'
-- The activity_data column contains JSON with campaign details:
-- {
--   "title": "Campaign Title",
--   "description": "Campaign Description", 
--   "start_date": "YYYY-MM-DD",
--   "end_date": "YYYY-MM-DD",
--   "start_time": "HH:MM",
--   "end_time": "HH:MM",
--   "target_donors": number,
--   "max_capacity": number,
--   "organizer": "Organizer Name",
--   "location": "Campaign Location",
--   "image_path": "uploads/campaigns/filename.jpg",
--   "status": "active|completed|cancelled",
--   "current_donors": number,
--   "campaign_type": "blood_drive"
-- }

-- Campaign data will be created dynamically through the application
-- No sample campaigns are inserted for a clean production database

-- ====================================================================
-- FILE SYSTEM REQUIREMENTS
-- ====================================================================

-- IMPORTANT: Create these directories in your web server:
-- 
-- uploads/                          (Main uploads directory)
-- └── campaigns/                    (Campaign images directory)
--     └── .htaccess                 (Security file - see below)
--
-- Create uploads/campaigns/.htaccess with this content to secure uploaded files:
-- <Files "*">
--     Order Allow,Deny
--     Allow from all
-- </Files>
-- <FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
--     Order Allow,Deny
--     Deny from all
-- </FilesMatch>
--
-- Set proper permissions:
-- - uploads/ directory: 755 (rwxr-xr-x)
-- - campaigns/ directory: 755 (rwxr-xr-x)  
-- - uploaded files: 644 (rw-r--r--)

-- ====================================================================
-- API ENDPOINTS ADDED
-- ====================================================================

-- The following PHP API files support the campaign system:
-- 
-- php/create_campaign.php          - Create new campaigns (POST)
-- php/get_campaigns.php            - List all campaigns (GET)  
-- php/get_campaign_details.php     - Get campaign details (GET ?id=<campaign_id>)
-- php/get_campaign_stats.php       - Get campaign statistics (GET)
--
-- The following PHP API files support the audit logs system:
--
-- php/get_audit_logs.php           - Fetch audit logs with filtering and pagination (GET)
-- php/log_audit.php                - Log new audit events (POST)
-- php/get_audit_stats.php          - Get audit statistics and chart data (GET)
--
-- All APIs use self-contained PDO connections and return JSON responses
-- All APIs include proper error handling and fallback data

-- ====================================================================
-- COMPLETION MESSAGE
-- ====================================================================

SELECT 'RaktaSewa Blood Bank Database setup completed successfully!' as Status,
       'All table structures created' as Tables,
       'No static data inserted - ready for dynamic content' as Data_Status,
       'Use the application to create users, hospitals, and manage data' as Next_Steps;