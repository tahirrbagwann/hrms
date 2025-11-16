<?php
/**
 * Role Management System Database Seeder
 *
 * This script initializes the role management system by:
 * 1. Creating necessary tables (departments, permissions, role_permissions)
 * 2. Updating existing tables (roles, users)
 * 3. Seeding default permissions
 * 4. Assigning permissions to existing roles
 * 5. Creating additional roles (Manager, HR, Team Lead)
 * 6. Creating default departments
 *
 * Run this file ONCE to set up the role management system.
 * Access via: http://localhost/hrms/database/seed_role_management.php
 */

require_once '../config/database.php';

$conn = getDBConnection();
$errors = [];
$successes = [];

// Function to execute SQL and track results
function executeSql($conn, $sql, $description) {
    global $errors, $successes;

    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        $successes[] = $description;
        return true;
    } else {
        $errors[] = "$description - Error: " . $conn->error;
        return false;
    }
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Role Management System - Database Seeder</title>
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
        <h1>🔐 Role Management System - Database Seeder</h1>
        <div class='info'>
            <strong>Running database migrations...</strong><br>
            This will create tables, seed permissions, and set up default roles and departments.
        </div>";

// Read and execute the SQL file
$sql_file = __DIR__ . '/role_management_schema.sql';

if (!file_exists($sql_file)) {
    echo "<div class='error'>❌ SQL file not found: $sql_file</div>";
} else {
    $sql = file_get_contents($sql_file);

    // Split by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) &&
                   substr($stmt, 0, 2) !== '--' &&
                   substr($stmt, 0, 2) !== '/*';
        }
    );

    $step = 1;
    foreach ($statements as $statement) {
        // Skip comments
        if (strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
            continue;
        }

        // Execute statement
        if (!empty(trim($statement))) {
            if ($conn->query($statement . ';')) {
                // Success - don't output every single query
            } else {
                if (strpos($conn->error, 'Duplicate') === false &&
                    strpos($conn->error, 'already exists') === false) {
                    $errors[] = "Query #$step failed: " . $conn->error;
                }
            }
            $step++;
        }
    }
}

// Verify installation
echo "<div class='section'>
        <h2>📊 Verification Results</h2>";

// Check if tables exist
$tables = ['departments', 'permissions', 'role_permissions'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<div class='success'>✓ Table '$table' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Table '$table' was not created</div>";
    }
}

// Check if columns were added to users table
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'department_id'");
if ($result->num_rows > 0) {
    echo "<div class='success'>✓ Column 'department_id' added to users table</div>";
} else {
    echo "<div class='error'>✗ Column 'department_id' was not added to users table</div>";
}

// Check permissions count
$result = $conn->query("SELECT COUNT(*) as count FROM permissions");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<div class='success'>✓ Created $count permissions</div>";
}

// Check roles count
$result = $conn->query("SELECT COUNT(*) as count FROM roles");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<div class='success'>✓ System has $count roles</div>";
}

// Check departments count
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<div class='success'>✓ Created $count departments</div>";
}

// Display permissions by module
echo "</div><div class='section'>
        <h2>🔑 Created Permissions</h2>";

$result = $conn->query("SELECT module, COUNT(*) as count FROM permissions GROUP BY module ORDER BY module");
if ($result) {
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f8f9fa; text-align:left;'><th style='padding:10px;'>Module</th><th style='padding:10px;'>Permissions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             ucfirst(str_replace('_', ' ', $row['module'])) .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             $row['count'] . " permissions</td></tr>";
    }
    echo "</table>";
}

// Display roles with permission counts
echo "</div><div class='section'>
        <h2>👥 Configured Roles</h2>";

$result = $conn->query("SELECT r.role_name, r.description, r.is_system_role, COUNT(rp.permission_id) as perm_count
                        FROM roles r
                        LEFT JOIN role_permissions rp ON r.id = rp.role_id
                        GROUP BY r.id
                        ORDER BY r.is_system_role DESC, r.role_name");
if ($result) {
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f8f9fa; text-align:left;'><th style='padding:10px;'>Role</th><th style='padding:10px;'>Description</th><th style='padding:10px;'>Type</th><th style='padding:10px;'>Permissions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $type = $row['is_system_role'] ? '<span style="background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:10px; font-size:12px;">System</span>' : '<span style="background:#f3e5f5; color:#7b1fa2; padding:4px 8px; border-radius:10px; font-size:12px;">Custom</span>';
        echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>" .
             ucfirst(str_replace('_', ' ', $row['role_name'])) .
             "</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             ($row['description'] ?? 'No description') .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             $type .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             $row['perm_count'] . " permissions</td></tr>";
    }
    echo "</table>";
}

// Display departments
echo "</div><div class='section'>
        <h2>🏢 Created Departments</h2>";

$result = $conn->query("SELECT department_name, department_code, description FROM departments ORDER BY department_name");
if ($result) {
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f8f9fa; text-align:left;'><th style='padding:10px;'>Code</th><th style='padding:10px;'>Department</th><th style='padding:10px;'>Description</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td style='padding:8px; border-bottom:1px solid #ddd;'><strong>" .
             htmlspecialchars($row['department_code']) .
             "</strong></td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['department_name']) .
             "</td><td style='padding:8px; border-bottom:1px solid #ddd;'>" .
             htmlspecialchars($row['description']) .
             "</td></tr>";
    }
    echo "</table>";
}

echo "</div>";

// Summary
if (empty($errors)) {
    echo "<div class='success'><strong>✅ Database seeding completed successfully!</strong><br>
          The role management system is now ready to use.</div>";
} else {
    echo "<div class='error'><strong>⚠️ Database seeding completed with warnings:</strong><br>";
    foreach ($errors as $error) {
        echo "• $error<br>";
    }
    echo "</div>";
}

echo "<div class='info'>
        <strong>Next Steps:</strong><br>
        1. Log in to the admin dashboard<br>
        2. Go to 'Manage Roles' to configure role permissions<br>
        3. Go to 'Manage Departments' to set up your organization<br>
        4. Assign departments and roles to users via 'Edit User'
      </div>";

echo "<a href='../admin/dashboard.php' class='btn'>Go to Admin Dashboard</a>
      <a href='../login.php' class='btn' style='background:#6c757d;'>Go to Login</a>";

echo "</div></body></html>";

closeDBConnection($conn);
?>
