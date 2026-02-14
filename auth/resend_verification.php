<!-- <?php
/**
 * Resend Email Verification
 * Place this file in: /auth/resend_verification.php
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';

$error = '';
$success = '';
$debug_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDB();
        $email_lower = strtolower($email);
        
        // Find user
        $stmt = $pdo->prepare('SELECT id, full_name, email_verified FROM users WHERE LOWER(email) = ?');
        $stmt->execute([$email_lower]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'No account found with this email address.';
        } elseif ($user['email_verified'] == 1) {
            $error = 'Your email is already verified. You can log in.';
        } else {
            // Generate new token
            $verification_token = bin2hex(random_bytes(32));
            $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Update token
            $stmt = $pdo->prepare('UPDATE users SET verification_token = ?, verification_token_expires = ? WHERE id = ?');
            $stmt->execute([$verification_token, $token_expires, $user['id']]);
            
            // Send email
            require_once dirname(__DIR__) . '/config/email_helper.php';
            $email_sent = sendVerificationEmail($email_lower, $user['full_name'], $verification_token);
            
            if ($email_sent) {
                $success = 'Verification email sent! Please check your inbox.';
            } else {
                $error = 'Failed to send email. Please try again or contact support.';
                // For development environments where mail() or SMTP isn't configured,
                // expose the verification link so the developer can complete verification.
                $debug_link = rtrim(BASE_URL, '/') . '/auth/verify.php?token=' . urlencode($verification_token);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap" rel="stylesheet">
    <style>
        body { 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%); 
            padding: 2rem 0; 
            font-family: 'Google Sans Flex', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
        }
        .resend-card { 
            border-radius: 1rem; 
            box-shadow: 0 1rem 3rem rgba(0,0,0,.15); 
        }
        .btn-primary { 
            background: #16a34a; 
            border-color: #16a34a; 
        }
        .btn-primary:hover { 
            background: #22c55e; 
            border-color: #22c55e; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card resend-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" 
                                 style="width: 64px; height: 64px; background: #dcfce7;">
                                <i class="bi bi-envelope-check text-success" style="font-size: 2rem;"></i>
                            </div>
                            <h4>Resend Verification Email</h4>
                            <p class="text-muted">Enter your email to receive a new verification link</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($debug_link)): ?>
                            <div class="alert alert-info">
                                <strong>Development link:</strong>
                                <p style="margin:0; word-break:break-all;"><a href="<?= htmlspecialchars($debug_link) ?>"><?= htmlspecialchars($debug_link) ?></a></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?= BASE_URL ?>/auth/resend_verification.php">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="your@email.com" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-send me-2"></i>Send Verification Email
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="<?= BASE_URL ?>/auth/login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->
