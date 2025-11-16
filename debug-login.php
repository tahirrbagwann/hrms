<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

echo "<h2>Login Debug</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    echo "<h3>Input Data:</h3>";
    echo "<p>Email: " . htmlspecialchars($email) . "</p>";
    echo "<p>Password: " . htmlspecialchars($password) . "</p>";

    $conn = getDBConnection();

    // Check if user exists
    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<h3>Query Results:</h3>";
    echo "<p>Rows found: " . $result->num_rows . "</p>";

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        echo "<h3>User Data:</h3>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Full Name: " . $user['full_name'] . "\n";
        echo "Role: " . $user['role_name'] . "\n";
        echo "Status: " . $user['status'] . "\n";
        echo "Password Hash: " . substr($user['password'], 0, 20) . "...\n";
        echo "</pre>";

        // Test password verification
        echo "<h3>Password Verification:</h3>";
        $verify_result = password_verify($password, $user['password']);
        echo "<p>Password match: " . ($verify_result ? '<strong style="color: green;">YES</strong>' : '<strong style="color: red;">NO</strong>') . "</p>";

        if ($verify_result) {
            echo "<h3 style='color: green;'>✓ Login should work!</h3>";
            echo "<p>Setting session variables...</p>";

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role_name'];

            echo "<p>Session set. You can now try the regular login page.</p>";

            if ($user['role_name'] === 'admin') {
                echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
            } else {
                echo "<p><a href='employee/dashboard.php'>Go to Employee Dashboard</a></p>";
            }
        } else {
            echo "<h3 style='color: red;'>✗ Password does not match</h3>";
            echo "<p>The password you entered doesn't match the hash in database.</p>";

            // Generate new hash for comparison
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "<h4>Hash Comparison:</h4>";
            echo "<p>Database hash: <code>" . $user['password'] . "</code></p>";
            echo "<p>If we hash your password: <code>" . $new_hash . "</code></p>";
        }
    } else {
        echo "<h3 style='color: red;'>✗ User not found</h3>";
        echo "<p>No user found with email: " . htmlspecialchars($email) . "</p>";

        // List all users
        echo "<h4>All Users in Database:</h4>";
        $all_users = $conn->query("SELECT email, username, status FROM users");
        if ($all_users->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Email</th><th>Username</th><th>Status</th></tr>";
            while ($u = $all_users->fetch_assoc()) {
                echo "<tr><td>" . $u['email'] . "</td><td>" . $u['username'] . "</td><td>" . $u['status'] . "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users in database!</p>";
        }
    }

    $stmt->close();
    closeDBConnection($conn);

} else {
    // Show form
    ?>
    <form method="POST" action="">
        <div style="margin-bottom: 15px;">
            <label>Email:</label><br>
            <input type="email" name="email" value="admin@hrms.com" style="padding: 8px; width: 300px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label>Password:</label><br>
            <input type="text" name="password" value="P@ssw0rd" style="padding: 8px; width: 300px;">
        </div>
        <button type="submit" style="padding: 10px 20px; background: #667eea; color: white; border: none; cursor: pointer;">Test Login</button>
    </form>
    <?php
}

echo "<hr>";
echo "<p><a href='login.php'>Back to Login Page</a></p>";
echo "<p><a href='reset-admin.php'>Reset Admin Password</a></p>";
?>
