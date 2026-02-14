<!-- <?php
/**
 * Email Verification Page
 * Place this file in: /auth/verify.php
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';

$token = $_GET['token'] ?? '';
$message = '';
$message_type = 'danger';

if (empty($token)) {
    $message = 'Invalid verification link.';
} else {
    $pdo = getDB();
    
    // Find user with this token
    $stmt = $pdo->prepare('SELECT id, email, full_name, email_verified, verification_token_expires FROM users WHERE verification_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $message = 'Invalid or expired verification link.';
    } elseif ($user['email_verified'] == 1) {
        $message = 'Your email is already verified. You can log in now.';
        $message_type = 'info';
    } elseif (strtotime($user['verification_token_expires']) < time()) {
        $message = 'This verification link has expired. Please request a new one.';
        $show_resend = true;
    } else {
        // Verify the account
        $stmt = $pdo->prepare('UPDATE users SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?');
        $stmt->execute([$user['id']]);
        
        $message = 'Email verified successfully! You can now log in.';
        $message_type = 'success';
        $show_login = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Parking Management System</title>
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
        .verify-card { 
            border-radius: 1rem; 
            box-shadow: 0 1rem 3rem rgba(0,0,0,.15); 
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
        }
        .icon-circle.success {
            background: #dcfce7;
            color: #16a34a;
        }
        .icon-circle.danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .icon-circle.info {
            background: #dbeafe;
            color: #2563eb;
        }
        .btn-primary { 
            background: #16a34a; 
            border-color: #16a34a; 
            padding: 0.75rem 2rem;
        }
        .btn-primary:hover { 
            background: #15803d; 
            border-color: #15803d; 
        }
        .btn-outline-primary {
            color: #16a34a;
            border-color: #16a34a;
            padding: 0.75rem 2rem;
        }
        .btn-outline-primary:hover {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card verify-card">
                    <div class="card-body p-5 text-center">
                        <?php if ($message_type === 'success'): ?>
                            <div class="icon-circle success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        <?php elseif ($message_type === 'info'): ?>
                            <div class="icon-circle info">
                                <i class="bi bi-info-circle-fill"></i>
                            </div>
                        <?php else: ?>
                            <div class="icon-circle danger">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="mb-3">
                            <?php if ($message_type === 'success'): ?>
                                Email Verified!
                            <?php elseif ($message_type === 'info'): ?>
                                Already Verified
                            <?php else: ?>
                                Verification Failed
                            <?php endif; ?>
                        </h3>
                        
                        <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                        
                        <div class="d-flex gap-2 justify-content-center">
                            <?php if (isset($show_login)): ?>
                                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                                </a>
                            <?php elseif (isset($show_resend)): ?>
                                <a href="<?= BASE_URL ?>/auth/resend_verification.php" class="btn btn-primary">
                                    <i class="bi bi-envelope me-2"></i>Resend Verification
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary">
                                <i class="bi bi-house me-2"></i>Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->
