<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'summary';
$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? '';
$selected_department = $_GET['department'] ?? '';
$selected_leave_type = $_GET['leave_type'] ?? '';

// Build summary report
$summary_query = "SELECT
    lt.leave_name,
    lt.leave_code,
    lt.icon,
    lt.color,
    COUNT(DISTINCT lr.id) as total_requests,
    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN lr.status = 'approved' THEN lr.total_days ELSE 0 END) as total_days_taken
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id AND YEAR(lr.start_date) = ?
    WHERE lt.is_active = 1
    GROUP BY lt.id
    ORDER BY total_days_taken DESC";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("i", $selected_year);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary_stmt->close();

// Build detailed employee report
$employee_query = "SELECT
    u.id,
    u.full_name,
    u.employee_id,
    u.email,
    d.department_name,
    SUM(CASE WHEN lr.status = 'approved' THEN lr.total_days ELSE 0 END) as days_taken,
    SUM(CASE WHEN lr.status = 'pending' THEN lr.total_days ELSE 0 END) as days_pending,
    SUM(lb.total_days) as days_allocated,
    SUM(lb.available_days) as days_available
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN leave_requests lr ON u.id = lr.user_id AND YEAR(lr.start_date) = ?
    LEFT JOIN leave_balances lb ON u.id = lb.user_id AND lb.year = ?
    WHERE u.status = 'active'";

$params = [$selected_year, $selected_year];
$types = "ii";

if ($selected_department) {
    $employee_query .= " AND u.department_id = ?";
    $params[] = $selected_department;
    $types .= "i";
}

$employee_query .= " GROUP BY u.id ORDER BY days_taken DESC";

$employee_stmt = $conn->prepare($employee_query);
$employee_stmt->bind_param($types, ...$params);
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employee_stmt->close();

// Get departments for filter
$departments_query = "SELECT * FROM departments ORDER BY department_name";
$departments_result = $conn->query($departments_query);

// Get leave types for filter
$leave_types_query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name";
$leave_types_result = $conn->query($leave_types_query);

// Overall statistics
$stats_query = "SELECT
    COUNT(DISTINCT user_id) as total_employees_on_leave,
    SUM(CASE WHEN status = 'approved' THEN total_days ELSE 0 END) as total_days_approved,
    SUM(CASE WHEN status = 'pending' THEN total_days ELSE 0 END) as total_days_pending,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_approvals
    FROM leave_requests
    WHERE YEAR(start_date) = ?";

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
    <title>Leave Reports - HRMS</title>
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

        .page-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .card h3 {
            color: #333;
            margin-bottom: 20px;
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

        .btn-export {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
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

        .chart-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 150px 1fr 80px;
            gap: 15px;
            align-items: center;
        }

        .bar-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .bar {
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .bar-value {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            text-align: right;
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .btn-print {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        @media print {
            .navbar, .filters, .btn-filter, .btn-export, .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Leave Reports & Analytics</h1>
        <div class="navbar-user">
            <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Leave Management Reports - <?php echo $selected_year; ?></h2>
            <div style="margin-top: 15px;">
                <button onclick="window.print()" class="btn-print">Print Report</button>
                <button onclick="exportToCSV()" class="btn-export">Export to CSV</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Employees on Leave</h3>
                <div class="stat-value"><?php echo $stats['total_employees_on_leave'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Days Approved</h3>
                <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['total_days_approved'] ?? 0, 1); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Days</h3>
                <div class="stat-value" style="color: #ffc107;"><?php echo number_format($stats['total_days_pending'] ?? 0, 1); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Approvals</h3>
                <div class="stat-value" style="color: #dc3545;"><?php echo $stats['pending_approvals'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php while ($dept = $departments_result->fetch_assoc()): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $selected_department == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">Apply Filters</button>
            </form>
        </div>

        <!-- Leave Type Summary -->
        <div class="card">
            <h3>Leave Type Summary</h3>
            <div class="chart-container">
                <div class="bar-chart">
                    <?php
                    $max_days = 1;
                    $summary_data = [];
                    $summary_result->data_seek(0);
                    while ($row = $summary_result->fetch_assoc()) {
                        $summary_data[] = $row;
                        if ($row['total_days_taken'] > $max_days) {
                            $max_days = $row['total_days_taken'];
                        }
                    }

                    foreach ($summary_data as $summary):
                        $percentage = $max_days > 0 ? ($summary['total_days_taken'] / $max_days) * 100 : 0;
                    ?>
                        <div class="bar-row">
                            <div class="bar-label">
                                <span style="font-size: 18px;"><?php echo $summary['icon']; ?></span>
                                <?php echo htmlspecialchars($summary['leave_name']); ?>
                            </div>
                            <div class="bar">
                                <div class="bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div class="bar-value"><?php echo number_format($summary['total_days_taken'], 1); ?> days</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <table style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Total Requests</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                        <th>Days Taken</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $summary_result->data_seek(0);
                    while ($summary = $summary_result->fetch_assoc()):
                    ?>
                        <tr>
                            <td>
                                <span style="font-size: 18px;"><?php echo $summary['icon']; ?></span>
                                <strong><?php echo htmlspecialchars($summary['leave_name']); ?></strong>
                            </td>
                            <td><?php echo $summary['total_requests']; ?></td>
                            <td style="color: #28a745;"><?php echo $summary['approved_requests']; ?></td>
                            <td style="color: #ffc107;"><?php echo $summary['pending_requests']; ?></td>
                            <td style="color: #dc3545;"><?php echo $summary['rejected_requests']; ?></td>
                            <td><strong><?php echo number_format($summary['total_days_taken'], 1); ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Employee-wise Report -->
        <div class="card">
            <h3>Employee-wise Leave Report</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Allocated</th>
                        <th>Taken</th>
                        <th>Pending</th>
                        <th>Available</th>
                        <th>Utilization %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employee_result->num_rows > 0): ?>
                        <?php while ($employee = $employee_result->fetch_assoc()):
                            $allocated = $employee['days_allocated'] ?? 0;
                            $taken = $employee['days_taken'] ?? 0;
                            $utilization = $allocated > 0 ? ($taken / $allocated) * 100 : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo $employee['employee_id']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['department_name'] ?: '-'); ?></td>
                                <td><?php echo number_format($allocated, 1); ?></td>
                                <td style="color: #dc3545;"><strong><?php echo number_format($taken, 1); ?></strong></td>
                                <td style="color: #ffc107;"><?php echo number_format($employee['days_pending'] ?? 0, 1); ?></td>
                                <td style="color: #28a745;"><strong><?php echo number_format($employee['days_available'] ?? 0, 1); ?></strong></td>
                                <td>
                                    <?php echo number_format($utilization, 1); ?>%
                                    <div class="bar" style="height: 8px; margin-top: 5px;">
                                        <div class="bar-fill" style="width: <?php echo min($utilization, 100); ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                No employee data available for the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportToCSV() {
            alert('CSV export functionality can be implemented with server-side processing.');
        }
    </script>
</body>
</html>
