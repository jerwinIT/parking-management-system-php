<?php
/**
 * Register Car - User adds vehicle (plate, model, color)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
require_once dirname(__DIR__) . '/models/PlateValidator.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$page_title = 'My Vehicles';
$current_page = 'register-car';
$error = '';
$success = '';

$pdo = getDB();
// One-time migration: add is_default column if missing
try {
    $pdo->query('SELECT is_default FROM vehicles LIMIT 1');
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER color');
    } else {
        throw $e;
    }
}

// Add new columns for vehicle type, owner details, original plate and updated_at if missing
try {
    $pdo->query('SELECT vehicle_type, plate_number_original, owner_name, owner_phone, owner_email, updated_at FROM vehicles LIMIT 1');
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Unknown column') !== false) {
        // Add columns safely
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN vehicle_type VARCHAR(50) NULL AFTER plate_number");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN plate_number_original VARCHAR(50) NULL AFTER vehicle_type");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN owner_name VARCHAR(100) NULL AFTER plate_number_original");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN owner_phone VARCHAR(30) NULL AFTER owner_name");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN owner_email VARCHAR(150) NULL AFTER owner_phone");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    } else {
        throw $e;
    }
}

// Which view: list or add form
$view = $_GET['view'] ?? 'list';
if (!in_array($view, ['list', 'add'], true)) {
    $view = 'list';
}

// Handle Set Default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'])) {
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    if ($vehicle_id) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND user_id = ?');
        $stmt->execute([$vehicle_id, currentUserId()]);
        if ($stmt->fetch()) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE vehicles SET is_default = 0 WHERE user_id = ?')->execute([currentUserId()]);
                $pdo->prepare('UPDATE vehicles SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$vehicle_id, currentUserId()]);
                $pdo->commit();
                setAlert('Default vehicle updated.', 'success');
                header('Location: ' . BASE_URL . '/user/register-car.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to set default vehicle.';
            }
        }
    }
}

// Handle Add/Edit Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    $plate_input = trim($_POST['plate_number'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);

    // Basic server-side validations
    if ($plate_input === '') {
        $error = 'Plate number is required.';
    } elseif ($vehicle_type === '') {
        $error = 'Please select a vehicle type.';
    } else {
        // Validate owner name - only letters and spaces, no special characters or numbers
        if (empty($owner_name)) {
            $error = 'Owner name is required.';
        } elseif (!preg_match('/^[a-zA-Z ]+$/', $owner_name)) {
            $error = 'Owner name can only contain letters and spaces. Special characters and numbers are not allowed.';
        } elseif (strlen($owner_name) < 2) {
            $error = 'Owner name must be at least 2 characters long.';
        } elseif (strlen($owner_name) > 100) {
            $error = 'Owner name is too long (maximum 100 characters).';
        }
        
        // Validate phone (Philippine format: 09XXXXXXXXX)
        if (empty($error)) {
            if (empty($owner_phone)) {
                $error = 'Owner phone is required.';
            } else {
                $clean_phone = preg_replace('/[^0-9]/', '', $owner_phone);
                if (!preg_match('/^09[0-9]{9}$/', $clean_phone)) {
                    $error = 'Owner phone must be in format 09XXXXXXXXX (11 digits starting with 09).';
                }
            }
        }
        
        // Validate email - must contain letters and be valid format
        if (empty($error)) {
            if (empty($owner_email)) {
                $error = 'Owner email is required.';
            } elseif (!preg_match('/[a-zA-Z]/', $owner_email)) {
                $error = 'Owner email must contain at least one letter.';
            } elseif (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Owner email must be a valid email address.';
            }
        }
        
        // Validate model - no special characters except spaces, hyphens, and parentheses
        if (empty($error) && !empty($model)) {
            if (!preg_match('/^[a-zA-Z0-9 \-()]+$/', $model)) {
                $error = 'Vehicle model can only contain letters, numbers, spaces, hyphens, and parentheses. Special characters are not allowed.';
            } elseif (strlen($model) > 100) {
                $error = 'Vehicle model is too long (maximum 100 characters).';
            }
        }
        
        // Validate year - must be positive number and reasonable range
        if (empty($error) && !empty($year)) {
            // Remove any non-numeric characters
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
            // Validate plate against selected vehicle type
            list($valid_plate, $plate_msg) = PlateValidator::validate($vehicle_type, $plate_input);
            if (!$valid_plate) {
                $error = $plate_msg;
            }
        }

        if (empty($error)) {
            $pdo = getDB();
            // Normalize values for storage
            $plate_norm = PlateValidator::normalize($plate_input);
            $plate_original = $plate_input;
            $phone_norm = PlateValidator::normalizePhone($owner_phone);
            $email_norm = strtolower($owner_email);

            if ($vehicle_id) {
                // Edit existing
                $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND user_id = ?');
                $stmt->execute([$vehicle_id, currentUserId()]);
                if (!$stmt->fetch()) {
                    $error = 'Vehicle not found.';
                } else {
                    // Prevent plate change if there are active/parked bookings for this vehicle
                    $stmtCheck = $pdo->prepare('SELECT plate_number FROM vehicles WHERE id = ? AND user_id = ?');
                    $stmtCheck->execute([$vehicle_id, currentUserId()]);
                    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if ($existing && $existing['plate_number'] !== $plate_norm) {
                        $stmtB = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN (\'pending\',\'confirmed\',\'parked\')');
                        $stmtB->execute([$vehicle_id]);
                        $activeCount = (int) $stmtB->fetchColumn();
                        if ($activeCount > 0) {
                            $error = 'Cannot change plate number while there are active or parked bookings for this vehicle. Cancel or complete those bookings first.';
                        }
                    }

                    if (empty($error)) {
                        // Check uniqueness (exclude current)
                        $stmtUnique = $pdo->prepare('SELECT id FROM vehicles WHERE plate_number = ? AND id != ?');
                        $stmtUnique->execute([$plate_norm, $vehicle_id]);
                        if ($stmtUnique->fetch()) {
                            $error = 'This plate number is already registered.';
                        } else {
                            // Append year to model if provided
                            $store_model = $model;
                            if ($year) {
                                if ($store_model) {
                                    $store_model = $store_model . ' (' . $year . ')';
                                } else {
                                    $store_model = $year;
                                }
                            }
                            $pdo->prepare('UPDATE vehicles SET plate_number = ?, plate_number_original = ?, vehicle_type = ?, model = ?, color = ?, owner_name = ?, owner_phone = ?, owner_email = ? WHERE id = ? AND user_id = ?')
                                ->execute([$plate_norm, $plate_original, $vehicle_type, $store_model ?: null, $color ?: null, $owner_name, $phone_norm, $email_norm, $vehicle_id, currentUserId()]);
                            setAlert('Vehicle details updated successfully.', 'success');
                            header('Location: ' . BASE_URL . '/user/register-car.php');
                            exit;
                        }
                    }
                }
            } else {
                // Add new
                $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE plate_number = ?');
                $stmt->execute([$plate_norm]);
                if ($stmt->fetch()) {
                    $error = 'This plate number is already registered.';
                } else {
                    // Append year to model if provided
                    $store_model = $model;
                    if ($year) {
                        if ($store_model) $store_model = $store_model . ' (' . $year . ')';
                        else $store_model = $year;
                    }
                    $is_first = $pdo->prepare('SELECT COUNT(*) FROM vehicles WHERE user_id = ?');
                    $is_first->execute([currentUserId()]);
                    $is_first = $is_first->fetchColumn() == 0;
                    $pdo->prepare('INSERT INTO vehicles (user_id, plate_number, plate_number_original, vehicle_type, model, color, owner_name, owner_phone, owner_email, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([currentUserId(), $plate_norm, $plate_original, $vehicle_type, $store_model ?: null, $color ?: null, $owner_name, $phone_norm, $email_norm, $is_first ? 1 : 0]);
                    setAlert('Vehicle added successfully.', 'success');
                    header('Location: ' . BASE_URL . '/user/register-car.php');
                    exit;
                }
            }
        }
    }
}

$vehicles = $pdo->prepare('SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
$vehicles->execute([currentUserId()]);
$vehicles = $vehicles->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
    * {
        font-family: 'Google Sans Flex', sans-serif;
    }
    .my-vehicles-page {
        max-width: 100%;
        width: 100%;
        margin: 0;
   
    }

    .vehicles-header {
        margin: 2rem 0 2.5rem 0;
        padding: 2rem;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border-radius: 16px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .vehicles-header-content h3 {
       font-size: 1.75rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-family: 'Google Sans Flex', sans-serif;
    }

    .vehicles-header-content p {
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.95rem;
        margin: 0;
    }

    .btn-add-vehicle {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        border: 2px solid rgba(255, 255, 255, 0.5);
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-add-vehicle:hover {
        background: rgba(255, 255, 255, 0.35);
        border-color: rgba(255, 255, 255, 0.8);
        transform: translateY(-2px);
    }

    .vehicle-form-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        padding: 2rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .vehicle-form-card h5 {
        font-weight: 800;
        color: #111;
        margin-bottom: 1.75rem;
        letter-spacing: -0.5px;
    }

    .vehicle-form-card .form-label {
        font-weight: 700;
        color: #374151;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
    }

    .vehicle-form-card .form-control {
        border-radius: 10px;
        padding: 0.85rem 1rem;
        border: 1px solid #e5e7eb;
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .vehicle-form-card .form-control:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .vehicle-form-card .form-text {
        font-size: 0.8rem;
        color: #9ca3af;
        margin-top: 0.4rem;
    }

    .vehicle-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
    }

    .vehicle-form-actions {
        display: flex;
        gap: 0.85rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .vehicle-form-actions .btn {
        flex: 1;
        min-width: 150px;
        padding: 0.85rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vehicle-form-actions .btn-cancel {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
    }

    .vehicle-form-actions .btn-cancel:hover {
        background: #e5e7eb;
        border-color: #d1d5db;
    }

    .vehicle-form-actions .btn-submit {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }

    .vehicle-form-actions .btn-submit:hover {
        background: linear-gradient(135deg, #15803d 0%, #166d31 100%);
        box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4);
        transform: translateY(-2px);
    }

    /* Modal action buttons (match requested style) */
    .modal-btn-cancel {
        flex:1; min-width:120px; padding:0.85rem 1.25rem; border-radius:12px; background:#fff; border:1px solid #e5e7eb; color:#374151; font-weight:700;
    }
    .modal-btn-save {
        flex:1; min-width:120px; padding:0.85rem 1.25rem; border-radius:12px; background:linear-gradient(135deg,#16a34a 0%,#15803d 100%); color:#fff; font-weight:700; border:none;
    }
    .modal-btn-save:hover {
        background: linear-gradient(180deg,#bbf7d0 0%,#ecfdf5 100%); color: #064e3b; box-shadow: 0 8px 20px rgba(16,185,129,0.12); border-radius:12px;
    }
    .modal-btn-save:active, .modal-btn-save.selected {
        background: linear-gradient(135deg,#0f6a2a 0%,#0b4d1e 100%); color:#fff;
    }

    .vehicle-list {
        display: grid;
        gap: 1.5rem;
    }

    .vehicle-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1.5rem;
        align-items: center;
        margin-bottom: 0.75rem;
        border-radius: 12px;
    }

    .vehicle-card:hover {
        box-shadow: 0 12px 24px rgba(16, 185, 129, 0.08);
        border-color: transparent;
        background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);
        transform: translateY(-4px);
        border-radius: 12px;
        outline: 0;
    }

    /* Selected card (e.g., default or active selection) */
    .vehicle-card.selected, .vehicle-card.is-selected {
        background: linear-gradient(135deg, #15803d 0%, #115e2b 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 12px 28px rgba(4, 120, 87, 0.18);
    }
    .vehicle-card.selected .vehicle-plate, .vehicle-card.is-selected .vehicle-plate {
        color: #fff;
    }
    .vehicle-card.selected .vehicle-model, .vehicle-card.is-selected .vehicle-model,
    .vehicle-card.selected .vehicle-color, .vehicle-card.is-selected .vehicle-color {
        color: rgba(255,255,255,0.9);
    }

    .vehicle-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
    }

    .vehicle-icon i {
        font-size: 2rem;
        color: #15803d;
    }

    .vehicle-details {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .vehicle-details-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .vehicle-plate {
        font-size: 1.2rem;
        font-weight: 900;
        color: #111;
        letter-spacing: -0.5px;
        font-family: 'Courier New', monospace;
    }

    .default-badge {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.1);
    }

    .vehicle-model {
        font-size: 0.95rem;
        color: #374151;
        font-weight: 600;
    }

    .vehicle-color {
        font-size: 0.85rem;
        color: #9ca3af;
    }

    .vehicle-actions {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .btn-action {
        padding: 0.65rem 1.25rem;
        border-radius: 10px;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-action-default {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.2);
    }

    .btn-action-default:hover {
        background: linear-gradient(135deg, #15803d 0%, #166d31 100%);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        transform: translateY(-1px);
    }

    .btn-action-edit {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
    }

    .btn-action-edit:hover {
        background: #e5e7eb;
        border-color: #d1d5db;
    }

    .btn-action-delete {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .btn-action-delete:hover {
        background: #fecaca;
        border-color: #fca5a5;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border: 2px dashed #d1d5db;
        border-radius: 14px;
        color: #6b7280;
    }

    .empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        display: block;
        margin-bottom: 1rem;
    }

    .empty-state p {
        margin: 0.5rem 0;
        color: #374151;
    }

    .empty-state p:first-of-type {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
    }

    .empty-state p:last-of-type {
        font-size: 0.9rem;
        color: #9ca3af;
    }

    @media (max-width: 768px) {
        .vehicles-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .vehicles-header-content h2 {
            font-size: 1.4rem;
        }

        .vehicle-card {
            grid-template-columns: auto 1fr;
            gap: 1rem;
        }

        .vehicle-actions {
            grid-column: 2;
        }

        .vehicle-form-card {
            padding: 1.5rem;
        }

        .vehicle-form-grid {
            grid-template-columns: 1fr;
        }

        .vehicle-form-actions {
            flex-direction: column;
        }

        .vehicle-form-actions .btn {
            width: 100%;
            min-width: auto;
        }

        .btn-action {
            padding: 0.55rem 0.9rem;
            font-size: 0.75rem;
        }
    }

    @media (max-width: 480px) {
        .my-vehicles-page {
            padding: 0;
        }

        .vehicles-header {
            border-radius: 0;
            margin: 1rem 0 1.5rem 0;
            padding: 1.5rem;
        }

        .vehicles-header-content h2 {
            font-size: 1.25rem;
        }

        .vehicle-card {
            grid-template-columns: auto 1fr;
            padding: 1rem;
            gap: 1rem;
        }

        .vehicle-icon {
            width: 56px;
            height: 56px;
        }

        .vehicle-icon i {
            font-size: 1.5rem;
        }

        .vehicle-plate {
            font-size: 1rem;
        }

        .vehicle-actions {
            grid-column: 1 / -1;
            gap: 0.5rem;
        }

        .btn-action {
            flex: 1;
        }
    }
</style>

<div class="my-vehicles-page">
<?php if ($error): ?>
    <?php setAlert($error, 'danger'); ?>
<?php endif; ?>

<div class="my-vehicles-page">
    <div class="vehicles-header" style="margin-bottom: 2rem;margin-top: 3rem;">
        <div class="vehicles-header-content">
            <h3><i class="bi bi-car-front"></i>My Vehicles</h2>
            <p>Manage your registered vehicles</p>
        </div>
        <?php if ($view === 'list'): ?>
            <button type="button" class="btn-add-vehicle" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                <i class="bi bi-plus-lg"></i> Add Vehicle
            </button>
        <?php endif; ?>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Register New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?= BASE_URL ?>/user/register-car.php" id="vehicleFormModal">
                        <input type="hidden" name="save_vehicle" value="1">

                        <div style="display:flex;flex-direction:column;gap:1rem;">
                            <!-- Vehicle Information -->
                            <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;border-radius:8px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;color:#16a34a;font-weight:700;"><i class="bi bi-car-front-fill"></i></div>
                                <div style="flex:1">
                                    <div style="font-weight:800;color:#111;font-size:0.95rem;">VEHICLE INFORMATION <span style="font-weight:700;color:#6b7280;font-size:0.8rem;margin-left:0.5rem;">Required</span></div>
                                </div>
                            </div>

                            <div class="vehicle-form-grid">
                                <div>
                                    <label class="form-label">Vehicle Type <span class="text-success">*</span></label>
                                    <select name="vehicle_type" id="vehicle_type" class="form-control" required>
                                        <option value="">Select vehicle type</option>
                                        <option value="<?= PlateValidator::TYPE_PRIVATE ?>">Private Vehicle (Car/SUV/Van)</option>
                                        <option value="<?= PlateValidator::TYPE_MOTORCYCLE ?>">Motorcycle/Tricycle</option>
                                        <option value="<?= PlateValidator::TYPE_GOVERNMENT ?>">Government Vehicle</option>
                                        <option value="<?= PlateValidator::TYPE_FOR_HIRE ?>">For-Hire Vehicle (Taxi/UV Express/Bus)</option>
                                        <option value="<?= PlateValidator::TYPE_ELECTRIC ?>">Electric Vehicle</option>
                                        <option value="<?= PlateValidator::TYPE_CONDUCTION ?>">Conduction Sticker (Temporary)</option>
                                    </select>
                                    <div class="form-text">Enables proper plate validation</div>
                                </div>

                                <div>
                                    <label class="form-label">Plate Number <span class="text-success">*</span></label>
                                    <div style="position:relative;">
                                        <input type="text" name="plate_number" id="plate_number" class="form-control" placeholder="ABC-1234" required style="padding-left:3.5rem;">
                                        <div style="position:absolute;left:0;top:0;bottom:0;display:flex;align-items:center;padding-left:0.85rem;color:#6b7280;"><i class="bi bi-card-text"></i></div>
                                    </div>
                                    <div class="form-text">Format: <span id="plateExample">ABC1234</span></div>
                                    <div class="text-danger" id="plateError" style="display:none;margin-top:0.4rem;"></div>
                                </div>

                                <div>
                                    <label class="form-label">Vehicle Model</label>
                                    <input type="text" name="model" class="form-control" placeholder="e.g., Toyota Corolla">
                                    <div class="form-text">Make and model of your vehicle</div>
                                </div>
                            </div>

                            <!-- Owner Details -->
                            <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-top:0.5rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;border-radius:8px;background:#ecfeff;display:flex;align-items:center;justify-content:center;color:#06b6d4;font-weight:700;"><i class="bi bi-person-circle"></i></div>
                                <div style="flex:1">
                                    <div style="font-weight:800;color:#111;font-size:0.95rem;">OWNER DETAILS <small style="color:#6b7280;font-weight:700;margin-left:0.5rem;">Required</small></div>
                                </div>
                            </div>

                            <div class="vehicle-form-grid">
                                <div>
                                    <label class="form-label">Owner Name <span class="text-success">*</span></label>
                                    <input type="text" name="owner_name" id="owner_name" class="form-control" placeholder="e.g., Juan Dela Cruz" required>
                                    <div class="form-text">Name will be used on receipts</div>
                                </div>
                                <div>
                                    <label class="form-label">Owner Phone <span class="text-success">*</span></label>
                                    <input type="text" name="owner_phone" id="owner_phone" class="form-control" placeholder="09XXXXXXXXX" required>
                                    <div class="form-text">Philippine mobile number</div>
                                </div>
                                <div>
                                    <label class="form-label">Owner Email <span class="text-success">*</span></label>
                                    <input type="email" name="owner_email" id="owner_email" class="form-control" placeholder="name@example.com" required>
                                    <div class="form-text">Receipt notifications sent here</div>
                                </div>
                            </div>

                            <!-- Additional Details -->
                            <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-top:0.5rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;border-radius:8px;background:#faf7ff;display:flex;align-items:center;justify-content:center;color:#7c3aed;font-weight:700;"><i class="bi bi-palette"></i></div>
                                <div style="flex:1">
                                    <div style="font-weight:800;color:#111;font-size:0.95rem;">ADDITIONAL DETAILS <small style="color:#6b7280;font-weight:700;margin-left:0.5rem;">Optional</small></div>
                                </div>
                            </div>

                            <div class="vehicle-form-grid">
                                <div>
                                    <label class="form-label">Color</label>
                                    <select name="color" class="form-control">
                                        <option value="">Select color</option>
                                        <option>White</option>
                                        <option>Black</option>
                                        <option>Silver</option>
                                        <option>Blue</option>
                                        <option>Red</option>
                                        <option>Other</option>
                                    </select>
                                    <div class="form-text">Vehicle exterior color</div>
                                </div>
                                <div>
                                    <label class="form-label">Year</label>
                                    <input type="number" name="year" class="form-control" placeholder="2026" value="<?= date('Y') ?>">
                                    <div class="form-text">Year of manufacture</div>
                                </div>
                                <div></div>
                            </div>

                            <div style="display:flex;gap:1rem;align-items:center;margin-top:0.5rem;">
                                <button type="button" class="modal-btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                                <button type="submit" class="modal-btn-save"><i class="bi bi-check-lg"></i> Save Vehicle</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <?php if ($view === 'add'): ?>
        <!-- Add Vehicle form view -->
        <div class="vehicle-form-card">
            <h5><i class="bi bi-plus-circle me-2"></i>Register New Vehicle</h5>
            <form method="post" action="<?= BASE_URL ?>/user/register-car.php?view=add" id="vehicleFormAdd">
                <input type="hidden" name="save_vehicle" value="1">
                <div class="vehicle-form-grid">
                    <div>
                        <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                        <select name="vehicle_type" id="vehicle_type_add" class="form-control" required>
                            <option value="">Select vehicle type</option>
                            <option value="<?= PlateValidator::TYPE_PRIVATE ?>">Private Vehicle (Car/SUV/Van)</option>
                            <option value="<?= PlateValidator::TYPE_MOTORCYCLE ?>">Motorcycle/Tricycle</option>
                            <option value="<?= PlateValidator::TYPE_GOVERNMENT ?>">Government Vehicle</option>
                            <option value="<?= PlateValidator::TYPE_FOR_HIRE ?>">For-Hire Vehicle (Taxi/UV Express/Bus)</option>
                            <option value="<?= PlateValidator::TYPE_ELECTRIC ?>">Electric Vehicle</option>
                            <option value="<?= PlateValidator::TYPE_CONDUCTION ?>">Conduction Sticker (Temporary)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Plate Number <span class="text-danger">*</span></label>
                        <input type="text" name="plate_number" id="plate_number_add" class="form-control" placeholder="ABC-1234" required>
                        <div class="form-text">Format: <span id="plateExampleAdd">ABC1234</span></div>
                        <div class="text-danger" id="plateErrorAdd" style="display:none;margin-top:0.4rem;"></div>
                    </div>
                    <div>
                        <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" id="owner_name_add" class="form-control" placeholder="e.g., Juan Dela Cruz" required>
                        <div class="form-text">Name will be used on receipts</div>
                    </div>
                    <div>
                        <label class="form-label">Owner Phone <span class="text-danger">*</span></label>
                        <input type="text" name="owner_phone" id="owner_phone_add" class="form-control" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required>
                        <div class="form-text">Philippine mobile number</div>
                    </div>
                    <div>
                        <label class="form-label">Owner Email <span class="text-danger">*</span></label>
                        <input type="email" name="owner_email" id="owner_email_add" class="form-control" placeholder="name@example.com" required>
                        <div class="form-text">We will send receipt notifications to this address</div>
                    </div>
                    <div>
                        <label class="form-label">Vehicle Model</label>
                        <input type="text" name="model" class="form-control" placeholder="e.g., Toyota Corolla">
                        <div class="form-text">Make and model of your vehicle</div>
                    </div>
                    <div>
                        <label class="form-label">Color</label>
                        <select name="color" class="form-control">
                            <option value="">Select color</option>
                            <option>White</option>
                            <option>Black</option>
                            <option>Silver</option>
                            <option>Blue</option>
                            <option>Red</option>
                            <option>Other</option>
                        </select>
                        <div class="form-text">Vehicle exterior color</div>
                    </div>
                    <div>
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" placeholder="2026" value="<?= date('Y') ?>">
                        <div class="form-text">Year of manufacture</div>
                    </div>
                </div>
                <div class="vehicle-form-actions">
                    <a href="<?= BASE_URL ?>/user/register-car.php" class="btn btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
                    <button type="submit" class="btn btn-submit"><i class="bi bi-check-lg"></i> Save Vehicle</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Vehicle List -->
        <?php if (empty($vehicles)): ?>
            <div class="empty-state">
                <i class="bi bi-car-front"></i>
                <p>No vehicles registered yet</p>
                <p>Click "Add Vehicle" to register your first car</p>
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
                            <button type="button" class="btn-action btn-action-edit edit-vehicle-btn" title="Edit"
                                data-id="<?= (int)$v['id'] ?>"
                                data-plate="<?= htmlspecialchars($v['plate_number']) ?>"
                                data-model="<?= htmlspecialchars($v['model'] ?? '') ?>"
                                data-color="<?= htmlspecialchars($v['color'] ?? '') ?>"
                                data-vehicle_type="<?= htmlspecialchars($v['vehicle_type'] ?? '') ?>"
                                data-owner_name="<?= htmlspecialchars($v['owner_name'] ?? '') ?>"
                                data-owner_phone="<?= htmlspecialchars($v['owner_phone'] ?? '') ?>"
                                data-owner_email="<?= htmlspecialchars($v['owner_email'] ?? '') ?>"
                            >
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <a href="<?= BASE_URL ?>/user/delete-car.php?id=<?= (int)$v['id'] ?>" class="btn-action btn-action-delete" title="Delete" onclick="return confirm('Remove this vehicle from your list?');">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i> Edit Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= BASE_URL ?>/user/register-car.php" id="editVehicleForm">
                    <input type="hidden" name="save_vehicle" value="1">
                    <input type="hidden" name="vehicle_id" id="edit_vehicle_id" value="0">
                    <input type="hidden" name="vehicle_type" id="edit_vehicle_type" value="">
                    <input type="hidden" name="plate_number" id="edit_plate_number_hidden" value="">

                    <div style="display:flex;flex-direction:column;gap:1rem;">
                        <div style="font-size:0.95rem;color:#6b7280;margin-bottom:0.25rem;">Update the details for your registered vehicle.</div>

                        <!-- Vehicle Information -->
                        <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                            <div style="width:36px;height:36px;border-radius:8px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;color:#16a34a;font-weight:700;"><i class="bi bi-car-front-fill"></i></div>
                            <div style="flex:1">
                                <div style="font-weight:800;color:#111;font-size:0.95rem;">VEHICLE INFORMATION <span style="font-weight:700;color:#6b7280;font-size:0.8rem;margin-left:0.5rem;">Required</span></div>
                            </div>
                        </div>

                        <div class="vehicle-form-grid">
                            <div>
                                <label class="form-label">Vehicle Type <span class="text-success">*</span></label>
                                <select name="vehicle_type_vis" id="edit_vehicle_type_vis" class="form-control" required>
                                    <option value="">Select vehicle type</option>
                                    <option value="<?= PlateValidator::TYPE_PRIVATE ?>">Private Vehicle (Car/SUV/Van)</option>
                                    <option value="<?= PlateValidator::TYPE_MOTORCYCLE ?>">Motorcycle/Tricycle</option>
                                    <option value="<?= PlateValidator::TYPE_GOVERNMENT ?>">Government Vehicle</option>
                                    <option value="<?= PlateValidator::TYPE_FOR_HIRE ?>">For-Hire Vehicle (Taxi/UV Express/Bus)</option>
                                    <option value="<?= PlateValidator::TYPE_ELECTRIC ?>">Electric Vehicle</option>
                                    <option value="<?= PlateValidator::TYPE_CONDUCTION ?>">Conduction Sticker (Temporary)</option>
                                </select>
                                <div class="form-text">Enables proper plate validation</div>
                            </div>

                            <div>
                                <label class="form-label">Plate Number</label>
                                <div style="position:relative;">
                                    <input type="text" id="edit_plate_number" class="form-control" disabled style="padding-left:3.5rem;">
                                    <div style="position:absolute;left:0;top:0;bottom:0;display:flex;align-items:center;padding-left:0.85rem;color:#6b7280;"><i class="bi bi-card-text"></i></div>
                                </div>
                                <div class="form-text">Format: <span id="plateExampleEdit">ABC1234</span></div>
                            </div>

                            <div>
                                <label class="form-label">Vehicle Model</label>
                                <input type="text" name="model" id="edit_model" class="form-control" placeholder="e.g., Toyota Corolla">
                                <div class="form-text">Make and model of your vehicle</div>
                            </div>
                        </div>

                        <!-- Owner Details -->
                        <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-top:0.5rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                            <div style="width:36px;height:36px;border-radius:8px;background:#ecfeff;display:flex;align-items:center;justify-content:center;color:#06b6d4;font-weight:700;"><i class="bi bi-person-circle"></i></div>
                            <div style="flex:1">
                                <div style="font-weight:800;color:#111;font-size:0.95rem;">OWNER DETAILS <small style="color:#6b7280;font-weight:700;margin-left:0.5rem;">Required</small></div>
                            </div>
                        </div>

                        <div class="vehicle-form-grid">
                            <div>
                                <label class="form-label">Owner Name <span class="text-success">*</span></label>
                                <input type="text" name="owner_name" id="edit_owner_name" class="form-control" placeholder="e.g., Juan Dela Cruz" required>
                                <div class="form-text">Name will be used on receipts</div>
                            </div>
                            <div>
                                <label class="form-label">Owner Phone <span class="text-success">*</span></label>
                                <input type="text" name="owner_phone" id="edit_owner_phone" class="form-control" placeholder="09XXXXXXXXX" required>
                                <div class="form-text">Philippine mobile number</div>
                            </div>
                            <div>
                                <label class="form-label">Owner Email <span class="text-success">*</span></label>
                                <input type="email" name="owner_email" id="edit_owner_email" class="form-control" placeholder="name@example.com" required>
                                <div class="form-text">Receipt notifications sent here</div>
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div style="border-bottom:1px solid #eef2f7;padding-bottom:0.75rem;margin-top:0.5rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                            <div style="width:36px;height:36px;border-radius:8px;background:#faf7ff;display:flex;align-items:center;justify-content:center;color:#7c3aed;font-weight:700;"><i class="bi bi-palette"></i></div>
                            <div style="flex:1">
                                <div style="font-weight:800;color:#111;font-size:0.95rem;">ADDITIONAL DETAILS <small style="color:#6b7280;font-weight:700;margin-left:0.5rem;">Optional</small></div>
                            </div>
                        </div>

                        <div class="vehicle-form-grid">
                            <div>
                                <label class="form-label">Color</label>
                                <select name="color" id="edit_color" class="form-control">
                                    <option value="">Select color</option>
                                    <option>White</option>
                                    <option>Black</option>
                                    <option>Silver</option>
                                    <option>Blue</option>
                                    <option>Red</option>
                                    <option>Other</option>
                                </select>
                                <div class="form-text">Vehicle exterior color</div>
                            </div>
                            <div>
                                <label class="form-label">Year</label>
                                <input type="number" name="year" id="edit_year" class="form-control" placeholder="2026">
                                <div class="form-text">Year of manufacture</div>
                            </div>
                            <div></div>
                        </div>

                        <div style="display:flex;gap:1rem;align-items:center;margin-top:0.5rem;">
                            <button type="button" class="modal-btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                            <button type="submit" class="modal-btn-save"><i class="bi bi-check-lg"></i> Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Plate examples and client-side regexes
const PLATE_SCHEMAS = {
    '<?= PlateValidator::TYPE_PRIVATE ?>': {
        example: 'ABC-1234 or ABC1234',
        pattern: /^[A-HJ-NP-Z]{3}[-\s]?[0-9]{3,4}$/i,
        error: 'Private vehicle plates must be 3 letters and 3-4 numbers (e.g., ABC-1234 or ABC1234)'
    },
    '<?= PlateValidator::TYPE_MOTORCYCLE ?>': {
        example: 'MC-12345 or MC12345',
        pattern: /^[A-Z]{2}[-\s]?[0-9]{4,5}$/i,
        error: 'Motorcycle plates must be 2 letters and 4-5 numbers (e.g., MC-12345 or MC12345)'
    },
    '<?= PlateValidator::TYPE_GOVERNMENT ?>': {
        example: 'SEN-123 or GOV1234',
        pattern: /^[A-Z]{2,3}[-\s]?[0-9]{1,4}$/i,
        error: 'Government vehicle plates must be 2-3 letters and 1-4 numbers (e.g., SEN-123)'
    },
    '<?= PlateValidator::TYPE_FOR_HIRE ?>': {
        example: 'TXI-1234 or TXI1234',
        pattern: /^[A-HJ-NP-Z]{3}[-\s]?[0-9]{3,4}$/i,
        error: 'For-hire vehicle plates must be 3 letters and 3-4 numbers (e.g., TXI-1234)'
    },
    '<?= PlateValidator::TYPE_ELECTRIC ?>': {
        example: 'E-ABC-1234 or EABC1234',
        pattern: /^E[-\s]?[A-HJ-NP-Z]{3}[-\s]?[0-9]{3,4}$/i,
        error: 'Electric vehicle plates must start with E followed by 3 letters and 3-4 numbers (e.g., E-ABC-1234 or EABC1234)'
    },
    '<?= PlateValidator::TYPE_CONDUCTION ?>': {
        example: '1234567 (digits only)',
        pattern: /^[0-9]{7,8}$/,
        error: 'Conduction stickers must be 7-8 digits only (no hyphens)'
    }
};

function updateExample(selectEl, exampleEl) {
    const t = selectEl.value;
    if (PLATE_SCHEMAS[t]) {
        exampleEl.textContent = PLATE_SCHEMAS[t].example;
    } else {
        exampleEl.textContent = '';
    }
}

function validatePlateForType(type, value) {
    const schema = PLATE_SCHEMAS[type];
    if (!schema) return [false, 'Please select a vehicle type'];
    if (!schema.pattern.test(value.trim())) return [false, schema.error];
    return [true, ''];
}

// Attach handlers for modal form
document.addEventListener('DOMContentLoaded', function(){
    const sel = document.getElementById('vehicle_type');
    const plate = document.getElementById('plate_number');
    const example = document.getElementById('plateExample');
    const plateError = document.getElementById('plateError');
    
    // Input validation helpers
    function validateOwnerName(name) {
        if (!name || name.trim() === '') return 'Owner name is required.';
        if (!/^[a-zA-Z ]+$/.test(name)) return 'Owner name can only contain letters and spaces. Special characters and numbers are not allowed.';
        if (name.trim().length < 2) return 'Owner name must be at least 2 characters long.';
        if (name.length > 100) return 'Owner name is too long (maximum 100 characters).';
        return '';
    }
    
    function validatePhone(phone) {
        if (!phone || phone.trim() === '') return 'Owner phone is required.';
        const clean = phone.replace(/[^0-9]/g, '');
        if (!/^09[0-9]{9}$/.test(clean)) return 'Owner phone must be in format 09XXXXXXXXX (11 digits starting with 09).';
        return '';
    }
    
    function validateEmail(email) {
        if (!email || email.trim() === '') return 'Owner email is required.';
        if (!/[a-zA-Z]/.test(email)) return 'Owner email must contain at least one letter.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Owner email must be a valid email address.';
        return '';
    }
    
    function validateModel(model) {
        if (model && !/^[a-zA-Z0-9 \-()]+$/.test(model)) return 'Vehicle model can only contain letters, numbers, spaces, hyphens, and parentheses.';
        if (model && model.length > 100) return 'Vehicle model is too long (maximum 100 characters).';
        return '';
    }
    
    function validateYear(year) {
        if (!year) return ''; // Optional field
        if (!/^[0-9]+$/.test(year)) return 'Year must be a valid number without special characters.';
        const yearNum = parseInt(year, 10);
        const currentYear = new Date().getFullYear();
        if (yearNum < 0) return 'Year cannot be negative.';
        if (yearNum < 1900) return 'Year must be 1900 or later.';
        if (yearNum > currentYear) return 'Year cannot be greater than the current year (' + currentYear + ').';
        return '';
    }
    
    function validateColor(color) {
        if (color && !/^[a-zA-Z ]+$/.test(color)) return 'Vehicle color can only contain letters and spaces. Special characters and numbers are not allowed.';
        if (color && color.length > 50) return 'Vehicle color is too long (maximum 50 characters).';
        return '';
    }
    
    // Add real-time validation for all fields
    function addFieldValidation(fieldId, validator, errorId) {
        const field = document.getElementById(fieldId);
        const errorEl = document.getElementById(errorId);
        if (field && errorEl) {
            field.addEventListener('blur', function() {
                const error = validator(field.value);
                if (error) {
                    errorEl.textContent = error;
                    errorEl.style.display = 'block';
                    errorEl.style.color = '#dc2626';
                    errorEl.style.fontSize = '0.875rem';
                    errorEl.style.marginTop = '0.25rem';
                    field.classList.add('is-invalid');
                } else {
                    errorEl.style.display = 'none';
                    field.classList.remove('is-invalid');
                }
            });
            
            // Clear error on input
            field.addEventListener('input', function() {
                errorEl.style.display = 'none';
                field.classList.remove('is-invalid');
            });
        }
    }
    
    if (sel && example) {
        sel.addEventListener('change', ()=> updateExample(sel, example));
    }
    if (plate && sel) {
        plate.addEventListener('blur', ()=>{
            const [ok, msg] = validatePlateForType(sel.value, plate.value);
            if (!ok) {
                plateError.style.display = 'block';
                plateError.textContent = msg;
            } else {
                plateError.style.display = 'none';
                plateError.textContent = '';
            }
        });
    }
    
    const formModal = document.getElementById('vehicleFormModal');
    if (formModal) {
        formModal.addEventListener('submit', function(e){
            let hasError = false;
            let firstError = null;
            
            // Validate all fields
            const ownerNameField = document.getElementById('owner_name');
            const ownerPhoneField = document.getElementById('owner_phone');
            const ownerEmailField = document.getElementById('owner_email');
            const modelField = document.getElementById('model');
            const yearField = document.getElementById('year');
            const colorField = document.getElementById('color');
            
            // Check owner name
            if (ownerNameField) {
                const error = validateOwnerName(ownerNameField.value);
                if (error) {
                    hasError = true;
                    if (!firstError) firstError = ownerNameField;
                    alert(error);
                }
            }
            
            // Check phone
            if (ownerPhoneField) {
                const error = validatePhone(ownerPhoneField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = ownerPhoneField;
                    alert(error);
                }
            }
            
            // Check email
            if (ownerEmailField) {
                const error = validateEmail(ownerEmailField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = ownerEmailField;
                    alert(error);
                }
            }
            
            // Check model
            if (modelField) {
                const error = validateModel(modelField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = modelField;
                    alert(error);
                }
            }
            
            // Check year
            if (yearField) {
                const error = validateYear(yearField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = yearField;
                    alert(error);
                }
            }
            
            // Check color
            if (colorField) {
                const error = validateColor(colorField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = colorField;
                    alert(error);
                }
            }
            
            // Check plate
            const [ok, msg] = validatePlateForType(sel.value, plate.value);
            if (!ok && !hasError) {
                hasError = true;
                plateError.style.display = 'block';
                plateError.textContent = msg;
                if (!firstError) firstError = plate;
            }
            
            if (hasError) {
                e.preventDefault();
                if (firstError) firstError.focus();
            }
        });
    }

    // Attach handlers for add view
    const selAdd = document.getElementById('vehicle_type_add');
    const plateAdd = document.getElementById('plate_number_add');
    const exampleAdd = document.getElementById('plateExampleAdd');
    const plateErrorAdd = document.getElementById('plateErrorAdd');
    
    if (selAdd && exampleAdd) selAdd.addEventListener('change', ()=> updateExample(selAdd, exampleAdd));
    if (plateAdd && selAdd) plateAdd.addEventListener('blur', ()=>{
        const [ok, msg] = validatePlateForType(selAdd.value, plateAdd.value);
        if (!ok) {
            plateErrorAdd.style.display = 'block';
            plateErrorAdd.textContent = msg;
        } else {
            plateErrorAdd.style.display = 'none';
            plateErrorAdd.textContent = '';
        }
    });

    // Edit vehicle modal population
    const editBtns = document.querySelectorAll('.edit-vehicle-btn');
    const editModalEl = document.getElementById('editVehicleModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    editBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            const plate = this.getAttribute('data-plate') || '';
            const model = this.getAttribute('data-model') || '';
            const color = this.getAttribute('data-color') || '';
            const vtype = this.getAttribute('data-vehicle_type') || '';
            const ownerName = this.getAttribute('data-owner_name') || '';
            const ownerPhone = this.getAttribute('data-owner_phone') || '';
            const ownerEmail = this.getAttribute('data-owner_email') || '';

            // If model contains year in parentheses, extract
            let year = '';
            let modelText = model;
            const m = model.match(/^(.+?)\s*\((\d{4})\)$/);
            if (m) { modelText = m[1].trim(); year = m[2]; }

            document.getElementById('edit_vehicle_id').value = id;
            document.getElementById('edit_plate_number').value = plate;
            document.getElementById('edit_plate_number_hidden').value = plate;
            document.getElementById('edit_vehicle_type').value = vtype;
            // visible select
            const visType = document.getElementById('edit_vehicle_type_vis');
            if (visType) visType.value = vtype;
            document.getElementById('edit_model').value = modelText;
            document.getElementById('edit_color').value = color;
            document.getElementById('edit_year').value = year;
            // owner fields
            const on = document.getElementById('edit_owner_name'); if (on) on.value = ownerName;
            const op = document.getElementById('edit_owner_phone'); if (op) op.value = ownerPhone;
            const oe = document.getElementById('edit_owner_email'); if (oe) oe.value = ownerEmail;

            if (editModal) editModal.show();
        });
    });

    // Validate edit form on submit
    const editForm = document.getElementById('editVehicleForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e){
            let hasError = false;
            let firstEl = null;
            const modelField = document.getElementById('edit_model');
            const yearField = document.getElementById('edit_year');
            const colorField = document.getElementById('edit_color');
            // model
            const mErr = validateModel(modelField ? modelField.value : '');
            if (mErr) { hasError = true; firstEl = modelField; alert(mErr); }
            // year
            if (!hasError) {
                const yErr = validateYear(yearField ? yearField.value : '');
                if (yErr) { hasError = true; firstEl = yearField; alert(yErr); }
            }
            // color
            if (!hasError) {
                const cErr = validateColor(colorField ? colorField.value : '');
                if (cErr) { hasError = true; firstEl = colorField; alert(cErr); }
            }
            if (hasError) { e.preventDefault(); if (firstEl) firstEl.focus(); }
        });
    }
    
    const formAdd = document.getElementById('vehicleFormAdd');
    if (formAdd) {
        formAdd.addEventListener('submit', function(e){
            let hasError = false;
            let firstError = null;
            
            // Validate all fields for add form
            const ownerNameField = document.getElementById('owner_name_add');
            const ownerPhoneField = document.getElementById('owner_phone_add');
            const ownerEmailField = document.getElementById('owner_email_add');
            const modelField = document.getElementById('model_add');
            const yearField = document.getElementById('year_add');
            const colorField = document.getElementById('color_add');
            
            // Check owner name
            if (ownerNameField) {
                const error = validateOwnerName(ownerNameField.value);
                if (error) {
                    hasError = true;
                    if (!firstError) firstError = ownerNameField;
                    alert(error);
                }
            }
            
            // Check phone
            if (ownerPhoneField) {
                const error = validatePhone(ownerPhoneField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = ownerPhoneField;
                    alert(error);
                }
            }
            
            // Check email
            if (ownerEmailField) {
                const error = validateEmail(ownerEmailField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = ownerEmailField;
                    alert(error);
                }
            }
            
            // Check model
            if (modelField) {
                const error = validateModel(modelField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = modelField;
                    alert(error);
                }
            }
            
            // Check year
            if (yearField) {
                const error = validateYear(yearField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = yearField;
                    alert(error);
                }
            }
            
            // Check color
            if (colorField) {
                const error = validateColor(colorField.value);
                if (error && !hasError) {
                    hasError = true;
                    if (!firstError) firstError = colorField;
                    alert(error);
                }
            }
            
            // Check plate
            const [ok, msg] = validatePlateForType(selAdd.value, plateAdd.value);
            if (!ok && !hasError) {
                hasError = true;
                plateErrorAdd.style.display = 'block';
                plateErrorAdd.textContent = msg;
                if (!firstError) firstError = plateAdd;
            }
            
            if (hasError) {
                e.preventDefault();
                if (firstError) firstError.focus();
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>