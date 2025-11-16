<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle Approve/Reject Leave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $request_id = $_POST['request_id'];
        $comments = $_POST['comments'] ?? '';

        // Get request details
        $request_stmt = $conn->prepare("SELECT lr.*, u.full_name, u.email, lt.leave_name FROM leave_requests lr JOIN users u ON lr.user_id = u.id JOIN leave_types lt ON lr.leave_type_id = lt.id WHERE lr.id = ?");
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_result = $request_stmt->get_result();
        $request = $request_result->fetch_assoc();
        $request_stmt->close();

        if ($request && $request['status'] == 'pending') {
            if ($action === 'approve') {
                // Update request status
                $update_stmt = $conn->prepare("UPDATE leave_requests SET status = 'approved', approver_id = ?, approver_comments = ?, approved_at = NOW() WHERE id = ?");
                $update_stmt->bind_param("isi", $_SESSION['user_id'], $comments, $request_id);

                if ($update_stmt->execute()) {
                    // Update leave balance
                    $year = date('Y', strtotime($request['start_date']));
                    $update_balance_stmt = $conn->prepare("UPDATE leave_balances SET used_days = used_days + ?, pending_days = pending_days - ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
                    $update_balance_stmt->bind_param("ddiii", $request['total_days'], $request['total_days'], $request['user_id'], $request['leave_type_id'], $year);
                    $update_balance_stmt->execute();
                    $update_balance_stmt->close();

                    $success = "Leave request approved successfully!";
                    logActivity($conn, 'leave.request_approved', "Approved leave request #$request_id for {$request['full_name']}", 'leave_request', $request_id, $request['full_name']);
                } else {
                    $error = "Error approving request: " . $update_stmt->error;
                }
                $update_stmt->close();
            } elseif ($action === 'reject') {
                // Update request status
                $update_stmt = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approver_id = ?, approver_comments = ?, approved_at = NOW() WHERE id = ?");
                $update_stmt->bind_param("isi", $_SESSION['user_id'], $comments, $request_id);

                if ($update_stmt->execute()) {
                    // Update leave balance (remove pending days)
                    $year = date('Y', strtotime($request['start_date']));
                    $update_balance_stmt = $conn->prepare("UPDATE leave_balances SET pending_days = pending_days - ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
                    $update_balance_stmt->bind_param("diii", $request['total_days'], $request['user_id'], $request['leave_type_id'], $year);
                    $update_balance_stmt->execute();
                    $update_balance_stmt->close();

                    $success = "Leave request rejected successfully!";
                    logActivity($conn, 'leave.request_rejected', "Rejected leave request #$request_id for {$request['full_name']}", 'leave_request', $request_id, $request['full_name']);
                } else {
                    $error = "Error rejecting request: " . $update_stmt->error;
                }
                $update_stmt->close();
            }
        } else {
            $error = "Invalid request or already processed.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$leave_type_filter = $_GET['leave_type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT lr.*, u.full_name, u.email, d.department_name, lt.leave_name, lt.leave_code, lt.color, lt.icon,
          approver.full_name as approver_name
          FROM leave_requests lr
          JOIN users u ON lr.user_id = u.id
          LEFT JOIN departments d ON u.department_id = d.id
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          LEFT JOIN users approver ON lr.approver_id = approver.id
          WHERE 1=1";

if ($status_filter != 'all') {
    $query .= " AND lr.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($leave_type_filter != 'all') {
    $query .= " AND lr.leave_type_id = " . intval($leave_type_filter);
}

if ($date_from) {
    $query .= " AND lr.start_date >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to) {
    $query .= " AND lr.end_date <= '" . $conn->real_escape_string($date_to) . "'";
}

$query .= " ORDER BY
    CASE lr.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        WHEN 'rejected' THEN 3
        WHEN 'cancelled' THEN 4
    END,
    lr.created_at DESC";

$requests_result = $conn->query($query);

// Get leave types for filter
$leave_types_query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name";
$leave_types_result = $conn->query($leave_types_query);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM leave_requests";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Leave Requests - HRMS</title>
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
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 36px;
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

        .card h2 {
            color: #333;
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

        .filter-group input,
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

        .btn-filter:hover {
            background: #5568d3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th, td {
            padding: 14px 12px;
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

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: opacity 0.2s;
        }

        .btn-small:hover {
            opacity: 0.8;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            background: #007bff;
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
            max-width: 700px;
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

        .request-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            color: #333;
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

        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            padding: 10px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Approve Leave Requests</h1>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Requests</h3>
                <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-value" style="color: #ffc107;"><?php echo $stats['pending_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Approved</h3>
                <div class="stat-value" style="color: #28a745;"><?php echo $stats['approved_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected</h3>
                <div class="stat-value" style="color: #dc3545;"><?php echo $stats['rejected_count']; ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Leave Requests</h2>

            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Leave Type</label>
                    <select name="leave_type">
                        <option value="all">All Types</option>
                        <?php while ($leave_type = $leave_types_result->fetch_assoc()): ?>
                            <option value="<?php echo $leave_type['id']; ?>" <?php echo $leave_type_filter == $leave_type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($leave_type['leave_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <button type="submit" class="btn-filter">Apply Filters</button>
            </form>

            <!-- Requests Table -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests_result->num_rows > 0): ?>
                        <?php while ($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['full_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($request['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($request['department_name'] ?: '-'); ?></td>
                                <td>
                                    <span style="font-size: 18px;"><?php echo $request['icon']; ?></span>
                                    <?php echo htmlspecialchars($request['leave_name']); ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($request['start_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($request['end_date'])); ?></td>
                                <td><strong><?php echo number_format($request['total_days'], 1); ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-view" onclick='viewRequest(<?php echo json_encode($request); ?>)'>View</button>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <button class="btn-small btn-approve" onclick='approveRequest(<?php echo json_encode($request); ?>)'>Approve</button>
                                            <button class="btn-small btn-reject" onclick='rejectRequest(<?php echo json_encode($request); ?>)'>Reject</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                                No leave requests found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View/Approve/Reject Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Leave Request Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        let currentRequest = null;

        function viewRequest(request) {
            currentRequest = request;
            document.getElementById('modalTitle').textContent = 'Leave Request #' + request.id;

            let html = '<div class="request-details">';
            html += '<div class="detail-row"><div class="detail-label">Employee:</div><div class="detail-value">' + request.full_name + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">' + request.email + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Department:</div><div class="detail-value">' + (request.department_name || '-') + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Leave Type:</div><div class="detail-value">' + request.icon + ' ' + request.leave_name + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Start Date:</div><div class="detail-value">' + request.start_date + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">End Date:</div><div class="detail-value">' + request.end_date + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Total Days:</div><div class="detail-value"><strong>' + request.total_days + '</strong></div></div>';
            html += '<div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="badge badge-' + request.status + '">' + request.status.charAt(0).toUpperCase() + request.status.slice(1) + '</span></div></div>';
            html += '<div class="detail-row"><div class="detail-label">Reason:</div><div class="detail-value">' + request.reason + '</div></div>';

            if (request.approver_name) {
                html += '<div class="detail-row"><div class="detail-label">Approved By:</div><div class="detail-value">' + request.approver_name + '</div></div>';
                if (request.approved_at) {
                    html += '<div class="detail-row"><div class="detail-label">Approved At:</div><div class="detail-value">' + request.approved_at + '</div></div>';
                }
                if (request.approver_comments) {
                    html += '<div class="detail-row"><div class="detail-label">Comments:</div><div class="detail-value">' + request.approver_comments + '</div></div>';
                }
            }
            html += '</div>';

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('requestModal').style.display = 'block';
        }

        function approveRequest(request) {
            currentRequest = request;
            document.getElementById('modalTitle').textContent = 'Approve Leave Request #' + request.id;

            let html = '<div class="request-details">';
            html += '<div class="detail-row"><div class="detail-label">Employee:</div><div class="detail-value"><strong>' + request.full_name + '</strong></div></div>';
            html += '<div class="detail-row"><div class="detail-label">Leave Type:</div><div class="detail-value">' + request.icon + ' ' + request.leave_name + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Duration:</div><div class="detail-value">' + request.start_date + ' to ' + request.end_date + ' (<strong>' + request.total_days + ' days</strong>)</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Reason:</div><div class="detail-value">' + request.reason + '</div></div>';
            html += '</div>';

            html += '<form method="POST">';
            html += '<input type="hidden" name="action" value="approve">';
            html += '<input type="hidden" name="request_id" value="' + request.id + '">';
            html += '<div class="form-group">';
            html += '<label>Comments (optional)</label>';
            html += '<textarea name="comments" placeholder="Add any comments..."></textarea>';
            html += '</div>';
            html += '<div class="modal-actions">';
            html += '<button type="button" class="btn-primary" onclick="closeModal()" style="background: #6c757d;">Cancel</button>';
            html += '<button type="submit" class="btn-primary" style="background: #28a745;">Approve Leave</button>';
            html += '</div>';
            html += '</form>';

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('requestModal').style.display = 'block';
        }

        function rejectRequest(request) {
            currentRequest = request;
            document.getElementById('modalTitle').textContent = 'Reject Leave Request #' + request.id;

            let html = '<div class="request-details">';
            html += '<div class="detail-row"><div class="detail-label">Employee:</div><div class="detail-value"><strong>' + request.full_name + '</strong></div></div>';
            html += '<div class="detail-row"><div class="detail-label">Leave Type:</div><div class="detail-value">' + request.icon + ' ' + request.leave_name + '</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Duration:</div><div class="detail-value">' + request.start_date + ' to ' + request.end_date + ' (<strong>' + request.total_days + ' days</strong>)</div></div>';
            html += '<div class="detail-row"><div class="detail-label">Reason:</div><div class="detail-value">' + request.reason + '</div></div>';
            html += '</div>';

            html += '<form method="POST">';
            html += '<input type="hidden" name="action" value="reject">';
            html += '<input type="hidden" name="request_id" value="' + request.id + '">';
            html += '<div class="form-group">';
            html += '<label>Reason for Rejection *</label>';
            html += '<textarea name="comments" required placeholder="Please provide a reason for rejecting this leave request..."></textarea>';
            html += '</div>';
            html += '<div class="modal-actions">';
            html += '<button type="button" class="btn-primary" onclick="closeModal()" style="background: #6c757d;">Cancel</button>';
            html += '<button type="submit" class="btn-primary" style="background: #dc3545;">Reject Leave</button>';
            html += '</div>';
            html += '</form>';

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('requestModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('requestModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
