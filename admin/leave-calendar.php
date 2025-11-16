<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Get selected month and year
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get first and last day of the month
$first_day = date('Y-m-01', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $selected_month, 1, $selected_year));

// Get all approved leaves for the month
$leaves_query = "SELECT lr.*, u.full_name, u.employee_id, lt.leave_name, lt.leave_code, lt.color, lt.icon, d.department_name
                 FROM leave_requests lr
                 JOIN users u ON lr.user_id = u.id
                 JOIN leave_types lt ON lr.leave_type_id = lt.id
                 LEFT JOIN departments d ON u.department_id = d.id
                 WHERE lr.status = 'approved'
                 AND ((lr.start_date BETWEEN ? AND ?) OR (lr.end_date BETWEEN ? AND ?) OR (lr.start_date <= ? AND lr.end_date >= ?))
                 ORDER BY lr.start_date";

$leaves_stmt = $conn->prepare($leaves_query);
$leaves_stmt->bind_param("ssssss", $first_day, $last_day, $first_day, $last_day, $first_day, $last_day);
$leaves_stmt->execute();
$leaves_result = $leaves_stmt->get_result();

// Organize leaves by date
$leave_calendar = [];
while ($leave = $leaves_result->fetch_assoc()) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include end date

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        if ($date_str >= $first_day && $date_str <= $last_day) {
            if (!isset($leave_calendar[$date_str])) {
                $leave_calendar[$date_str] = [];
            }
            $leave_calendar[$date_str][] = $leave;
        }
    }
}
$leaves_stmt->close();

// Get holidays for the month
$holidays_query = "SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? AND is_active = 1 ORDER BY holiday_date";
$holidays_stmt = $conn->prepare($holidays_query);
$holidays_stmt->bind_param("ss", $first_day, $last_day);
$holidays_stmt->execute();
$holidays_result = $holidays_stmt->get_result();

$holidays = [];
while ($holiday = $holidays_result->fetch_assoc()) {
    $holidays[$holiday['holiday_date']] = $holiday;
}
$holidays_stmt->close();

// Get calendar data
$num_days = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$first_day_of_week = date('w', mktime(0, 0, 0, $selected_month, 1, $selected_year));

$month_name = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Calendar - HRMS</title>
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

        .calendar-header {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calendar-header h2 {
            color: #333;
            font-size: 28px;
        }

        .month-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-nav {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-nav:hover {
            background: #5568d3;
        }

        .btn-today {
            background: #28a745;
        }

        .btn-today:hover {
            background: #218838;
        }

        .calendar-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .calendar-table thead th {
            background: #f8f9fa;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            color: #333;
            border: 1px solid #dee2e6;
        }

        .calendar-table thead th.sunday {
            color: #dc3545;
        }

        .calendar-table tbody td {
            border: 1px solid #dee2e6;
            padding: 5px;
            vertical-align: top;
            height: 120px;
            min-height: 120px;
            position: relative;
            background: white;
        }

        .calendar-table tbody td.other-month {
            background: #f8f9fa;
        }

        .calendar-table tbody td.today {
            background: #fff3cd;
        }

        .calendar-table tbody td.weekend {
            background: #f9f9f9;
        }

        .calendar-table tbody td.holiday {
            background: #ffe6e6;
        }

        .date-number {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            padding: 5px;
        }

        .date-number.sunday {
            color: #dc3545;
        }

        .holiday-badge {
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .leave-item {
            font-size: 11px;
            padding: 3px 6px;
            margin: 2px 0;
            border-radius: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }

        .leave-item:hover {
            opacity: 0.8;
            transform: translateX(2px);
        }

        .leave-icon {
            font-size: 10px;
        }

        .leave-count {
            background: #667eea;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-top: 3px;
            display: inline-block;
        }

        .legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
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
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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

        .leave-detail {
            padding: 15px;
            border-left: 4px solid;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .leave-detail h4 {
            color: #333;
            margin-bottom: 8px;
        }

        .leave-detail p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Leave Calendar</h1>
        <div class="navbar-user">
            <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="<?php echo isAdmin() ? 'dashboard.php' : '../employee/dashboard.php'; ?>" class="btn-back">Back to Dashboard</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="calendar-header">
            <h2><?php echo $month_name; ?></h2>
            <div class="month-nav">
                <?php
                $prev_month = $selected_month - 1;
                $prev_year = $selected_year;
                if ($prev_month < 1) {
                    $prev_month = 12;
                    $prev_year--;
                }

                $next_month = $selected_month + 1;
                $next_year = $selected_year;
                if ($next_month > 12) {
                    $next_month = 1;
                    $next_year++;
                }
                ?>
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn-nav">&larr; Previous</a>
                <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="btn-nav btn-today">Today</a>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn-nav">Next &rarr;</a>
            </div>
        </div>

        <div class="calendar-card">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th class="sunday">Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $weeks = ceil(($num_days + $first_day_of_week) / 7);

                    for ($week = 0; $week < $weeks; $week++) {
                        echo "<tr>";
                        for ($dow = 0; $dow < 7; $dow++) {
                            if (($week == 0 && $dow < $first_day_of_week) || $day > $num_days) {
                                echo "<td class='other-month'></td>";
                            } else {
                                $current_date = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $day);
                                $is_today = $current_date == date('Y-m-d');
                                $is_weekend = $dow == 0 || $dow == 6;
                                $is_holiday = isset($holidays[$current_date]);

                                $classes = [];
                                if ($is_today) $classes[] = 'today';
                                if ($is_weekend) $classes[] = 'weekend';
                                if ($is_holiday) $classes[] = 'holiday';

                                echo "<td class='" . implode(' ', $classes) . "'>";
                                echo "<div class='date-number " . ($dow == 0 ? 'sunday' : '') . "'>$day";
                                if ($is_holiday) {
                                    echo "<span class='holiday-badge'>" . substr($holidays[$current_date]['holiday_name'], 0, 10) . "</span>";
                                }
                                echo "</div>";

                                // Display leaves for this date
                                if (isset($leave_calendar[$current_date])) {
                                    $leave_count = count($leave_calendar[$current_date]);
                                    $displayed = 0;
                                    $max_display = 3;

                                    foreach ($leave_calendar[$current_date] as $leave) {
                                        if ($displayed < $max_display) {
                                            echo "<div class='leave-item' style='background: " . $leave['color'] . "20; border-left: 3px solid " . $leave['color'] . ";' onclick='showLeaveDetails(" . json_encode($leave) . ")'>";
                                            echo "<span class='leave-icon'>" . $leave['icon'] . "</span> ";
                                            echo htmlspecialchars(substr($leave['full_name'], 0, 15));
                                            echo "</div>";
                                            $displayed++;
                                        }
                                    }

                                    if ($leave_count > $max_display) {
                                        echo "<div class='leave-count'>+" . ($leave_count - $max_display) . " more</div>";
                                    }
                                }

                                echo "</td>";
                                $day++;
                            }
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box" style="background: #fff3cd;"></div>
                    <span>Today</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background: #f9f9f9;"></div>
                    <span>Weekend</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background: #ffe6e6;"></div>
                    <span>Holiday</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Leave Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="leaveDetailsContent"></div>
        </div>
    </div>

    <script>
        function showLeaveDetails(leave) {
            const content = `
                <div class="leave-detail" style="border-left-color: ${leave.color};">
                    <h4>${leave.icon} ${leave.leave_name}</h4>
                    <p><strong>Employee:</strong> ${leave.full_name} (${leave.employee_id})</p>
                    <p><strong>Department:</strong> ${leave.department_name || 'N/A'}</p>
                    <p><strong>Duration:</strong> ${leave.start_date} to ${leave.end_date}</p>
                    <p><strong>Total Days:</strong> ${leave.total_days}</p>
                    <p><strong>Reason:</strong> ${leave.reason}</p>
                </div>
            `;
            document.getElementById('leaveDetailsContent').innerHTML = content;
            document.getElementById('leaveModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('leaveModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('leaveModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
