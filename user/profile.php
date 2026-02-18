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
        // Profile update - enforce same name/email/phone rules as registration
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Full name validation - must contain only letters and spaces and have at least two words
        $profile_ok = true;
        if (empty($full_name)) {
            setAlert('Full name is required.', 'danger'); $profile_ok = false;
        } else {
            $nameParts = preg_split('/\s+/', $full_name, -1, PREG_SPLIT_NO_EMPTY);
            if (count($nameParts) < 2) {
                setAlert('Please enter both first name and last name in the Full Name field.', 'danger'); $profile_ok = false;
            } elseif (!preg_match('/^[A-Za-z\s]+$/', $full_name)) {
                setAlert('Full name can only contain letters and spaces. Special characters and numbers are not allowed.', 'danger'); $profile_ok = false;
            }
        }

        // Email server-side validation (copy of registration rules)
        if (empty($email)) {
            setAlert('Email is required.', 'danger'); $profile_ok = false;
        } else {
            $email_parts = explode('@', $email);
            $email_local = $email_parts[0] ?? '';
            $email_domain = $email_parts[1] ?? '';

            if (strlen($email) > 254) {
                setAlert('Email address is too long (maximum 254 characters).', 'danger'); $profile_ok = false;
            } elseif (!preg_match('/^[A-Za-z0-9._+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)) {
                setAlert('Please enter a valid email address (local-part@domain).', 'danger'); $profile_ok = false;
            } elseif (strpos($email, '..') !== false) {
                setAlert('Email cannot contain consecutive dots.', 'danger'); $profile_ok = false;
            } elseif (!preg_match('/^[A-Za-z0-9._+\-]+$/', $email_local)) {
                setAlert('Invalid email format', 'danger'); $profile_ok = false;
            } elseif (strlen($email_local) < 3) {
                setAlert('Invalid email format', 'danger'); $profile_ok = false;
            } elseif (!preg_match('/[A-Za-z]/', $email_local)) {
                setAlert('Email must contain at least one letter', 'danger'); $profile_ok = false;
            } elseif (preg_match('/^[0-9]+$/', $email_local)) {
                setAlert('Email cannot be all numbers', 'danger'); $profile_ok = false;
            } elseif (strlen($email_local) === 0 || $email_local[0] === '.' || substr($email_local, -1) === '.') {
                setAlert('Local part of the email cannot start or end with a dot.', 'danger'); $profile_ok = false;
            } elseif (strpos($email_domain, '.') === false) {
                setAlert('Email domain must contain at least one dot (e.g. example.com).', 'danger'); $profile_ok = false;
            } elseif (!preg_match('/^(?!-)([A-Za-z0-9\-]+)(?<!-)(\.(?!-)([A-Za-z0-9\-]+)(?<!-))*$/', $email_domain)) {
                setAlert('Email domain contains invalid characters or labels.', 'danger'); $profile_ok = false;
            }
        }

        // Phone validation - accept Philippine format 09XXXXXXXXX
        $phone_normalized = preg_replace('/[^0-9]/', '', $phone);
        if ($phone_normalized !== '' && !preg_match('/^09[0-9]{9}$/', $phone_normalized)) {
            setAlert('Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).', 'danger'); $profile_ok = false;
        }

        // If validations passed, proceed to uniqueness check and update
        if ($profile_ok) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([strtolower($email), currentUserId()]);
            if ($stmt->fetch()) {
                setAlert('Email already used by another account.', 'danger');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$full_name, strtolower($email), $phone ?: null, currentUserId()]);
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
        // Enforce registration-level password rules: 8-128 chars, upper, lower, number, special
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            setAlert('All password fields are required.', 'danger');
        } elseif ($new_password !== $confirm_password) {
            setAlert('New password and confirmation do not match.', 'danger');
        } elseif (strlen($new_password) < 8) {
            setAlert('New password must be at least 8 characters long.', 'danger');
        } elseif (strlen($new_password) > 128) {
            setAlert('New password is too long (maximum 128 characters).', 'danger');
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            setAlert('New password must contain at least one uppercase letter.', 'danger');
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            setAlert('New password must contain at least one lowercase letter.', 'danger');
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            setAlert('New password must contain at least one number.', 'danger');
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
            setAlert('New password must contain at least one special character (@, #, $, %, etc.).', 'danger');
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
        // If a server-side alert is present, wait until it's dismissed before opening settings
        if (window.__hasServerAlert) {
            var alertModalEl = document.getElementById('alertModal');
            if (alertModalEl) {
                alertModalEl.addEventListener('hidden.bs.modal', function handler(){
                    alertModalEl.removeEventListener('hidden.bs.modal', handler);
                    bs.show();
                });
            } else {
                // fallback
                setTimeout(function(){ bs.show(); }, 400);
            }
        } else {
            bs.show();
        }

        modalEl.addEventListener('hidden.bs.modal', function(){
            window.location = '<?= BASE_URL ?>/index.php';
        });
    }

    // Client-side validation: mirror registration rules for profile fields
    var fullNameEl = document.querySelector('input[name="full_name"]');
    var emailEl = document.querySelector('input[name="email"]');
    var phoneEl = document.querySelector('input[name="phone"]');
    var newPassEl = document.querySelector('input[name="new_password"]');
    var confirmPassEl = document.querySelector('input[name="confirm_password"]');
    var profileForm = document.querySelector('form[action$="?tab=profile"]');
    var securityForm = document.querySelector('form[action$="?tab=security"]');

    function validateEmailFormat(email) {
        var e = (email || '').toLowerCase().trim();
        if (!e) return 'Email is required';
        if (e.length > 254) return 'Invalid email format';
        var basic = /^[A-Za-z0-9._+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/;
        if (!basic.test(e)) return 'Invalid email format';
        if (e.indexOf('..') !== -1) return 'Invalid email format';
        var parts = e.split('@');
        var local = parts[0] || '';
        var domain = parts[1] || '';
        if (!local) return 'Invalid email format';
        if (local.length < 3) return 'Invalid email format';
        if (!/^[A-Za-z0-9._+\-]+$/.test(local)) return 'Invalid email format';
        if (local.startsWith('.') || local.endsWith('.')) return 'Invalid email format';
        if (!/[A-Za-z]/.test(local)) return 'Email must contain at least one letter';
        if (/^[0-9]+$/.test(local)) return 'Email cannot be all numbers';
        if (!domain || domain.startsWith('.') || domain.endsWith('.')) return 'Invalid email format';
        if (domain.indexOf('.') === -1) return 'Invalid email format';
        var labels = domain.split('.');
        for (var i=0;i<labels.length;i++){
            var lab = labels[i];
            if (!lab) return 'Invalid email format';
            if (lab.startsWith('-') || lab.endsWith('-')) return 'Invalid email format';
            if (!/^[A-Za-z0-9\-]+$/.test(lab)) return 'Invalid email format';
        }
        return '';
    }

    if (fullNameEl) {
        fullNameEl.addEventListener('input', function(){
            var v = (this.value || '').trim();
            var parts = v.split(/\s+/).filter(Boolean);
            var valid = /^[A-Za-z\s]+$/.test(v) && parts.length >= 2;
            this.classList.toggle('is-valid', valid && v.length>0);
            this.classList.toggle('is-invalid', !valid && v.length>0);
        });
    }

    if (emailEl) {
        emailEl.addEventListener('input', function(){
            var msg = validateEmailFormat(this.value);
            this.classList.toggle('is-valid', !msg);
            this.classList.toggle('is-invalid', !!msg);
        });
    }

    if (phoneEl) {
        phoneEl.addEventListener('input', function(){
            var v = (this.value || '').replace(/[^0-9]/g,'');
            var ok = v === '' || /^09[0-9]{9}$/.test(v);
            this.classList.toggle('is-valid', ok && v.length>0);
            this.classList.toggle('is-invalid', !ok && v.length>0);
        });
    }

    // Security tab: password strength and match
    function checkNewPasswordStrength() {
        if (!newPassEl) return true;
        var p = newPassEl.value || '';
        var ok = p.length >=8 && p.length <=128 && /[A-Z]/.test(p) && /[a-z]/.test(p) && /[0-9]/.test(p) && /[^a-zA-Z0-9]/.test(p);
        newPassEl.classList.toggle('is-valid', ok && p.length>0);
        newPassEl.classList.toggle('is-invalid', !ok && p.length>0);
        return ok;
    }
    function checkNewPasswordMatch() {
        if (!newPassEl || !confirmPassEl) return true;
        var p = newPassEl.value || '';
        var c = confirmPassEl.value || '';
        var match = (p === c && c.length>0);
        confirmPassEl.classList.toggle('is-valid', match);
        confirmPassEl.classList.toggle('is-invalid', !match && c.length>0);
        return match;
    }
    if (newPassEl) newPassEl.addEventListener('input', function(){ checkNewPasswordStrength(); checkNewPasswordMatch(); });
    if (confirmPassEl) confirmPassEl.addEventListener('input', checkNewPasswordMatch);

    // Profile form submit validation
    if (profileForm) {
        profileForm.addEventListener('submit', function(e){
            var fn = fullNameEl ? fullNameEl.value.trim() : '';
            var fnParts = fn.split(/\s+/).filter(Boolean);
            if (!fn || fnParts.length < 2 || !/^[A-Za-z\s]+$/.test(fn)) {
                e.preventDefault();
                if (fullNameEl) { fullNameEl.classList.add('is-invalid'); fullNameEl.focus(); }
                return;
            }
            var emailMsg = emailEl ? validateEmailFormat(emailEl.value) : 'Email is required';
            if (emailMsg) { e.preventDefault(); if (emailEl) { emailEl.classList.add('is-invalid'); emailEl.focus(); } return; }
            var phoneVal = phoneEl ? phoneEl.value.replace(/[^0-9]/g,'') : '';
            if (phoneVal && !/^09[0-9]{9}$/.test(phoneVal)) { e.preventDefault(); if (phoneEl) { phoneEl.classList.add('is-invalid'); phoneEl.focus(); } return; }
        });
    }

    // Security form submit validation
    if (securityForm) {
        securityForm.addEventListener('submit', function(e){
            if (!checkNewPasswordStrength() || !checkNewPasswordMatch()) {
                e.preventDefault();
                if (newPassEl) newPassEl.focus();
            }
        });
    }
});
</script>