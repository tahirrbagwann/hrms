<?php
/**
 * Activity Logs System Database Seeder
 *
 * This script initializes the activity logs and audit trails system by:
 * 1. Creating activity_logs table
 * 2. Creating login_attempts table
 * 3. Creating views for reporting
 * 4. Creating stored procedures for cleanup
 *
 * Run this file ONCE to set up the activity logging system.
 * Access via: http://localhost/hrms/database/seed_activity_logs.php
 */

require_once '../config/database.php';

$conn = getDBConnection();
$errors = [];
$successes = [];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Activity Logs System - Database Seeder</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .section h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📝 Activity Logs System - Database Seeder</h1>
        <div class='info'>
            <strong>Running database migrations...</strong><br>
            This will create tables for activity logs and audit trails.
        </div>";

// Drop existing tables and views to ensure clean installation
echo "<div class='info'>Dropping existing tables and views (if any)...</div>";
$conn->query("DROP VIEW IF EXISTS recent_activities");
$conn->query("DROP VIEW IF EXISTS failed_login_summary");
$conn->query("DROP TABLE IF EXISTS activity_logs");
$conn->query("DROP TABLE IF EXISTS login_attempts");
$conn->query("DROP PROCEDURE IF EXISTS cleanup_old_logs");
echo "<div class='success'>✓ Cleaned up existing objects</div><br>";

// Create activity_logs table
echo "<div class='info'>Creating tables...</div>";

$create_activity_logs = "CREATE TABLE activity_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($create_activity_logs)) {
    echo "<div class='success'>✓ Table 'activity_logs' created successfully</div>";
} else {
    echo "<div class='error'>Failed to create activity_logs table: " . $conn->error . "</div>";
    $errors[] = "Failed to create activity_logs table";
}

// Create login_attempts table
$create_login_attempts = "CREATE TABLE login_attempts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($create_login_attempts)) {
    echo "<div class='success'>✓ Table 'login_attempts' created successfully</div>";
} else {
    echo "<div class='error'>Failed to create login_attempts table: " . $conn->error . "</div>";
    $errors[] = "Failed to create login_attempts table";
}

echo "<br>";

// Create the stored procedure
$conn->query("DROP PROCEDURE IF EXISTS cleanup_old_logs");

$procedure_sql = "CREATE PROCEDURE cleanup_old_logs(IN days_to_keep INT)
BEGIN
    DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END";

if ($conn->query($procedure_sql)) {
    echo "<div class='success'>✓ Stored procedure 'cleanup_old_logs' created successfully</div>";
} else {
    echo "<div class='error'>Failed to create stored procedure: " . $conn->error . "</div>";
    $errors[] = "Failed to create stored procedure";
}

// Create views
echo "<br><div class='info'>Creating views...</div>";

// Create recent_activities view
$view1_sql = "CREATE VIEW recent_activities AS
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
ORDER BY al.created_at DESC";

if ($conn->query($view1_sql)) {
    echo "<div class='success'>✓ View 'recent_activities' created successfully</div>";
} else {
    echo "<div class='error'>Failed to create view 'recent_activities': " . $conn->error . "</div>";
    $errors[] = "Failed to create recent_activities view";
}

// Create failed_login_summary view
$view2_sql = "CREATE VIEW failed_login_summary AS
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
ORDER BY attempt_count DESC";

if ($conn->query($view2_sql)) {
    echo "<div class='success'>✓ View 'failed_login_summary' created successfully</div>";
} else {
    echo "<div class='error'>Failed to create view 'failed_login_summary': " . $conn->error . "</div>";
    $errors[] = "Failed to create failed_login_summary view";
}

// Verify installation
echo "<div class='section'>
        <h2>📊 Verification Results</h2>";

// Check if tables exist
$tables = ['activity_logs', 'login_attempts'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ Table '$table' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Table '$table' was not created</div>";
    }
}

// Check if views exist
$views = ['recent_activities', 'failed_login_summary'];
foreach ($views as $view) {
    $result = $conn->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW' AND Tables_in_" . DB_NAME . " = '$view'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ View '$view' created successfully</div>";
    } else {
        echo "<div class='error'>✗ View '$view' was not created</div>";
    }
}

// Check activity_logs table structure
$result = $conn->query("DESCRIBE activity_logs");
if ($result) {
    $field_count = $result->num_rows;
    echo "<div class='success'>✓ Activity logs table has $field_count fields</div>";
}

// Display column information
echo "</div><div class='section'>
        <h2>🔍 Activity Logs Table Structure</h2>";

$result = $conn->query("DESCRIBE activity_logs");
if ($result) {
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f8f9fa; text-align:left;'><th style='padding:10px;'>Field</th><th style='padding:10px;'>Type</th><th style='padding:10px;'>Null</th><th style='padding:10px;'>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['Field']) .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['Type']) .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['Null']) .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['Key']) .
             "</td></tr>";
    }
    echo "</table>";
}

echo "</div>";

// Summary
if (empty($errors)) {
    echo "<div class='success'><strong>✅ Database seeding completed successfully!</strong><br>
          The activity logging system is now ready to use.</div>";
} else {
    echo "<div class='error'><strong>⚠️ Database seeding completed with warnings:</strong><br>";
    foreach ($errors as $error) {
        echo "• $error<br>";
    }
    echo "</div>";
}

echo "<div class='info'>
        <strong>Next Steps:</strong><br>
        1. The system will now automatically log user activities<br>
        2. View activity logs at: <a href='../admin/activity-logs.php'>Admin Dashboard → Activity Logs</a><br>
        3. Login attempts are automatically tracked<br>
        4. Failed login attempts are monitored for security
      </div>";

echo "<a href='../admin/dashboard.php' class='btn'>Go to Admin Dashboard</a>
      <a href='../admin/activity-logs.php' class='btn' style='background:#28a745;'>View Activity Logs</a>";

echo "</div></body></html>";

closeDBConnection($conn);
?>
