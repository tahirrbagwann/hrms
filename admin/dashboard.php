<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Get statistics
$total_users_query = "SELECT COUNT(*) as count FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'employee')";
$total_users_result = $conn->query($total_users_query);
$total_users = $total_users_result->fetch_assoc()['count'];

$today = date('Y-m-d');
$present_today_query = "SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE date = '$today'";
$present_today_result = $conn->query($present_today_query);
$present_today = $present_today_result->fetch_assoc()['count'];

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HRMS</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .menu-card h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .menu-card p {
            color: #666;
            font-size: 14px;
        }

        .menu-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>HRMS - Admin Dashboard</h1>
        <div class="navbar-user">
            <span>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="../profile.php" class="btn-profile">My Profile</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>

            <div class="stat-card">
                <h3>Present Today</h3>
                <div class="stat-value"><?php echo $present_today; ?></div>
            </div>

            <div class="stat-card">
                <h3>Absent Today</h3>
                <div class="stat-value"><?php echo $total_users - $present_today; ?></div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="create-user.php" class="menu-card">
                <div class="menu-icon">👤</div>
                <h2>Create User</h2>
                <p>Add new employees to the system</p>
            </a>

            <a href="manage-users.php" class="menu-card">
                <div class="menu-icon">👥</div>
                <h2>Manage Users</h2>
                <p>View and manage all users</p>
            </a>

            <a href="manage-roles.php" class="menu-card">
                <div class="menu-icon">🔐</div>
                <h2>Manage Roles</h2>
                <p>Configure roles and permissions</p>
            </a>

            <a href="manage-departments.php" class="menu-card">
                <div class="menu-icon">🏢</div>
                <h2>Manage Departments</h2>
                <p>Organize and manage departments</p>
            </a>

            <a href="attendance-report.php" class="menu-card">
                <div class="menu-icon">📊</div>
                <h2>Attendance Report</h2>
                <p>View employee attendance records</p>
            </a>

            <a href="bulk-import-users.php" class="menu-card">
                <div class="menu-icon">📤</div>
                <h2>Bulk Import Users</h2>
                <p>Import multiple users via CSV file</p>
            </a>

            <a href="activity-logs.php" class="menu-card">
                <div class="menu-icon">📝</div>
                <h2>Activity Logs</h2>
                <p>View audit trails and user activities</p>
            </a>

            <a href="approve-leave.php" class="menu-card">
                <div class="menu-icon">✅</div>
                <h2>Approve Leave</h2>
                <p>Review and approve leave requests</p>
            </a>

            <a href="manage-leave-types.php" class="menu-card">
                <div class="menu-icon">📋</div>
                <h2>Leave Types</h2>
                <p>Configure leave types and policies</p>
            </a>

            <a href="manage-leave-balances.php" class="menu-card">
                <div class="menu-icon">💼</div>
                <h2>Leave Balances</h2>
                <p>Manage employee leave balances</p>
            </a>

            <a href="manage-holidays.php" class="menu-card">
                <div class="menu-icon">🗓️</div>
                <h2>Holidays</h2>
                <p>Manage company holidays calendar</p>
            </a>

            <a href="leave-calendar.php" class="menu-card">
                <div class="menu-icon">📅</div>
                <h2>Leave Calendar</h2>
                <p>View team leave calendar</p>
            </a>

            <a href="leave-reports.php" class="menu-card">
                <div class="menu-icon">📈</div>
                <h2>Leave Reports</h2>
                <p>Analytics and leave reports</p>
            </a>
        </div>
    </div>
</body>
</html>
