<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Get filter parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build query
$query = "SELECT a.*, u.full_name, u.username
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          WHERE 1=1";

if (!empty($filter_date)) {
    $query .= " AND a.date = '$filter_date'";
}

if ($filter_user > 0) {
    $query .= " AND a.user_id = $filter_user";
}

$query .= " ORDER BY a.date DESC, a.punch_in DESC";

$attendance_result = $conn->query($query);

// Get all employees for filter
$employees_query = "SELECT id, full_name FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'employee')";
$employees_result = $conn->query($employees_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - HRMS</title>
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
            max-width: 1200px;
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
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            height: 42px;
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

        .badge-absent {
            background: #ffebee;
            color: #c62828;
        }

        .badge-half-day {
            background: #fff3e0;
            color: #e65100;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Attendance Report</h1>
        <div class="navbar-user">
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Filter Attendance</h2>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo $filter_date; ?>">
                </div>

                <div class="form-group">
                    <label for="user_id">Employee</label>
                    <select id="user_id" name="user_id">
                        <option value="0">All Employees</option>
                        <?php while ($emp = $employees_result->fetch_assoc()): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo ($filter_user == $emp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">Filter</button>
            </form>
        </div>

        <div class="card">
            <h2>Attendance Records</h2>

            <?php if ($attendance_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Work Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $attendance_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
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
                <div class="no-records">No attendance records found for the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
