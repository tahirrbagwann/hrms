<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle Create/Update/Delete Holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create' || $action === 'update') {
            $holiday_name = $_POST['holiday_name'];
            $holiday_date = $_POST['holiday_date'];
            $holiday_type = $_POST['holiday_type'];
            $description = $_POST['description'];
            $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description, is_recurring, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiii", $holiday_name, $holiday_date, $holiday_type, $description, $is_recurring, $is_active, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    $success = "Holiday added successfully!";
                    logActivity($conn, 'leave.holiday_created', "Created holiday: $holiday_name on $holiday_date", 'holiday', $stmt->insert_id, $holiday_name);
                } else {
                    $error = "Error adding holiday: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE holidays SET holiday_name=?, holiday_date=?, holiday_type=?, description=?, is_recurring=?, is_active=? WHERE id=?");
                $stmt->bind_param("ssssiis", $holiday_name, $holiday_date, $holiday_type, $description, $is_recurring, $is_active, $id);

                if ($stmt->execute()) {
                    $success = "Holiday updated successfully!";
                    logActivity($conn, 'leave.holiday_updated', "Updated holiday: $holiday_name", 'holiday', $id, $holiday_name);
                } else {
                    $error = "Error updating holiday: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $success = "Holiday deleted successfully!";
                logActivity($conn, 'leave.holiday_deleted', "Deleted holiday ID: $id", 'holiday', $id);
            } else {
                $error = "Error deleting holiday: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get selected year (default to current year)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all holidays for selected year
$holidays_query = "SELECT * FROM holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date";
$stmt = $conn->prepare($holidays_query);
$stmt->bind_param("i", $selected_year);
$stmt->execute();
$holidays_result = $stmt->get_result();
$stmt->close();

// Get available years
$years_query = "SELECT DISTINCT YEAR(holiday_date) as year FROM holidays ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
while ($row = $years_result->fetch_assoc()) {
    $available_years[] = $row['year'];
}
if (!in_array(date('Y'), $available_years)) {
    $available_years[] = date('Y');
}
if (!in_array(date('Y') + 1, $available_years)) {
    $available_years[] = date('Y') + 1;
}
sort($available_years);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Holidays - HRMS</title>
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
            max-width: 1200px;
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
            margin-bottom: 20px;
        }

        .page-header h2 {
            color: #333;
        }

        .header-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .year-selector {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
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

        .holiday-grid {
            display: grid;
            gap: 15px;
        }

        .holiday-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            align-items: center;
            transition: all 0.3s;
        }

        .holiday-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .holiday-date {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
        }

        .holiday-date .day {
            font-size: 32px;
            font-weight: bold;
            display: block;
            line-height: 1;
        }

        .holiday-date .month {
            font-size: 14px;
            display: block;
            margin-top: 5px;
            opacity: 0.9;
        }

        .holiday-date .weekday {
            font-size: 12px;
            display: block;
            margin-top: 3px;
            opacity: 0.8;
        }

        .holiday-details h3 {
            color: #333;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .holiday-details p {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        .badge-public {
            background: #d4edda;
            color: #155724;
        }

        .badge-optional {
            background: #fff3cd;
            color: #856404;
        }

        .badge-restricted {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-recurring {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }

        .btn-small {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: opacity 0.2s;
            white-space: nowrap;
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
        .form-group input[type="date"],
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

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box p {
            color: #666;
            font-size: 13px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Manage Holidays</h1>
        <div class="navbar-user">
            <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="header-row">
                <h2>Holiday Calendar</h2>
                <div class="header-controls">
                    <select class="year-selector" onchange="window.location.href='?year=' + this.value">
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-primary" onclick="openCreateModal()">+ Add Holiday</button>
                </div>
            </div>

            <div class="info-box">
                <h4>Holiday Types</h4>
                <p>
                    <span class="badge badge-public">Public</span> Mandatory holidays for all employees |
                    <span class="badge badge-optional">Optional</span> Employees can choose to work |
                    <span class="badge badge-restricted">Restricted</span> Limited availability
                </p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="holiday-grid">
                <?php if ($holidays_result->num_rows > 0): ?>
                    <?php while ($holiday = $holidays_result->fetch_assoc()): ?>
                        <?php
                        $date = new DateTime($holiday['holiday_date']);
                        $day = $date->format('d');
                        $month = $date->format('M');
                        $weekday = $date->format('l');
                        ?>
                        <div class="holiday-item">
                            <div class="holiday-date">
                                <span class="day"><?php echo $day; ?></span>
                                <span class="month"><?php echo strtoupper($month); ?></span>
                                <span class="weekday"><?php echo $weekday; ?></span>
                            </div>
                            <div class="holiday-details">
                                <h3><?php echo htmlspecialchars($holiday['holiday_name']); ?></h3>
                                <p><?php echo htmlspecialchars($holiday['description']); ?></p>
                                <div>
                                    <span class="badge badge-<?php echo $holiday['holiday_type']; ?>">
                                        <?php echo ucfirst($holiday['holiday_type']); ?>
                                    </span>
                                    <?php if ($holiday['is_recurring']): ?>
                                        <span class="badge badge-recurring">Recurring</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button class="btn-small btn-edit" onclick='openEditModal(<?php echo json_encode($holiday); ?>)'>Edit</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this holiday?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $holiday['id']; ?>">
                                    <button type="submit" class="btn-small btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: #999;">
                        No holidays configured for <?php echo $selected_year; ?>. Click "Add Holiday" to create one.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="holidayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Holiday</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="holidayForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="holidayId">

                <div class="form-group">
                    <label for="holiday_name">Holiday Name *</label>
                    <input type="text" id="holiday_name" name="holiday_name" required>
                </div>

                <div class="form-group">
                    <label for="holiday_date">Date *</label>
                    <input type="date" id="holiday_date" name="holiday_date" required>
                </div>

                <div class="form-group">
                    <label for="holiday_type">Holiday Type *</label>
                    <select id="holiday_type" name="holiday_type" required>
                        <option value="public">Public Holiday</option>
                        <option value="optional">Optional Holiday</option>
                        <option value="restricted">Restricted Holiday</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="is_recurring" name="is_recurring">
                    <label for="is_recurring">Recurring (appears every year)</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    <label for="is_active">Active</label>
                </div>

                <div style="margin-top: 25px; text-align: right;">
                    <button type="button" class="btn-small" onclick="closeModal()" style="background: #6c757d; color: white; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn-primary" style="margin-left: 10px;">Save Holiday</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Holiday';
            document.getElementById('formAction').value = 'create';
            document.getElementById('holidayForm').reset();
            document.getElementById('is_active').checked = true;
            document.getElementById('holiday_date').value = '<?php echo $selected_year; ?>-01-01';
            document.getElementById('holidayModal').style.display = 'block';
        }

        function openEditModal(holiday) {
            document.getElementById('modalTitle').textContent = 'Edit Holiday';
            document.getElementById('formAction').value = 'update';
            document.getElementById('holidayId').value = holiday.id;
            document.getElementById('holiday_name').value = holiday.holiday_name;
            document.getElementById('holiday_date').value = holiday.holiday_date;
            document.getElementById('holiday_type').value = holiday.holiday_type;
            document.getElementById('description').value = holiday.description || '';
            document.getElementById('is_recurring').checked = holiday.is_recurring == 1;
            document.getElementById('is_active').checked = holiday.is_active == 1;
            document.getElementById('holidayModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('holidayModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('holidayModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
