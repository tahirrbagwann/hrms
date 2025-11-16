<?php
// Check Database Status
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Database Status Check</h2>";

$conn = getDBConnection();

// Check roles table
echo "<h3>Roles Table:</h3>";
$roles_query = "SELECT * FROM roles";
$roles_result = $conn->query($roles_query);

if ($roles_result && $roles_result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Role Name</th><th>Created At</th></tr>";
    while ($role = $roles_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $role['id'] . "</td>";
        echo "<td>" . $role['role_name'] . "</td>";
        echo "<td>" . $role['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No roles found. Please import database schema.</p>";
}

// Check users table
echo "<h3>Users Table:</h3>";
$users_query = "SELECT u.id, u.username, u.email, u.full_name, r.role_name, u.status FROM users u JOIN roles r ON u.role_id = r.id";
$users_result = $conn->query($users_query);

if ($users_result && $users_result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
    while ($user = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['full_name'] . "</td>";
        echo "<td>" . $user['role_name'] . "</td>";
        echo "<td>" . $user['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No users found.</p>";
}

// Check attendance table
echo "<h3>Attendance Table:</h3>";
$attendance_query = "SELECT COUNT(*) as count FROM attendance";
$attendance_result = $conn->query($attendance_query);
$attendance_count = $attendance_result->fetch_assoc()['count'];
echo "<p>Total attendance records: <strong>$attendance_count</strong></p>";

closeDBConnection($conn);

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "<p><a href='fix-admin.php'>Fix Admin Password</a></p>";
?>
