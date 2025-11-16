<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Database Connection</h2>";

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'hrms_db';

try {
    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
    }

    echo "<p style='color: green;'>✓ Connected successfully to database!</p>";

    // Check if tables exist
    $tables = ['roles', 'users', 'attendance'];
    echo "<h3>Checking Tables:</h3>";

    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' NOT found</p>";
        }
    }

    $conn->close();

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>
