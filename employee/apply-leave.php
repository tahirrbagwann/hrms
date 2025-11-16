<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Handle Leave Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after start date.";
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = "Cannot apply for leave in the past.";
    } else {
        // Calculate total days (excluding weekends and holidays)
        $total_days = 0;
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);

        // Get holidays in the date range
        $holidays_stmt = $conn->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ? AND is_active = 1 AND holiday_type = 'public'");
        $holidays_stmt->bind_param("ss", $start_date, $end_date);
        $holidays_stmt->execute();
        $holidays_result = $holidays_stmt->get_result();
        $holiday_dates = [];
        while ($row = $holidays_result->fetch_assoc()) {
            $holiday_dates[] = $row['holiday_date'];
        }
        $holidays_stmt->close();

        while ($current_date <= $end_date_obj) {
            $day_of_week = $current_date->format('N');
            $current_date_str = $current_date->format('Y-m-d');

            // Count only weekdays and non-holidays
            if ($day_of_week < 6 && !in_array($current_date_str, $holiday_dates)) {
                $total_days++;
            }
            $current_date->modify('+1 day');
        }

        if ($total_days == 0) {
            $error = "Selected date range contains no working days.";
        } else {
            // Check leave balance
            $year = date('Y', strtotime($start_date));
            $balance_stmt = $conn->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = ?");
            $balance_stmt->bind_param("iii", $user_id, $leave_type_id, $year);
            $balance_stmt->execute();
            $balance_result = $balance_stmt->get_result();
            $balance = $balance_result->fetch_assoc();
            $balance_stmt->close();

            if (!$balance) {
                $error = "You don't have an allocation for this leave type.";
            } elseif ($balance['available_days'] < $total_days) {
                $error = "Insufficient leave balance. Available: " . $balance['available_days'] . " days, Requested: " . $total_days . " days.";
            } else {
                // Check for overlapping leave requests
                $overlap_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE user_id = ? AND status IN ('pending', 'approved') AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) OR (start_date >= ? AND end_date <= ?))");
                $overlap_stmt->bind_param("issssss", $user_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);
                $overlap_stmt->execute();
                $overlap_result = $overlap_stmt->get_result();
                $overlap = $overlap_result->fetch_assoc();
                $overlap_stmt->close();

                if ($overlap['count'] > 0) {
                    $error = "You already have a leave request for this date range.";
                } else {
                    // Create leave request
                    $insert_stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type_id, start_date, end_date, total_days, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $insert_stmt->bind_param("iissds", $user_id, $leave_type_id, $start_date, $end_date, $total_days, $reason);

                    if ($insert_stmt->execute()) {
                        $leave_request_id = $insert_stmt->insert_id;

                        // Update pending days in leave balance
                        $update_balance_stmt = $conn->prepare("UPDATE leave_balances SET pending_days = pending_days + ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
                        $update_balance_stmt->bind_param("diii", $total_days, $user_id, $leave_type_id, $year);
                        $update_balance_stmt->execute();
                        $update_balance_stmt->close();

                        $success = "Leave request submitted successfully! Request ID: #" . $leave_request_id;
                        logActivity($conn, 'leave.request_created', "Applied for $total_days days of leave from $start_date to $end_date", 'leave_request', $leave_request_id);
                    } else {
                        $error = "Error submitting leave request: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
            }
        }
    }
}

// Handle Cancel Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];

    // Get request details
    $request_stmt = $conn->prepare("SELECT * FROM leave_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $request_stmt->bind_param("ii", $request_id, $user_id);
    $request_stmt->execute();
    $request_result = $request_stmt->get_result();
    $request = $request_result->fetch_assoc();
    $request_stmt->close();

    if ($request) {
        // Update request status
        $update_stmt = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
        $update_stmt->bind_param("i", $request_id);

        if ($update_stmt->execute()) {
            // Update leave balance
            $year = date('Y', strtotime($request['start_date']));
            $update_balance_stmt = $conn->prepare("UPDATE leave_balances SET pending_days = pending_days - ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
            $update_balance_stmt->bind_param("diii", $request['total_days'], $user_id, $request['leave_type_id'], $year);
            $update_balance_stmt->execute();
            $update_balance_stmt->close();

            $success = "Leave request cancelled successfully!";
            logActivity($conn, 'leave.request_cancelled', "Cancelled leave request #$request_id", 'leave_request', $request_id);
        } else {
            $error = "Error cancelling request: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $error = "Cannot cancel this request.";
    }
}

// Get active leave types
$leave_types_query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name";
$leave_types_result = $conn->query($leave_types_query);

// Get user's leave balances for current year
$current_year = date('Y');
$balances_query = "SELECT lb.*, lt.leave_name, lt.leave_code, lt.color, lt.icon FROM leave_balances lb JOIN leave_types lt ON lb.leave_type_id = lt.id WHERE lb.user_id = ? AND lb.year = ? ORDER BY lt.leave_name";
$balances_stmt = $conn->prepare($balances_query);
$balances_stmt->bind_param("ii", $user_id, $current_year);
$balances_stmt->execute();
$balances_result = $balances_stmt->get_result();
$balances_stmt->close();

// Get user's leave requests
$requests_query = "SELECT lr.*, lt.leave_name, lt.leave_code, lt.color, lt.icon FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.id WHERE lr.user_id = ? ORDER BY lr.created_at DESC LIMIT 20";
$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->bind_param("i", $user_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();
$requests_stmt->close();

// Get upcoming holidays
$upcoming_holidays_query = "SELECT * FROM holidays WHERE holiday_date >= CURDATE() AND is_active = 1 ORDER BY holiday_date LIMIT 5";
$upcoming_holidays_result = $conn->query($upcoming_holidays_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave - HRMS</title>
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

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2, .card h3 {
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .balance-card {
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .balance-card .icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .balance-card .leave-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .balance-card .leave-code {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .balance-card .days {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .balance-card .days-label {
            font-size: 12px;
            color: #666;
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

        .badge {
            display: inline-block;
            padding: 4px 10px;
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

        .btn-cancel {
            padding: 5px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-cancel:hover {
            opacity: 0.8;
        }

        .holiday-item {
            padding: 12px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .holiday-item .date {
            font-weight: 600;
            color: #667eea;
            font-size: 13px;
        }

        .holiday-item .name {
            color: #333;
            font-size: 14px;
            margin-top: 3px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Leave Management</h1>
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

        <!-- Leave Balance Summary -->
        <div class="card">
            <h2>Your Leave Balance (<?php echo $current_year; ?>)</h2>
            <div class="balance-grid">
                <?php if ($balances_result->num_rows > 0): ?>
                    <?php while ($balance = $balances_result->fetch_assoc()): ?>
                        <div class="balance-card" style="border-left-color: <?php echo $balance['color']; ?>; background: <?php echo $balance['color']; ?>15;">
                            <div class="icon"><?php echo $balance['icon']; ?></div>
                            <div class="leave-name"><?php echo htmlspecialchars($balance['leave_name']); ?></div>
                            <div class="leave-code"><?php echo $balance['leave_code']; ?></div>
                            <div class="days"><?php echo number_format($balance['available_days'], 1); ?></div>
                            <div class="days-label">Available of <?php echo number_format($balance['total_days'], 1); ?></div>
                            <?php if ($balance['pending_days'] > 0): ?>
                                <div style="font-size: 11px; color: #856404; margin-top: 5px;">
                                    <?php echo number_format($balance['pending_days'], 1); ?> pending
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #999; grid-column: 1/-1;">No leave balance allocated yet. Please contact HR.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <!-- Apply for Leave -->
            <div class="card">
                <h3>Apply for Leave</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="leave_type_id">Leave Type *</label>
                        <select id="leave_type_id" name="leave_type_id" required>
                            <option value="">Select Leave Type</option>
                            <?php
                            $leave_types_result->data_seek(0);
                            while ($leave_type = $leave_types_result->fetch_assoc()):
                            ?>
                                <option value="<?php echo $leave_type['id']; ?>">
                                    <?php echo $leave_type['icon'] . ' ' . htmlspecialchars($leave_type['leave_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date *</label>
                            <input type="date" id="end_date" name="end_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason *</label>
                        <textarea id="reason" name="reason" required placeholder="Please provide a reason for your leave request..."></textarea>
                    </div>

                    <button type="submit" name="apply_leave" class="btn-primary">Submit Leave Request</button>
                </form>
            </div>

            <!-- Upcoming Holidays -->
            <div class="card">
                <h3>Upcoming Holidays</h3>
                <?php if ($upcoming_holidays_result->num_rows > 0): ?>
                    <?php while ($holiday = $upcoming_holidays_result->fetch_assoc()): ?>
                        <div class="holiday-item">
                            <div class="date"><?php echo date('d M Y (D)', strtotime($holiday['holiday_date'])); ?></div>
                            <div class="name"><?php echo htmlspecialchars($holiday['holiday_name']); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No upcoming holidays</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave Requests History -->
        <div class="card">
            <h3>Your Leave Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Reason</th>
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
                                    <span style="font-size: 18px;"><?php echo $request['icon']; ?></span>
                                    <?php echo htmlspecialchars($request['leave_name']); ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($request['start_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo number_format($request['total_days'], 1); ?></td>
                                <td><?php echo htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['status'] == 'pending'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="cancel_request" class="btn-cancel">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                No leave requests yet. Apply for your first leave above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
