<?php
/**
 * Admin - Account Settings (Profile / Security / Notifications)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();

$page_title = 'Account Settings';
$current_page = 'admin-settings';
$message = '';
$pdo = getDB();
$uid = currentUserId();

// fetch current user
$user = $pdo->prepare('SELECT id, username, email, full_name, phone FROM users WHERE id = ?');
$user->execute([$uid]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($full_name === '' || $email === '') {
        setAlert('Full name and email are required.', 'danger');
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([$full_name, $email, $phone, $uid]);
        setAlert('Profile updated successfully.', 'success');
        // refresh user data
        $user['full_name'] = $full_name;
        $user['email'] = $email;
        $user['phone'] = $phone;
        $_SESSION['full_name'] = $full_name;
    }
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
.notification-item {
    display: flex; justify-content: space-between; align-items: center; padding: 1rem 0;
    border-bottom: 1px solid #e5e7eb;
}
.notification-item:last-child { border-bottom: none; }
.notification-label { font-weight: 500; color: #374151; }
.form-check-input:checked { background-color: #22c55e; border-color: #22c55e; }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Fallback link -->
        <div class="mb-3">
            <a href="<?= BASE_URL ?>/admin/monitor.php" class="d-inline-flex align-items-center text-decoration-none text-dark" style="color: #111;"><i class="bi bi-arrow-left me-1"></i> Back</a>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-link">Open Settings</a>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="adminSettingsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Account Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <h3 class="fw-bold">Settings</h3>
                <p class="text-muted">Manage your account and preferences</p>

                <div class="settings-tabs mb-3" id="settingsTabs">
                    <div class="settings-tab active" data-tab="profile"><i class="bi bi-person me-2"></i> Profile</div>
                    <div class="settings-tab" data-tab="security"><i class="bi bi-shield-lock me-2"></i> Security</div>
                    <div class="settings-tab" data-tab="notifications"><i class="bi bi-bell me-2"></i> Notifications</div>
                </div>

                <div class="settings-card">
                    <div id="tabProfile">
                        <h5 class="fw-bold">Profile Information</h5>
                        <form method="post" action="<?= BASE_URL ?>/admin/settings.php">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" name="save_profile" class="btn btn-success">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <div id="tabSecurity" style="display:none;">
                        <h5 class="fw-bold">Security</h5>
                        <p class="text-muted">Change your account password</p>
                        <form method="post" action="<?= BASE_URL ?>/admin/settings.php">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" name="change_password" class="btn btn-success">Change Password</button>
                            </div>
                        </form>
                    </div>

                    <div id="tabNotifications" style="display:none;">
                        <h5 class="fw-bold">Notifications</h5>
                        <p class="text-muted">Manage your notification preferences</p>
                        <form method="post" action="<?= BASE_URL ?>/admin/settings.php">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" checked>
                                <label class="form-check-label" for="emailNotifications">Email notifications for booking updates</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="smsNotifications" name="sms_notifications">
                                <label class="form-check-label" for="smsNotifications">SMS notifications</label>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" name="save_notifications" class="btn btn-success">Save Preferences</button>
                            </div>
                        </form>
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modalEl = document.getElementById('adminSettingsModal');
    if (modalEl && typeof bootstrap !== 'undefined'){
        var bs = new bootstrap.Modal(modalEl);
        bs.show();
        modalEl.addEventListener('hidden.bs.modal', function(){
            window.location = '<?= BASE_URL ?>/admin/monitor.php';
        });
    }

    document.querySelectorAll('.settings-tab').forEach(function(el){
        el.addEventListener('click', function(){
            document.querySelectorAll('.settings-tab').forEach(t=>t.classList.remove('active'));
            el.classList.add('active');
            var tab = el.getAttribute('data-tab');
            document.getElementById('tabProfile').style.display = tab==='profile' ? 'block' : 'none';
            document.getElementById('tabSecurity').style.display = tab==='security' ? 'block' : 'none';
            document.getElementById('tabNotifications').style.display = tab==='notifications' ? 'block' : 'none';
        });
    });
});
</script>
