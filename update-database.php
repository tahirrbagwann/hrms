<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Database Update for Profile Management</h2>";

$conn = getDBConnection();

// Read and execute the SQL file
$sql_file = file_get_contents('database/update_profile_schema.sql');

// Remove comments and split by semicolon
$sql_statements = array_filter(
    array_map('trim',
        explode(';', preg_replace('/--.*$/m', '', $sql_file))
    )
);

$success_count = 0;
$error_count = 0;

echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

foreach ($sql_statements as $statement) {
    if (empty($statement) || $statement === 'USE hrms_db') {
        continue;
    }

    if ($conn->query($statement)) {
        $success_count++;
        // Extract table/action info
        if (preg_match('/ALTER TABLE (\w+)|CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
            $table = $matches[1] ?? $matches[2] ?? 'unknown';
            echo "<p style='color: green;'>✓ Updated: $table</p>";
        } else {
            echo "<p style='color: green;'>✓ Statement executed</p>";
        }
    } else {
        $error_count++;
        echo "<p style='color: orange;'>⚠ " . $conn->error . "</p>";
    }
}

echo "</div>";

echo "<h3 style='margin-top: 20px;'>Summary:</h3>";
echo "<p><strong>Successful operations:</strong> $success_count</p>";
echo "<p><strong>Errors/Warnings:</strong> $error_count</p>";

if ($error_count === 0 || strpos($conn->error, 'Duplicate column') !== false) {
    echo "<div style='background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3>✓ Database Updated Successfully!</h3>";
    echo "<p>New features added:</p>";
    echo "<ul>";
    echo "<li>Phone and address fields</li>";
    echo "<li>Emergency contact information</li>";
    echo "<li>Profile picture support</li>";
    echo "<li>Email verification system</li>";
    echo "<li>Two-factor authentication (2FA)</li>";
    echo "<li>Activity logging</li>";
    echo "</ul>";
    echo "</div>";
}

closeDBConnection($conn);

echo "<hr>";
echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
?>
