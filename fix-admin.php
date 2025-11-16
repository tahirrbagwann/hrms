<?php
// Fix Admin Password
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Admin Account Fix</h2>";

$conn = getDBConnection();

// Check if admin exists
$check_query = "SELECT * FROM users WHERE email = 'admin@hrms.com'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>Admin user exists. Updating password...</p>";

    // Update admin password
    $new_password = password_hash('admin123', PASSWORD_DEFAULT);
    $update_query = "UPDATE users SET password = ? WHERE email = 'admin@hrms.com'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("s", $new_password);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Admin password updated successfully!</p>";
        echo "<p><strong>Email:</strong> admin@hrms.com</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to update password</p>";
    }
    $stmt->close();

} else {
    echo "<p style='color: orange;'>Admin user not found. Creating new admin...</p>";

    // Get admin role id
    $role_query = "SELECT id FROM roles WHERE role_name = 'admin'";
    $role_result = $conn->query($role_query);

    if ($role_result->num_rows > 0) {
        $role = $role_result->fetch_assoc();
        $role_id = $role['id'];

        // Create admin user
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO users (username, email, password, full_name, role_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);

        $username = 'admin';
        $email = 'admin@hrms.com';
        $full_name = 'System Administrator';

        $stmt->bind_param("ssssi", $username, $email, $password, $full_name, $role_id);

        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
            echo "<p><strong>Email:</strong> admin@hrms.com</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create admin user</p>";
        }
        $stmt->close();

    } else {
        echo "<p style='color: red;'>✗ Admin role not found in database</p>";
        echo "<p>Please run the database schema first.</p>";
    }
}

closeDBConnection($conn);

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>
