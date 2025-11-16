<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

// Check if user is employee
function isEmployee() {
    return isLoggedIn() && $_SESSION['role'] === 'employee';
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /hrms/login.php');
        exit();
    }
}

// Redirect to login if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /hrms/employee/dashboard.php');
        exit();
    }
}

// Redirect to login if not employee
function requireEmployee() {
    requireLogin();
    if (!isEmployee()) {
        header('Location: /hrms/admin/dashboard.php');
        exit();
    }
}

// Logout user
function logout() {
    session_destroy();
    header('Location: /hrms/login.php');
    exit();
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get current user data
function getCurrentUser($conn) {
    if (!isLoggedIn()) {
        return null;
    }

    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT u.*, r.role_name, d.department_name FROM users u
                           JOIN roles r ON u.role_id = r.id
                           LEFT JOIN departments d ON u.department_id = d.id
                           WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// ============================================
// PERMISSION-BASED FUNCTIONS
// ============================================

// Get all permissions for the current user
function getUserPermissions($conn) {
    if (!isLoggedIn()) {
        return [];
    }

    // Check if permissions are already cached in session
    if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
        return $_SESSION['user_permissions'];
    }

    $user_id = $_SESSION['user_id'];

    // Get user's role
    $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return [];
    }

    // Get all permissions for this role
    $stmt = $conn->prepare("SELECT p.permission_slug
                           FROM permissions p
                           JOIN role_permissions rp ON p.id = rp.permission_id
                           WHERE rp.role_id = ?");
    $stmt->bind_param("i", $user['role_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['permission_slug'];
    }
    $stmt->close();

    // Cache permissions in session
    $_SESSION['user_permissions'] = $permissions;

    return $permissions;
}

// Clear cached permissions (call this when user role/permissions change)
function clearPermissionsCache() {
    if (isset($_SESSION['user_permissions'])) {
        unset($_SESSION['user_permissions']);
    }
}

// Check if user has a specific permission
function hasPermission($conn, $permission_slug) {
    if (!isLoggedIn()) {
        return false;
    }

    // Admin always has all permissions
    if (isAdmin()) {
        return true;
    }

    $permissions = getUserPermissions($conn);
    return in_array($permission_slug, $permissions);
}

// Check if user has any of the given permissions
function hasAnyPermission($conn, $permission_slugs) {
    if (!isLoggedIn()) {
        return false;
    }

    // Admin always has all permissions
    if (isAdmin()) {
        return true;
    }

    $permissions = getUserPermissions($conn);
    foreach ($permission_slugs as $slug) {
        if (in_array($slug, $permissions)) {
            return true;
        }
    }
    return false;
}

// Check if user has all of the given permissions
function hasAllPermissions($conn, $permission_slugs) {
    if (!isLoggedIn()) {
        return false;
    }

    // Admin always has all permissions
    if (isAdmin()) {
        return true;
    }

    $permissions = getUserPermissions($conn);
    foreach ($permission_slugs as $slug) {
        if (!in_array($slug, $permissions)) {
            return false;
        }
    }
    return true;
}

// Redirect if user doesn't have permission
function requirePermission($conn, $permission_slug, $redirect_url = '/hrms/employee/dashboard.php') {
    requireLogin();

    if (!hasPermission($conn, $permission_slug)) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Redirect if user doesn't have any of the given permissions
function requireAnyPermission($conn, $permission_slugs, $redirect_url = '/hrms/employee/dashboard.php') {
    requireLogin();

    if (!hasAnyPermission($conn, $permission_slugs)) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Check if user belongs to a specific department
function isInDepartment($conn, $department_id) {
    if (!isLoggedIn()) {
        return false;
    }

    $user = getCurrentUser($conn);
    return $user && $user['department_id'] == $department_id;
}

// Check if user is department head
function isDepartmentHead($conn) {
    if (!isLoggedIn()) {
        return false;
    }

    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT id FROM departments WHERE head_user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_head = $result->num_rows > 0;
    $stmt->close();

    return $is_head;
}

// Get user's role name
function getUserRole($conn) {
    if (!isLoggedIn()) {
        return null;
    }

    $user = getCurrentUser($conn);
    return $user ? $user['role_name'] : null;
}

// Check if user has a specific role
function hasRole($role_name) {
    return isLoggedIn() && $_SESSION['role'] === $role_name;
}

// Check if user has any of the given roles
function hasAnyRole($role_names) {
    if (!isLoggedIn()) {
        return false;
    }

    return in_array($_SESSION['role'], $role_names);
}

// ============================================
// ACTIVITY LOGGING FUNCTIONS
// ============================================

/**
 * Log user activity to the database
 *
 * @param mysqli $conn Database connection
 * @param string $action_type Type of action (e.g., 'user.login', 'user.created')
 * @param string $action_description Human-readable description of the action
 * @param string|null $target_type Type of target entity (e.g., 'user', 'role', 'department')
 * @param int|null $target_id ID of the target entity
 * @param string|null $target_name Name of the target entity
 * @param array|null $old_values Previous values (for updates)
 * @param array|null $new_values New values (for updates)
 * @param string $status Status of the action ('success', 'failed', 'warning')
 * @return bool True if logged successfully
 */
function logActivity(
    $conn,
    $action_type,
    $action_description,
    $target_type = null,
    $target_id = null,
    $target_name = null,
    $old_values = null,
    $new_values = null,
    $status = 'success'
) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $old_values_json = $old_values ? json_encode($old_values) : null;
    $new_values_json = $new_values ? json_encode($new_values) : null;

    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, username, action_type, action_description, target_type, target_id, target_name, ip_address, user_agent, old_values, new_values, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "issssissssss",
        $user_id,
        $username,
        $action_type,
        $action_description,
        $target_type,
        $target_id,
        $target_name,
        $ip_address,
        $user_agent,
        $old_values_json,
        $new_values_json,
        $status
    );

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Log authentication attempt
 *
 * @param mysqli $conn Database connection
 * @param string $email Email address used for login
 * @param string $status 'success' or 'failed'
 * @param string|null $failure_reason Reason for failure
 * @return bool True if logged successfully
 */
function logLoginAttempt($conn, $email, $status, $failure_reason = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (email, ip_address, user_agent, status, failure_reason)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssss", $email, $ip_address, $user_agent, $status, $failure_reason);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get recent activities for a user
 *
 * @param mysqli $conn Database connection
 * @param int|null $user_id User ID (null for all users)
 * @param int $limit Number of records to return
 * @return array Array of activity records
 */
function getRecentActivities($conn, $user_id = null, $limit = 50) {
    if ($user_id) {
        $stmt = $conn->prepare(
            "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param("ii", $user_id, $limit);
    } else {
        $stmt = $conn->prepare(
            "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];

    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }

    $stmt->close();
    return $activities;
}

/**
 * Get failed login attempts for an email
 *
 * @param mysqli $conn Database connection
 * @param string $email Email address
 * @param int $hours Number of hours to look back
 * @return int Number of failed attempts
 */
function getFailedLoginAttempts($conn, $email, $hours = 24) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM login_attempts
         WHERE email = ? AND status = 'failed'
         AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)"
    );

    $stmt->bind_param("si", $email, $hours);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'];
}

/**
 * Clean up old activity logs
 *
 * @param mysqli $conn Database connection
 * @param int $days_to_keep Number of days to keep
 * @return int Number of deleted records
 */
function cleanupOldLogs($conn, $days_to_keep = 90) {
    $stmt = $conn->prepare(
        "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
    );
    $stmt->bind_param("i", $days_to_keep);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    return $deleted;
}
?>
