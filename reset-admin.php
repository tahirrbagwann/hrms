<?php
// Reset Admin Account
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Reset Admin Account</h2>";

$conn = getDBConnection();

// Check current admin
$check = $conn->query("SELECT * FROM users WHERE email = 'admin@hrms.com'");
if ($check->num_rows > 0) {
    $current = $check->fetch_assoc();
    echo "<p>Current admin found:</p>";
    echo "<pre>ID: " . $current['id'] . "\nPassword Hash: " . substr($current['password'], 0, 30) . "...</pre>";
}

// Delete existing admin user
echo "<p>Deleting existing admin user...</p>";
$delete_query = "DELETE FROM users WHERE email = 'admin@hrms.com'";
if ($conn->query($delete_query)) {
    echo "<p style='color: green;'>✓ Existing admin deleted</p>";
} else {
    echo "<p style='color: red;'>Error deleting: " . $conn->error . "</p>";
}

// Get admin role id
$role_query = "SELECT id FROM roles WHERE role_name = 'admin'";
$role_result = $conn->query($role_query);

if ($role_result && $role_result->num_rows > 0) {
    $role = $role_result->fetch_assoc();
    $role_id = $role['id'];

    // Create new admin user with P@ssw0rd
    $password = password_hash('P@ssw0rd', PASSWORD_DEFAULT);
    $insert_query = "INSERT INTO users (username, email, password, full_name, role_id, status) VALUES (?, ?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($insert_query);

    $username = 'admin';
    $email = 'admin@hrms.com';
    $full_name = 'System Administrator';

    $stmt->bind_param("ssssi", $username, $email, $password, $full_name, $role_id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ New admin user created successfully!</p>";
        echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>New Admin Credentials:</h3>";
        echo "<p><strong>Email:</strong> admin@hrms.com</p>";
        echo "<p><strong>Password:</strong> P@ssw0rd</p>";
        echo "</div>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create admin user: " . $stmt->error . "</p>";
    }
    $stmt->close();

} else {
    echo "<p style='color: red;'>✗ Admin role not found in database</p>";
    echo "<p>Please run the database schema first.</p>";
}

closeDBConnection($conn);

echo "<hr>";
echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
?>
