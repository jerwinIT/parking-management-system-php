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
$pdo = getDB();

// active tab
$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile','security'])) $active_tab = 'profile';

// fetch user
$stmt = $pdo->prepare('SELECT full_name, email, phone, password FROM users WHERE id = ?');
$stmt->execute([currentUserId()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: ' . BASE_URL . '/index.php'); exit; }

// default values
$full_name = $user['full_name'];
$email = $user['email'];
$phone = $user['phone'] ?? '';

// handle submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PROFILE
    if (isset($_POST['save_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Full name checks
        if (empty($full_name)) {
            setAlert('Full name is required.', 'danger');
        } elseif (!preg_match('/^[a-zA-Z ]+$/', $full_name)) {
            setAlert('Full name can only contain letters and spaces. Special characters and numbers are not allowed.', 'danger');
        } elseif (strlen($full_name) < 2) {
            setAlert('Full name must be at least 2 characters long.', 'danger');
        } elseif (strlen($full_name) > 100) {
            setAlert('Full name is too long (maximum 100 characters).', 'danger');
        }
        // Email checks
        elseif (empty($email)) {
            setAlert('Email address is required.', 'danger');
        } elseif (!preg_match('/[a-zA-Z]/', $email)) {
            setAlert('Email must contain at least one letter.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setAlert('Please enter a valid email address.', 'danger');
        } elseif (strlen($email) > 254) {
            setAlert('Email address is too long (maximum 254 characters).', 'danger');
        }
        // Phone checks (optional)
        elseif (!empty($phone)) {
            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            if (!preg_match('/^09[0-9]{9}$/', $clean_phone)) {
                setAlert('Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).', 'danger');
            } else {
                $phone = $clean_phone;
                // email uniqueness
                $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?');
                $stmt->execute([$email, currentUserId()]);
                if ($stmt->fetch()) {
                    setAlert('Email address is already in use by another account.', 'danger');
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                    $stmt->execute([$full_name, $email, $phone, currentUserId()]);
                    setAlert('Profile updated successfully.', 'success');
                    $_SESSION['full_name'] = $full_name;
                    $user['full_name'] = $full_name; $user['email'] = $email; $user['phone'] = $phone;
                }
            }
        } else {
            // phone optional path
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?');
            $stmt->execute([$email, currentUserId()]);
            if ($stmt->fetch()) {
                setAlert('Email address is already in use by another account.', 'danger');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$full_name, $email, $phone ?: null, currentUserId()]);
                setAlert('Profile updated successfully.', 'success');
                $_SESSION['full_name'] = $full_name;
                $user['full_name'] = $full_name; $user['email'] = $email; $user['phone'] = $phone;
            }
        }

    // PASSWORD
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            setAlert('Current password is required.', 'danger');
        } else {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([currentUserId()]);
            $user_data = $stmt->fetch();
            if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                setAlert('Current password is incorrect.', 'danger');
            }
            // new password rules
            elseif (empty($new_password)) {
                setAlert('New password is required.', 'danger');
            } elseif (strlen($new_password) < 8) {
                setAlert('Password must be at least 8 characters long.', 'danger');
            } elseif (strlen($new_password) > 128) {
                setAlert('Password is too long (maximum 128 characters).', 'danger');
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                setAlert('Password must contain at least one uppercase letter.', 'danger');
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                setAlert('Password must contain at least one lowercase letter.', 'danger');
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                setAlert('Password must contain at least one number.', 'danger');
            } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
                setAlert('Password must contain at least one special character (@, #, $, %, etc.).', 'danger');
            }
            // confirmation
            elseif ($new_password !== $confirm_password) {
                setAlert('New password and confirmation do not match.', 'danger');
            }
            // not same as current
            elseif (password_verify($new_password, $user_data['password'])) {
                setAlert('New password must be different from current password.', 'danger');
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hashed, currentUserId()]);
                setAlert('Password changed successfully.', 'success');
            }
        }
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

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" style="color:#6b7280;font-size:.95rem;">Manage your account and preferences</p>
        <div class="settings-tabs">
            <a href="<?= BASE_URL ?>/user/profile.php?tab=profile" class="settings-tab <?= $active_tab==='profile'?'active':'' ?>"><i class="bi bi-person"></i> Profile</a>
            <a href="<?= BASE_URL ?>/user/profile.php?tab=security" class="settings-tab <?= $active_tab==='security'?'active':'' ?>"><i class="bi bi-lock"></i> Security</a>
        </div>

        <?php if ($active_tab === 'profile'): ?>
        <div class="settings-content-card">
            <h5 class="settings-section-title">Profile Information</h5>
            <form method="post" action="<?= BASE_URL ?>/user/profile.php?tab=profile">
                <input type="hidden" name="save_profile" value="1">
                <div class="settings-form-group">
                    <label class="settings-form-label">Full Name</label>
                    <input type="text" name="full_name" id="fullName" class="settings-form-input" value="<?= htmlspecialchars($full_name) ?>" required>
                    <div class="invalid-feedback" id="fullNameError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
                </div>
                <div class="settings-form-group">
                    <label class="settings-form-label">Email Address</label>
                    <input type="email" name="email" id="emailAddr" class="settings-form-input" value="<?= htmlspecialchars($email) ?>" required>
                    <div class="invalid-feedback" id="emailError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
                </div>
                <div class="settings-form-group">
                    <label class="settings-form-label">Phone Number</label>
                    <input type="text" name="phone" id="phoneNum" class="settings-form-input" value="<?= htmlspecialchars($phone) ?>" placeholder="09XXXXXXXXX">
                    <div class="invalid-feedback" id="phoneError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
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
                    <input type="password" name="current_password" id="currentPassword" class="settings-form-input" required>
                    <div class="invalid-feedback" id="currentPasswordError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
                </div>
                <div class="settings-form-group">
                    <label class="settings-form-label">New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="settings-form-input" required minlength="8">
                    <small class="text-muted">Must be 8+ characters with uppercase, lowercase, number, and special character</small>
                    <div class="invalid-feedback" id="newPasswordError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
                </div>
                <div class="settings-form-group">
                    <label class="settings-form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" class="settings-form-input" required minlength="8">
                    <div class="invalid-feedback" id="confirmPasswordError" style="display:none;margin-top:.35rem;color:#dc2626;font-size:.9rem;"></div>
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
// Client-side validation and behavior copied from admin/settings.php (real-time, blur, submit)
document.addEventListener('DOMContentLoaded', function(){
    var modalEl = document.getElementById('settingsModal');
    if (modalEl && typeof bootstrap !== 'undefined'){
        var bs = new bootstrap.Modal(modalEl);
        if (window.__hasServerAlert) {
            var alertModalEl = document.getElementById('alertModal');
            if (alertModalEl) { alertModalEl.addEventListener('hidden.bs.modal', function handler(){ alertModalEl.removeEventListener('hidden.bs.modal', handler); bs.show(); }); }
            else { setTimeout(function(){ bs.show(); }, 400); }
        } else { bs.show(); }
        modalEl.addEventListener('hidden.bs.modal', function(){ window.location = '<?= BASE_URL ?>/index.php'; });
    }

    // inputs
    var fullName = document.getElementById('fullName');
    var email = document.getElementById('emailAddr');
    var phone = document.getElementById('phoneNum');
    var current = document.getElementById('currentPassword');
    var nw = document.getElementById('newPassword');
    var conf = document.getElementById('confirmPassword');

    function showError(field, message){ if(!field) return; var fb = field.parentElement.querySelector('.invalid-feedback'); if (fb) { fb.textContent = message; fb.style.display = message ? 'block' : 'none'; field.style.borderColor = message ? '#dc2626' : ''; field.style.backgroundColor = message ? '#fef2f2' : ''; } else { field.style.borderColor = message ? '#dc2626' : ''; field.style.backgroundColor = message ? '#fef2f2' : ''; var ex = field.parentElement.querySelector('.validation-error'); if (ex) ex.remove(); if (message){ var d=document.createElement('div'); d.className='validation-error'; d.style.cssText='color:#dc2626;font-size:0.875rem;margin-top:0.25rem;'; d.textContent=message; field.parentElement.appendChild(d); } } }
    function clearError(field){ if(!field) return; var fb = field.parentElement.querySelector('.invalid-feedback'); if (fb){ fb.textContent=''; fb.style.display='none'; } field.style.borderColor=''; field.style.backgroundColor=''; var ex = field.parentElement.querySelector('.validation-error'); if (ex) ex.remove(); field.classList.remove('is-invalid'); }

    function validateFullName(name){ if (!name || name.trim()==='') return 'Full name is required.'; if (!/^[a-zA-Z ]+$/.test(name)) return 'Full name can only contain letters and spaces. Special characters and numbers are not allowed.'; if (name.trim().length < 2) return 'Full name must be at least 2 characters long.'; if (name.length > 100) return 'Full name is too long (maximum 100 characters).'; return ''; }
    function validateEmail(emailVal){ if (!emailVal || emailVal.trim()==='') return 'Email address is required.'; if (!/[a-zA-Z]/.test(emailVal)) return 'Email must contain at least one letter.'; if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) return 'Please enter a valid email address.'; if (emailVal.length > 254) return 'Email address is too long (maximum 254 characters).'; return ''; }
    function validatePhone(phoneVal){ if (!phoneVal || phoneVal.trim()==='') return ''; var clean = phoneVal.replace(/[^0-9]/g,''); if (!/^09[0-9]{9}$/.test(clean)) return 'Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).'; return ''; }
    function validatePassword(pw){ if (!pw || pw==='') return 'Password is required.'; if (pw.length < 8) return 'Password must be at least 8 characters long.'; if (pw.length > 128) return 'Password is too long (maximum 128 characters).'; if (!/[A-Z]/.test(pw)) return 'Password must contain at least one uppercase letter.'; if (!/[a-z]/.test(pw)) return 'Password must contain at least one lowercase letter.'; if (!/[0-9]/.test(pw)) return 'Password must contain at least one number.'; if (!/[^a-zA-Z0-9]/.test(pw)) return 'Password must contain at least one special character (@, #, $, %, etc.).'; return ''; }

    if (fullName){ fullName.addEventListener('blur', function(){ showError(this, validateFullName(this.value)); }); fullName.addEventListener('input', function(){ clearError(this); }); }
    if (email){ email.addEventListener('blur', function(){ showError(this, validateEmail(this.value)); }); email.addEventListener('input', function(){ clearError(this); }); }
    if (phone){ phone.addEventListener('blur', function(){ showError(this, validatePhone(this.value)); }); phone.addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g,''); clearError(this); }); }
    if (nw){ nw.addEventListener('blur', function(){ showError(this, validatePassword(this.value)); }); nw.addEventListener('input', function(){ clearError(this); }); }
    if (conf){ conf.addEventListener('blur', function(){ if (nw && nw.value && this.value && nw.value !== this.value) showError(this, 'Passwords do not match.'); else clearError(this); }); conf.addEventListener('input', function(){ clearError(this); }); }

    var profileForm = document.querySelector('form[action$="?tab=profile"]');
    if (profileForm) profileForm.addEventListener('submit', function(e){ var hasError=false, first=null; if (fullName){ var m=validateFullName(fullName.value); if (m){ showError(fullName,m); hasError=true; first=first||fullName; } } if (email){ var em=validateEmail(email.value); if (em){ showError(email,em); hasError=true; first=first||email; } } if (phone){ var ph=validatePhone(phone.value); if (ph){ showError(phone,ph); hasError=true; first=first||phone; } } if (hasError){ e.preventDefault(); if (first){ first.focus(); first.scrollIntoView({behavior:'smooth', block:'center'}); } } });

    var securityForm = document.querySelector('form[action$="?tab=security"]');
    if (securityForm) securityForm.addEventListener('submit', function(e){ var hasError=false, first=null; if (!current || !current.value){ showError(current,'Current password is required.'); hasError=true; first=first||current; } if (nw){ var pErr=validatePassword(nw.value); if (pErr){ showError(nw,pErr); hasError=true; first=first||nw; } } if (nw && conf && nw.value !== conf.value){ showError(conf,'Passwords do not match.'); hasError=true; first=first||conf; } if (current && nw && current.value && nw.value && current.value === nw.value){ showError(nw,'New password must be different from current password.'); hasError=true; first=first||nw; } if (hasError){ e.preventDefault(); if (first){ first.focus(); first.scrollIntoView({behavior:'smooth', block:'center'}); } } });
});
</script>
