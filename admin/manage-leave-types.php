<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle Create/Update Leave Type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create' || $action === 'update') {
            $leave_name = $_POST['leave_name'];
            $leave_code = strtoupper($_POST['leave_code']);
            $description = $_POST['description'];
            $is_paid = isset($_POST['is_paid']) ? 1 : 0;
            $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
            $max_consecutive_days = !empty($_POST['max_consecutive_days']) ? $_POST['max_consecutive_days'] : NULL;
            $can_carry_forward = isset($_POST['can_carry_forward']) ? 1 : 0;
            $carry_forward_limit = !empty($_POST['carry_forward_limit']) ? $_POST['carry_forward_limit'] : NULL;
            $color = $_POST['color'];
            $icon = $_POST['icon'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO leave_types (leave_name, leave_code, description, is_paid, requires_approval, max_consecutive_days, can_carry_forward, carry_forward_limit, color, icon, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiiiiissi", $leave_name, $leave_code, $description, $is_paid, $requires_approval, $max_consecutive_days, $can_carry_forward, $carry_forward_limit, $color, $icon, $is_active);

                if ($stmt->execute()) {
                    $success = "Leave type created successfully!";
                    logActivity($conn, 'leave.type_created', "Created leave type: $leave_name", 'leave_type', $stmt->insert_id, $leave_name);
                } else {
                    $error = "Error creating leave type: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE leave_types SET leave_name=?, leave_code=?, description=?, is_paid=?, requires_approval=?, max_consecutive_days=?, can_carry_forward=?, carry_forward_limit=?, color=?, icon=?, is_active=? WHERE id=?");
                $stmt->bind_param("sssiiiiissii", $leave_name, $leave_code, $description, $is_paid, $requires_approval, $max_consecutive_days, $can_carry_forward, $carry_forward_limit, $color, $icon, $is_active, $id);

                if ($stmt->execute()) {
                    $success = "Leave type updated successfully!";
                    logActivity($conn, 'leave.type_updated', "Updated leave type: $leave_name", 'leave_type', $id, $leave_name);
                } else {
                    $error = "Error updating leave type: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];

            // Check if leave type is used in any leave requests
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE leave_type_id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check = $check_result->fetch_assoc();
            $check_stmt->close();

            if ($check['count'] > 0) {
                $error = "Cannot delete this leave type as it has associated leave requests. Consider deactivating it instead.";
            } else {
                $stmt = $conn->prepare("DELETE FROM leave_types WHERE id = ?");
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $success = "Leave type deleted successfully!";
                    logActivity($conn, 'leave.type_deleted', "Deleted leave type ID: $id", 'leave_type', $id);
                } else {
                    $error = "Error deleting leave type: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get all leave types
$leave_types_query = "SELECT * FROM leave_types ORDER BY leave_name";
$leave_types_result = $conn->query($leave_types_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - HRMS</title>
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

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-logout, .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-logout:hover, .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: #333;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        td {
            color: #666;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-paid {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: opacity 0.2s;
        }

        .btn-small:hover {
            opacity: 0.8;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h3 {
            color: #333;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .color-icon-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 15px;
        }

        .leave-icon {
            font-size: 32px;
            text-align: center;
        }

        .leave-color-box {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Manage Leave Types</h1>
        <div class="navbar-user">
            <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Leave Types Configuration</h2>
            <button class="btn-primary" onclick="openCreateModal()">+ Add Leave Type</button>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Max Days</th>
                        <th>Carry Forward</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($leave_types_result->num_rows > 0): ?>
                        <?php while ($leave_type = $leave_types_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="leave-icon" style="color: <?php echo $leave_type['color']; ?>">
                                        <?php echo $leave_type['icon']; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($leave_type['leave_name']); ?></strong>
                                    <br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($leave_type['description']); ?></small>
                                </td>
                                <td><strong><?php echo $leave_type['leave_code']; ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $leave_type['is_paid'] ? 'paid' : 'unpaid'; ?>">
                                        <?php echo $leave_type['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                    </span>
                                </td>
                                <td><?php echo $leave_type['max_consecutive_days'] ?: 'No limit'; ?></td>
                                <td>
                                    <?php if ($leave_type['can_carry_forward']): ?>
                                        ✓ (Max: <?php echo $leave_type['carry_forward_limit'] ?: 'Unlimited'; ?>)
                                    <?php else: ?>
                                        ✗
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $leave_type['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $leave_type['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-edit" onclick='openEditModal(<?php echo json_encode($leave_type); ?>)'>Edit</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leave type?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $leave_type['id']; ?>">
                                            <button type="submit" class="btn-small btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                No leave types configured yet. Click "Add Leave Type" to create one.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="leaveTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Leave Type</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="leaveTypeForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="leaveTypeId">

                <div class="form-group">
                    <label for="leave_name">Leave Name *</label>
                    <input type="text" id="leave_name" name="leave_name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="leave_code">Leave Code *</label>
                        <input type="text" id="leave_code" name="leave_code" maxlength="20" required>
                    </div>
                    <div class="form-group">
                        <label for="max_consecutive_days">Max Consecutive Days</label>
                        <input type="number" id="max_consecutive_days" name="max_consecutive_days" min="1">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="color-icon-row">
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="color" id="color" name="color" value="#667eea" style="width: 100%; height: 50px; border: none; border-radius: 5px;">
                    </div>
                    <div class="form-group">
                        <label for="icon">Icon (Emoji)</label>
                        <input type="text" id="icon" name="icon" value="📅" maxlength="10">
                    </div>
                </div>

                <div class="form-row">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_paid" name="is_paid" checked>
                        <label for="is_paid">Paid Leave</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="requires_approval" name="requires_approval" checked>
                        <label for="requires_approval">Requires Approval</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="checkbox-group">
                        <input type="checkbox" id="can_carry_forward" name="can_carry_forward">
                        <label for="can_carry_forward">Can Carry Forward</label>
                    </div>
                    <div class="form-group">
                        <label for="carry_forward_limit">Carry Forward Limit</label>
                        <input type="number" id="carry_forward_limit" name="carry_forward_limit" min="0">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    <label for="is_active">Active</label>
                </div>

                <div style="margin-top: 25px; text-align: right;">
                    <button type="button" class="btn-small" onclick="closeModal()" style="background: #6c757d; color: white; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn-primary" style="margin-left: 10px;">Save Leave Type</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Leave Type';
            document.getElementById('formAction').value = 'create';
            document.getElementById('leaveTypeForm').reset();
            document.getElementById('is_paid').checked = true;
            document.getElementById('requires_approval').checked = true;
            document.getElementById('is_active').checked = true;
            document.getElementById('color').value = '#667eea';
            document.getElementById('icon').value = '📅';
            document.getElementById('leaveTypeModal').style.display = 'block';
        }

        function openEditModal(leaveType) {
            document.getElementById('modalTitle').textContent = 'Edit Leave Type';
            document.getElementById('formAction').value = 'update';
            document.getElementById('leaveTypeId').value = leaveType.id;
            document.getElementById('leave_name').value = leaveType.leave_name;
            document.getElementById('leave_code').value = leaveType.leave_code;
            document.getElementById('description').value = leaveType.description || '';
            document.getElementById('is_paid').checked = leaveType.is_paid == 1;
            document.getElementById('requires_approval').checked = leaveType.requires_approval == 1;
            document.getElementById('max_consecutive_days').value = leaveType.max_consecutive_days || '';
            document.getElementById('can_carry_forward').checked = leaveType.can_carry_forward == 1;
            document.getElementById('carry_forward_limit').value = leaveType.carry_forward_limit || '';
            document.getElementById('color').value = leaveType.color;
            document.getElementById('icon').value = leaveType.icon;
            document.getElementById('is_active').checked = leaveType.is_active == 1;
            document.getElementById('leaveTypeModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('leaveTypeModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('leaveTypeModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
