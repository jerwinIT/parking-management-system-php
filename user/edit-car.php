<?php
/**
 * Edit Vehicle - Update model and color (saved to database). Plate cannot be changed.
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/register-car.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, plate_number, model, color FROM vehicles WHERE id = ? AND user_id = ?');
$stmt->execute([$id, currentUserId()]);
$car = $stmt->fetch();
if (!$car) {
    header('Location: ' . BASE_URL . '/user/register-car.php');
    exit;
}

$page_title = 'Edit Vehicle';
$current_page = 'register-car';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $color = trim($_POST['color'] ?? '');
    
    // Validate model - no special characters except spaces, hyphens, and parentheses
    if (!empty($model)) {
        if (!preg_match('/^[a-zA-Z0-9 \-()]+$/', $model)) {
            $error = 'Vehicle model can only contain letters, numbers, spaces, hyphens, and parentheses. Special characters are not allowed.';
        } elseif (strlen($model) > 100) {
            $error = 'Vehicle model is too long (maximum 100 characters).';
        }
    }
    
    // Validate year - must be positive number and reasonable range
    if (empty($error) && !empty($year)) {
        if (!preg_match('/^[0-9]+$/', $year)) {
            $error = 'Year must be a valid number without special characters.';
        } else {
            $year_num = (int)$year;
            $current_year = (int)date('Y');
            
            if ($year_num < 0) {
                $error = 'Year cannot be negative.';
            } elseif ($year_num < 1900) {
                $error = 'Year must be 1900 or later.';
            } elseif ($year_num > $current_year) {
                $error = 'Year cannot be greater than the current year (' . $current_year . ').';
            }
        }
    }
    
    // Validate color - only letters and spaces, no special characters or numbers
    if (empty($error) && !empty($color)) {
        if (!preg_match('/^[a-zA-Z ]+$/', $color)) {
            $error = 'Vehicle color can only contain letters and spaces. Special characters and numbers are not allowed.';
        } elseif (strlen($color) > 50) {
            $error = 'Vehicle color is too long (maximum 50 characters).';
        }
    }
    
    if (empty($error)) {
        // Append year to model if provided
        $store_model = $model;
        if ($year) {
            if ($store_model) $store_model = $store_model . ' (' . $year . ')';
            else $store_model = $year;
        }
        
        $stmt = $pdo->prepare('UPDATE vehicles SET model = ?, color = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$store_model ?: null, $color ?: null, $id, currentUserId()]);
        header('Location: ' . BASE_URL . '/user/register-car.php?updated=1');
        exit;
    }
}

$model = $car['model'] ?? '';
$color = $car['color'] ?? '';

// Extract year from model if it's in the format "Model (Year)"
$year = '';
if (preg_match('/^(.+?)\s*\((\d{4})\)$/', $model, $matches)) {
    $model = trim($matches[1]);
    $year = $matches[2];
}

// fetch all vehicles for this user to show below the edit form
$vehiclesStmt = $pdo->prepare('SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
$vehiclesStmt->execute([currentUserId()]);
$vehicles = $vehiclesStmt->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* Modern Vehicle Management Styling - Matches register-car.php */
* {
    font-family: 'Google Sans Flex', sans-serif;
}
.vehicles-header {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    padding: 2rem 1.5rem;
    border-radius: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
}
.vehicles-header-content h2 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #fff;
}
.vehicles-header-content p {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.vehicle-form-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(22, 163, 74, 0.08);
    border: 1px solid #e5f2e8;
    padding: 2rem;
    margin-bottom: 1.5rem;
}
.vehicle-form-card h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1.5rem;
}
.vehicle-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}
.vehicle-form-card .form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}
.vehicle-form-card .form-control,
.vehicle-form-card .form-select {
    border-radius: 10px;
    padding: 0.85rem 1rem;
    border: 1px solid #e5f2e8;
    background: #fafcfb;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}
.vehicle-form-card .form-control:focus,
.vehicle-form-card .form-select:focus {
    border-color: #16a34a;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
.vehicle-form-card .form-control:disabled {
    background: #f0fdf4;
    color: #6b7280;
}
.form-text {
    font-size: 0.85rem;
    color: #9ca3af;
}

.vehicle-form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5f2e8;
}

.btn-cancel {
    background: #fff;
    border: 1.5px solid #d1d5db;
    color: #374151;
    padding: 0.9rem 1.5rem;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
}
.btn-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #111;
}
.btn-cancel:active {
    background: #f3f4f6;
}

.btn-submit {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border: none;
    color: #fff;
    padding: 0.9rem 1.5rem;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(22, 163, 74, 0.25);
}
.btn-submit:active {
    transform: translateY(0);
}

.vehicle-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.vehicle-card {
    background: #fff;
    border: 1px solid #e5f2e8;
    border-radius: 12px;
    padding: 1.5rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1.5rem;
    align-items: center;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.vehicle-card:hover {
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.12);
    border-color: #16a34a;
}

.vehicle-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
    flex-shrink: 0;
}

.vehicle-details-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.vehicle-plate {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
}

.default-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #dcfce7;
    color: #166534;
    border-radius: 999px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.vehicle-model {
    font-size: 0.95rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.vehicle-color {
    font-size: 0.85rem;
    color: #9ca3af;
}

.vehicle-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-action {
    border: none;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    white-space: nowrap;
}

.btn-action-default {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
}
.btn-action-default:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(22, 163, 74, 0.25);
}
.btn-action-default:active {
    transform: translateY(0);
}

.btn-action-edit {
    background: #f3f4f6;
    color: #6b7280;
    padding: 0.65rem 0.75rem;
}
.btn-action-edit:hover {
    background: #e5e7eb;
    color: #374151;
}

.btn-action-delete {
    background: #fee2e2;
    color: #dc2626;
    padding: 0.65rem 0.75rem;
}
.btn-action-delete:hover {
    background: #fecaca;
    color: #b91c1c;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: #f9fafb;
    border: 2px dashed #e5f2e8;
    border-radius: 12px;
    color: #9ca3af;
}
.empty-state i {
    font-size: 3.5rem;
    color: #d1d5db;
    display: block;
    margin-bottom: 1rem;
}
.empty-state p {
    margin: 0.5rem 0;
    font-size: 0.95rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .vehicles-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .vehicle-form-grid {
        grid-template-columns: 1fr;
    }
    .vehicle-card {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    .vehicle-icon {
        width: 48px;
        height: 48px;
        font-size: 1.5rem;
    }
    .vehicle-actions {
        gap: 0.25rem;
    }
    .btn-action {
        padding: 0.5rem 0.6rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .vehicles-header {
        padding: 1.5rem 1rem;
    }
    .vehicles-header-content h2 {
        font-size: 1.4rem;
    }
    .vehicle-form-card {
        padding: 1.5rem 1rem;
    }
    .vehicle-plate {
        font-size: 1rem;
    }
    .btn-action {
        padding: 0.5rem 0.5rem;
        gap: 0;
    }
    .btn-action i {
        margin: 0;
    }
}

/* Validation Error Styling */
.form-control.is-invalid {
    border-color: #dc2626 !important;
    background-color: #fef2f2;
}

.form-control.is-invalid:focus {
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
}

/* Alert Styling */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #fca5a5;
    color: #991b1b;
}

.alert-danger i {
    font-size: 1.25rem;
    color: #dc2626;
}
</style>

<div class="my-vehicles-page">
    <div class="vehicles-header">
        <div class="vehicles-header-content">
            <h2><i class="bi bi-car-front me-2"></i>Edit Vehicle</h2>
            <p>Update vehicle details</p>
        </div>
    </div>

    <div class="vehicle-form-card">
        <h5><i class="bi bi-pencil-square me-2"></i>Vehicle Information</h5>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?= BASE_URL ?>/user/edit-car.php?id=<?= $id ?>" id="editVehicleForm">
            <div class="vehicle-form-grid">
                <div>
                    <label class="form-label">Plate Number</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($car['plate_number']) ?>" disabled>
                    <div class="form-text">Plate number cannot be changed</div>
                </div>
                <div>
                    <label class="form-label">Vehicle Model</label>
                    <input type="text" name="model" id="model" class="form-control" value="<?= htmlspecialchars($model) ?>" placeholder="e.g. Toyota Corolla">
                    <div class="form-text">Make and model of your vehicle</div>
                    <div id="modelError" style="display: none; color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label class="form-label">Year</label>
                    <input type="text" name="year" id="year" class="form-control" value="<?= htmlspecialchars($year) ?>" placeholder="e.g. 2020" maxlength="4">
                    <div class="form-text">Year of manufacture (optional)</div>
                    <div id="yearError" style="display: none; color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label class="form-label">Color</label>
                    <input type="text" name="color" id="color" class="form-control" value="<?= htmlspecialchars($color) ?>" placeholder="e.g. White, Black, Silver">
                    <div class="form-text">Vehicle exterior color (optional)</div>
                    <div id="colorError" style="display: none; color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem;"></div>
                </div>
            </div>
            <div class="vehicle-form-actions">
                <a href="<?= BASE_URL ?>/user/register-car.php" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
                <button type="submit" class="btn-submit"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <!-- My Vehicles List -->
    <div class="vehicle-form-card">
        <h5><i class="bi bi-list me-2"></i>My Vehicles</h5>
        <?php if (empty($vehicles)): ?>
            <div class="empty-state">
                <i class="bi bi-car-front"></i>
                <p>No vehicles registered yet</p>
            </div>
        <?php else: ?>
            <div class="vehicle-list">
                <?php foreach ($vehicles as $v): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-icon">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <div class="vehicle-details">
                            <div class="vehicle-details-header">
                                <div class="vehicle-plate"><?= htmlspecialchars($v['plate_number']) ?></div>
                                <?php if ($v['is_default']): ?>
                                    <span class="default-badge">
                                        <i class="bi bi-check-lg"></i> Default
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="vehicle-model"><?= htmlspecialchars($v['model'] ?? '—') ?></div>
                            <div class="vehicle-color"><i class="bi bi-palette"></i> <?= htmlspecialchars($v['color'] ?? 'Not specified') ?></div>
                        </div>
                        <div class="vehicle-actions">
                            <?php if (!$v['is_default']): ?>
                                <form method="post" action="<?= BASE_URL ?>/user/register-car.php" style="display: inline;">
                                    <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                                    <input type="hidden" name="set_default" value="1">
                                    <button type="submit" class="btn-action btn-action-default" title="Set as Default">
                                        <i class="bi bi-check-lg"></i> Set Default
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/user/edit-car.php?id=<?= (int)$v['id'] ?>" class="btn-action btn-action-edit" title="Edit">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="<?= BASE_URL ?>/user/delete-car.php?id=<?= (int)$v['id'] ?>" class="btn-action btn-action-delete" title="Delete" onclick="return confirm('Remove this vehicle from your list?');">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editVehicleForm');
    const modelField = document.getElementById('model');
    const yearField = document.getElementById('year');
    const colorField = document.getElementById('color');
    const modelError = document.getElementById('modelError');
    const yearError = document.getElementById('yearError');
    const colorError = document.getElementById('colorError');
    
    // Validation functions
    function validateModel(model) {
        if (model && !/^[a-zA-Z0-9 \-()]+$/.test(model)) {
            return 'Vehicle model can only contain letters, numbers, spaces, hyphens, and parentheses.';
        }
        if (model && model.length > 100) {
            return 'Vehicle model is too long (maximum 100 characters).';
        }
        return '';
    }
    
    function validateYear(year) {
        if (!year) return ''; // Optional field
        if (!/^[0-9]+$/.test(year)) {
            return 'Year must be a valid number without special characters.';
        }
        const yearNum = parseInt(year, 10);
        const currentYear = new Date().getFullYear();
        if (yearNum < 0) return 'Year cannot be negative.';
        if (yearNum < 1900) return 'Year must be 1900 or later.';
        if (yearNum > currentYear) {
            return 'Year cannot be greater than the current year (' + currentYear + ').';
        }
        return '';
    }
    
    function validateColor(color) {
        if (color && !/^[a-zA-Z ]+$/.test(color)) {
            return 'Vehicle color can only contain letters and spaces. Special characters and numbers are not allowed.';
        }
        if (color && color.length > 50) {
            return 'Vehicle color is too long (maximum 50 characters).';
        }
        return '';
    }
    
    function showError(field, errorEl, message) {
        if (message) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
            field.classList.add('is-invalid');
            field.style.borderColor = '#dc2626';
        } else {
            errorEl.style.display = 'none';
            field.classList.remove('is-invalid');
            field.style.borderColor = '';
        }
    }
    
    // Real-time validation on blur
    if (modelField) {
        modelField.addEventListener('blur', function() {
            const error = validateModel(this.value.trim());
            showError(modelField, modelError, error);
        });
        
        modelField.addEventListener('input', function() {
            modelError.style.display = 'none';
            this.classList.remove('is-invalid');
            this.style.borderColor = '';
        });
    }
    
    if (yearField) {
        yearField.addEventListener('blur', function() {
            const error = validateYear(this.value.trim());
            showError(yearField, yearError, error);
        });
        
        yearField.addEventListener('input', function() {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            yearError.style.display = 'none';
            this.classList.remove('is-invalid');
            this.style.borderColor = '';
        });
    }
    
    if (colorField) {
        colorField.addEventListener('blur', function() {
            const error = validateColor(this.value.trim());
            showError(colorField, colorError, error);
        });
        
        colorField.addEventListener('input', function() {
            colorError.style.display = 'none';
            this.classList.remove('is-invalid');
            this.style.borderColor = '';
        });
    }
    
    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            let firstError = null;
            
            // Validate model
            const modelValue = modelField ? modelField.value.trim() : '';
            const modelErr = validateModel(modelValue);
            if (modelErr) {
                hasError = true;
                showError(modelField, modelError, modelErr);
                if (!firstError) firstError = modelField;
            }
            
            // Validate year
            const yearValue = yearField ? yearField.value.trim() : '';
            const yearErr = validateYear(yearValue);
            if (yearErr) {
                hasError = true;
                showError(yearField, yearError, yearErr);
                if (!firstError) firstError = yearField;
            }
            
            // Validate color
            const colorValue = colorField ? colorField.value.trim() : '';
            const colorErr = validateColor(colorValue);
            if (colorErr) {
                hasError = true;
                showError(colorField, colorError, colorErr);
                if (!firstError) firstError = colorField;
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

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>