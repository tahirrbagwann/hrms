<?php
/**
 * CSV Template Download for Bulk User Import
 * Generates a CSV template file with sample data
 */

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=user_import_template.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['full_name', 'username', 'email', 'password', 'role_name', 'department_name']);

// Write sample data rows
fputcsv($output, ['John Doe', 'johndoe', 'john.doe@example.com', 'password123', 'employee', 'Engineering']);
fputcsv($output, ['Jane Smith', 'janesmith', 'jane.smith@example.com', 'password456', 'manager', 'HR']);
fputcsv($output, ['Bob Wilson', 'bobwilson', 'bob.wilson@example.com', 'password789', 'team_lead', 'Sales']);

fclose($output);
exit();
?>
