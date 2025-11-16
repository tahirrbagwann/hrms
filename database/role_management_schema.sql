-- Role Management System Schema
-- This file adds comprehensive role and permission management to HRMS

-- ============================================
-- 1. DEPARTMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    department_code VARCHAR(20) UNIQUE,
    description TEXT,
    head_user_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_department_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. PERMISSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    permission_slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permission_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. ROLE_PERMISSIONS JUNCTION TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_permissions_role (role_id),
    INDEX idx_role_permissions_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. UPDATE ROLES TABLE
-- ============================================
ALTER TABLE roles ADD COLUMN IF NOT EXISTS description TEXT AFTER role_name;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS is_system_role TINYINT(1) DEFAULT 0 AFTER description;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER is_system_role;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Mark existing roles as system roles (cannot be deleted)
UPDATE roles SET is_system_role = 1 WHERE role_name IN ('admin', 'employee');
UPDATE roles SET description = 'System administrator with full access' WHERE role_name = 'admin';
UPDATE roles SET description = 'Regular employee with basic access' WHERE role_name = 'employee';

-- ============================================
-- 5. UPDATE USERS TABLE
-- ============================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER role_id;
ALTER TABLE users ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_users_department (department_id);

-- ============================================
-- 6. INSERT DEFAULT PERMISSIONS
-- ============================================

-- User Management Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Users', 'view_users', 'Can view list of users', 'user_management'),
('Create Users', 'create_users', 'Can create new users', 'user_management'),
('Edit Users', 'edit_users', 'Can edit user information', 'user_management'),
('Delete Users', 'delete_users', 'Can delete users', 'user_management'),
('Manage User Roles', 'manage_user_roles', 'Can assign roles to users', 'user_management')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- Role Management Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Roles', 'view_roles', 'Can view list of roles', 'role_management'),
('Create Roles', 'create_roles', 'Can create new roles', 'role_management'),
('Edit Roles', 'edit_roles', 'Can edit role information and permissions', 'role_management'),
('Delete Roles', 'delete_roles', 'Can delete roles', 'role_management')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- Department Management Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Departments', 'view_departments', 'Can view list of departments', 'department_management'),
('Create Departments', 'create_departments', 'Can create new departments', 'department_management'),
('Edit Departments', 'edit_departments', 'Can edit department information', 'department_management'),
('Delete Departments', 'delete_departments', 'Can delete departments', 'department_management')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- Attendance Management Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Own Attendance', 'view_own_attendance', 'Can view own attendance records', 'attendance'),
('Punch In/Out', 'punch_in_out', 'Can punch in and out', 'attendance'),
('View All Attendance', 'view_all_attendance', 'Can view all employee attendance', 'attendance'),
('Edit Attendance', 'edit_attendance', 'Can edit attendance records', 'attendance'),
('Delete Attendance', 'delete_attendance', 'Can delete attendance records', 'attendance'),
('View Attendance Reports', 'view_attendance_reports', 'Can view attendance reports', 'attendance')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- Profile Management Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Own Profile', 'view_own_profile', 'Can view own profile', 'profile'),
('Edit Own Profile', 'edit_own_profile', 'Can edit own profile', 'profile'),
('View All Profiles', 'view_all_profiles', 'Can view all user profiles', 'profile'),
('Edit All Profiles', 'edit_all_profiles', 'Can edit any user profile', 'profile')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- Dashboard Permissions
INSERT INTO permissions (permission_name, permission_slug, description, module) VALUES
('View Admin Dashboard', 'view_admin_dashboard', 'Can access admin dashboard', 'dashboard'),
('View Employee Dashboard', 'view_employee_dashboard', 'Can access employee dashboard', 'dashboard'),
('View Statistics', 'view_statistics', 'Can view system statistics', 'dashboard')
ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name);

-- ============================================
-- 7. ASSIGN PERMISSIONS TO EXISTING ROLES
-- ============================================

-- Admin Role - Full Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_name = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Employee Role - Basic Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN (
    'view_own_attendance',
    'punch_in_out',
    'view_own_profile',
    'edit_own_profile',
    'view_employee_dashboard'
)
WHERE r.role_name = 'employee'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- ============================================
-- 8. INSERT ADDITIONAL ROLES
-- ============================================

-- Manager Role
INSERT INTO roles (role_name, description, is_system_role, status) VALUES
('manager', 'Team manager with department-level access', 0, 'active')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- HR Role
INSERT INTO roles (role_name, description, is_system_role, status) VALUES
('hr', 'HR personnel with user and attendance management access', 0, 'active')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- Team Lead Role
INSERT INTO roles (role_name, description, is_system_role, status) VALUES
('team_lead', 'Team lead with limited management capabilities', 0, 'active')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- ============================================
-- 9. ASSIGN PERMISSIONS TO NEW ROLES
-- ============================================

-- Manager Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN (
    'view_users',
    'view_all_attendance',
    'view_attendance_reports',
    'view_own_profile',
    'edit_own_profile',
    'view_all_profiles',
    'view_admin_dashboard',
    'view_statistics',
    'view_departments',
    'punch_in_out',
    'view_own_attendance'
)
WHERE r.role_name = 'manager'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- HR Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN (
    'view_users',
    'create_users',
    'edit_users',
    'manage_user_roles',
    'view_all_attendance',
    'edit_attendance',
    'view_attendance_reports',
    'view_own_profile',
    'edit_own_profile',
    'view_all_profiles',
    'edit_all_profiles',
    'view_admin_dashboard',
    'view_statistics',
    'view_departments',
    'create_departments',
    'edit_departments'
)
WHERE r.role_name = 'hr'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Team Lead Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_slug IN (
    'view_users',
    'view_all_attendance',
    'view_attendance_reports',
    'view_own_profile',
    'edit_own_profile',
    'view_admin_dashboard',
    'view_departments',
    'punch_in_out',
    'view_own_attendance'
)
WHERE r.role_name = 'team_lead'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- ============================================
-- 10. INSERT DEFAULT DEPARTMENTS
-- ============================================

INSERT INTO departments (department_name, department_code, description, status) VALUES
('Engineering', 'ENG', 'Software development and engineering team', 'active'),
('Human Resources', 'HR', 'Human resources and recruitment team', 'active'),
('Sales', 'SALES', 'Sales and business development team', 'active'),
('Marketing', 'MKT', 'Marketing and communications team', 'active'),
('Finance', 'FIN', 'Finance and accounting team', 'active'),
('Operations', 'OPS', 'Operations and support team', 'active')
ON DUPLICATE KEY UPDATE department_name = VALUES(department_name);

-- ============================================
-- VERIFICATION QUERIES (for testing)
-- ============================================

-- View all permissions by module
-- SELECT module, permission_name, permission_slug FROM permissions ORDER BY module, permission_name;

-- View all roles with permission counts
-- SELECT r.role_name, r.description, COUNT(rp.permission_id) as permission_count
-- FROM roles r
-- LEFT JOIN role_permissions rp ON r.id = rp.role_id
-- GROUP BY r.id
-- ORDER BY r.role_name;

-- View permissions for a specific role
-- SELECT r.role_name, p.module, p.permission_name
-- FROM roles r
-- JOIN role_permissions rp ON r.id = rp.role_id
-- JOIN permissions p ON rp.permission_id = p.id
-- WHERE r.role_name = 'admin'
-- ORDER BY p.module, p.permission_name;
