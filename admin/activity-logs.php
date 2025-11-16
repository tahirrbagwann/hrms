<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
$filter_action = isset($_GET['filter_action']) ? sanitizeInput($_GET['filter_action']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitizeInput($_GET['filter_status']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? sanitizeInput($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? sanitizeInput($_GET['filter_date_to']) : '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($filter_user > 0) {
    $where_clauses[] = "al.user_id = ?";
    $params[] = $filter_user;
    $types .= 'i';
}

if (!empty($filter_action)) {
    $where_clauses[] = "al.action_type LIKE ?";
    $params[] = "%$filter_action%";
    $types .= 's';
}

if (!empty($filter_status)) {
    $where_clauses[] = "al.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_date_from)) {
    $where_clauses[] = "DATE(al.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $where_clauses[] = "DATE(al.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total records
$count_query = "SELECT COUNT(*) as total FROM activity_logs al $where_sql";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get activity logs
$logs_query = "SELECT al.*, u.full_name, u.email
               FROM activity_logs al
               LEFT JOIN users u ON al.user_id = u.id
               $where_sql
               ORDER BY al.created_at DESC
               LIMIT ? OFFSET ?";

$stmt = $conn->prepare($logs_query);
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$logs_result = $stmt->get_result();
$stmt->close();

// Get all users for filter dropdown
$users_query = "SELECT id, full_name, username FROM users ORDER BY full_name";
$users_result = $conn->query($users_query);

// Get action types for filter
$actions_query = "SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type";
$actions_result = $conn->query($actions_query);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - HRMS</title>
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
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
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            align-self: flex-end;
        }

        .btn-filter:hover {
            background: #5568d3;
        }

        .btn-clear {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            align-self: flex-end;
        }

        .btn-clear:hover {
            background: #5a6268;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
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
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-failed {
            background: #ffebee;
            color: #c62828;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .action-type {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-box p {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .details-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
            font-size: 12px;
        }

        .details-btn:hover {
            color: #5568d3;
        }

        .log-details {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 12px;
        }

        .log-details.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Activity Logs & Audit Trails</h1>
        <div>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="stats">
                <div class="stat-box">
                    <h3><?php echo number_format($total_records); ?></h3>
                    <p>Total Activities</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $total_pages; ?></h3>
                    <p>Total Pages</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $page; ?></h3>
                    <p>Current Page</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Filters</h2>
            <form method="GET" action="">
                <div class="filters">
                    <div class="filter-group">
                        <label for="filter_user">User</label>
                        <select id="filter_user" name="filter_user">
                            <option value="">All Users</option>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"
                                    <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                    (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter_action">Action Type</label>
                        <select id="filter_action" name="filter_action">
                            <option value="">All Actions</option>
                            <?php while ($action = $actions_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($action['action_type']); ?>"
                                    <?php echo ($filter_action == $action['action_type']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action['action_type']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter_status">Status</label>
                        <select id="filter_status" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="success" <?php echo ($filter_status == 'success') ? 'selected' : ''; ?>>Success</option>
                            <option value="failed" <?php echo ($filter_status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                            <option value="warning" <?php echo ($filter_status == 'warning') ? 'selected' : ''; ?>>Warning</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter_date_from">Date From</label>
                        <input type="date" id="filter_date_from" name="filter_date_from" value="<?php echo $filter_date_from; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="filter_date_to">Date To</label>
                        <input type="date" id="filter_date_to" name="filter_date_to" value="<?php echo $filter_date_to; ?>">
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-filter">Apply Filters</button>
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <a href="activity-logs.php" class="btn-clear">Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Activity Logs</h2>

            <?php if ($logs_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Target</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php if ($log['full_name']): ?>
                                        <?php echo htmlspecialchars($log['full_name']); ?><br>
                                        <small style="color: #888;"><?php echo htmlspecialchars($log['username'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <em>System</em>
                                    <?php endif; ?>
                                </td>
                                <td><span class="action-type"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                                <td>
                                    <?php if ($log['target_name']): ?>
                                        <?php echo htmlspecialchars($log['target_name']); ?><br>
                                        <small style="color: #888;"><?php echo htmlspecialchars($log['target_type'] ?? ''); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                        <button class="details-btn" onclick="toggleDetails(<?php echo $log['id']; ?>)">
                                            View Details
                                        </button>
                                        <div id="details-<?php echo $log['id']; ?>" class="log-details">
                                            <?php if ($log['old_values']): ?>
                                                <strong>Old Values:</strong>
                                                <pre><?php echo htmlspecialchars(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT)); ?></pre>
                                            <?php endif; ?>
                                            <?php if ($log['new_values']): ?>
                                                <strong>New Values:</strong>
                                                <pre><?php echo htmlspecialchars(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT)); ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&filter_user=<?php echo $filter_user; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_status=<?php echo $filter_status; ?>&filter_date_from=<?php echo $filter_date_from; ?>&filter_date_to=<?php echo $filter_date_to; ?>">
                                &laquo; Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&filter_user=<?php echo $filter_user; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_status=<?php echo $filter_status; ?>&filter_date_from=<?php echo $filter_date_from; ?>&filter_date_to=<?php echo $filter_date_to; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&filter_user=<?php echo $filter_user; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_status=<?php echo $filter_status; ?>&filter_date_from=<?php echo $filter_date_from; ?>&filter_date_to=<?php echo $filter_date_to; ?>">
                                Next &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No activity logs found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDetails(id) {
            const details = document.getElementById('details-' + id);
            details.classList.toggle('show');
        }
    </script>
</body>
</html>
