<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle role deletion
if (isset($_GET['delete_id'])) {
    $role_id = intval($_GET['delete_id']);

    // Check if it's a system role
    $check_stmt = $conn->prepare("SELECT is_system_role, role_name FROM roles WHERE id = ?");
    $check_stmt->bind_param("i", $role_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $role = $result->fetch_assoc();
    $check_stmt->close();

    if ($role && $role['is_system_role'] == 1) {
        $error = "Cannot delete system role '" . $role['role_name'] . "'.";
    } else {
        // Check if any users have this role
        $user_check = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE role_id = ?");
        $user_check->bind_param("i", $role_id);
        $user_check->execute();
        $user_result = $user_check->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_check->close();

        if ($user_data['user_count'] > 0) {
            $error = "Cannot delete role. " . $user_data['user_count'] . " user(s) are assigned to this role.";
        } else {
            // Delete role (cascade will delete role_permissions)
            $delete_stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $delete_stmt->bind_param("i", $role_id);

            if ($delete_stmt->execute()) {
                $success = "Role deleted successfully!";
            } else {
                $error = "Failed to delete role.";
            }
            $delete_stmt->close();
        }
    }
}

// Get all roles with permission counts
$roles_query = "SELECT r.*,
                COUNT(DISTINCT rp.permission_id) as permission_count,
                COUNT(DISTINCT u.id) as user_count
                FROM roles r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN users u ON r.id = u.role_id
                GROUP BY r.id
                ORDER BY r.is_system_role DESC, r.role_name ASC";
$roles_result = $conn->query($roles_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles - HRMS</title>
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

        .navbar-actions {
            display: flex;
            gap: 10px;
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
        }

        .btn-back:hover, .btn-new:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
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

        .badge-system {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-custom {
            background: #f3e5f5;
            color: #7b1fa2;
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
            transition: background 0.3s;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-edit {
            background: #667eea;
            color: white;
        }

        .btn-edit:hover {
            background: #5568d3;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-delete.disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
        }

        .info-box p {
            color: #1565c0;
            margin: 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Manage Roles</h1>
        <div class="navbar-actions">
            <a href="create-role.php" class="btn-new">+ Create New Role</a>
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

        <div class="info-box">
            <p><strong>Role Management System</strong></p>
            <p>Create custom roles and assign specific permissions. System roles (Admin, Employee) cannot be deleted.</p>
        </div>

        <div class="card">
            <h2>All Roles</h2>

            <table>
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></td>
                            <td>
                                <?php if ($role['is_system_role']): ?>
                                    <span class="badge badge-system">System</span>
                                <?php else: ?>
                                    <span class="badge badge-custom">Custom</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $role['status']; ?>">
                                    <?php echo ucfirst($role['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $role['permission_count']; ?> permissions</td>
                            <td><?php echo $role['user_count']; ?> users</td>
                            <td>
                                <a href="edit-role.php?id=<?php echo $role['id']; ?>" class="btn-edit">Edit</a>
                                <?php if ($role['is_system_role'] == 0 && $role['user_count'] == 0): ?>
                                    <a href="?delete_id=<?php echo $role['id']; ?>"
                                       class="btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this role?')">Delete</a>
                                <?php else: ?>
                                    <span class="btn-delete disabled" title="Cannot delete system roles or roles with users">Delete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
