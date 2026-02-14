<?php
/**
 * Login Page - Choose role (User or Admin) then sign in. Validates role matches account.
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
// Login security helper (locks after failed attempts)
require_once dirname(__DIR__) . '/models/LoginSecurity.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$success = isset($_GET['registered']);
$role = trim($_GET['role'] ?? $_POST['login_as'] ?? '');
$allowed_roles = ['user', 'admin'];
if (!in_array($role, $allowed_roles)) {
    $role = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_as = trim($_POST['login_as'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!in_array($login_as, $allowed_roles)) {
        $error = 'Please choose to log in as User or Admin.';
        $role = $login_as ?: 'user';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
        $role = $login_as;
    } else {
        // Check if this identifier is currently locked
        $lock = LoginSecurity::isLocked($username);
        if ($lock['locked']) {
            $error = 'Too many failed attempts. Please try again in ' . LoginSecurity::formatRemaining($lock['remaining']);
            $role = $login_as;
        } else {
            $pdo = getDB();
            // Use case-insensitive match for username/email
            $stmt = $pdo->prepare('SELECT id, username, password, full_name, role_id, email_verified, email FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                // record failed attempt against the entered identifier
                LoginSecurity::recordFailed($username);
                $lock = LoginSecurity::isLocked($username);
                if ($lock['locked']) {
                    $error = 'Too many failed attempts. Please try again in ' . LoginSecurity::formatRemaining($lock['remaining']);
                } else {
                    $error = 'Invalid username or password.';
                }
                $role = $login_as;
            } elseif (!password_verify($password, $user['password'])) {
                LoginSecurity::recordFailed($username);
                $lock = LoginSecurity::isLocked($username);
                if ($lock['locked']) {
                    $error = 'Too many failed attempts. Please try again in ' . LoginSecurity::formatRemaining($lock['remaining']);
                } else {
                    $error = 'Invalid username or password.';
                }
                $role = $login_as;
            } else {
                // successful login -> reset failed attempts (by entered identifier and by stored username/email)
                LoginSecurity::reset($username);
                if (!empty($user['username'])) LoginSecurity::reset($user['username']);
                if (!empty($user['email'])) LoginSecurity::reset($user['email']);

                // Check role matches selection
                $isAdminAccount = isset($user['role_id']) && intval($user['role_id']) === 1;
                if ($login_as === 'admin' && !$isAdminAccount) {
                    $error = 'This account is a driver. Please use "Log in as User" below.';
                    $role = $login_as;
                } elseif ($login_as === 'user' && $isAdminAccount) {
                    $error = 'This account is an admin. Please use "Log in as Admin" below.';
                    $role = $login_as;
                } else {
                    // Login successful
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
            }
        }
    }
}
        // If there was an error during POST handling, surface it via the alert system so users see feedback
        if (!empty($error)) {
            setAlert($error, 'danger');
        }

// Prepare attempt status for displaying in the form
$attemptsInfo = ['attempts' => 0, 'locked_until' => null];
$lockedInfo = ['locked' => false, 'remaining' => 0];
$enteredIdentifier = trim($_POST['username'] ?? '');
if ($enteredIdentifier !== '') {
    $attemptsInfo = LoginSecurity::getStatus($enteredIdentifier);
    $lockedInfo = LoginSecurity::isLocked($enteredIdentifier);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #16a34a; --primary-light: #22c55e; --accent: #22c55e; --muted: #64748b; }
        body { font-family: 'Google Sans Flex', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; min-height: 100vh; margin: 0; background: linear-gradient(160deg, #f0fdf4 0%, #dcfce7 40%, #f8fafc 100%); display: flex; align-items: center; justify-content: center; padding: 2rem 0; }
        .login-wrap { width: 100%; max-width: 440px; }
        .back-link { color: var(--muted); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 1.5rem; }
        .back-link:hover { color: var(--primary); }
        .login-card { background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(22,163,74,.08); border: 1px solid rgba(0,0,0,.04); overflow: hidden; }
        .login-card .card-body { padding: 2rem 2rem 2.25rem; }
        .login-brand { text-align: center; margin-bottom: 1.5rem; }
        .login-brand img { max-height: 64px; width: auto; margin: 0 auto 0.75rem; display: block; }
        .login-brand h1 { font-size: 1.35rem; font-weight: 700; color: #166534; margin: 0.25rem 0 0.15rem; }
        .login-brand p { font-size: 0.9rem; color: var(--muted); margin: 0; }
        .role-choices { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.75rem; }
        .role-btn { display: block; padding: 1.1rem 1rem; text-align: center; text-decoration: none; border-radius: 14px; border: 2px solid #e2e8f0; background: #fff; color: #334155; font-weight: 600; font-size: 0.95rem; transition: all .2s; }
        .role-btn:hover { border-color: var(--primary-light); background: #f0fdf4; color: var(--primary); }
        .role-btn i { display: block; font-size: 1.75rem; margin-bottom: 0.5rem; color: var(--accent); }
        .role-btn.user.selected { border-color: var(--accent); background: #f0fdf4; color: #166534; }
        .role-btn.admin.selected { border-color: var(--primary); background: #f0fdf4; color: #166534; }
        .role-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1.25rem; }
        .role-badge.user { background: #dcfce7; color: #166534; }
        .role-badge.admin { background: #dcfce7; color: #166534; }
        .role-badge a { color: inherit; text-decoration: underline; font-weight: 500; }
        .form-label { font-weight: 600; color: #334155; }
        .form-control { border-radius: 10px; padding: 0.6rem 0.85rem; border: 1px solid #e2e8f0; }
        .form-control:focus { border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
        .btn-submit { width: 100%; padding: 0.75rem; border-radius: 12px; font-weight: 600; background: var(--primary); border: none; color: #fff; transition: transform .15s, box-shadow .15s; }
        .btn-submit:hover { background: var(--primary-light); color: #fff; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(22,163,74,.3); }
        .login-footer { text-align: center; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; }
        .login-footer a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <a href="<?= BASE_URL ?>/index.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to home</a>

                <div class="login-card card">
            <div class="card-body">
                <div class="login-brand">
                    <img src="<?= BASE_URL ?>/assets/parkit.svg" alt="Parking Management System" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <span class="d-none" style="font-size: 2.5rem; color: #16a34a;"><i class="bi bi-p-square-fill"></i></span>
                    <h1>Parking Management System</h1>
                    <p>Sign in to your account</p>
                </div>

                <?php if (!$role): ?>
                    <p class="text-muted small text-center mb-3">Choose how you want to log in</p>
                    <div class="role-choices">
                        <a href="<?= BASE_URL ?>/auth/login.php?role=user" class="role-btn user">
                            <i class="bi bi-person-badge"></i>
                            Log in as User
                        </a>
                        <a href="<?= BASE_URL ?>/auth/login.php?role=admin" class="role-btn admin">
                            <i class="bi bi-shield-lock"></i>
                            Log in as Admin
                        </a>
                    </div>
                    <p class="text-center small text-muted mb-0">Drivers book slots and manage vehicles. Admins manage slots, hours, and monitor the lot.</p>
                <?php else: ?>
                    <div class="role-badge <?= $role ?>">
                        <?= $role === 'admin' ? '<i class="bi bi-shield-lock"></i> Logging in as Admin' : '<i class="bi bi-person-badge"></i> Logging in as User' ?>
                        <a href="<?= BASE_URL ?>/auth/login.php">Switch</a>
                    </div>

                    <form method="post" action="<?= BASE_URL ?>/auth/login.php">
                        <input type="hidden" name="login_as" value="<?= htmlspecialchars($role) ?>">
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus placeholder="Enter username or email" <?= ($lockedInfo['locked'] ? 'disabled' : '') ?> >
                            <?php if (!empty($enteredIdentifier)): ?>
                                <div class="form-text mt-1">
                                    Failed attempts: <?= intval($attemptsInfo['attempts']) ?> of <?= LoginSecurity::MAX_ATTEMPTS ?>
                                </div>
                                <?php if ($lockedInfo['locked']): ?>
                                    <div class="text-danger small mt-1">Account locked. Please try again in <span id="lockCountdown"><?= LoginSecurity::formatRemaining($lockedInfo['remaining']) ?></span></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="Enter password" <?= ($lockedInfo['locked'] ? 'disabled' : '') ?> >
                        </div>
                        <button type="submit" class="btn btn-submit" <?= ($lockedInfo['locked'] ? 'disabled' : '') ?> >Log in</button>
                    </form>

                    <?php if ($role === 'user'): ?>
                        <div class="login-footer">
                            <a href="<?= BASE_URL ?>/auth/register.php">Create an account</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.12);">
                <div class="modal-body text-center pt-5 pb-5" id="alertContent">
                </div>
            </div>
        </div>
    </div>
    <script>
    function showAlert(message, type = 'success') {
        const icons = {
            'success': 'bi-check-circle-fill',
            'danger': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        };
        const colors = {
            'success': { bg: '#dcfce7', border: '#bbf7d0', text: '#064e3b', icon: '#16a34a' },
            'danger': { bg: '#fee2e2', border: '#fecaca', text: '#7f1d1d', icon: '#dc2626' },
            'warning': { bg: '#fff7ed', border: '#ffedd5', text: '#92400e', icon: '#f59e0b' },
            'info': { bg: '#dbeafe', border: '#bfdbfe', text: '#0c4a6e', icon: '#2563eb' }
        };
        const color = colors[type] || colors['info'];
        const icon = icons[type] || icons['info'];
        const html = '<div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;background:' + color.bg + ';border:2px solid ' + color.border + ';">' +
            '<i class="bi ' + icon + '" style="font-size:28px;color:' + color.icon + ';"></i></div>' +
            '<p style="color:' + color.text + ';font-weight:500;margin-bottom:1.5rem;">' + message + '</p>' +
            '<button type="button" class="btn btn-sm" style="background:' + color.icon + ';color:#fff;border:none;border-radius:8px;padding:0.5rem 1.2rem;font-weight:600;" data-bs-dismiss="modal">OK</button>';
        document.getElementById('alertContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('alertModal')).show();
    }
    </script>
    <?php if (!empty($enteredIdentifier) && $lockedInfo['locked']): ?>
    <script>
    // Countdown for lock remaining time
    (function(){
        var remaining = <?= intval($lockedInfo['remaining']) ?>;
        var el = document.getElementById('lockCountdown');
        if (!el) return;
        function tick(){
            if (remaining <= 0) { el.textContent = '0 minutes 00 seconds'; location.reload(); return; }
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            el.textContent = m + ' minutes ' + String(s).padStart(2,'0') + ' seconds';
            remaining--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
    </script>
    <?php endif; ?>
    <?php
    $alert = getAlert();
    if ($alert) {
        echo '<script>showAlert("' . addslashes(htmlspecialchars($alert['message'])) . '", "' . htmlspecialchars($alert['type']) . '");</script>';
    }
    ?>
</body>
</html>
