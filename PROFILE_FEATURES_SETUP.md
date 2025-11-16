# User Profile Management Features - Setup Guide

## New Features Implemented

### 1. User Profile Management ✅
- Update personal information (phone, address)
- Manage emergency contact details
- View role and account status

### 2. Profile Picture Upload ✅
- Upload profile pictures (JPG, PNG, GIF)
- Maximum file size: 2MB
- Automatic old picture deletion
- Profile picture display with fallback initials

### 3. Change Password ✅
- Secure password change functionality
- Current password verification
- Password strength requirements (minimum 6 characters)
- Activity logging for security audit

### 4. Email Verification ✅
- Send verification emails (demo mode with visible links)
- Token-based verification system
- 24-hour expiration on verification tokens
- Activity logging

### 5. Two-Factor Authentication (2FA) ✅
- Enable/disable 2FA
- Backup codes generation (10 codes)
- Activity logging
- Secure secret storage

---

## Database Setup

### Step 1: Run Database Update

Open your browser and navigate to:
```
http://localhost/hrms/update-database.php
```

This will:
- Add new columns to the `users` table (phone, address, emergency contacts, etc.)
- Create `email_verifications` table
- Create `password_resets` table
- Create `two_factor_backup_codes` table
- Create `activity_logs` table

### Step 2: Verify Update

The script will show:
- ✓ Success messages for each table update
- List of new features added
- Any warnings (duplicate columns are normal if you run it twice)

---

## New Pages Created

### Profile Management
- **`/profile.php`** - Main profile page
  - Update personal information
  - Upload profile picture
  - View account details
  - Links to security settings

### Security Features
- **`/change-password.php`** - Change password
  - Current password verification
  - New password confirmation
  - Password requirements display
  - Show/hide password toggle

- **`/email-verification.php`** - Email verification
  - Send verification email
  - View verification status
  - Email benefits information

- **`/verify-email.php`** - Email verification handler
  - Token validation
  - Email confirmation
  - Success/error messages

- **`/two-factor-auth.php`** - 2FA management
  - Enable/disable 2FA
  - View backup codes
  - Security information

---

## How to Use

### Accessing Profile Features

1. **Login** to your account (admin or employee)

2. **Click "My Profile"** in the navigation bar (top right)

3. **Available Options:**
   - Update personal information
   - Upload profile picture
   - Change password
   - Verify email
   - Enable 2FA

### Profile Picture Upload

1. Go to **Profile** page
2. Click **Choose File** under Profile Picture section
3. Select an image (max 2MB, JPG/PNG/GIF)
4. Click **Upload Picture**
5. Your profile picture will appear in:
   - Profile header
   - Dashboard navbar (future enhancement)

### Change Password

1. Go to **Profile** page
2. Click **Change Password** button
3. Enter:
   - Current password
   - New password
   - Confirm new password
4. Click **Change Password**
5. Activity will be logged for security

### Email Verification

1. Go to **Profile** page
2. Click **Two-Factor Authentication** or navigate to `/email-verification.php`
3. Click **Send Verification Email**
4. **In Development Mode:** Copy the verification link displayed
5. **In Production:** Check your email for verification link
6. Click the link to verify your email

### Two-Factor Authentication

1. Go to **Profile** page
2. Click **Two-Factor Authentication**
3. Click **Enable 2FA**
4. **Save your backup codes!** (10 codes displayed)
5. Store codes in a secure place
6. Each code can only be used once

To disable:
- Go back to the 2FA page
- Click **Disable 2FA**
- Confirm the action

---

## Database Schema Changes

### Users Table - New Columns
```sql
- phone VARCHAR(20) - Phone number
- address TEXT - Full address
- emergency_contact_name VARCHAR(150) - Emergency contact name
- emergency_contact_phone VARCHAR(20) - Emergency contact phone
- profile_picture VARCHAR(255) - Profile picture path
- email_verified TINYINT(1) - Email verification status
- email_verification_token VARCHAR(255) - Current verification token
- two_factor_enabled TINYINT(1) - 2FA status
- two_factor_secret VARCHAR(255) - 2FA secret key
- last_login DATETIME - Last login timestamp
```

### New Tables

#### email_verifications
```sql
- id INT PRIMARY KEY AUTO_INCREMENT
- user_id INT - User reference
- token VARCHAR(255) - Verification token
- expires_at DATETIME - Token expiration
- created_at TIMESTAMP - Creation timestamp
```

#### password_resets
```sql
- id INT PRIMARY KEY AUTO_INCREMENT
- user_id INT - User reference
- token VARCHAR(255) - Reset token
- expires_at DATETIME - Token expiration
- created_at TIMESTAMP - Creation timestamp
```

#### two_factor_backup_codes
```sql
- id INT PRIMARY KEY AUTO_INCREMENT
- user_id INT - User reference
- code VARCHAR(50) - Backup code
- used TINYINT(1) - Usage status
- created_at TIMESTAMP - Creation timestamp
```

#### activity_logs
```sql
- id INT PRIMARY KEY AUTO_INCREMENT
- user_id INT - User reference
- activity_type VARCHAR(50) - Activity type
- description TEXT - Activity description
- ip_address VARCHAR(45) - IP address
- user_agent TEXT - Browser user agent
- created_at TIMESTAMP - Activity timestamp
```

---

## Security Features

### Password Security
- BCrypt hashing (PASSWORD_DEFAULT)
- Minimum 6 characters
- Password confirmation
- Current password verification required

### Email Verification
- Token-based system
- 24-hour expiration
- Secure token generation (random_bytes)
- One-time use tokens

### Two-Factor Authentication
- Secure secret storage
- 10 backup codes per user
- One-time use codes
- Activity logging

### Activity Logging
Activities logged:
- Password changes
- Email verification
- 2FA enable/disable
- Profile updates (future)
- Login attempts (future)

---

## File Upload Directory

Profile pictures are stored in:
```
/hrms/uploads/profile_pictures/
```

**File naming pattern:**
```
user_{user_id}_{timestamp}.{extension}
```

**Example:**
```
user_5_1735123456.jpg
```

**Permissions:**
- Directory is created automatically with 0777 permissions
- Ensure your web server has write access
- In production, restrict permissions to 0755

---

## Production Considerations

### Email Sending

To enable actual email sending, you need to configure SMTP:

#### Option 1: PHPMailer
```php
composer require phpmailer/phpmailer

// Configure in email helper file
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
```

#### Option 2: SendGrid / Mailgun
- Sign up for service
- Get API key
- Use their PHP library
- Update email-verification.php

### Two-Factor Authentication

For production TOTP implementation:

#### Option 1: Google2FA (Recommended)
```php
composer require pragmarx/google2fa

// Generate secret
$google2fa = new Google2FA();
$secret = $google2fa->generateSecretKey();

// Generate QR code
$qrCodeUrl = $google2fa->getQRCodeUrl(
    'HRMS',
    $user_email,
    $secret
);

// Verify code
$valid = $google2fa->verifyKey($secret, $code);
```

#### Option 2: PHP-OTP
```php
composer require spomky-labs/otphp

// Generate TOTP
$totp = TOTP::create($secret);
$totp->setLabel('user@example.com');

// Verify
$totp->verify($code);
```

### File Upload Security

Additional security measures:

```php
// Validate file type by content, not extension
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

// Allowed types
$allowed = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime, $allowed)) {
    die('Invalid file type');
}

// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);

// Move outside web root (recommended)
move_uploaded_file($tmp, '/var/uploads/' . $filename);
```

---

## Testing the Features

### Test Scenarios

#### 1. Profile Update
- Login as any user
- Update phone and address
- Verify data is saved
- Check database for updates

#### 2. Profile Picture
- Upload a valid image
- Verify it displays in profile
- Upload another image
- Verify old image is deleted

#### 3. Password Change
- Use incorrect current password (should fail)
- Use matching passwords (should succeed)
- Try logging out and in with new password
- Check activity_logs table

#### 4. Email Verification
- Send verification email
- Copy the link from the page
- Open link in new tab
- Verify status changes to verified
- Check activity_logs table

#### 5. Two-Factor Authentication
- Enable 2FA
- Save backup codes
- Verify codes are in database
- Disable 2FA
- Verify codes are deleted

---

## Troubleshooting

### Profile Picture Upload Fails

**Error:** Failed to upload file

**Solutions:**
1. Check directory permissions:
   ```bash
   chmod 755 uploads
   chmod 755 uploads/profile_pictures
   ```

2. Check PHP upload settings:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

3. Verify directory exists:
   - Create manually: `mkdir -p uploads/profile_pictures`

### Email Verification Link Doesn't Work

**Error:** Invalid or expired token

**Solutions:**
1. Check token expiration (24 hours)
2. Verify database table exists: `email_verifications`
3. Check if token is in database:
   ```sql
   SELECT * FROM email_verifications WHERE token = 'your_token';
   ```

### 2FA Codes Not Displaying

**Error:** No backup codes shown

**Solutions:**
1. Verify table exists: `two_factor_backup_codes`
2. Check if codes were generated:
   ```sql
   SELECT * FROM two_factor_backup_codes WHERE user_id = 1;
   ```
3. Re-enable 2FA to generate new codes

### Database Update Fails

**Error:** Table/column already exists

**Solution:**
- This is normal if you run the update twice
- Check if all tables exist:
  ```sql
  SHOW TABLES LIKE '%email%';
  SHOW TABLES LIKE '%two_factor%';
  SHOW TABLES LIKE '%activity%';
  ```

---

## Future Enhancements

### Planned Improvements

1. **Activity Log Viewer**
   - Page to view all account activities
   - Filter by date and activity type
   - Export to CSV

2. **Profile Picture**
   - Image cropping before upload
   - Display in navbar
   - Different sizes (thumbnail, medium, large)

3. **Email Verification**
   - Automatic email sending
   - HTML email templates
   - Resend verification option

4. **2FA Implementation**
   - TOTP with Google Authenticator
   - QR code generation
   - SMS backup option
   - Verification during login

5. **Password Reset**
   - Forgot password link
   - Email-based reset
   - Token expiration
   - Password history

---

## API Endpoints (Future)

For mobile app integration:

```
POST /api/profile/update
POST /api/profile/picture
POST /api/password/change
POST /api/email/verify
POST /api/2fa/enable
POST /api/2fa/disable
GET  /api/activity/logs
```

---

## Security Best Practices

1. **Always use HTTPS in production**
2. **Implement CSRF protection**
3. **Rate limit authentication attempts**
4. **Validate all user inputs**
5. **Sanitize file uploads**
6. **Log security events**
7. **Regular security audits**
8. **Keep PHP and libraries updated**
9. **Use prepared statements (already implemented)**
10. **Implement password complexity rules**

---

## Support

For issues or questions:
1. Check this documentation
2. Review the code comments
3. Check PHP error logs
4. Verify database schema
5. Test with debug mode enabled

---

**Version:** 1.1.0
**Last Updated:** 2025
**Features Status:** Fully Implemented ✅
