<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireEmployee();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Check today's attendance
$today = date('Y-m-d');
$check_query = "SELECT * FROM attendance WHERE user_id = $user_id AND date = '$today' ORDER BY id DESC LIMIT 1";
$check_result = $conn->query($check_query);
$today_attendance = $check_result->fetch_assoc();

// Handle Punch In/Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'punch_in') {
            if ($today_attendance && !$today_attendance['punch_out']) {
                $error = 'You have already punched in today.';
            } else {
                $punch_in = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO attendance (user_id, punch_in, date) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $punch_in, $today);

                if ($stmt->execute()) {
                    $success = 'Successfully punched in!';
                    // Refresh today's attendance
                    $check_result = $conn->query($check_query);
                    $today_attendance = $check_result->fetch_assoc();
                } else {
                    $error = 'Failed to punch in. Please try again.';
                }

                $stmt->close();
            }
        } elseif ($action === 'punch_out') {
            if (!$today_attendance) {
                $error = 'You need to punch in first.';
            } elseif ($today_attendance['punch_out']) {
                $error = 'You have already punched out today.';
            } else {
                $punch_out = date('Y-m-d H:i:s');
                $attendance_id = $today_attendance['id'];

                // Calculate work hours
                $punch_in_time = strtotime($today_attendance['punch_in']);
                $punch_out_time = strtotime($punch_out);
                $work_hours = ($punch_out_time - $punch_in_time) / 3600;

                $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, work_hours = ? WHERE id = ?");
                $stmt->bind_param("sdi", $punch_out, $work_hours, $attendance_id);

                if ($stmt->execute()) {
                    $success = 'Successfully punched out!';
                    // Refresh today's attendance
                    $check_result = $conn->query($check_query);
                    $today_attendance = $check_result->fetch_assoc();
                } else {
                    $error = 'Failed to punch out. Please try again.';
                }

                $stmt->close();
            }
        }
    }
}

// Get recent attendance history
$history_query = "SELECT * FROM attendance WHERE user_id = $user_id ORDER BY date DESC, punch_in DESC LIMIT 10";
$history_result = $conn->query($history_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - HRMS</title>
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

        .navbar-user span {
            font-size: 14px;
        }

        .btn-logout, .btn-profile {
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

        .btn-logout:hover, .btn-profile:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .welcome-card h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .welcome-card p {
            color: #666;
            font-size: 14px;
        }

        .attendance-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .attendance-card h3 {
            color: #333;
            margin-bottom: 20px;
        }

        .punch-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .btn-punch {
            padding: 20px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-punch:hover:not(:disabled) {
            transform: translateY(-3px);
        }

        .btn-punch:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-punch-in {
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            color: white;
        }

        .btn-punch-out {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            color: white;
        }

        .status-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-info p {
            margin: 5px 0;
            color: #555;
        }

        .status-info strong {
            color: #333;
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

        .badge-present {
            background: #e8f5e9;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>HRMS - Employee Dashboard</h1>
        <div class="navbar-user">
            <span>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="../profile.php" class="btn-profile">My Profile</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>!</h2>
            <p><?php echo date('l, F d, Y'); ?></p>
        </div>

        <div class="attendance-card">
            <h3>Mark Attendance</h3>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($today_attendance): ?>
                <div class="status-info">
                    <p><strong>Today's Status:</strong></p>
                    <p>Punch In: <?php echo date('h:i A', strtotime($today_attendance['punch_in'])); ?></p>
                    <?php if ($today_attendance['punch_out']): ?>
                        <p>Punch Out: <?php echo date('h:i A', strtotime($today_attendance['punch_out'])); ?></p>
                        <p>Work Hours: <?php echo number_format($today_attendance['work_hours'], 2); ?> hours</p>
                    <?php else: ?>
                        <p>Status: Currently Working</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="punch-buttons">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="punch_in">
                    <button type="submit" class="btn-punch btn-punch-in"
                        <?php echo ($today_attendance && !$today_attendance['punch_out']) ? 'disabled' : ''; ?>>
                        Punch In
                    </button>
                </form>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="punch_out">
                    <button type="submit" class="btn-punch btn-punch-out"
                        <?php echo (!$today_attendance || $today_attendance['punch_out']) ? 'disabled' : ''; ?>>
                        Punch Out
                    </button>
                </form>
            </div>
        </div>

        <div class="attendance-card">
            <h3>Recent Attendance History</h3>

            <?php if ($history_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Work Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($record['punch_in'])); ?></td>
                                <td><?php echo $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-'; ?></td>
                                <td><?php echo $record['work_hours'] ? number_format($record['work_hours'], 2) . ' hrs' : '-'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No attendance records yet.</p>
            <?php endif; ?>
        </div>

        <!-- Leave Management Quick Links -->
        <div class="attendance-card">
            <h3>Leave Management</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                <a href="apply-leave.php" style="display: block; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; text-align: center; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 32px; margin-bottom: 10px;">📝</div>
                    <div style="font-weight: 600;">Apply Leave</div>
                    <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Request time off</div>
                </a>
                <a href="../admin/leave-calendar.php" style="display: block; padding: 20px; background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; text-decoration: none; border-radius: 8px; text-align: center; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                    <div style="font-weight: 600;">Leave Calendar</div>
                    <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">View team calendar</div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
