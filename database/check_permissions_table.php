<?php
require_once '../config/database.php';

$conn = getDBConnection();

echo "Checking permissions table structure...\n\n";

$result = $conn->query("DESCRIBE permissions");

if ($result) {
    echo "Columns in permissions table:\n";
    echo "----------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n\nSample permissions data:\n";
echo "------------------------\n";
$result = $conn->query("SELECT * FROM permissions LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No permissions found or error: " . $conn->error . "\n";
}

closeDBConnection($conn);
?>
