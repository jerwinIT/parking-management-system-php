# Email Verification System - Installation Guide

## 📋 Overview
This email verification system ensures all new users verify their email addresses before accessing the parking management system.

---

## 🗄️ Step 1: Update Database

Run the SQL migration to add verification columns to your `users` table:

```sql
-- Run this in your MySQL/phpMyAdmin
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER email,
ADD COLUMN verification_token VARCHAR(64) NULL AFTER email_verified,
ADD COLUMN verification_token_expires DATETIME NULL AFTER verification_token;

ALTER TABLE users 
ADD INDEX idx_verification_token (verification_token);

-- Optional: Set existing users as verified (so they can still log in)
UPDATE users SET email_verified = 1 WHERE email_verified = 0;
```

**File:** `email_verification_migration.sql`

---

## 📁 Step 2: Install Files

### 1. Email Helper Functions
**Location:** `/config/email_helper.php`
**File:** `email_helper.php`
Contains functions for sending verification emails and generating tokens.

### 2. Updated Registration Page
**Location:** `/auth/register.php`
**File:** `register.php`
Replaces your existing register.php - creates unverified accounts and sends verification emails.

### 3. Email Verification Page
**Location:** `/auth/verify.php`
**File:** `verify.php` (NEW)
Users click the link in their email and land here to verify their account.

### 4. Resend Verification Page
**Location:** `/auth/resend_verification.php`
**File:** `resend_verification.php` (NEW)
Allows users to request a new verification email if their link expired.

---

## 🔧 Step 3: Update Login Page

Open your existing `/auth/login.php` and make these changes:

### A. Update the SELECT query to include email_verified:

**Find:**
```php
$stmt = $pdo->prepare('SELECT id, username, password, full_name, role_id FROM users WHERE username = ? OR email = ?');
```

**Replace with:**
```php
$stmt = $pdo->prepare('SELECT id, username, password, full_name, role_id, email_verified FROM users WHERE username = ? OR email = ?');
```

### B. Add email verification check after password verification:

**Find:**
```php
if (password_verify($password, $user['password'])) {
    // Login successful
    $_SESSION['user_id'] = $user['id'];
    // ... rest of login code
```

**Replace with:**
```php
if (password_verify($password, $user['password'])) {
    // Check if email is verified
    if (isset($user['email_verified']) && $user['email_verified'] == 0) {
        $error = 'Please verify your email address before logging in. <a href="' . BASE_URL . '/auth/resend_verification.php">Resend verification email</a>';
    } else {
        // Login successful - email is verified
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        
        // Redirect based on role
        if ($user['role_id'] == 1) {
            header('Location: ' . BASE_URL . '/admin/monitor.php');
        } else {
            header('Location: ' . BASE_URL . '/index.php');
        }
        exit;
    }
} else {
    $error = 'Invalid username or password.';
}
```

**Reference file:** `LOGIN_UPDATE_INSTRUCTIONS.php`

---

## 📧 Step 4: Configure Email Settings

### For Development (Testing):
PHP's `mail()` function may not work on localhost. Options:

1. **Use a fake SMTP service** (recommended for testing):
   - [Mailtrap.io](https://mailtrap.io) - Free testing
   - [MailHog](https://github.com/mailhog/MailHog) - Local SMTP

2. **Configure PHP to use Gmail SMTP:**
   Update `php.ini`:
   ```ini
   [mail function]
   SMTP = smtp.gmail.com
   smtp_port = 587
   sendmail_from = your-email@gmail.com
   ```

### For Production:
Use a transactional email service:
- **SendGrid** (free tier: 100 emails/day)
- **Mailgun** (free tier: 5,000 emails/month)
- **AWS SES** (very cheap)
- **Postmark** (100 emails/month free)

To integrate these, update the `sendVerificationEmail()` function in `email_helper.php`.

---

## 🧪 Step 5: Test the System

### Test Flow:
1. ✅ Register new account with valid email
2. ✅ Check you receive verification email
3. ✅ Click verification link
4. ✅ Try to login before verifying (should be blocked)
5. ✅ Verify email
6. ✅ Login successfully
7. ✅ Test expired token (wait 24 hours or manually expire in database)
8. ✅ Test resend verification

### Test Accounts:
```
Username: testuser
Email: youremail@gmail.com (use your real email)
Password: Test123!@#
```

---

## 🎨 Customization

### Change Token Expiration:
In `register.php` and `resend_verification.php`, find:
```php
$token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
```
Change `+24 hours` to your desired time (e.g., `+1 hour`, `+7 days`).

### Change Email Template:
Edit `email_helper.php` → `sendVerificationEmail()` function.
Customize colors, text, branding.

### Add SMS Verification:
Integrate Twilio or similar service to send SMS codes to phone numbers.

---

## 🐛 Troubleshooting

### Emails Not Sending?
1. Check PHP `mail()` is configured
2. Check spam/junk folder
3. Use Mailtrap.io for testing
4. Check server error logs: `/var/log/apache2/error.log`

### "Invalid verification link" error?
1. Token may have expired (24 hours)
2. User may have already verified
3. Check database `verification_token` column

### Users can't login after registering?
1. Ensure database migration ran successfully
2. Check `email_verified` column exists
3. Set existing users to verified: `UPDATE users SET email_verified = 1`

---

## 🔒 Security Features

✅ **Secure tokens** - 64-character random hex tokens
✅ **Token expiration** - Links expire after 24 hours
✅ **One-time use** - Tokens cleared after verification
✅ **Case-insensitive emails** - Prevents duplicate accounts
✅ **Strong password requirements** - 8+ chars, mixed case, numbers, symbols
✅ **SQL injection protection** - Prepared statements
✅ **XSS protection** - htmlspecialchars() on all output

---

## 📝 Summary

**New Database Columns:**
- `email_verified` (0 or 1)
- `verification_token` (64 chars)
- `verification_token_expires` (datetime)

**New Files:**
- `/config/email_helper.php`
- `/auth/verify.php`
- `/auth/resend_verification.php`

**Updated Files:**
- `/auth/register.php` (replaced)
- `/auth/login.php` (updated)

---

## 🎯 Next Steps

After installation:
1. Test with a real email address
2. Configure production email service
3. Customize email templates with your branding
4. Add password reset functionality (similar pattern)
5. Consider adding 2FA for admin accounts

---

## 💡 Optional Enhancements

- **Email change verification** - Verify new email when users update
- **SMS verification** - Add phone number verification
- **2FA** - Two-factor authentication for admins
- **Password reset** - Forgot password feature
- **Account activation delay** - Require admin approval for new accounts
- **Email templates** - Use professional email template builder

---

**Need Help?** Check the troubleshooting section or contact support.

**Version:** 1.0
**Last Updated:** 2025
