<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Get role ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-roles.php');
    exit();
}

$role_id = intval($_GET['id']);

// Get role data
$stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->bind_param("i", $role_id);
$stmt->execute();
$result = $stmt->get_result();
$role = $result->fetch_assoc();
$stmt->close();

if (!$role) {
    header('Location: manage-roles.php');
    exit();
}

// Get current role permissions
$perm_stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$perm_stmt->bind_param("i", $role_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result();

$current_permissions = [];
while ($perm_row = $perm_result->fetch_assoc()) {
    $current_permissions[] = $perm_row['permission_id'];
}
$perm_stmt->close();

// Get all permissions grouped by module
$permissions_query = "SELECT * FROM permissions ORDER BY module, permission_name";
$permissions_result = $conn->query($permissions_query);

$permissions_by_module = [];
while ($perm = $permissions_result->fetch_assoc()) {
    $permissions_by_module[$perm['module']][] = $perm;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = sanitizeInput($_POST['role_name']);
    $description = sanitizeInput($_POST['description']);
    $status = sanitizeInput($_POST['status']);
    $selected_permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    // Validate role name
    if (empty($role_name)) {
        $error = "Role name is required.";
    } else {
        // Check if role name already exists for other roles
        $check_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ? AND id != ?");
        $check_stmt->bind_param("si", $role_name, $role_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Role name already exists.";
        } else {
            // Update role
            $stmt = $conn->prepare("UPDATE roles SET role_name = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssi", $role_name, $description, $status, $role_id);

            if ($stmt->execute()) {
                // Delete existing permissions
                $delete_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $delete_stmt->bind_param("i", $role_id);
                $delete_stmt->execute();
                $delete_stmt->close();

                // Insert new permissions
                if (!empty($selected_permissions)) {
                    $perm_stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

                    foreach ($selected_permissions as $permission_id) {
                        $perm_id = intval($permission_id);
                        $perm_stmt->bind_param("ii", $role_id, $perm_id);
                        $perm_stmt->execute();
                    }

                    $perm_stmt->close();
                }

                // Clear permissions cache for all users with this role
                // (In a production system, you'd want to clear cache for specific users)
                if (session_status() === PHP_SESSION_ACTIVE) {
                    clearPermissionsCache();
                }

                $success = "Role updated successfully!";

                // Refresh current permissions
                $perm_stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
                $perm_stmt->bind_param("i", $role_id);
                $perm_stmt->execute();
                $perm_result = $perm_stmt->get_result();

                $current_permissions = [];
                while ($perm_row = $perm_result->fetch_assoc()) {
                    $current_permissions[] = $perm_row['permission_id'];
                }
                $perm_stmt->close();

                // Refresh role data
                $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
                $stmt->bind_param("i", $role_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $role = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Failed to update role. Please try again.";
            }

            $stmt->close();
        }
        $check_stmt->close();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role - HRMS</title>
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
            max-width: 900px;
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
            transition: border-color 0.3s;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
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

        .permissions-section {
            margin-top: 30px;
        }

        .permission-module {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .permission-module h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }

        .permission-item {
            display: flex;
            align-items: flex-start;
        }

        .permission-item input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            margin-top: 3px;
        }

        .permission-item label {
            cursor: pointer;
            font-weight: normal;
            color: #555;
            line-height: 1.4;
        }

        .permission-item small {
            display: block;
            color: #888;
            font-size: 12px;
            margin-top: 2px;
        }

        .select-all-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            margin-bottom: 10px;
        }

        .select-all-btn:hover {
            background: #5568d3;
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

        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .warning-box p {
            color: #856404;
            margin: 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Edit Role: <?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?></h1>
        <div class="navbar-user">
            <a href="manage-roles.php" class="btn-back">Back to Roles</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($role['is_system_role']): ?>
            <div class="warning-box">
                <p><strong>System Role</strong> - This is a system role. You can modify permissions but the role name cannot be changed.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Role Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="role_name">Role Name *
                        <?php if ($role['is_system_role']): ?>
                            <span class="badge badge-system">System Role</span>
                        <?php endif; ?>
                    </label>
                    <input type="text" id="role_name" name="role_name"
                           value="<?php echo htmlspecialchars($role['role_name']); ?>"
                           <?php echo $role['is_system_role'] ? 'disabled' : 'required'; ?>>
                    <?php if ($role['is_system_role']): ?>
                        <input type="hidden" name="role_name" value="<?php echo htmlspecialchars($role['role_name']); ?>">
                    <?php endif; ?>
                    <small style="color: #888; font-size: 12px;">Use lowercase and underscores (e.g., team_lead)</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Brief description of this role's responsibilities"><?php echo htmlspecialchars($role['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo ($role['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($role['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="permissions-section">
                    <h2>Assign Permissions</h2>
                    <p style="color: #666; margin-bottom: 20px;">Select the permissions this role should have:</p>

                    <?php foreach ($permissions_by_module as $module => $permissions): ?>
                        <div class="permission-module">
                            <h3><?php echo ucfirst(str_replace('_', ' ', $module)); ?></h3>
                            <button type="button" class="select-all-btn"
                                    onclick="toggleModulePermissions('<?php echo $module; ?>')">
                                Select All
                            </button>
                            <div class="permission-grid">
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="permission-item">
                                        <input type="checkbox"
                                               id="perm_<?php echo $permission['id']; ?>"
                                               name="permissions[]"
                                               value="<?php echo $permission['id']; ?>"
                                               class="module-<?php echo $module; ?>"
                                               <?php echo in_array($permission['id'], $current_permissions) ? 'checked' : ''; ?>>
                                        <label for="perm_<?php echo $permission['id']; ?>">
                                            <?php echo htmlspecialchars($permission['permission_name']); ?>
                                            <?php if ($permission['description']): ?>
                                                <small><?php echo htmlspecialchars($permission['description']); ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-submit">Update Role</button>
            </form>
        </div>
    </div>

    <script>
        function toggleModulePermissions(module) {
            const checkboxes = document.querySelectorAll('.module-' + module);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
        }
    </script>
</body>
</html>
