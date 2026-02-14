<?php
/**
 * Email Helper Functions
 * Place this file in: /config/email_helper.php
 */

/**
 * Send verification email to user
 * @param string $email User's email address
 * @param string $full_name User's full name
 * @param string $token Verification token
 * @return bool Success status
 */
function sendVerificationEmail($email, $full_name, $token) {
    $verification_link = BASE_URL . '/auth/verify.php?token=' . urlencode($token);
    
    $subject = 'Verify Your Email - Parking Management System';
    
    // HTML Email Template
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%); padding: 40px 30px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; }
            .content { padding: 40px 30px; }
            .content h2 { color: #15803d; margin-top: 0; font-size: 24px; }
            .content p { color: #4b5563; font-size: 16px; margin: 16px 0; }
            .button { display: inline-block; padding: 14px 32px; background: #16a34a; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 24px 0; }
            .button:hover { background: #15803d; }
            .footer { background: #f9fafb; padding: 30px; text-align: center; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; }
            .token-box { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center; font-family: monospace; font-size: 18px; letter-spacing: 2px; color: #1f2937; }
            .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 24px 0; border-radius: 4px; }
            .warning p { margin: 0; color: #92400e; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🅿️ Parking Management System</h1>
            </div>
            <div class="content">
                <h2>Welcome, ' . htmlspecialchars($full_name) . '! 👋</h2>
                <p>Thank you for registering with our Parking Management System. To complete your registration and activate your account, please verify your email address.</p>
                
                <p style="text-align: center;">
                    <a href="' . $verification_link . '" class="button">Verify Email Address</a>
                </p>
                
                <p>Or copy and paste this link into your browser:</p>
                <div class="token-box">' . htmlspecialchars($verification_link) . '</div>
                
                <div class="warning">
                    <p><strong>⏰ Important:</strong> This verification link will expire in 24 hours.</p>
                </div>
                
                <p>If you did not create an account, please ignore this email and the account will not be activated.</p>
            </div>
            <div class="footer">
                <p><strong>Parking Management System</strong></p>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' Parking Management System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: Parking System <noreply@parkingsystem.com>" . "\r\n";
    $headers .= "Reply-To: support@parkingsystem.com" . "\r\n";
    
    // Send email
    return mail($email, $subject, $message, $headers);
}

/**
 * Send password reset email
 * @param string $email User's email address
 * @param string $full_name User's full name
 * @param string $token Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($email, $full_name, $token) {
    $reset_link = BASE_URL . '/auth/reset_password.php?token=' . urlencode($token);
    
    $subject = 'Password Reset Request - Parking Management System';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%); padding: 40px 30px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; }
            .content { padding: 40px 30px; }
            .content h2 { color: #15803d; margin-top: 0; }
            .button { display: inline-block; padding: 14px 32px; background: #16a34a; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 24px 0; }
            .footer { background: #f9fafb; padding: 30px; text-align: center; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; }
            .warning { background: #fee2e2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🅿️ Parking Management System</h1>
            </div>
            <div class="content">
                <h2>Password Reset Request</h2>
                <p>Hello ' . htmlspecialchars($full_name) . ',</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                
                <p style="text-align: center;">
                    <a href="' . $reset_link . '" class="button">Reset Password</a>
                </p>
                
                <div class="warning">
                    <p><strong>⚠️ Security Notice:</strong> This link will expire in 1 hour.</p>
                </div>
                
                <p>If you did not request a password reset, please ignore this email and your password will remain unchanged.</p>
            </div>
            <div class="footer">
                <p><strong>Parking Management System</strong></p>
                <p>&copy; ' . date('Y') . ' All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: Parking System <noreply@parkingsystem.com>" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Generate secure random token
 * @return string 64-character token
 */
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}
