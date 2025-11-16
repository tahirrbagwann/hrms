<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';
$info = '';

// Check if email is already verified
if ($current_user['email_verified'] == 1) {
    $info = 'Your email is already verified!';
}

// Handle send verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_verification') {
    // Generate verification token
    $token = bin2hex(random_bytes(32));
    $user_id = $_SESSION['user_id'];
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Delete old tokens for this user
    $conn->query("DELETE FROM email_verifications WHERE user_id = $user_id");

    // Insert new token
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expires_at);

    if ($stmt->execute()) {
        // In a real application, you would send this via email
        // For now, we'll just display the link
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/hrms/verify-email.php?token=" . $token;

        $success = 'Verification email sent! (In production, this would be sent to your email)';
        $info = '<strong>Verification Link:</strong><br><a href="' . $verification_link . '" target="_blank">' . $verification_link . '</a>';
    } else {
        $error = 'Failed to generate verification token. Please try again.';
    }

    $stmt->close();
}

closeDBConnection($conn);
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
            max-width: 600px;
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

        .info-message {
            background: #e3f2fd;
            color: #1565c0;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .verification-status {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-icon {
            font-size: 48px;
        }

        .status-text h3 {
            color: #333;
            margin-bottom: 5px;
        }

        .status-text p {
            color: #666;
            font-size: 14px;
        }

        .btn-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .benefits {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .benefits h4 {
            color: #333;
            margin-bottom: 15px;
        }

        .benefits ul {
            margin-left: 20px;
            color: #666;
        }

        .benefits li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Email Verification</h1>
        <div class="navbar-user">
            <a href="profile.php" class="btn-back">Back to Profile</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($info): ?>
            <div class="info-message"><?php echo $info; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Verify Your Email Address</h2>

            <div class="verification-status">
                <?php if ($current_user['email_verified'] == 1): ?>
                    <div class="status-icon">✅</div>
                    <div class="status-text">
                        <h3>Email Verified</h3>
                        <p>Your email address has been verified successfully!</p>
                    </div>
                <?php else: ?>
                    <div class="status-icon">⚠️</div>
                    <div class="status-text">
                        <h3>Email Not Verified</h3>
                        <p>Please verify your email address: <?php echo htmlspecialchars($current_user['email']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($current_user['email_verified'] != 1): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="send_verification">
                    <button type="submit" class="btn-submit">Send Verification Email</button>
                </form>

                <div class="benefits">
                    <h4>Why verify your email?</h4>
                    <ul>
                        <li>Secure your account</li>
                        <li>Receive important notifications</li>
                        <li>Reset your password if you forget it</li>
                        <li>Unlock additional features</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="color: #333; margin-bottom: 15px;">Note for Development</h3>
            <p style="color: #666; line-height: 1.6;">
                In a production environment, verification emails would be sent automatically to your registered email address.
                For this demo, the verification link is displayed above. Click the link to verify your email.
            </p>
            <p style="color: #666; margin-top: 10px; line-height: 1.6;">
                <strong>To enable email sending:</strong> Configure SMTP settings in your PHP configuration or use a service like SendGrid, Mailgun, or PHPMailer.
            </p>
        </div>
    </div>
</body>
</html>
