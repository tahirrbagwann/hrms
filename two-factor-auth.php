<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';
$backup_codes = [];

// Handle enable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = $_SESSION['user_id'];

    if ($_POST['action'] === 'enable_2fa') {
        // Generate a simple secret (in production, use Google Authenticator compatible secret)
        $secret = bin2hex(random_bytes(16));

        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
        $stmt->bind_param("si", $secret, $user_id);

        if ($stmt->execute()) {
            // Generate backup codes
            for ($i = 0; $i < 10; $i++) {
                $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $backup_codes[] = $code;

                $code_stmt = $conn->prepare("INSERT INTO two_factor_backup_codes (user_id, code) VALUES (?, ?)");
                $code_stmt->bind_param("is", $user_id, $code);
                $code_stmt->execute();
                $code_stmt->close();
            }

            $success = 'Two-factor authentication enabled successfully!';
            $current_user = getCurrentUser($conn);

            // Log activity
            $activity_type = '2fa_enabled';
            $description = 'Two-factor authentication enabled';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = 'Failed to enable 2FA. Please try again.';
        }

        $stmt->close();
    } elseif ($_POST['action'] === 'disable_2fa') {
        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            // Delete backup codes
            $conn->query("DELETE FROM two_factor_backup_codes WHERE user_id = $user_id");

            $success = 'Two-factor authentication disabled.';
            $current_user = getCurrentUser($conn);

            // Log activity
            $activity_type = '2fa_disabled';
            $description = 'Two-factor authentication disabled';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = 'Failed to disable 2FA. Please try again.';
        }

        $stmt->close();
    }
}

// Get existing backup codes if 2FA is enabled
if ($current_user['two_factor_enabled'] == 1 && empty($backup_codes)) {
    $codes_result = $conn->query("SELECT code, used FROM two_factor_backup_codes WHERE user_id = " . $_SESSION['user_id']);
    while ($row = $codes_result->fetch_assoc()) {
        $backup_codes[] = $row;
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - HRMS</title>
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
            max-width: 700px;
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

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-enabled {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-disabled {
            background: #ffebee;
            color: #c62828;
        }

        .btn-primary {
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

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            background: #c82333;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info-box h4 {
            color: #333;
            margin-bottom: 15px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #666;
        }

        .info-box li {
            margin: 8px 0;
        }

        .backup-codes {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 20px 0;
        }

        .backup-code {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            border: 2px solid #dee2e6;
        }

        .backup-code.used {
            opacity: 0.5;
            text-decoration: line-through;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .warning-box strong {
            color: #856404;
        }

        .warning-box p {
            color: #856404;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Two-Factor Authentication (2FA)</h1>
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

        <div class="card">
            <h2>Two-Factor Authentication Status</h2>

            <?php if ($current_user['two_factor_enabled'] == 1): ?>
                <span class="status-badge status-enabled">✓ Enabled</span>
                <p style="color: #666; margin-bottom: 20px;">
                    Your account is protected with two-factor authentication.
                </p>

                <form method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.');">
                    <input type="hidden" name="action" value="disable_2fa">
                    <button type="submit" class="btn-danger">Disable 2FA</button>
                </form>
            <?php else: ?>
                <span class="status-badge status-disabled">✗ Disabled</span>
                <p style="color: #666; margin-bottom: 20px;">
                    Enable two-factor authentication to add an extra layer of security to your account.
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="enable_2fa">
                    <button type="submit" class="btn-primary">Enable 2FA</button>
                </form>
            <?php endif; ?>

            <div class="info-box">
                <h4>What is Two-Factor Authentication?</h4>
                <ul>
                    <li>Adds an extra layer of security to your account</li>
                    <li>Requires a verification code in addition to your password</li>
                    <li>Protects against unauthorized access even if your password is compromised</li>
                    <li>Industry-standard security practice</li>
                </ul>
            </div>
        </div>

        <?php if ($current_user['two_factor_enabled'] == 1 && !empty($backup_codes)): ?>
        <div class="card">
            <h2>Backup Codes</h2>

            <div class="warning-box">
                <strong>⚠️ Important:</strong>
                <p>Save these backup codes in a secure place. You can use them to access your account if you lose access to your authentication device.</p>
            </div>

            <div class="backup-codes">
                <?php foreach ($backup_codes as $code): ?>
                    <?php if (is_array($code)): ?>
                        <div class="backup-code <?php echo $code['used'] ? 'used' : ''; ?>">
                            <?php echo htmlspecialchars($code['code']); ?>
                        </div>
                    <?php else: ?>
                        <div class="backup-code">
                            <?php echo htmlspecialchars($code); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <p style="color: #666; font-size: 13px; margin-top: 15px;">
                Each backup code can only be used once. Codes marked with strikethrough have been used.
            </p>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="color: #333; margin-bottom: 15px;">Implementation Note</h3>
            <p style="color: #666; line-height: 1.6;">
                This is a basic 2FA implementation. In a production environment, you would integrate with:
            </p>
            <ul style="margin: 15px 0 15px 20px; color: #666;">
                <li>Google Authenticator or similar TOTP apps</li>
                <li>SMS-based verification codes</li>
                <li>Hardware security keys (FIDO2/WebAuthn)</li>
                <li>Email-based verification codes</li>
            </ul>
            <p style="color: #666; line-height: 1.6;">
                Libraries like <strong>php-otp</strong> or <strong>google2fa</strong> can be used for proper TOTP implementation.
            </p>
        </div>
    </div>
</body>
</html>
