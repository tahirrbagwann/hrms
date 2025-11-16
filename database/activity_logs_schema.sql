-- Activity Logs and Audit Trails Schema
-- This file adds comprehensive activity logging and audit trail functionality

-- ============================================
-- 1. ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id INT NULL,
    target_name VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    status ENUM('success', 'failed', 'warning') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_target_type (target_type),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. LOGIN ATTEMPTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. INSERT SAMPLE ACTIVITY TYPES
-- ============================================
-- These are reference data showing common activity types
-- The actual logging happens in the application code

-- Authentication Activities:
-- - user.login
-- - user.logout
-- - user.login.failed
-- - user.password.changed
-- - user.email.verified
-- - user.2fa.enabled
-- - user.2fa.disabled

-- User Management Activities:
-- - user.created
-- - user.updated
-- - user.deleted
-- - user.activated
-- - user.deactivated
-- - user.role.changed
-- - user.department.changed
-- - user.bulk.imported

-- Role Management Activities:
-- - role.created
-- - role.updated
-- - role.deleted
-- - role.permissions.updated

-- Department Management Activities:
-- - department.created
-- - department.updated
-- - department.deleted
-- - department.head.assigned

-- Attendance Activities:
-- - attendance.punch.in
-- - attendance.punch.out
-- - attendance.created
-- - attendance.updated
-- - attendance.deleted

-- Profile Activities:
-- - profile.updated
-- - profile.picture.uploaded

-- ============================================
-- 4. STORED PROCEDURE FOR CLEANUP
-- ============================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS cleanup_old_logs(days_to_keep INT)
BEGIN
    DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END$$

DELIMITER ;

-- To run cleanup: CALL cleanup_old_logs(90); -- Keep 90 days of logs

-- ============================================
-- 5. VIEW FOR RECENT ACTIVITIES
-- ============================================
CREATE OR REPLACE VIEW recent_activities AS
SELECT
    al.id,
    al.user_id,
    al.username,
    al.action_type,
    al.action_description,
    al.target_type,
    al.target_name,
    al.status,
    al.created_at,
    u.full_name,
    u.email
FROM activity_logs al
LEFT JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC;

-- ============================================
-- 6. VIEW FOR FAILED LOGIN ATTEMPTS
-- ============================================
CREATE OR REPLACE VIEW failed_login_summary AS
SELECT
    email,
    COUNT(*) as attempt_count,
    MAX(created_at) as last_attempt,
    ip_address
FROM login_attempts
WHERE status = 'failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY email, ip_address
HAVING attempt_count >= 3
ORDER BY attempt_count DESC;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- View table structure
-- DESCRIBE activity_logs;
-- DESCRIBE login_attempts;

-- View recent activities
-- SELECT * FROM recent_activities;

-- View failed login attempts
-- SELECT * FROM failed_login_summary;

-- Count activities by type
-- SELECT action_type, COUNT(*) as count
-- FROM activity_logs
-- GROUP BY action_type
-- ORDER BY count DESC;

-- User activity summary
-- SELECT u.username, u.full_name, COUNT(al.id) as activity_count
-- FROM users u
-- LEFT JOIN activity_logs al ON u.id = al.user_id
-- GROUP BY u.id
-- ORDER BY activity_count DESC;
