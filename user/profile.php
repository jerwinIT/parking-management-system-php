<?php
/**
 * User Settings - Profile and Security tabs (modal)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$page_title = 'Settings';
$current_page = 'profile';
$error = '';
$success = '';
$pdo = getDB();

// Get active tab
$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile', 'security'])) {
    $active_tab = 'profile';
}

// Get user data
$stmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = ?');
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();
if (!$user) { header('Location: ' . BASE_URL . '/index.php'); exit; }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        // Profile update
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($full_name)) {
            setAlert('Full name is required.', 'danger');
        } elseif (empty($email)) {
            setAlert('Email is required.', 'danger');
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, currentUserId()]);
            if ($stmt->fetch()) {
                setAlert('Email already used by another account.', 'danger');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$full_name, $email, $phone ?: null, currentUserId()]);
                $_SESSION['full_name'] = $full_name;
                setAlert('Profile updated successfully.', 'success');
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone'] = $phone;
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            setAlert('All password fields are required.', 'danger');
        } elseif ($new_password !== $confirm_password) {
            setAlert('New password and confirmation do not match.', 'danger');
        } elseif (strlen($new_password) < 6) {
            setAlert('New password must be at least 6 characters.', 'danger');
        } else {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([currentUserId()]);
            $db_user = $stmt->fetch();
            if (!password_verify($current_password, $db_user['password'])) {
                setAlert('Current password is incorrect.', 'danger');
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hashed, currentUserId()]);
                setAlert('Password changed successfully.', 'success');
            }
        }
    }
} else {
    $full_name = $user['full_name'];
    $email = $user['email'];
    $phone = $user['phone'] ?? '';
}

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
.settings-page {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}
.settings-tabs {
    display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 1.5rem;
}
.settings-tab {
    padding: 0.75rem 1.5rem; background: transparent; border: none;
    display: flex; align-items: center; gap: 0.5rem; color: #9ca3af; font-weight: 500; font-size: 0.95rem;
    cursor: pointer; transition: color .2s; position: relative; bottom: -2px; text-decoration: none;
}
.settings-tab::after {
    content: ''; position: absolute; left: 0; right: 0; bottom: -2px;
    height: 3px; background: transparent; border-radius: 999px;
}
.settings-tab:hover { color: #374151; }
.settings-tab.active {
    color: #374151;
}
.settings-tab.active::after {
    background: #22c55e;
}
.settings-tab i { font-size: 1.1rem; }
.settings-content-card {
    background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1);
    padding: 2rem; margin-bottom: 2rem;
}
.settings-section-title {
    font-size: 1.25rem; font-weight: 700; color: #374151; margin-bottom: 1.5rem;
}
.settings-form-group {
    margin-bottom: 1.5rem;
}
.settings-form-label {
    display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem;
}
.settings-form-input {
    width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 0.95rem; color: #374151; transition: border-color .2s;
}
.settings-form-input:focus {
    outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}
.settings-form-actions {
    display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 2rem;
}
.btn-cancel {
    background: #fff; color: #374151; border: 1px solid #d1d5db; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background .2s;
}
.btn-cancel:hover {
    background: #f9fafb; color: #111;
}
.btn-save {
    background: #22c55e; color: #fff; border: none; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background .2s;
}
.btn-save:hover {
    background: #16a34a; color: #fff;
}
.form-check-input:checked { background-color: #22c55e; border-color: #22c55e; }
</style>

<!-- Modal fallback link (visible if JS disabled) -->
<div class="mb-3">
    <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center text-decoration-none text-dark" style="color: #111;"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <a href="<?= BASE_URL ?>/user/profile.php?tab=<?= $active_tab ?>" class="btn btn-link">Open Settings</a>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" style="color: #6b7280; font-size: 0.95rem;">Manage your account and preferences</p>

        <!-- Tabs -->
        <div class="settings-tabs">
            <a href="<?= BASE_URL ?>/user/profile.php?tab=profile" class="settings-tab <?= $active_tab === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person"></i> Profile
            </a>
            <a href="<?= BASE_URL ?>/user/profile.php?tab=security" class="settings-tab <?= $active_tab === 'security' ? 'active' : '' ?>">
                <i class="bi bi-lock"></i> Security
            </a>
            <!-- Notifications tab removed -->
        </div>

        <!-- Tab Content -->
        <?php if ($active_tab === 'profile'): ?>
            <div class="settings-content-card">
                <h5 class="settings-section-title">Profile Information</h5>
                <form method="post" action="<?= BASE_URL ?>/user/profile.php?tab=profile">
                    <input type="hidden" name="save_profile" value="1">
                    <div class="settings-form-group">
                        <label class="settings-form-label">Full Name</label>
                        <input type="text" name="full_name" class="settings-form-input" value="<?= htmlspecialchars($full_name) ?>" required>
                    </div>
                    <div class="settings-form-group">
                        <label class="settings-form-label">Email Address</label>
                        <input type="email" name="email" class="settings-form-input" value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    <div class="settings-form-group">
                        <label class="settings-form-label">Phone Number</label>
                        <input type="text" name="phone" class="settings-form-input" value="<?= htmlspecialchars($phone) ?>" placeholder="+1 (555) 123-4567">
                    </div>
                    <div class="settings-form-actions">
                        <a href="<?= BASE_URL ?>/index.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>

        <?php elseif ($active_tab === 'security'): ?>
            <div class="settings-content-card">
                <h5 class="settings-section-title">Change Password</h5>
                <form method="post" action="<?= BASE_URL ?>/user/profile.php?tab=security">
                    <input type="hidden" name="change_password" value="1">
                    <div class="settings-form-group">
                        <label class="settings-form-label">Current Password</label>
                        <input type="password" name="current_password" class="settings-form-input" required>
                    </div>
                    <div class="settings-form-group">
                        <label class="settings-form-label">New Password</label>
                        <input type="password" name="new_password" class="settings-form-input" required minlength="6">
                        <small class="text-muted">Must be at least 6 characters</small>
                    </div>
                    <div class="settings-form-group">
                        <label class="settings-form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="settings-form-input" required minlength="6">
                    </div>
                    <div class="settings-form-actions">
                        <a href="<?= BASE_URL ?>/user/profile.php?tab=security" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Change Password</button>
                    </div>
                </form>
            </div>

        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modalEl = document.getElementById('settingsModal');
    if (modalEl && typeof bootstrap !== 'undefined'){
        var bs = new bootstrap.Modal(modalEl);
        bs.show();
        modalEl.addEventListener('hidden.bs.modal', function(){
            window.location = '<?= BASE_URL ?>/index.php';
        });
    }
});
</script>