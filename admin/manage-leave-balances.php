<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle Initialize Balances for All Users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initialize_all'])) {
    $year = $_POST['year'];

    // Get all active users
    $users_query = "SELECT id FROM users WHERE status = 'active'";
    $users_result = $conn->query($users_query);

    // Get all active leave policies
    $policies_query = "SELECT * FROM leave_policies WHERE is_active = 1";
    $policies_result = $conn->query($policies_query);
    $policies = [];
    while ($policy = $policies_result->fetch_assoc()) {
        $policies[] = $policy;
    }

    $initialized_count = 0;
    $skipped_count = 0;

    while ($user = $users_result->fetch_assoc()) {
        $user_id = $user['id'];

        // Get user's role and department
        $user_info_stmt = $conn->prepare("SELECT role_id, department_id FROM users WHERE id = ?");
        $user_info_stmt->bind_param("i", $user_id);
        $user_info_stmt->execute();
        $user_info_result = $user_info_stmt->get_result();
        $user_info = $user_info_result->fetch_assoc();
        $user_info_stmt->close();

        foreach ($policies as $policy) {
            // Check if policy applies to this user
            $applies = true;
            if ($policy['role_id'] && $policy['role_id'] != $user_info['role_id']) {
                $applies = false;
            }
            if ($policy['department_id'] && $policy['department_id'] != $user_info['department_id']) {
                $applies = false;
            }

            if ($applies) {
                // Check if balance already exists
                $check_stmt = $conn->prepare("SELECT id FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = ?");
                $check_stmt->bind_param("iii", $user_id, $policy['leave_type_id'], $year);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->num_rows > 0;
                $check_stmt->close();

                if (!$exists) {
                    // Create balance
                    $insert_stmt = $conn->prepare("INSERT INTO leave_balances (user_id, leave_type_id, year, total_days) VALUES (?, ?, ?, ?)");
                    $insert_stmt->bind_param("iiid", $user_id, $policy['leave_type_id'], $year, $policy['days_per_year']);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    $initialized_count++;
                } else {
                    $skipped_count++;
                }
            }
        }
    }

    $success = "Initialized $initialized_count leave balances for year $year. Skipped $skipped_count existing balances.";
    logActivity($conn, 'leave.balances_initialized', "Initialized leave balances for $initialized_count users for year $year", 'leave_balance');
}

// Handle Update Individual Balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance'])) {
    $balance_id = $_POST['balance_id'];
    $total_days = $_POST['total_days'];

    $update_stmt = $conn->prepare("UPDATE leave_balances SET total_days = ? WHERE id = ?");
    $update_stmt->bind_param("di", $total_days, $balance_id);

    if ($update_stmt->execute()) {
        $success = "Leave balance updated successfully!";
        logActivity($conn, 'leave.balance_updated', "Updated leave balance ID: $balance_id", 'leave_balance', $balance_id);
    } else {
        $error = "Error updating balance: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Get filter parameters
$selected_year = $_GET['year'] ?? date('Y');
$selected_user = $_GET['user'] ?? '';
$selected_leave_type = $_GET['leave_type'] ?? '';

// Build query for balances
$balances_query = "SELECT lb.*, u.full_name, u.email, u.employee_id, lt.leave_name, lt.leave_code, lt.color, lt.icon, d.department_name
                   FROM leave_balances lb
                   JOIN users u ON lb.user_id = u.id
                   JOIN leave_types lt ON lb.leave_type_id = lt.id
                   LEFT JOIN departments d ON u.department_id = d.id
                   WHERE lb.year = ?";

$params = [$selected_year];
$types = "i";

if ($selected_user) {
    $balances_query .= " AND lb.user_id = ?";
    $params[] = $selected_user;
    $types .= "i";
}

if ($selected_leave_type) {
    $balances_query .= " AND lb.leave_type_id = ?";
    $params[] = $selected_leave_type;
    $types .= "i";
}

$balances_query .= " ORDER BY u.full_name, lt.leave_name";

$balances_stmt = $conn->prepare($balances_query);
$balances_stmt->bind_param($types, ...$params);
$balances_stmt->execute();
$balances_result = $balances_stmt->get_result();
$balances_stmt->close();

// Get all users for filter
$users_query = "SELECT id, full_name, employee_id FROM users WHERE status = 'active' ORDER BY full_name";
$users_result = $conn->query($users_query);

// Get all leave types for filter
$leave_types_query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name";
$leave_types_result = $conn->query($leave_types_query);

// Get statistics
$stats_query = "SELECT
    COUNT(DISTINCT user_id) as total_users,
    SUM(total_days) as total_allocated,
    SUM(used_days) as total_used,
    SUM(pending_days) as total_pending,
    SUM(available_days) as total_available
    FROM leave_balances WHERE year = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $selected_year);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Balances - HRMS</title>
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
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .header-row {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .card {
            background: white;
            padding: 25px;
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

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .filter-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            align-self: end;
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
            font-size: 13px;
        }

        td {
            color: #666;
            font-size: 13px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }

        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            background: #007bff;
            color: white;
        }

        .btn-small:hover {
            opacity: 0.8;
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
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .alert-info {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            color: #0c5460;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Manage Leave Balances</h1>
        <div class="navbar-user">
            <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-row">
                <h2>Leave Balance Management</h2>
                <button class="btn-primary" onclick="openInitializeModal()">Initialize Balances</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Allocated</h3>
                <div class="stat-value"><?php echo number_format($stats['total_allocated'] ?? 0, 1); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Used</h3>
                <div class="stat-value" style="color: #dc3545;"><?php echo number_format($stats['total_used'] ?? 0, 1); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Available</h3>
                <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['total_available'] ?? 0, 1); ?></div>
            </div>
        </div>

        <div class="card">
            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Employee</label>
                    <select name="user">
                        <option value="">All Employees</option>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $selected_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['employee_id']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Leave Type</label>
                    <select name="leave_type">
                        <option value="">All Types</option>
                        <?php while ($leave_type = $leave_types_result->fetch_assoc()): ?>
                            <option value="<?php echo $leave_type['id']; ?>" <?php echo $selected_leave_type == $leave_type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($leave_type['leave_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">Apply Filters</button>
            </form>

            <!-- Balances Table -->
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Total</th>
                        <th>Used</th>
                        <th>Pending</th>
                        <th>Available</th>
                        <th>Utilization</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($balances_result->num_rows > 0): ?>
                        <?php while ($balance = $balances_result->fetch_assoc()): ?>
                            <?php
                            $utilization = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($balance['full_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo $balance['employee_id']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($balance['department_name'] ?: '-'); ?></td>
                                <td>
                                    <span style="font-size: 18px;"><?php echo $balance['icon']; ?></span>
                                    <?php echo htmlspecialchars($balance['leave_name']); ?>
                                </td>
                                <td><strong><?php echo number_format($balance['total_days'], 1); ?></strong></td>
                                <td><?php echo number_format($balance['used_days'], 1); ?></td>
                                <td><?php echo number_format($balance['pending_days'], 1); ?></td>
                                <td><strong style="color: #28a745;"><?php echo number_format($balance['available_days'], 1); ?></strong></td>
                                <td>
                                    <?php echo number_format($utilization, 1); ?>%
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min($utilization, 100); ?>%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn-small" onclick='editBalance(<?php echo json_encode($balance); ?>)'>Edit</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                                No leave balances found. Click "Initialize Balances" to set up balances for employees.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Initialize Modal -->
    <div id="initializeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Initialize Leave Balances</h3>
                <span class="close" onclick="closeInitializeModal()">&times;</span>
            </div>
            <div class="alert-info">
                This will create leave balances for all active employees based on configured leave policies.
                Existing balances will be skipped.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="init_year">Year *</label>
                    <select id="init_year" name="year" required>
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-small" onclick="closeInitializeModal()" style="background: #6c757d; padding: 10px 20px;">Cancel</button>
                    <button type="submit" name="initialize_all" class="btn-primary" style="margin-left: 10px;">Initialize Balances</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Balance Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Leave Balance</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="balance_id" id="edit_balance_id">
                <div class="form-group">
                    <label>Employee</label>
                    <input type="text" id="edit_employee" disabled>
                </div>
                <div class="form-group">
                    <label>Leave Type</label>
                    <input type="text" id="edit_leave_type" disabled>
                </div>
                <div class="form-group">
                    <label for="edit_total_days">Total Days *</label>
                    <input type="number" id="edit_total_days" name="total_days" step="0.5" min="0" required>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-small" onclick="closeEditModal()" style="background: #6c757d; padding: 10px 20px;">Cancel</button>
                    <button type="submit" name="update_balance" class="btn-primary" style="margin-left: 10px;">Update Balance</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openInitializeModal() {
            document.getElementById('initializeModal').style.display = 'block';
        }

        function closeInitializeModal() {
            document.getElementById('initializeModal').style.display = 'none';
        }

        function editBalance(balance) {
            document.getElementById('edit_balance_id').value = balance.id;
            document.getElementById('edit_employee').value = balance.full_name;
            document.getElementById('edit_leave_type').value = balance.leave_name;
            document.getElementById('edit_total_days').value = balance.total_days;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('initializeModal')) {
                closeInitializeModal();
            }
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
