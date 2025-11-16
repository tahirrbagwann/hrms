<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$error = '';
$success = '';
$import_results = [];
$show_results = false;

// Get roles and departments for display
$roles_query = "SELECT * FROM roles WHERE status = 'active' ORDER BY role_name";
$roles_result = $conn->query($roles_query);

$departments_query = "SELECT * FROM departments WHERE status = 'active' ORDER BY department_name";
$departments_result = $conn->query($departments_query);

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error. Please try again.';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $error = 'File size exceeds 5MB limit.';
    } elseif (!in_array($file['type'], ['text/csv', 'text/plain', 'application/vnd.ms-excel'])) {
        $error = 'Invalid file type. Please upload a CSV file.';
    } else {
        // Process CSV file
        $handle = fopen($file['tmp_name'], 'r');

        if ($handle !== false) {
            $row_number = 0;
            $header = null;
            $success_count = 0;
            $error_count = 0;

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row_number++;

                // First row is header
                if ($row_number === 1) {
                    $header = $data;
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                // Map CSV columns (expected: full_name, username, email, password, role_name, department_name)
                $full_name = trim($data[0] ?? '');
                $username = trim($data[1] ?? '');
                $email = trim($data[2] ?? '');
                $password = trim($data[3] ?? '');
                $role_name = trim($data[4] ?? 'employee');
                $department_name = trim($data[5] ?? '');

                // Validate required fields
                if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'error',
                        'message' => "Missing required fields",
                        'data' => "$full_name, $username, $email"
                    ];
                    $error_count++;
                    continue;
                }

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'error',
                        'message' => "Invalid email format",
                        'data' => "$full_name, $email"
                    ];
                    $error_count++;
                    continue;
                }

                // Validate password length
                if (strlen($password) < 6) {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'error',
                        'message' => "Password must be at least 6 characters",
                        'data' => "$full_name, $username"
                    ];
                    $error_count++;
                    continue;
                }

                // Check if user already exists
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'warning',
                        'message' => "User already exists (skipped)",
                        'data' => "$full_name, $username, $email"
                    ];
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();

                // Get role ID
                $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ? AND status = 'active'");
                $role_stmt->bind_param("s", $role_name);
                $role_stmt->execute();
                $role_result = $role_stmt->get_result();
                $role = $role_result->fetch_assoc();
                $role_stmt->close();

                if (!$role) {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'error',
                        'message' => "Invalid role: $role_name",
                        'data' => "$full_name, $username"
                    ];
                    $error_count++;
                    continue;
                }
                $role_id = $role['id'];

                // Get department ID (optional)
                $department_id = null;
                if (!empty($department_name)) {
                    $dept_stmt = $conn->prepare("SELECT id FROM departments WHERE department_name = ? AND status = 'active'");
                    $dept_stmt->bind_param("s", $department_name);
                    $dept_stmt->execute();
                    $dept_result = $dept_stmt->get_result();
                    $dept = $dept_result->fetch_assoc();
                    $dept_stmt->close();

                    if ($dept) {
                        $department_id = $dept['id'];
                    }
                }

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $insert_stmt = $conn->prepare(
                    "INSERT INTO users (full_name, username, email, password, role_id, department_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'active')"
                );
                $insert_stmt->bind_param("sssiii", $full_name, $username, $email, $hashed_password, $role_id, $department_id);

                if ($insert_stmt->execute()) {
                    $new_user_id = $insert_stmt->insert_id;

                    // Log activity
                    logActivity(
                        $conn,
                        'user.bulk.imported',
                        "User imported via CSV: $full_name ($username)",
                        'user',
                        $new_user_id,
                        $full_name,
                        null,
                        ['username' => $username, 'email' => $email, 'role' => $role_name]
                    );

                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'success',
                        'message' => "User created successfully",
                        'data' => "$full_name, $username, $email"
                    ];
                    $success_count++;
                } else {
                    $import_results[] = [
                        'row' => $row_number,
                        'status' => 'error',
                        'message' => "Database error: " . $conn->error,
                        'data' => "$full_name, $username"
                    ];
                    $error_count++;
                }

                $insert_stmt->close();
            }

            fclose($handle);

            // Log bulk import activity
            logActivity(
                $conn,
                'user.bulk.import.completed',
                "Bulk user import completed: $success_count created, $error_count failed",
                null,
                null,
                null,
                null,
                ['success_count' => $success_count, 'error_count' => $error_count, 'total_rows' => $row_number - 1]
            );

            $success = "Import completed: $success_count users created successfully, $error_count failed.";
            $show_results = true;
        } else {
            $error = 'Failed to open CSV file.';
        }
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Users - HRMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar h1 {
            font-size: 24px;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
        }

        .info-box h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #1565c0;
            margin: 5px 0;
            font-size: 14px;
        }

        .info-box ul {
            margin: 10px 0 10px 20px;
            color: #1565c0;
        }

        .info-box li {
            margin: 5px 0;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success-message {
            background: #efe;
            color: #2a2;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .btn-download {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-download:hover {
            background: #218838;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #333;
        }

        .results-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        .status-success {
            color: #28a745;
            font-weight: 600;
        }

        .status-error {
            color: #dc3545;
            font-weight: 600;
        }

        .status-warning {
            color: #ffc107;
            font-weight: 600;
        }

        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Bulk Import Users</h1>
        <div>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>CSV File Format Instructions</h2>

            <div class="info-box">
                <h3>📋 CSV Template Format</h3>
                <p><strong>Required columns (in order):</strong></p>
                <ul>
                    <li><code>full_name</code> - Full name of the user (required)</li>
                    <li><code>username</code> - Username for login (required, must be unique)</li>
                    <li><code>email</code> - Email address (required, must be unique and valid)</li>
                    <li><code>password</code> - Password (required, minimum 6 characters)</li>
                    <li><code>role_name</code> - Role (optional, defaults to 'employee')</li>
                    <li><code>department_name</code> - Department (optional)</li>
                </ul>

                <p><strong>Available Roles:</strong>
                    <?php
                    $roles_result->data_seek(0);
                    $role_names = [];
                    while ($role = $roles_result->fetch_assoc()) {
                        $role_names[] = $role['role_name'];
                    }
                    echo implode(', ', $role_names);
                    ?>
                </p>

                <p><strong>Available Departments:</strong>
                    <?php
                    if ($departments_result && $departments_result->num_rows > 0):
                        $departments_result->data_seek(0);
                        $dept_names = [];
                        while ($dept = $departments_result->fetch_assoc()) {
                            $dept_names[] = $dept['department_name'];
                        }
                        echo implode(', ', $dept_names);
                    else:
                        echo 'None (create departments first)';
                    endif;
                    ?>
                </p>

                <p><strong>Example CSV content:</strong></p>
                <code style="display: block; padding: 10px; background: white;">
full_name,username,email,password,role_name,department_name<br>
John Doe,johndoe,john@example.com,password123,employee,Engineering<br>
Jane Smith,janesmith,jane@example.com,password456,manager,HR
                </code>

                <p style="margin-top: 15px;">
                    <a href="download-csv-template.php" class="btn-download">📥 Download CSV Template</a>
                </p>
            </div>
        </div>

        <div class="card">
            <h2>Upload CSV File</h2>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file">Select CSV File (Max 5MB)</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>

                <button type="submit" class="btn-submit">Upload and Import Users</button>
            </form>
        </div>

        <?php if ($show_results && !empty($import_results)): ?>
            <div class="card">
                <h2>Import Results</h2>

                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_results as $result): ?>
                            <tr>
                                <td><?php echo $result['row']; ?></td>
                                <td class="status-<?php echo $result['status']; ?>">
                                    <?php echo ucfirst($result['status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($result['message']); ?></td>
                                <td><?php echo htmlspecialchars($result['data']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
