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

// handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (empty($current_password)) {
        setAlert('Current password is required.', 'danger');
    } else {
        // Verify current password
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user_data = $stmt->fetch();
        
        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            setAlert('Current password is incorrect.', 'danger');
        }
        // Validate new password - strong password requirements
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
        // Validate password confirmation
        elseif ($new_password !== $confirm_password) {
            setAlert('New password and confirmation do not match.', 'danger');
        }
        // Check if new password is same as current
        elseif (password_verify($new_password, $user_data['password'])) {
            setAlert('New password must be different from current password.', 'danger');
        } else {
            // All validations passed - update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed_password, $uid]);
            setAlert('Password changed successfully.', 'success');
        }
    }
}

// handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate full name - required, letters and spaces only
    if (empty($full_name)) {
        setAlert('Full name is required.', 'danger');
    } elseif (!preg_match('/^[a-zA-Z ]+$/', $full_name)) {
        setAlert('Full name can only contain letters and spaces. Special characters and numbers are not allowed.', 'danger');
    } elseif (strlen($full_name) < 2) {
        setAlert('Full name must be at least 2 characters long.', 'danger');
    } elseif (strlen($full_name) > 100) {
        setAlert('Full name is too long (maximum 100 characters).', 'danger');
    }
    // Validate email - required, must be valid format and contain letters
    elseif (empty($email)) {
        setAlert('Email address is required.', 'danger');
    } elseif (!preg_match('/[a-zA-Z]/', $email)) {
        setAlert('Email must contain at least one letter.', 'danger');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setAlert('Please enter a valid email address.', 'danger');
    } elseif (strlen($email) > 254) {
        setAlert('Email address is too long (maximum 254 characters).', 'danger');
    }
    // Validate phone - Philippine format 09XXXXXXXXX
    elseif (!empty($phone)) {
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (!preg_match('/^09[0-9]{9}$/', $clean_phone)) {
            setAlert('Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).', 'danger');
        } else {
            // Phone is valid, update with cleaned version
            $phone = $clean_phone;
            
            // Check if email is already taken by another user
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?');
            $stmt->execute([$email, $uid]);
            if ($stmt->fetch()) {
                setAlert('Email address is already in use by another account.', 'danger');
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
    } else {
        // Phone is optional but if provided must be valid
        // Check if email is already taken by another user
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?');
        $stmt->execute([$email, $uid]);
        if ($stmt->fetch()) {
            setAlert('Email address is already in use by another account.', 'danger');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->execute([$full_name, $email, $phone ?: null, $uid]);
            setAlert('Profile updated successfully.', 'success');
            // refresh user data
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $_SESSION['full_name'] = $full_name;
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
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                <small class="form-text text-muted">Letters and spaces only</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <small class="form-text text-muted">Must be a valid email address</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="09XXXXXXXXX" maxlength="11">
                                <small class="form-text text-muted">Philippine format: 09XXXXXXXXX (optional)</small>
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
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required>
                                <small class="form-text text-muted">
                                    Must be 8+ characters with uppercase, lowercase, number, and special character
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                                <small class="form-text text-muted">Re-enter your new password</small>
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
    
    // Validation functions
    function validateFullName(name) {
        if (!name || name.trim() === '') return 'Full name is required.';
        if (!/^[a-zA-Z ]+$/.test(name)) return 'Full name can only contain letters and spaces. Special characters and numbers are not allowed.';
        if (name.trim().length < 2) return 'Full name must be at least 2 characters long.';
        if (name.length > 100) return 'Full name is too long (maximum 100 characters).';
        return '';
    }
    
    function validateEmail(email) {
        if (!email || email.trim() === '') return 'Email address is required.';
        if (!/[a-zA-Z]/.test(email)) return 'Email must contain at least one letter.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Please enter a valid email address.';
        if (email.length > 254) return 'Email address is too long (maximum 254 characters).';
        return '';
    }
    
    function validatePhone(phone) {
        if (!phone || phone.trim() === '') return ''; // Optional field
        const clean = phone.replace(/[^0-9]/g, '');
        if (!/^09[0-9]{9}$/.test(clean)) return 'Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).';
        return '';
    }
    
    function validatePassword(password) {
        if (!password || password === '') return 'Password is required.';
        if (password.length < 8) return 'Password must be at least 8 characters long.';
        if (password.length > 128) return 'Password is too long (maximum 128 characters).';
        if (!/[A-Z]/.test(password)) return 'Password must contain at least one uppercase letter.';
        if (!/[a-z]/.test(password)) return 'Password must contain at least one lowercase letter.';
        if (!/[0-9]/.test(password)) return 'Password must contain at least one number.';
        if (!/[^a-zA-Z0-9]/.test(password)) return 'Password must contain at least one special character (@, #, $, %, etc.).';
        return '';
    }
    
    function showError(field, message) {
        if (!field) return;
        
        // Remove existing error
        var existingError = field.parentElement.querySelector('.validation-error');
        if (existingError) existingError.remove();
        
        if (message) {
            field.style.borderColor = '#dc2626';
            field.style.backgroundColor = '#fef2f2';
            
            var errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error';
            errorDiv.style.cssText = 'color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem;';
            errorDiv.textContent = message;
            field.parentElement.appendChild(errorDiv);
        } else {
            field.style.borderColor = '';
            field.style.backgroundColor = '';
        }
    }
    
    function clearError(field) {
        showError(field, '');
    }
    
    // Profile form validation
    var profileForm = document.querySelector('form[action*="settings.php"] button[name="save_profile"]');
    if (profileForm) {
        var form = profileForm.closest('form');
        var fullNameInput = form.querySelector('input[name="full_name"]');
        var emailInput = form.querySelector('input[name="email"]');
        var phoneInput = form.querySelector('input[name="phone"]');
        
        // Real-time validation
        if (fullNameInput) {
            fullNameInput.addEventListener('blur', function() {
                showError(this, validateFullName(this.value));
            });
            fullNameInput.addEventListener('input', function() {
                clearError(this);
            });
        }
        
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                showError(this, validateEmail(this.value));
            });
            emailInput.addEventListener('input', function() {
                clearError(this);
            });
        }
        
        if (phoneInput) {
            phoneInput.addEventListener('blur', function() {
                showError(this, validatePhone(this.value));
            });
            phoneInput.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                clearError(this);
            });
        }
        
        // Form submission validation
        form.addEventListener('submit', function(e) {
            var hasError = false;
            var firstError = null;
            
            var nameError = validateFullName(fullNameInput.value);
            if (nameError) {
                hasError = true;
                showError(fullNameInput, nameError);
                if (!firstError) firstError = fullNameInput;
            }
            
            var emailError = validateEmail(emailInput.value);
            if (emailError) {
                hasError = true;
                showError(emailInput, emailError);
                if (!firstError) firstError = emailInput;
            }
            
            var phoneError = validatePhone(phoneInput.value);
            if (phoneError) {
                hasError = true;
                showError(phoneInput, phoneError);
                if (!firstError) firstError = phoneInput;
            }
            
            if (hasError) {
                e.preventDefault();
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
    
    // Password form validation
    var passwordForm = document.querySelector('form[action*="settings.php"] button[name="change_password"]');
    if (passwordForm) {
        var form = passwordForm.closest('form');
        var currentPasswordInput = form.querySelector('input[name="current_password"]');
        var newPasswordInput = form.querySelector('input[name="new_password"]');
        var confirmPasswordInput = form.querySelector('input[name="confirm_password"]');
        
        // Real-time validation
        if (newPasswordInput) {
            newPasswordInput.addEventListener('blur', function() {
                showError(this, validatePassword(this.value));
            });
            newPasswordInput.addEventListener('input', function() {
                clearError(this);
            });
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('blur', function() {
                if (newPasswordInput.value && this.value && newPasswordInput.value !== this.value) {
                    showError(this, 'Passwords do not match.');
                } else {
                    clearError(this);
                }
            });
            confirmPasswordInput.addEventListener('input', function() {
                clearError(this);
            });
        }
        
        // Form submission validation
        form.addEventListener('submit', function(e) {
            var hasError = false;
            var firstError = null;
            
            if (!currentPasswordInput.value) {
                hasError = true;
                showError(currentPasswordInput, 'Current password is required.');
                if (!firstError) firstError = currentPasswordInput;
            }
            
            var passwordError = validatePassword(newPasswordInput.value);
            if (passwordError) {
                hasError = true;
                showError(newPasswordInput, passwordError);
                if (!firstError) firstError = newPasswordInput;
            }
            
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                hasError = true;
                showError(confirmPasswordInput, 'Passwords do not match.');
                if (!firstError) firstError = confirmPasswordInput;
            }
            
            if (currentPasswordInput.value && newPasswordInput.value && 
                currentPasswordInput.value === newPasswordInput.value) {
                hasError = true;
                showError(newPasswordInput, 'New password must be different from current password.');
                if (!firstError) firstError = newPasswordInput;
            }
            
            if (hasError) {
                e.preventDefault();
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});
</script>