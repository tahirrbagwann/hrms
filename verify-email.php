<?php
require_once 'config/database.php';

$success = false;
$error = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $conn = getDBConnection();

    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT * FROM email_verifications WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $verification = $result->fetch_assoc();
        $user_id = $verification['user_id'];

        // Update user as verified
        $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);

        if ($update_stmt->execute()) {
            // Delete the verification token
            $delete_stmt = $conn->prepare("DELETE FROM email_verifications WHERE token = ?");
            $delete_stmt->bind_param("s", $token);
            $delete_stmt->execute();
            $delete_stmt->close();

            $success = true;

            // Log activity
            $activity_type = 'email_verified';
            $description = 'Email address verified successfully';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = 'Failed to verify email. Please try again.';
        }

        $update_stmt->close();
    } else {
        $error = 'Invalid or expired verification token.';
    }

    $stmt->close();
    closeDBConnection($conn);
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - HRMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .verification-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .icon {
            font-size: 72px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .success {
            color: #2e7d32;
        }

        .error {
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($success): ?>
            <div class="icon">✅</div>
            <h1 class="success">Email Verified!</h1>
            <p>Your email address has been successfully verified. You can now enjoy full access to all features.</p>
            <a href="login.php" class="btn">Go to Login</a>
        <?php else: ?>
            <div class="icon">❌</div>
            <h1 class="error">Verification Failed</h1>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="email-verification.php" class="btn">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>
