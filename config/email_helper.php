<!-- <?php
/**
 * Email Helper Functions
 * Primary implementation for sending verification and password emails.
 */
if (!defined('PARKING_ACCESS')) exit;

// If Composer autoload is present, load it so PHPMailer can be used
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

/**
 * Send verification email to user
 * @param string $email User's email address
 * @param string $full_name User's full name
 * @param string $token Verification token
 * @return bool Success status
 */
function sendVerificationEmail($email, $full_name, $token) {
    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $email");
        return false;
    }
    
    $verification_link = rtrim(BASE_URL, '/') . '/auth/verify.php?token=' . urlencode($token);
    $subject = 'Verify Your Email - Parking Management System';
    
    // HTML Email Template
    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;background-color:#f5f5f5}.container{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#15803d 0%,#16a34a 50%,#22c55e 100%);padding:40px 30px;text-align:center}.header h1{color:#fff;margin:0;font-size:28px;font-weight:700}.content{padding:40px 30px}.content h2{color:#15803d;margin-top:0;font-size:24px}.content p{color:#4b5563;font-size:16px;margin:16px 0}.button{display:inline-block;padding:14px 32px;background:#16a34a;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;margin:24px 0}.footer{background:#f9fafb;padding:30px;text-align:center;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb}.token-box{background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:16px;margin:24px 0;text-align:center;font-family:monospace;font-size:18px;letter-spacing:2px;color:#1f2937;word-break:break-all}.warning{background:#fef3c7;border-left:4px solid #f59e0b;padding:16px;margin:24px 0;border-radius:4px}.warning p{margin:0;color:#92400e;font-size:14px}</style></head><body><div class="container"><div class="header"><h1>🅿️ Parking Management System</h1></div><div class="content"><h2>Welcome, ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . '! 👋</h2><p>Thank you for registering with our Parking Management System. To complete your registration and activate your account, please verify your email address.</p><p style="text-align:center"><a href="' . htmlspecialchars($verification_link, ENT_QUOTES, 'UTF-8') . '" class="button">Verify Email Address</a></p><p>Or copy and paste this link into your browser:</p><div class="token-box">' . htmlspecialchars($verification_link, ENT_QUOTES, 'UTF-8') . '</div><div class="warning"><p><strong>⏰ Important:</strong> This verification link will expire in 24 hours.</p></div><p>If you did not create an account, please ignore this email and the account will not be activated.</p></div><div class="footer"><p><strong>Parking Management System</strong></p><p>This is an automated message. Please do not reply to this email.</p><p>&copy; ' . date('Y') . ' Parking Management System. All rights reserved.</p></div></div></body></html>';
    
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@parkingsystem.com';
    $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'support@parkingsystem.com';

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: Parking System <" . $from . ">" . "\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Prefer PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            // Use SMTP if configured
            if (defined('SMTP_HOST') && SMTP_HOST) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
                if (defined('SMTP_USER') && SMTP_USER) {
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS ?? '';
                }
                if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION) {
                    $enc = SMTP_ENCRYPTION;
                    // PHPMailer expects 'ssl' or 'tls'
                    $mail->SMTPSecure = $enc;
                }
            }
            $mail->setFrom($from, 'Parking System');
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>'], "\n", $message));
            return $mail->send();
        } catch (Exception $e) {
            // fallback to socket SMTP or mail()
        }
    }

    // If PHPMailer not available, try custom SMTP helper when configured
    if (defined('SMTP_HOST') && SMTP_HOST) {
        $smtp = [
            'host' => SMTP_HOST,
            'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
            'user' => defined('SMTP_USER') ? SMTP_USER : null,
            'pass' => defined('SMTP_PASS') ? SMTP_PASS : null,
            'encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : null,
        ];
        return smtp_send($email, $subject, $message, $from, $headers, $smtp);
    }

    // Final fallback to PHP mail()
    return mail($email, $subject, $message, $headers);
}

/**
 * Basic SMTP send implementation using sockets. Supports STARTTLS and SSL.
 * Returns true on success, false on failure.
 */
function smtp_send($to, $subject, $body, $from, $headers, $smtp)
{
    $host = $smtp['host'];
    $port = $smtp['port'] ?? 587;
    $user = $smtp['user'] ?? null;
    $pass = $smtp['pass'] ?? null;
    $encryption = $smtp['encryption'] ?? 'tls'; // 'tls' or 'ssl' or null

    $transport = ($encryption === 'ssl') ? 'ssl://' : '';
    $errno = 0; 
    $errstr = '';
    
    // Suppress warnings and use error_log instead
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 30);
    if (!$fp) {
        error_log("SMTP Connection failed: $errstr ($errno)");
        return false;
    }

    // Read initial server greeting
    $res = fgets($fp, 515);
    if (strpos($res, '220') === false) {
        error_log("SMTP Error: Invalid server greeting - $res");
        fclose($fp);
        return false;
    }

    $send = function($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
        $response = fgets($fp, 515);
        // Read multiline responses (lines ending with -)
        while ($response && isset($response[3]) && $response[3] === '-') {
            $response = fgets($fp, 515);
        }
        return $response;
    };

    // Send EHLO
    $ehlo = 'EHLO ' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
    $res = $send($ehlo);
    if (strpos($res, '250') === false) {
        error_log("SMTP Error: EHLO failed - $res");
        fclose($fp);
        return false;
    }

    // Handle STARTTLS
    if ($encryption === 'tls') {
        $res = $send('STARTTLS');
        if (strpos($res, '220') === false) {
            error_log("SMTP Error: STARTTLS failed - $res");
            fclose($fp);
            return false;
        }
        
        // Enable TLS encryption
        $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            error_log("SMTP Error: TLS encryption failed");
            fclose($fp);
            return false;
        }
        
        // Send EHLO again after STARTTLS
        $res = $send($ehlo);
        if (strpos($res, '250') === false) {
            error_log("SMTP Error: EHLO after STARTTLS failed - $res");
            fclose($fp);
            return false;
        }
    }

    // Authenticate if credentials provided
    if ($user && $pass) {
        $res = $send('AUTH LOGIN');
        if (strpos($res, '334') === false) {
            error_log("SMTP Error: AUTH LOGIN failed - $res");
            fclose($fp);
            return false;
        }
        
        $res = $send(base64_encode($user));
        if (strpos($res, '334') === false) {
            error_log("SMTP Error: Username authentication failed - $res");
            fclose($fp);
            return false;
        }
        
        $res = $send(base64_encode($pass));
        if (strpos($res, '235') === false) {
            error_log("SMTP Error: Password authentication failed - $res");
            fclose($fp);
            return false;
        }
    }

    // Extract email from "Name <email>" format if needed
    $fromEmail = $from;
    if (preg_match('/<(.+?)>/', $from, $matches)) {
        $fromEmail = $matches[1];
    }
    
    // MAIL FROM
    $res = $send('MAIL FROM: <' . $fromEmail . '>');
    if (strpos($res, '250') === false) {
        error_log("SMTP Error: MAIL FROM failed - $res");
        fclose($fp);
        return false;
    }
    
    // RCPT TO
    $res = $send('RCPT TO: <' . $to . '>');
    if (strpos($res, '250') === false) {
        error_log("SMTP Error: RCPT TO failed - $res");
        fclose($fp);
        return false;
    }
    
    // DATA command
    $res = $send('DATA');
    if (strpos($res, '354') === false) {
        error_log("SMTP Error: DATA command failed - $res");
        fclose($fp);
        return false;
    }

    // Construct full message with headers
    $fullMessage = "Subject: " . $subject . "\r\n" . $headers . "\r\n\r\n" . $body;
    
    // Send message body with proper dot-stuffing
    $lines = explode("\r\n", $fullMessage);
    foreach ($lines as $line) {
        // Dot-stuffing: prepend a dot to lines that start with a dot
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
        fwrite($fp, $line . "\r\n");
    }
    
    // End DATA with <CRLF>.<CRLF>
    fwrite($fp, ".\r\n");
    $res = fgets($fp, 515);
    if (strpos($res, '250') === false) {
        error_log("SMTP Error: Message send failed - $res");
        fclose($fp);
        return false;
    }
    
    // QUIT
    $send('QUIT');
    fclose($fp);
    
    return true;
}

/**
 * Send password reset email
 * @param string $email User's email address
 * @param string $full_name User's full name
 * @param string $token Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($email, $full_name, $token) {
    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $email");
        return false;
    }
    
    $reset_link = rtrim(BASE_URL, '/') . '/auth/reset_password.php?token=' . urlencode($token);
    $subject = 'Password Reset Request - Parking Management System';
    
    // HTML Email Template
    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;background-color:#f5f5f5}.container{max-width:600px;margin:0 auto;background:#fff}.header{background:linear-gradient(135deg,#15803d 0%,#16a34a 50%,#22c55e 100%);padding:40px 30px;text-align:center}.header h1{color:#fff;margin:0;font-size:28px;font-weight:700}.content{padding:40px 30px}.content h2{color:#15803d;margin-top:0;font-size:24px}.content p{color:#4b5563;font-size:16px;margin:16px 0}.button{display:inline-block;padding:14px 32px;background:#16a34a;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;margin:24px 0}.footer{background:#f9fafb;padding:30px;text-align:center;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb}.token-box{background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:16px;margin:24px 0;text-align:center;font-family:monospace;font-size:14px;word-break:break-all;color:#1f2937}.warning{background:#fee2e2;border-left:4px solid #dc2626;padding:16px;margin:24px 0;border-radius:4px}.warning p{margin:0;color:#991b1b;font-size:14px}</style></head><body><div class="container"><div class="header"><h1>🅿️ Parking Management System</h1></div><div class="content"><h2>Password Reset Request</h2><p>Hello ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . ',</p><p>We received a request to reset the password for your account. Click the button below to reset your password:</p><p style="text-align:center"><a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '" class="button">Reset Password</a></p><p>Or copy and paste this link into your browser:</p><div class="token-box">' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '</div><div class="warning"><p><strong>⚠️ Security Notice:</strong> This password reset link will expire in 1 hour. If you did not request a password reset, please ignore this email and your password will remain unchanged.</p></div></div><div class="footer"><p><strong>Parking Management System</strong></p><p>This is an automated message. Please do not reply to this email.</p><p>&copy; ' . date('Y') . ' Parking Management System. All rights reserved.</p></div></div></body></html>';
    
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@parkingsystem.com';
    $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'support@parkingsystem.com';
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: Parking System <" . $from . ">" . "\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    if (defined('SMTP_HOST') && SMTP_HOST) {
        $smtp = [
            'host' => SMTP_HOST,
            'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
            'user' => defined('SMTP_USER') ? SMTP_USER : null,
            'pass' => defined('SMTP_PASS') ? SMTP_PASS : null,
            'encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
        ];
        return smtp_send($email, $subject, $message, $from, $headers, $smtp);
    }

    return mail($email, $subject, $message, $headers);
}

/**
 * Generate secure random token
 * @param int $length Token length in bytes (default 32)
 * @return string Hexadecimal token
 */
function generateSecureToken($length = 32) {
    if ($length < 16) {
        $length = 16; // Minimum 16 bytes for security
    }
    return bin2hex(random_bytes($length));
} -->