<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name']);
    $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone']);

    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $full_name, $phone, $address, $emergency_contact_name, $emergency_contact_phone, $user_id);

    if ($stmt->execute()) {
        $success = 'Profile updated successfully!';
        $_SESSION['full_name'] = $full_name;
        // Refresh user data
        $current_user = getCurrentUser($conn);
    } else {
        $error = 'Failed to update profile. Please try again.';
    }

    $stmt->close();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_picture') {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        } elseif ($file_size > $max_size) {
            $error = 'File size exceeds 2MB limit.';
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($current_user['profile_picture']) && file_exists($current_user['profile_picture'])) {
                    unlink($current_user['profile_picture']);
                }

                // Update database
                $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $upload_path, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    $success = 'Profile picture updated successfully!';
                    $current_user = getCurrentUser($conn);
                } else {
                    $error = 'Failed to update profile picture in database.';
                }

                $stmt->close();
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HRMS</title>
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
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-picture-container {
            position: relative;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }

        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .profile-info h2 {
            color: #333;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: #666;
            margin: 3px 0;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
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

        .btn-secondary {
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .upload-area {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>My Profile</h1>
        <div class="navbar-user">
            <?php if (isAdmin()): ?>
                <a href="admin/dashboard.php" class="btn-back">Back to Dashboard</a>
            <?php else: ?>
                <a href="employee/dashboard.php" class="btn-back">Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-picture-container">
                <?php if (!empty($current_user['profile_picture']) && file_exists($current_user['profile_picture'])): ?>
                    <img src="<?php echo $current_user['profile_picture']; ?>" alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <h2><?php echo htmlspecialchars($current_user['full_name']); ?></h2>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($current_user['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($current_user['email']); ?></p>
                <p><span class="badge"><?php echo ucfirst($current_user['role_name']); ?></span></p>
            </div>
        </div>

        <!-- Profile Picture Upload -->
        <div class="card">
            <h3>Profile Picture</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_picture">
                <div class="upload-area">
                    <input type="file" name="profile_picture" accept="image/*" required>
                    <p style="margin-top: 10px; color: #666; font-size: 13px;">Max size: 2MB. Formats: JPG, PNG, GIF</p>
                </div>
                <button type="submit" class="btn-submit">Upload Picture</button>
            </form>
        </div>

        <!-- Personal Information -->
        <div class="card">
            <h3>Personal Information</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email (Read-only)</label>
                        <input type="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" readonly style="background: #f5f5f5;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($current_user['address'] ?? ''); ?></textarea>
                </div>

                <h4 style="margin: 30px 0 15px 0; color: #333;">Emergency Contact</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($current_user['emergency_contact_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_phone">Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($current_user['emergency_contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Update Profile</button>
            </form>
        </div>

        <!-- Security Settings -->
        <div class="card">
            <h3>Security Settings</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="change-password.php" class="btn-secondary">Change Password</a>
                <a href="two-factor-auth.php" class="btn-secondary">Two-Factor Authentication</a>
            </div>
        </div>
    </div>
</body>
</html>
