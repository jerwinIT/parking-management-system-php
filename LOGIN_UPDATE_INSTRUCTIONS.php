<?php
/**
 * LOGIN PAGE UPDATE - Email Verification Check
 * 
 * INSTRUCTIONS:
 * Add this code to your login.php after password verification and before setting session
 * 
 * Find this section in your login.php:
 *     if (password_verify($password, $user['password'])) {
 *         // Login successful
 *         $_SESSION['user_id'] = $user['id'];
 *         $_SESSION['username'] = $user['username'];
 *         ...
 * 
 * Replace with this:
 */

// After password verification succeeds, add email verification check:
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

/**
 * Also update the SELECT query to include email_verified:
 * 
 * FROM:
 *     $stmt = $pdo->prepare('SELECT id, username, password, full_name, role_id FROM users WHERE username = ? OR email = ?');
 * 
 * TO:
 *     $stmt = $pdo->prepare('SELECT id, username, password, full_name, role_id, email_verified FROM users WHERE username = ? OR email = ?');
 */
