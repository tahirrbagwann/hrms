<?php
/**
 * Leave Management System Database Setup
 * This script creates all necessary tables for the leave management system
 */

require_once '../config/database.php';

$conn = getDBConnection();

echo "Setting up Leave Management System Database...\n\n";

// 1. Create leave_types table
$sql_leave_types = "CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_name VARCHAR(100) NOT NULL,
    leave_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    is_paid BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT TRUE,
    max_consecutive_days INT DEFAULT NULL,
    can_carry_forward BOOLEAN DEFAULT FALSE,
    carry_forward_limit INT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#667eea',
    icon VARCHAR(50) DEFAULT '📅',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_leave_types) === TRUE) {
    echo "✓ Table 'leave_types' created successfully\n";
} else {
    echo "✗ Error creating leave_types table: " . $conn->error . "\n";
}

// 2. Create leave_policies table
$sql_leave_policies = "CREATE TABLE IF NOT EXISTS leave_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_type_id INT NOT NULL,
    role_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    days_per_year DECIMAL(5,2) NOT NULL,
    accrual_type ENUM('yearly', 'monthly', 'per_pay_period') DEFAULT 'yearly',
    min_service_months INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
)";

if ($conn->query($sql_leave_policies) === TRUE) {
    echo "✓ Table 'leave_policies' created successfully\n";
} else {
    echo "✗ Error creating leave_policies table: " . $conn->error . "\n";
}

// 3. Create leave_balances table
$sql_leave_balances = "CREATE TABLE IF NOT EXISTS leave_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    total_days DECIMAL(5,2) DEFAULT 0,
    used_days DECIMAL(5,2) DEFAULT 0,
    pending_days DECIMAL(5,2) DEFAULT 0,
    available_days DECIMAL(5,2) GENERATED ALWAYS AS (total_days - used_days - pending_days) STORED,
    carried_forward DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_leave_year (user_id, leave_type_id, year)
)";

if ($conn->query($sql_leave_balances) === TRUE) {
    echo "✓ Table 'leave_balances' created successfully\n";
} else {
    echo "✗ Error creating leave_balances table: " . $conn->error . "\n";
}

// 4. Create leave_requests table
$sql_leave_requests = "CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approver_id INT DEFAULT NULL,
    approver_comments TEXT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql_leave_requests) === TRUE) {
    echo "✓ Table 'leave_requests' created successfully\n";
} else {
    echo "✗ Error creating leave_requests table: " . $conn->error . "\n";
}

// 5. Create holidays table
$sql_holidays = "CREATE TABLE IF NOT EXISTS holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_name VARCHAR(200) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('public', 'optional', 'restricted') DEFAULT 'public',
    description TEXT,
    is_recurring BOOLEAN DEFAULT FALSE,
    applicable_departments TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql_holidays) === TRUE) {
    echo "✓ Table 'holidays' created successfully\n";
} else {
    echo "✗ Error creating holidays table: " . $conn->error . "\n";
}

// 6. Create leave_approvals table (for multi-level approval workflow)
$sql_leave_approvals = "CREATE TABLE IF NOT EXISTS leave_approvals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_request_id INT NOT NULL,
    approver_id INT NOT NULL,
    approval_level INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    comments TEXT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql_leave_approvals) === TRUE) {
    echo "✓ Table 'leave_approvals' created successfully\n";
} else {
    echo "✗ Error creating leave_approvals table: " . $conn->error . "\n";
}

// 7. Create leave_attachments table (for supporting documents)
$sql_leave_attachments = "CREATE TABLE IF NOT EXISTS leave_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE
)";

if ($conn->query($sql_leave_attachments) === TRUE) {
    echo "✓ Table 'leave_attachments' created successfully\n";
} else {
    echo "✗ Error creating leave_attachments table: " . $conn->error . "\n";
}

// Insert default leave types
echo "\nInserting default leave types...\n";

$default_leave_types = [
    ['Sick Leave', 'SL', 'Leave for medical reasons or illness', 1, 1, 7, 0, NULL, '#f44336', '🤒'],
    ['Casual Leave', 'CL', 'Short-term leave for personal reasons', 1, 1, 3, 1, 5, '#2196f3', '🏖️'],
    ['Vacation Leave', 'VL', 'Annual vacation or planned time off', 1, 1, 15, 1, 10, '#4caf50', '✈️'],
    ['Maternity Leave', 'ML', 'Leave for maternity purposes', 1, 1, NULL, 0, NULL, '#e91e63', '🤱'],
    ['Paternity Leave', 'PL', 'Leave for paternity purposes', 1, 1, NULL, 0, NULL, '#9c27b0', '👶'],
    ['Bereavement Leave', 'BL', 'Leave due to death of a family member', 1, 1, 5, 0, NULL, '#000000', '🕊️'],
    ['Unpaid Leave', 'UL', 'Leave without pay', 0, 1, NULL, 0, NULL, '#9e9e9e', '📭'],
    ['Compensatory Off', 'CO', 'Compensatory time off for overtime work', 1, 1, NULL, 0, NULL, '#ff9800', '⏰']
];

$insert_leave_type = $conn->prepare("INSERT IGNORE INTO leave_types (leave_name, leave_code, description, is_paid, requires_approval, max_consecutive_days, can_carry_forward, carry_forward_limit, color, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$inserted_count = 0;
foreach ($default_leave_types as $leave_type) {
    $insert_leave_type->bind_param("sssiiiiiss", ...$leave_type);
    if ($insert_leave_type->execute() && $conn->affected_rows > 0) {
        echo "✓ Inserted leave type: {$leave_type[0]}\n";
        $inserted_count++;
    } else {
        echo "⊘ Skipped leave type (already exists): {$leave_type[0]}\n";
    }
}
$insert_leave_type->close();
if ($inserted_count == 0) {
    echo "  All leave types already exist.\n";
}

// Insert default leave policies (for all employees)
echo "\nInserting default leave policies...\n";

// First, get the leave type IDs by code
$leave_type_ids = [];
$result = $conn->query("SELECT id, leave_code FROM leave_types");
while ($row = $result->fetch_assoc()) {
    $leave_type_ids[$row['leave_code']] = $row['id'];
}

$default_policies = [
    ['SL', 12],  // Sick Leave - 12 days
    ['CL', 10],  // Casual Leave - 10 days
    ['VL', 15],  // Vacation Leave - 15 days
    ['ML', 90],  // Maternity Leave - 90 days
    ['PL', 7],   // Paternity Leave - 7 days
    ['BL', 5],   // Bereavement Leave - 5 days
    ['CO', 12]   // Compensatory Off - 12 days
];

$insert_policy = $conn->prepare("INSERT IGNORE INTO leave_policies (leave_type_id, days_per_year, accrual_type) VALUES (?, ?, 'yearly')");

$policy_inserted_count = 0;
foreach ($default_policies as $policy) {
    if (isset($leave_type_ids[$policy[0]])) {
        $leave_type_id = $leave_type_ids[$policy[0]];
        $insert_policy->bind_param("id", $leave_type_id, $policy[1]);
        if ($insert_policy->execute() && $conn->affected_rows > 0) {
            echo "✓ Created policy for {$policy[0]}: {$policy[1]} days/year\n";
            $policy_inserted_count++;
        } else {
            echo "⊘ Skipped policy for {$policy[0]} (already exists)\n";
        }
    }
}
$insert_policy->close();
if ($policy_inserted_count == 0) {
    echo "  All policies already exist.\n";
}

// Insert sample holidays for 2025
echo "\nInserting sample holidays for 2025...\n";

$sample_holidays = [
    ['New Year\'s Day', '2025-01-01', 'public', 'Public holiday for New Year'],
    ['Republic Day', '2025-01-26', 'public', 'National holiday'],
    ['Holi', '2025-03-14', 'public', 'Festival of colors'],
    ['Good Friday', '2025-04-18', 'public', 'Christian holiday'],
    ['Independence Day', '2025-08-15', 'public', 'National holiday'],
    ['Gandhi Jayanti', '2025-10-02', 'public', 'National holiday'],
    ['Diwali', '2025-10-20', 'public', 'Festival of lights'],
    ['Christmas', '2025-12-25', 'public', 'Christian holiday']
];

// Check if holidays already exist for each date
$insert_holiday = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description, is_recurring)
                                   SELECT ?, ?, ?, ?, 1
                                   WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = ?)");

$holiday_inserted_count = 0;
foreach ($sample_holidays as $holiday) {
    $insert_holiday->bind_param("sssss", $holiday[0], $holiday[1], $holiday[2], $holiday[3], $holiday[1]);
    if ($insert_holiday->execute() && $conn->affected_rows > 0) {
        echo "✓ Added holiday: {$holiday[0]} on {$holiday[1]}\n";
        $holiday_inserted_count++;
    } else {
        echo "⊘ Skipped holiday (already exists): {$holiday[0]}\n";
    }
}
$insert_holiday->close();
if ($holiday_inserted_count == 0) {
    echo "  All holidays already exist.\n";
}

// Create permissions for leave management
echo "\nCreating permissions for leave management...\n";

$leave_permissions = [
    ['leave.apply', 'Apply for Leave', 'Allow employees to apply for leave', 'leave_management'],
    ['leave.view_own', 'View Own Leave Records', 'View own leave history and balance', 'leave_management'],
    ['leave.view_all', 'View All Leave Records', 'View all employee leave records', 'leave_management'],
    ['leave.approve', 'Approve Leave Requests', 'Approve employee leave requests', 'leave_management'],
    ['leave.reject', 'Reject Leave Requests', 'Reject employee leave requests', 'leave_management'],
    ['leave.cancel', 'Cancel Leave Requests', 'Cancel own leave requests', 'leave_management'],
    ['leave.manage_types', 'Manage Leave Types', 'Configure leave types and settings', 'leave_management'],
    ['leave.manage_policies', 'Manage Leave Policies', 'Configure leave policies and allocations', 'leave_management'],
    ['leave.manage_holidays', 'Manage Holidays', 'Manage company holiday calendar', 'leave_management'],
    ['leave.view_calendar', 'View Leave Calendar', 'View team leave calendar', 'leave_management'],
    ['leave.export_reports', 'Export Leave Reports', 'Export leave reports and analytics', 'leave_management']
];

// Check if permissions table exists
$check_permissions_table = $conn->query("SHOW TABLES LIKE 'permissions'");
if ($check_permissions_table->num_rows > 0) {
    $insert_permission = $conn->prepare("INSERT IGNORE INTO permissions (permission_slug, permission_name, description, module) VALUES (?, ?, ?, ?)");

    foreach ($leave_permissions as $permission) {
        $insert_permission->bind_param("ssss", $permission[0], $permission[1], $permission[2], $permission[3]);
        if ($insert_permission->execute()) {
            echo "✓ Created permission: {$permission[1]}\n";
        }
    }
    $insert_permission->close();
} else {
    echo "! Permissions table not found. Skipping permission creation.\n";
}

closeDBConnection($conn);

echo "\n✅ Leave Management System Database Setup Complete!\n";
echo "\nNext steps:\n";
echo "1. Access the admin panel to configure leave policies\n";
echo "2. Initialize leave balances for existing employees\n";
echo "3. Configure approval workflows\n";
?>
