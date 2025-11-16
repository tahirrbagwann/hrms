<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle department creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $department_name = sanitizeInput($_POST['department_name']);
    $department_code = sanitizeInput($_POST['department_code']);
    $description = sanitizeInput($_POST['description']);
    $head_user_id = !empty($_POST['head_user_id']) ? intval($_POST['head_user_id']) : null;
    $status = sanitizeInput($_POST['status']);

    if (empty($department_name)) {
        $error = "Department name is required.";
    } else {
        // Check if department already exists
        $check_stmt = $conn->prepare("SELECT id FROM departments WHERE department_name = ? OR department_code = ?");
        $check_stmt->bind_param("ss", $department_name, $department_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Department name or code already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO departments (department_name, department_code, description, head_user_id, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $department_name, $department_code, $description, $head_user_id, $status);

            if ($stmt->execute()) {
                $success = "Department created successfully!";
            } else {
                $error = "Failed to create department.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle department update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $department_id = intval($_POST['department_id']);
    $department_name = sanitizeInput($_POST['department_name']);
    $department_code = sanitizeInput($_POST['department_code']);
    $description = sanitizeInput($_POST['description']);
    $head_user_id = !empty($_POST['head_user_id']) ? intval($_POST['head_user_id']) : null;
    $status = sanitizeInput($_POST['status']);

    // Check if name/code exists for other departments
    $check_stmt = $conn->prepare("SELECT id FROM departments WHERE (department_name = ? OR department_code = ?) AND id != ?");
    $check_stmt->bind_param("ssi", $department_name, $department_code, $department_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "Department name or code already exists.";
    } else {
        $stmt = $conn->prepare("UPDATE departments SET department_name = ?, department_code = ?, description = ?, head_user_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $department_name, $department_code, $description, $head_user_id, $status, $department_id);

        if ($stmt->execute()) {
            $success = "Department updated successfully!";
        } else {
            $error = "Failed to update department.";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Handle department deletion
if (isset($_GET['delete_id'])) {
    $department_id = intval($_GET['delete_id']);

    // Check if any users are in this department
    $user_check = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE department_id = ?");
    $user_check->bind_param("i", $department_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_check->close();

    if ($user_data['user_count'] > 0) {
        $error = "Cannot delete department. " . $user_data['user_count'] . " user(s) are assigned to this department.";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $delete_stmt->bind_param("i", $department_id);

        if ($delete_stmt->execute()) {
            $success = "Department deleted successfully!";
        } else {
            $error = "Failed to delete department.";
        }
        $delete_stmt->close();
    }
}

// Get all departments with user counts
$departments_query = "SELECT d.*,
                     u.full_name as head_name,
                     COUNT(DISTINCT du.id) as user_count
                     FROM departments d
                     LEFT JOIN users u ON d.head_user_id = u.id
                     LEFT JOIN users du ON d.id = du.department_id
                     GROUP BY d.id
                     ORDER BY d.department_name ASC";
$departments_result = $conn->query($departments_query);

// Get all users for department head dropdown
$users_query = "SELECT id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name";
$users_result = $conn->query($users_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - HRMS</title>
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

        .btn-back, .btn-new {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
            cursor: pointer;
        }

        .btn-back:hover, .btn-new:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            font-weight: 600;
            color: #333;
        }

        td {
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-inactive {
            background: #ffebee;
            color: #c62828;
        }

        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            margin-right: 5px;
            cursor: pointer;
            border: none;
        }

        .btn-edit {
            background: #667eea;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Manage Departments</h1>
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

        <div class="grid-container">
            <!-- Create Department Form -->
            <div class="card">
                <h2>Add New Department</h2>
                <form method="POST" id="createForm">
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label for="department_name">Department Name *</label>
                        <input type="text" id="department_name" name="department_name" required>
                    </div>

                    <div class="form-group">
                        <label for="department_code">Department Code *</label>
                        <input type="text" id="department_code" name="department_code" required
                               placeholder="e.g., ENG, HR, SALES">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="head_user_id">Department Head</label>
                        <select id="head_user_id" name="head_user_id">
                            <option value="">-- No Head --</option>
                            <?php
                            $users_result->data_seek(0);
                            while ($user = $users_result->fetch_assoc()):
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Create Department</button>
                </form>
            </div>

            <!-- Departments List -->
            <div class="card">
                <h2>All Departments</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Head</th>
                            <th>Employees</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($dept = $departments_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['department_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                <td><?php echo $dept['head_name'] ? htmlspecialchars($dept['head_name']) : '-'; ?></td>
                                <td><?php echo $dept['user_count']; ?> users</td>
                                <td>
                                    <span class="badge badge-<?php echo $dept['status']; ?>">
                                        <?php echo ucfirst($dept['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="editDepartment(<?php echo $dept['id']; ?>)" class="btn-edit">Edit</button>
                                    <?php if ($dept['user_count'] == 0): ?>
                                        <a href="?delete_id=<?php echo $dept['id']; ?>"
                                           class="btn-delete"
                                           onclick="return confirm('Delete this department?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Edit functionality would require AJAX or a separate edit form
        // For simplicity, we'll use the create form for both create and edit
        function editDepartment(id) {
            // In a real implementation, you'd fetch department data via AJAX
            alert('Edit functionality: Use create form and modify to include update action');
        }
    </script>
</body>
</html>
