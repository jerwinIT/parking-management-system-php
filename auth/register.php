<?php
/**
 * Registration Page - New user (driver) sign up
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $full_name = trim($first_name . ' ' . $last_name);
    $phone = trim($_POST['phone'] ?? '');

    // Prepare email local/domain parts for validation
    $email_parts = explode('@', $email);
    $email_local = $email_parts[0] ?? '';
    $email_domain = $email_parts[1] ?? '';

    // Validation - check required fields, prefer specific email message
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($phone)) {
        if (empty($email)) {
            $error = 'Email is required';
        } else {
            $error = 'Please fill in all required fields.';
        }
    }
    // Username validation
    elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Username must be between 3 and 20 characters.';
    }
    elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, underscores, and hyphens.';
    }
    // Email validation - RFC-like checks
    elseif (strlen($email) > 254) {
        $error = 'Email address is too long (maximum 254 characters).';
    }
    // Basic structure and allowed characters
    elseif (!preg_match('/^[A-Za-z0-9._+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)) {
        $error = 'Please enter a valid email address (local-part@domain).';
    }
    // No consecutive dots
    elseif (strpos($email, '..') !== false) {
        $error = 'Email cannot contain consecutive dots.';
    }
    // Local-part allowed characters and length
    elseif (!preg_match('/^[A-Za-z0-9._+\-]+$/', $email_local)) {
        $error = 'Invalid email format';
    }
    elseif (strlen($email_local) < 3) {
        $error = 'Invalid email format';
    }
    // Local-part must contain at least one letter and cannot be all numbers
    elseif (!preg_match('/[A-Za-z]/', $email_local)) {
        $error = 'Email must contain at least one letter';
    }
    elseif (preg_match('/^[0-9]+$/', $email_local)) {
        $error = 'Email cannot be all numbers';
    }
    // Local part: cannot start or end with dot
    elseif (strlen($email_local) === 0 || $email_local[0] === '.' || substr($email_local, -1) === '.') {
        $error = 'Local part of the email cannot start or end with a dot.';
    }
    // Domain must contain at least one dot
    elseif (strpos($email_domain, '.') === false) {
        $error = 'Email domain must contain at least one dot (e.g. example.com).';
    }
    // Validate domain labels: allowed chars and no leading/trailing hyphens
    elseif (!preg_match('/^(?!-)([A-Za-z0-9\-]+)(?<!-)(\.(?!-)([A-Za-z0-9\-]+)(?<!-))*$/', $email_domain)) {
        $error = 'Email domain contains invalid characters or labels.';
    }
    // Password validation - Strong requirements
    elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    }
    elseif (strlen($password) > 128) {
        $error = 'Password is too long (maximum 128 characters).';
    }
    elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    }
    elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    }
    elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    }
    elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = 'Password must contain at least one special character (@, #, $, %, etc.).';
    }
    elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    }
                // Name validation - reject special characters, numbers, and symbols (only letters and spaces allowed)
                elseif (!preg_match('/^[a-zA-Z ]+$/', $first_name)) {
                    $error = 'First name can only contain letters and spaces. Special characters and numbers are not allowed.';
                }
                elseif (!preg_match('/^[a-zA-Z ]+$/', $last_name)) {
                    $error = 'Last name can only contain letters and spaces. Special characters and numbers are not allowed.';
                }
                // Phone validation - Philippine format: 09XXXXXXXXX (11 digits)
                $phone_normalized = preg_replace('/[^0-9]/', '', $phone);
                
                // Validate Philippine mobile format: starts with 09 followed by 9 more digits (11 digits total)
                if (!preg_match('/^09[0-9]{9}$/', $phone_normalized)) {
                    $error = 'Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).';
                }
    else {
        $pdo = getDB();
        // Normalize email to lowercase for case-insensitive check
        $email_lower = strtolower($email);
        
        // Check if username exists (case-insensitive)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken. Please choose another.';
        } else {
            // Check if email exists (case-insensitive)
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ?');
            $stmt->execute([$email_lower]);
            if ($stmt->fetch()) {
                $error = 'Email already registered. Please use another email or login.';
            } else {
                // Only generate verification token if verification is required
                if (defined('REQUIRE_EMAIL_VERIFICATION') && REQUIRE_EMAIL_VERIFICATION) {
                    $verification_token = bin2hex(random_bytes(32));
                    $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                } else {
                    $verification_token = null;
                    $token_expires = null;
                }

        // First/Last name and phone validation

                // All validations passed - create UNVERIFIED account
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Determine role id for regular users (ensure 'user' role exists)
                try {
                    $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE LOWER(role_name) = LOWER(?) LIMIT 1');
                    $stmtRole->execute(['user']);
                    $role_user = $stmtRole->fetchColumn();

                    if ($role_user === false) {
                        $stmtIns = $pdo->prepare('INSERT INTO roles (role_name, description) VALUES (?, ?)');
                        $stmtIns->execute(['user', 'Driver / User - can book parking slots']);
                        $role_user = $pdo->lastInsertId();
                    } else {
                        $role_user = (int)$role_user;
                    }
                } catch (Exception $e) {
                    // If anything goes wrong (DB permissions, missing table), fall back to default id 2
                    $role_user = 2;
                }

                // Ensure the verification columns exist; attempt to add them if missing.
                try {
                    $colsToCheck = ['email_verified','verification_token','verification_token_expires'];
                    $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN (?,?,?)");
                    $stmtCols->execute($colsToCheck);
                    $found = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
                    $missing = array_diff($colsToCheck, $found ?: []);

                    if (!empty($missing)) {
                        $alter = [];
                        if (in_array('email_verified', $missing)) $alter[] = "ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0";
                        if (in_array('verification_token', $missing)) $alter[] = "ADD COLUMN `verification_token` VARCHAR(128) DEFAULT NULL";
                        if (in_array('verification_token_expires', $missing)) $alter[] = "ADD COLUMN `verification_token_expires` DATETIME DEFAULT NULL";

                        if (!empty($alter)) {
                            // Try to alter the users table to add missing columns
                            $pdo->exec('ALTER TABLE users ' . implode(', ', $alter));
                        }
                    }
                } catch (Exception $e) {
                    // If altering the table fails (insufficient privileges), continue and build INSERT without those columns.
                }

                // Re-check which columns are present to build INSERT dynamically
                $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
                $stmtCols->execute();
                $allCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

                // Normalize phone to local 0-prefixed format (09XXXXXXXXX) before inserting
                $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
                if (preg_match('/^(\+63|63)9[0-9]{9}$/', $phone_clean)) {
                    $phone = preg_replace('/^(\+63|63)/', '0', $phone_clean);
                } elseif (preg_match('/^9[0-9]{9}$/', $phone_clean)) {
                    $phone = '0' . $phone_clean;
                } else {
                    $phone = $phone_clean;
                }

                $fields = ['role_id','username','email','password','full_name','phone'];
                $placeholders = ['?','?','?','?','?','?'];
                $values = [$role_user, $username, $email_lower, $hashed, $full_name, $phone ?: null];

                if (in_array('email_verified', $allCols)) {
                    $fields[] = 'email_verified';
                    $placeholders[] = '?';
                    // If the app requires verification, set to 0; otherwise auto-verify (1)
                    $values[] = (defined('REQUIRE_EMAIL_VERIFICATION') && REQUIRE_EMAIL_VERIFICATION) ? 0 : 1;
                }
                if (in_array('verification_token', $allCols) && defined('REQUIRE_EMAIL_VERIFICATION') && REQUIRE_EMAIL_VERIFICATION) {
                    $fields[] = 'verification_token';
                    $placeholders[] = '?';
                    $values[] = $verification_token;
                }
                if (in_array('verification_token_expires', $allCols) && defined('REQUIRE_EMAIL_VERIFICATION') && REQUIRE_EMAIL_VERIFICATION) {
                    $fields[] = 'verification_token_expires';
                    $placeholders[] = '?';
                    $values[] = $token_expires;
                }

                $sql = 'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                // Send verification email only if verification is required
                if (defined('REQUIRE_EMAIL_VERIFICATION') && REQUIRE_EMAIL_VERIFICATION) {
                    require_once dirname(__DIR__) . '/config/email_helper.php';
                    $email_sent = sendVerificationEmail($email_lower, $full_name, $verification_token);
                    if ($email_sent) {
                        setAlert('Registration successful! Please check your email to verify your account.', 'success');
                    } else {
                        setAlert('Account created! However, we couldn\'t send the verification email. Please contact support.', 'warning');
                    }
                } else {
                    setAlert('Registration successful! You can now log in.', 'success');
                }
                
                // Redirect to login
                header('Location: ' . BASE_URL . '/auth/login.php?registered=1');
                exit;
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
    <title>Register - Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%); padding: 2rem 0; font-family: 'Google Sans Flex', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .register-card { border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.15); }
        .btn-primary { background: #16a34a; border-color: #16a34a; }
        .btn-primary:hover { background: #22c55e; border-color: #22c55e; }
        .password-requirements {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 1.25rem;
        }
        .password-requirements li {
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .password-requirements li.valid {
            color: #16a34a;
        }
        .password-requirements li.valid::marker {
            content: "✓ ";
        }
        .form-text-helper {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .is-invalid {
            border-color: #dc2626 !important;
        }
        .is-valid {
            border-color: #16a34a !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card register-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="<?= BASE_URL ?>/assets/parkit.png" alt="Parking Management System" style="max-height: 56px; width: auto;" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                            <i class="bi bi-p-square-fill d-none text-success" style="font-size: 2.25rem;"></i>
                            <h4 class="mt-2">Create Account</h4>
                            <p class="text-muted">Register as a driver</p>
                        </div>
                        <form method="post" action="<?= BASE_URL ?>/auth/register.php" id="registerForm">
                            <div class="row g-2">
                                <div class="col-12 mb-3">
                                    <div id="namePhoneWarning" class="alert alert-warning" style="display:none;">
                                            <strong>Required:</strong>
                                            <ul style="margin:0.5rem 0 0 1rem;padding:0;">
                                                <li>First name and last name are required.</li>
                                                <li>Phone must contain exactly 11 digits.</li>
                                            </ul>
                                        </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" id="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" id="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-12 mb-2">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                                    <input type="tel" inputmode="tel" pattern="^(\+63|63|0)9[0-9]{9}$" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" maxlength="14" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required>
                                                </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Username <span class="text-danger">*</span></label>
                                            <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_-]+">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required maxlength="255">
                            </div>
                            <div class="mb-2">
                                <div class="form-text-helper">3-20 characters. Letters, numbers, underscores, and hyphens only.</div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12 mb-2">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control" required minlength="8" maxlength="128">
                                    <div class="password-requirements" id="passwordRequirements">
                                        <strong style="color: #374151; font-size: 0.9rem;">Password must contain:</strong>
                                        <ul>
                                            <li id="req-length">At least 8 characters</li>
                                            <li id="req-uppercase">One uppercase letter (A-Z)</li>
                                            <li id="req-lowercase">One lowercase letter (a-z)</li>
                                            <li id="req-number">One number (0-9)</li>
                                            <li id="req-special">One special character (@#$%^&*!)</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 mb-2">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                    <div class="form-text-helper" id="passwordMatch"></div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-3" id="submitBtn">Register</button>
                        </form>
                        <p class="text-center mt-3 mb-0">
                            <a href="<?= BASE_URL ?>/auth/login.php">Already have an account? Login</a>
                        </p>
                    </div>
                </div>
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
    // Real-time password validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        // Check each requirement
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);
        
        // Update visual feedback
        document.getElementById('req-length').className = hasLength ? 'valid' : '';
        document.getElementById('req-uppercase').className = hasUppercase ? 'valid' : '';
        document.getElementById('req-lowercase').className = hasLowercase ? 'valid' : '';
        document.getElementById('req-number').className = hasNumber ? 'valid' : '';
        document.getElementById('req-special').className = hasSpecial ? 'valid' : '';
        
        // Check if all requirements are met
        const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
        
        if (password.length > 0) {
            if (allValid) {
                passwordInput.classList.remove('is-invalid');
                passwordInput.classList.add('is-valid');
            } else {
                passwordInput.classList.remove('is-valid');
                passwordInput.classList.add('is-invalid');
            }
        } else {
            passwordInput.classList.remove('is-valid', 'is-invalid');
        }
        
        // Check password match
        checkPasswordMatch();
    });
    
    confirmInput.addEventListener('input', checkPasswordMatch);
    
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        const matchDiv = document.getElementById('passwordMatch');
        
        if (confirm.length > 0) {
            if (password === confirm) {
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
                matchDiv.textContent = '✓ Passwords match';
                matchDiv.style.color = '#16a34a';
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
                matchDiv.textContent = '✗ Passwords do not match';
                matchDiv.style.color = '#dc2626';
            }
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            matchDiv.textContent = '';
        }
    }
    
    // Username validation
    const usernameInput = document.getElementById('username');
    usernameInput.addEventListener('input', function() {
        const username = this.value;
        const isValid = /^[a-zA-Z0-9_-]+$/.test(username) && username.length >= 3 && username.length <= 20;
        
        if (username.length > 0) {
            if (isValid) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        } else {
            this.classList.remove('is-valid', 'is-invalid');
        }
    });
    // First/Last name validation: reject numbers/symbols
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    [firstNameInput, lastNameInput].forEach(function(el) {
        if (!el) return;
        el.addEventListener('input', function() {
            const v = this.value.trim();
            const valid = /^[A-Za-z\s]+$/.test(v);
            if (v.length > 0) {
                if (valid) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    });
    
    // Email validation with whitelist of legitimate email providers
    const emailInput = document.getElementById('email');
    
    // Email validation (client-side) - RFC-like format checks
    // Returns an empty string when valid, otherwise an error message
    function validateEmailFormat(email) {
        const e = (email || '').toLowerCase().trim();
        if (!e) return 'Email is required';
        if (e.length > 254) return 'Invalid email format';

        // Basic overall structure
        const basic = /^[A-Za-z0-9._+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/;
        if (!basic.test(e)) return 'Invalid email format';
        if (e.indexOf('..') !== -1) return 'Invalid email format';

        const parts = e.split('@');
        const local = parts[0] || '';
        const domain = parts[1] || '';

        // Local part checks
        if (!local) return 'Invalid email format';
        if (local.length < 3) return 'Invalid email format';
        if (!/^[A-Za-z0-9._+\-]+$/.test(local)) return 'Invalid email format';
        if (local.startsWith('.') || local.endsWith('.')) return 'Invalid email format';
        if (!/[A-Za-z]/.test(local)) return 'Email must contain at least one letter';
        if (/^[0-9]+$/.test(local)) return 'Email cannot be all numbers';

        // Domain checks
        if (!domain || domain.startsWith('.') || domain.endsWith('.')) return 'Invalid email format';
        if (domain.indexOf('.') === -1) return 'Invalid email format';
        const labels = domain.split('.');
        for (let lab of labels) {
            if (!lab) return 'Invalid email format';
            if (lab.startsWith('-') || lab.endsWith('-')) return 'Invalid email format';
            if (!/^[A-Za-z0-9\-]+$/.test(lab)) return 'Invalid email format';
        }

        return '';
    }

    emailInput.addEventListener('input', function() {
        const msg = validateEmailFormat(this.value);
        if (!msg) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });

    // Phone input validation: Philippine format 09XXXXXXXXX with live feedback
    const phoneInputEl = document.getElementById('phone');
    if (phoneInputEl) {
        phoneInputEl.addEventListener('input', function() {
            const raw = this.value || '';
            const v = raw.replace(/[^0-9]/g, ''); // allow digits only
            const warnEl = document.getElementById('namePhoneWarning');
            const phPattern = /^09[0-9]{9}$/;

            // Check format
            if (!phPattern.test(v)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                if (warnEl) { warnEl.innerHTML = '<strong>Invalid phone number. Use format 09XXXXXXXXX (11 digits starting with 09).</strong>'; warnEl.style.display = 'block'; }
                return;
            }

            // Valid phone
            this.classList.remove('is-invalid');
            if (v.length > 0) this.classList.add('is-valid'); else this.classList.remove('is-valid');

            // If names are missing or invalid, show the name/phone warning; otherwise hide
            const firstVal = (document.getElementById('first_name')?.value || '').trim();
            const lastVal = (document.getElementById('last_name')?.value || '').trim();
            const nameInvalid = !/^[A-Za-z\s]+$/.test(firstVal) || !/^[A-Za-z\s]+$/.test(lastVal);
            if ((!firstVal || !lastVal || nameInvalid) && warnEl) {
                warnEl.innerHTML = '<strong>Required:</strong><ul style="margin:0.5rem 0 0 1rem;padding:0;"><li>First name and last name are required and must contain only letters and spaces (no special characters or numbers).</li><li>Phone must be in format 09XXXXXXXXX (11 digits starting with 09).</li></ul>';
                warnEl.style.display = 'block';
            } else if (warnEl) {
                warnEl.style.display = 'none';
            }
        });
    }
    
    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(e) {
        let hasError = false;
        let errorMessage = '';
        
        // Validate email with domain whitelist
        const email = emailInput.value.trim();
        
        // Validate email format
        const emailCheck = validateEmailFormat(email);
        if (emailCheck) {
            hasError = true;
            errorMessage = emailCheck;
            emailInput.classList.add('is-invalid');
            emailInput.focus();
        }
        
        // Validate username
        const username = usernameInput.value;
        const usernameValid = /^[a-zA-Z0-9_-]+$/.test(username) && username.length >= 3 && username.length <= 20;
        if (!usernameValid && !hasError) {
            hasError = true;
            errorMessage = 'Username must be 3-20 characters and contain only letters, numbers, underscores, and hyphens.';
            usernameInput.classList.add('is-invalid');
            usernameInput.focus();
        }

        // Validate first and last name (letters and spaces only - reject special characters)
        const firstVal = firstNameInput ? firstNameInput.value.trim() : '';
        const lastVal = lastNameInput ? lastNameInput.value.trim() : '';
        const namePattern = /^[A-Za-z\s]+$/;
        if (!hasError) {
            if (!firstVal || !lastVal) {
                hasError = true;
                errorMessage = 'First name and last name are required.';
                if (!firstVal && firstNameInput) { firstNameInput.classList.add('is-invalid'); firstNameInput.focus(); }
                else if (!lastVal && lastNameInput) { lastNameInput.classList.add('is-invalid'); lastNameInput.focus(); }
            } else if (!namePattern.test(firstVal)) {
                hasError = true;
                errorMessage = 'First name can only contain letters and spaces. Special characters and numbers are not allowed.';
                if (firstNameInput) { firstNameInput.classList.add('is-invalid'); firstNameInput.focus(); }
            } else if (!namePattern.test(lastVal)) {
                hasError = true;
                errorMessage = 'Last name can only contain letters and spaces. Special characters and numbers are not allowed.';
                if (lastNameInput) { lastNameInput.classList.add('is-invalid'); lastNameInput.focus(); }
            }
        }
        
        // Validate password
        const password = passwordInput.value;
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);
        const passwordValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
        
        if (!passwordValid && !hasError) {
            hasError = true;
            errorMessage = 'Password must meet all requirements: 8+ characters, uppercase, lowercase, number, and special character.';
            passwordInput.classList.add('is-invalid');
            passwordInput.focus();
        }
        
        // Validate password match
        const confirm = confirmInput.value;
        if (password !== confirm && !hasError) {
            hasError = true;
            errorMessage = 'Passwords do not match.';
            confirmInput.classList.add('is-invalid');
            confirmInput.focus();
        }
        // Validate phone - Philippine format: 09XXXXXXXXX (11 digits)
        const phoneValRaw = document.getElementById('phone') ? document.getElementById('phone').value.trim() : '';
        if (!hasError) {
            const phoneVal = phoneValRaw.replace(/[^0-9]/g, '');
            const phPattern = /^09[0-9]{9}$/;
            
            if (!phoneVal) {
                hasError = true;
                errorMessage = 'Phone number is required. Please enter in format 09XXXXXXXXX.';
                const ph = document.getElementById('phone'); ph.classList.add('is-invalid'); ph.focus();
            } else if (!phPattern.test(phoneVal)) {
                hasError = true;
                errorMessage = 'Phone number must be in format 09XXXXXXXXX (11 digits starting with 09).';
                const ph = document.getElementById('phone'); ph.classList.add('is-invalid'); ph.focus();
            }
        }
        
        // Prevent submission if there are errors
        if (hasError) {
            e.preventDefault();
            showAlert(errorMessage, 'danger');
        }
    });
    
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
    <?php
    if ($error) {
        echo '<script>showAlert("' . addslashes(htmlspecialchars($error)) . '", "danger");</script>';
    }
    ?>
</body>
</html>