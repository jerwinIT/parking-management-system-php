<?php
/**
 * Book a Parking Slot - 3 steps: Select Slot → Choose Time → Confirm
 * IMPROVED VERSION with Vehicle Double-Booking Prevention
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$page_title = 'Book a Parking Slot';
$current_page = 'book';
$error = '';
$pdo = getDB();

// parking operating hours for client-side validation
$__parking_hours = $pdo->query('SELECT opening_time, closing_time FROM parking_settings LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$opening_time_setting = $__parking_hours['opening_time'] ?? null;
$closing_time_setting = $__parking_hours['closing_time'] ?? null;

$vehicles = $pdo->prepare('SELECT id, plate_number, model, color FROM vehicles WHERE user_id = ?');
$vehicles->execute([currentUserId()]);
$vehicles = $vehicles->fetchAll();

$step = (int) ($_GET['step'] ?? 1);
if ($step < 1 || $step > 4) $step = 1;

$show_confirmation = false;
$confirmation_booking = null;
if (!empty($_GET['confirmed']) && !empty($_GET['booking_id'])) {
    $bid = (int) $_GET['booking_id'];
    $stmt = $pdo->prepare('SELECT b.id, b.entry_time, b.exit_time, b.planned_entry_time, b.booked_at, b.parking_slot_id, ps.slot_number, p.amount, p.payment_method, p.payment_subtype, p.wallet_contact, p.account_number, p.payer_name, p.payment_status, p.paid_at FROM bookings b JOIN parking_slots ps ON ps.id = b.parking_slot_id LEFT JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.user_id = ?');
    $stmt->execute([$bid, currentUserId()]);
    $confirmation_booking = $stmt->fetch();
    if ($confirmation_booking) {
        $show_confirmation = true;
    }
}

// Step 2: receive selected slot from step 1
$selected_slot_id = (int) ($_GET['slot_id'] ?? $_POST['slot_id'] ?? 0);
$parking_date = trim($_GET['parking_date'] ?? $_POST['parking_date'] ?? '');
$start_time = trim($_GET['start_time'] ?? $_POST['start_time'] ?? '');
$end_time = trim($_GET['end_time'] ?? $_POST['end_time'] ?? '');
if ($step >= 2 && $selected_slot_id) {
    $slot_check = $pdo->prepare('SELECT id, slot_number FROM parking_slots WHERE id = ? AND status = ?');
    $slot_check->execute([$selected_slot_id, 'available']);
    $selected_slot = $slot_check->fetch();
    if (!$selected_slot) { $selected_slot_id = 0; $step = 1; }
}

// Step 3: confirm and submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    $slot_id = (int) ($_POST['slot_id'] ?? 0);
    if (!$vehicle_id || !$slot_id) {
        $error = 'Please select a vehicle and slot.';
        $step = 3;
        $selected_slot_id = $slot_id;
    } else {
        $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND user_id = ?');
        $stmt->execute([$vehicle_id, currentUserId()]);
        if (!$stmt->fetch()) {
            $error = 'Invalid vehicle.';
            $step = 3;
            $selected_slot_id = $slot_id;
        } else {
            $stmt = $pdo->prepare('SELECT id FROM parking_slots WHERE id = ? AND status = ?');
            $stmt->execute([$slot_id, 'available']);
            if (!$stmt->fetch()) {
                $error = 'Slot no longer available. Please choose another.';
                $step = 1;
            } else {
                // ===== NEW: Check if vehicle is already booked for overlapping time =====
                $pd = trim($_POST['parking_date'] ?? '');
                $st = trim($_POST['start_time'] ?? '');
                $et = trim($_POST['end_time'] ?? '');
                $entry_dt = null;
                $exit_dt = null;
                if ($pd && $st) $entry_dt = $pd . ' ' . (strlen($st) === 5 ? $st . ':00' : substr($st, 0, 8));
                if ($pd && $et) $exit_dt = $pd . ' ' . (strlen($et) === 5 ? $et . ':00' : substr($et, 0, 8));
                
                if ($entry_dt && $exit_dt) {
                    // Check for overlapping bookings with same vehicle
                    // Two periods overlap if: existing_start < new_end AND existing_end > new_start
                    $overlapCheck = $pdo->prepare('
                        SELECT b.id, ps.slot_number, b.planned_entry_time, b.exit_time
                        FROM bookings b 
                        JOIN parking_slots ps ON ps.id = b.parking_slot_id
                        WHERE b.vehicle_id = ? 
                        AND b.status IN (?, ?, ?)
                        AND b.planned_entry_time IS NOT NULL
                        AND b.exit_time IS NOT NULL
                        AND b.planned_entry_time < ?
                        AND b.exit_time > ?
                    ');
                    $overlapCheck->execute([
                        $vehicle_id, 
                        'pending', 'active', 'confirmed',
                        $exit_dt,   // Existing must start before new ends
                        $entry_dt   // Existing must end after new starts
                    ]);
                    $overlap = $overlapCheck->fetch();
                    
                    if ($overlap) {
                        $overlap_start = date('g:i A', strtotime($overlap['planned_entry_time']));
                        $overlap_end = date('g:i A', strtotime($overlap['exit_time']));
                        $error = 'This vehicle is already booked for ' . htmlspecialchars($overlap_start . ' - ' . $overlap_end) . ' in Slot ' . htmlspecialchars(slotLabel($overlap['slot_number'])) . '. Please select a different vehicle or time.';
                        $step = 3;
                        $selected_slot_id = $slot_id;
                        goto BOOKING_END_SKIP;
                    }
                }
                // ===== END: Vehicle overlap check =====
                
                // validate booking times against parking operating hours
                if (!$entry_dt) $entry_dt = date('Y-m-d H:i:s');

                // Validate date is not in the past
                $current_datetime = date('Y-m-d H:i:s');
                $today_date = date('Y-m-d');
                
                if ($pd && $pd < $today_date) {
                    $error = 'Cannot book parking for past dates. Please select today or a future date.';
                    $step = 3;
                    $selected_slot_id = $slot_id;
                    goto BOOKING_END_SKIP;
                }
                
                // If booking for today, validate time is not in the past
                if ($pd === $today_date && $entry_dt && $entry_dt < $current_datetime) {
                    $error = 'Cannot book parking for past time. Please select a future time.';
                    $step = 3;
                    $selected_slot_id = $slot_id;
                    goto BOOKING_END_SKIP;
                }
                
                // Validate exit time is after entry time
                if ($entry_dt && $exit_dt && $exit_dt <= $entry_dt) {
                    $error = 'Exit time must be after entry time.';
                    $step = 3;
                    $selected_slot_id = $slot_id;
                    goto BOOKING_END_SKIP;
                }

                // fetch operating hours
                $hours = $pdo->query('SELECT opening_time, closing_time FROM parking_settings LIMIT 1')->fetch();
                $opening = $hours['opening_time'] ?? null;
                $closing = $hours['closing_time'] ?? null;
                if ($opening && $closing && $pd) {
                    // compare times (same day)
                    $entry_time_only = date('H:i:s', strtotime($entry_dt));
                    $entry_ts = strtotime($entry_time_only);
                    $open_ts = strtotime($opening);
                    if ($entry_ts < $open_ts) {
                        $error = 'Entry time must be at or after opening time (' . date('g:i A', strtotime($opening)) . ').';
                        $step = 3;
                        $selected_slot_id = $slot_id;
                        // stop here
                        goto BOOKING_END_SKIP;
                    }
                    if ($exit_dt) {
                        $exit_time_only = date('H:i:s', strtotime($exit_dt));
                        $exit_ts = strtotime($exit_time_only);
                        $close_ts = strtotime($closing);
                        if ($exit_ts > $close_ts) {
                            $error = 'Exit time must be at or before closing time (' . date('g:i A', strtotime($closing)) . ').';
                            $step = 3;
                            $selected_slot_id = $slot_id;
                            goto BOOKING_END_SKIP;
                        }
                    }
                }

                // Instead of creating booking here, send user to Payment step (step=4)
                $params = ['step' => 4, 'slot_id' => $slot_id, 'vehicle_id' => $vehicle_id];
                if ($pd) $params['parking_date'] = $pd;
                if ($st) $params['start_time'] = $st;
                if ($et) $params['end_time'] = $et;
                header('Location: ' . BASE_URL . '/user/book.php?' . http_build_query($params));
                exit;
            }
            BOOKING_END_SKIP: ;
        }
    }
}

// All slots for grid (by row/col), with status
$all_slots = $pdo->query('SELECT id, slot_number, status FROM parking_slots ORDER BY slot_row, slot_column')->fetchAll();
$available_slots = array_filter($all_slots, function($s) { return $s['status'] === 'available'; });

// Format slot label for display (e.g. A1 -> A-01)
function slotLabel($slot_number) {
    if (preg_match('/^([A-Z])(\d+)$/', $slot_number, $m)) return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return $slot_number;
}

// Handle payment submission (step 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    $slot_id = (int) ($_POST['slot_id'] ?? 0);
    $payment_mode = trim($_POST['payment_mode'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $payer_name = trim($_POST['payer_name'] ?? '');
    // subtype inputs
    $credit_card_type = trim($_POST['credit_card_type'] ?? '');
    $debit_card_type = trim($_POST['debit_card_type'] ?? '');
    $mobile_wallet_type = trim($_POST['mobile_wallet_type'] ?? '');
    $wallet_contact = trim($_POST['wallet_contact'] ?? '');

    if (!$vehicle_id || !$slot_id || !$payment_mode) {
        $error = 'Missing payment or booking information.';
        $step = 4;
    } else {
        // Validate payment mode selection
        $valid_payment_modes = ['cash', 'credit_card', 'debit_card', 'mobile_wallet', 'upon_parking'];
        if (!in_array($payment_mode, $valid_payment_modes)) {
            $error = 'Invalid payment method selected.';
            $step = 4;
        }
        
        // Validate payer name - required for card/wallet payments, optional for cash upon parking
        if (empty($error) && $payment_mode !== 'upon_parking') {
            if (empty($payer_name)) {
                $error = 'Payer name is required.';
                $step = 4;
            } elseif (!preg_match('/^[a-zA-Z ]+$/', $payer_name)) {
                $error = 'Payer name can only contain letters and spaces. Special characters and numbers are not allowed.';
                $step = 4;
            } elseif (strlen($payer_name) < 2) {
                $error = 'Payer name must be at least 2 characters long.';
                $step = 4;
            } elseif (strlen($payer_name) > 100) {
                $error = 'Payer name is too long (maximum 100 characters).';
                $step = 4;
            }
        }
        
        // Validate account number for credit/debit cards - required, no special characters
        if (empty($error) && in_array($payment_mode, ['credit_card', 'debit_card'])) {
            if (empty($account_number)) {
                $error = 'Account/Card number is required.';
                $step = 4;
            } elseif (!preg_match('/^[0-9]+$/', $account_number)) {
                $error = 'Account/Card number can only contain numbers. Special characters are not allowed.';
                $step = 4;
            } elseif (strlen($account_number) < 10) {
                $error = 'Account/Card number must be at least 10 digits.';
                $step = 4;
            } elseif (strlen($account_number) > 19) {
                $error = 'Account/Card number is too long (maximum 19 digits).';
                $step = 4;
            }
        }
        
        // Validate card type selection for credit/debit cards
        if (empty($error) && $payment_mode === 'credit_card') {
            if (empty($credit_card_type)) {
                $error = 'Please select a credit card type.';
                $step = 4;
            } elseif (!in_array($credit_card_type, ['visa', 'mastercard', 'amex'])) {
                $error = 'Invalid credit card type selected.';
                $step = 4;
            }
        }
        
        if (empty($error) && $payment_mode === 'debit_card') {
            if (empty($debit_card_type)) {
                $error = 'Please select a debit card type.';
                $step = 4;
            } elseif (!in_array($debit_card_type, ['visa', 'mastercard', 'visa_debit', 'mastercard_debit', 'local_bank'])) {
                $error = 'Invalid debit card type selected.';
                $step = 4;
            }
        }
        
        // Validate mobile wallet selection and contact number
        if (empty($error) && $payment_mode === 'mobile_wallet') {
            if (empty($mobile_wallet_type)) {
                $error = 'Please select a mobile wallet type.';
                $step = 4;
            } elseif (!in_array($mobile_wallet_type, ['gcash', 'paymaya', 'grabpay', 'shopeepay', 'apple_pay', 'google_pay'])) {
                $error = 'Invalid mobile wallet type selected.';
                $step = 4;
            }
            
            // Validate wallet contact for GCash and PayMaya
            if (empty($error) && in_array($mobile_wallet_type, ['gcash', 'paymaya'])) {
                if (empty($wallet_contact)) {
                    $error = 'Mobile wallet contact number is required for ' . ucfirst($mobile_wallet_type) . '.';
                    $step = 4;
                } else {
                    $clean_contact = preg_replace('/[^0-9]/', '', $wallet_contact);
                    if (!preg_match('/^09[0-9]{9}$/', $clean_contact)) {
                        $error = 'Mobile wallet contact must be in format 09XXXXXXXXX (11 digits starting with 09).';
                        $step = 4;
                    }
                }
            }
        }
        
        if (empty($error)) {
        // prepare entry/exit datetimes
        $pd = trim($_POST['parking_date'] ?? '');
        $st = trim($_POST['start_time'] ?? '');
        $et = trim($_POST['end_time'] ?? '');
        $entry_dt = $pd && $st ? $pd . ' ' . (strlen($st) === 5 ? $st . ':00' : substr($st,0,8)) : date('Y-m-d H:i:s');
        $exit_dt = $pd && $et ? $pd . ' ' . (strlen($et) === 5 ? $et . ':00' : substr($et,0,8)) : null;
        
        // Validate date is not in the past
        $current_datetime = date('Y-m-d H:i:s');
        $today_date = date('Y-m-d');
        
        if (empty($error) && $pd && $pd < $today_date) {
            $error = 'Cannot book parking for past dates. Please select today or a future date.';
            $step = 4;
        }
        
        // If booking for today, validate time is not in the past
        if (empty($error) && $pd === $today_date && $entry_dt && $entry_dt < $current_datetime) {
            $error = 'Cannot book parking for past time. Please select a future time.';
            $step = 4;
        }
        
        // Validate exit time is after entry time
        if (empty($error) && $entry_dt && $exit_dt && $exit_dt <= $entry_dt) {
            $error = 'Exit time must be after entry time.';
            $step = 4;
        }
        } // close the first if (empty($error)) block from date validation
        
        if (empty($error)) {

        // ensure payments table has payer fields and subtype/contact
        try {
            $required_columns = [
                'payer_name' => 'VARCHAR(100) NULL',
                'account_number' => 'VARCHAR(255) NULL',
                'payment_subtype' => 'VARCHAR(100) NULL',
                'wallet_contact' => 'VARCHAR(255) NULL'
            ];
            $colCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = ?");
            foreach ($required_columns as $col => $definition) {
                $colCheck->execute([$col]);
                if ((int)$colCheck->fetchColumn() === 0) {
                    try {
                        $pdo->exec("ALTER TABLE payments ADD COLUMN {$col} {$definition}");
                    } catch (Exception $ex) {
                        // ignore individual failures (e.g., insufficient privileges)
                    }
                }
            }
        } catch (Exception $e) {
            // If information_schema is not accessible, fall back to no-op and rely on existing schema.
        }

        // validate subtype selection and wallet contact when required
        $payment_subtype = '';
        if ($payment_mode === 'credit_card') {
            if (!$credit_card_type) { $error = 'Please select a credit card type.'; $step = 4; }
            $payment_subtype = $credit_card_type;
        } elseif ($payment_mode === 'debit_card') {
            if (!$debit_card_type) { $error = 'Please select a debit card type.'; $step = 4; }
            $payment_subtype = $debit_card_type;
        } elseif ($payment_mode === 'mobile_wallet') {
            if (!$mobile_wallet_type) { $error = 'Please select a mobile wallet.'; $step = 4; }
            $payment_subtype = $mobile_wallet_type;
            // require wallet contact for some wallets
            if (in_array($mobile_wallet_type, ['gcash', 'paymaya']) && !$wallet_contact) {
                $error = 'Please enter your mobile account or reference for the selected wallet.'; $step = 4;
            }
        } elseif ($payment_mode === 'upon_parking') {
            // cash upon parking - payer name is optional
            $payment_subtype = null;
        } else {
            $payment_subtype = null;
        }

        if ($error) {
            // if validation failed, do not proceed
            goto PAYMENT_VALIDATE_END;
        }

        // compute amount (hourly rate PHP 30, bill in 15-minute increments, minimum 15 mins)
        $hourly_rate_php = 30;
        $amount = (float) $hourly_rate_php * 0.25; // minimum 15-minute charge
        if ($entry_dt && $exit_dt) {
            $mins = max(0, (int) ((strtotime($exit_dt) - strtotime($entry_dt)) / 60));
            $min_increment = 15;
            $billed_mins = max($min_increment, (int) (ceil($mins / $min_increment) * $min_increment));
            $amount = round(($billed_mins / 60) * $hourly_rate_php, 2);
        }

        $pdo->beginTransaction();
        try {
            if ($payment_mode === 'upon_parking') {
                // Cash on parking: booking pending, slot pending, payment pending
                $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['pending', $slot_id]);
                $status = 'pending';
                $payment_status = 'pending';
            } else {
                // Online payment: booking pending, payment marked paid, slot pending (reserved)
                $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['pending', $slot_id]);
                $status = 'pending';
                $payment_status = 'paid';
            }

            $pdo->prepare('INSERT INTO bookings (user_id, vehicle_id, parking_slot_id, status, booked_at, planned_entry_time, entry_time, exit_time) VALUES (?, ?, ?, ?, NOW(), ?, NULL, ?)')
                ->execute([currentUserId(), $vehicle_id, $slot_id, $status, $entry_dt, $exit_dt]);
            $bid = $pdo->lastInsertId();

            // store payment_subtype and wallet_contact if available
            $stmt = $pdo->prepare('INSERT INTO payments (booking_id, amount, payment_status, payment_method, payment_subtype, wallet_contact, account_number, payer_name, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ? )');
            $paid_at = $payment_status === 'paid' ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$bid, $amount, $payment_status, $payment_mode, $payment_subtype, $wallet_contact ?: null, $account_number ?: null, $payer_name ?: null, $paid_at]);

            $pdo->commit();

            // Always show confirmation page
            header('Location: ' . BASE_URL . '/user/book.php?confirmed=1&booking_id=' . (int)$bid);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Payment processing failed. Please try again.';
            $step = 4;
        }
        PAYMENT_VALIDATE_END: ;
    }
}
}

require dirname(__DIR__) . '/includes/header.php';
?>
<style>
/* Modern Book Slot Styling */
* {
    font-family: 'Google Sans Flex', sans-serif;
}
.book-slot-page {
    max-width: 100%;
    width: 100%;
    margin: 0;
}
.book-slot-header {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
}
.book-slot-header h4 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.book-slot-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    margin: 0;
}
.book-slot-main {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(22, 163, 74, 0.08);
    border: 1px solid #e5f2e8;
    padding: 2rem;
    margin-bottom: 2rem;
}
.confirmation-center {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    padding: 1rem;
}
.book-slot-page .book-progress {
    gap: 1rem;
    justify-content: center;
    background: #f9fafb;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}
.book-slot-page .progress-num {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
    background: #e5e7eb;
    color: #9ca3af;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    flex-shrink: 0;
}
.book-slot-page .progress-num.active {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}
.book-slot-page .progress-label {
    margin-left: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #9ca3af;
    white-space: nowrap;
}
.book-slot-page .progress-label.active {
    color: #16a34a;
    font-weight: 700;
}
.book-slot-page .progress-line {
    width: 40px;
    max-width: 40px;
    height: 2px;
    flex-shrink: 0;
    border-radius: 1px;
}
.book-slot-card-wrap {
    max-width: 100%;
}
.slot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(56px, 1fr));
    gap: 0.6rem;
    align-items: stretch;
}
@media (max-width: 992px) {
    .slot-grid {
        grid-template-columns: repeat(auto-fit, minmax(48px, 1fr));
    }
}
@media (max-width: 576px) {
    .slot-grid {
        grid-template-columns: repeat(auto-fit, minmax(40px, 1fr));
        gap: 0.4rem;
    }
}
.slot-btn {
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    font-weight: 700;
    border: 2px solid transparent;
    border-radius: 10px;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    cursor: pointer;
    padding: 0.25rem;
    min-height: 40px;
    min-width: 40px;
}
.slot-btn.slot-available {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}
.slot-btn.slot-available:hover:not(:disabled) {
    background: #bbf7d0;
    border-color: #86efac;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
    transform: translateY(-2px);
}
.slot-btn.slot-available.selected {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    border-color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}
.slot-btn.slot-occupied {
    background: #f3f4f6;
    color: #9ca3af;
    border-color: #e5e7eb;
    cursor: not-allowed;
    opacity: 0.6;
}
.selected-slot-box {
    background: #dcfce7 !important;
    border: 1px solid #bbf7d0;
    color: #166534;
}
.btn-continue-slot {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    cursor: pointer;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    border: none;
    font-weight: 600;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
}
.btn-continue-slot:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(22, 163, 74, 0.25);
}
.btn-continue-slot:active:not(:disabled) {
    transform: translateY(0);
}
.btn-continue-slot:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-outline-secondary {
    border: 1.5px solid #d1d5db;
    color: #374151;
    font-weight: 600;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}
.btn-outline-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #111;
}
</style>
<div class="book-slot-page">
<a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center text-decoration-none text-dark mb-4" style="color: #111;"><i class="bi bi-arrow-left me-1"></i></a>

<div class="book-slot-header">
    <h4><i class="bi bi-p-circle"></i>Book a Parking Slot</h4>
    <p>Select your preferred slot and time</p>
</div>

<div class="book-slot-main">
<?php $progress_done = $show_confirmation; ?>
<!-- Progress indicator - clean style (4 steps: Select Slot → Choose Time → Confirm → Pay) -->
<ul class="list-unstyled d-flex align-items-center book-progress mb-4">
    <li class="d-flex align-items-center">
        <span class="progress-num <?= ($step >= 1 || $progress_done) ? 'active' : '' ?>">1</span>
        <span class="progress-label <?= ($step >= 1 || $progress_done) ? 'active' : '' ?>">Select Slot</span>
    </li>
    <li class="progress-line" style="background:<?= ($step >= 2 || $progress_done) ? '#86efac' : '#e5e7eb' ?>;"></li>
    <li class="d-flex align-items-center">
        <span class="progress-num <?= ($step >= 2 || $progress_done) ? 'active' : '' ?>">2</span>
        <span class="progress-label <?= ($step >= 2 || $progress_done) ? 'active' : '' ?>">Choose Time</span>
    </li>
    <li class="progress-line" style="background:<?= ($step >= 3 || $progress_done) ? '#86efac' : '#e5e7eb' ?>;"></li>
    <li class="d-flex align-items-center">
        <?php if ($show_confirmation): ?>
            <span class="progress-num active"><i class="bi bi-check-lg"></i></span>
            <span class="progress-label active">Confirm</span>
        <?php else: ?>
            <span class="progress-num <?= $step >= 3 ? 'active' : '' ?>">3</span>
            <span class="progress-label <?= $step >= 3 ? 'active' : '' ?>">Confirm</span>
        <?php endif; ?>
    </li>
    <li class="progress-line" style="background:<?= ($step >= 4 || $progress_done) ? '#86efac' : '#e5e7eb' ?>;"></li>
    <li class="d-flex align-items-center">
        <span class="progress-num <?= ($step >= 4 || $progress_done) ? 'active' : '' ?>">4</span>
        <span class="progress-label <?= ($step >= 4 || $progress_done) ? 'active' : '' ?>">Pay</span>
    </li>
</ul>

<?php if ($error && !$show_confirmation): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($show_confirmation && $confirmation_booking): ?>
    <?php
    $cb = $confirmation_booking;
    $slot_label = slotLabel($cb['slot_number']);
    // Use actual entry_time if available, otherwise use planned_entry_time or booked_at as fallback
    $entry_source = $cb['entry_time'] ?? $cb['planned_entry_time'] ?? $cb['booked_at'] ?? null;
    $exit_source = $cb['exit_time'] ?? null;
    $duration_mins = 0;
    if ($entry_source && $exit_source) {
        $duration_mins = max(0, (int) ((strtotime($exit_source) - strtotime($entry_source)) / 60));
    }
    $min_increment = 15;
    $billed_mins = $duration_mins > 0 ? max($min_increment, (int) (ceil($duration_mins / $min_increment) * $min_increment)) : 0;
    // Friendly date and 12-hour time format
    $conf_date = $entry_source ? date('F j, Y', strtotime($entry_source)) : '—';
    $conf_start = $entry_source ? date('g:i A', strtotime($entry_source)) : '—';
    $conf_end = $exit_source ? date('g:i A', strtotime($exit_source)) : '—';
    $conf_time = ($conf_start !== '—' && $conf_end !== '—') ? $conf_start . ' - ' . $conf_end : ($conf_start !== '—' ? $conf_start : '—');
    $conf_amount = isset($cb['amount']) && $cb['amount'] !== null ? number_format((float)$cb['amount'], 2) : '0.00';
    ?>
    <div class="confirmation-center">
    <div class="card border-0 rounded-3 shadow-lg" style="max-width:720px; width:100%;">
        <div class="card-body p-4 p-md-5 text-start">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);box-shadow:0 4px 12px rgba(22, 163, 74, 0.3);">
                <i class="bi bi-check-lg text-white" style="font-size: 2rem;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1" style="font-size: 1.5rem;">Booking Confirmed!</h4>
            <p class="text-muted mb-4">Your parking slot has been successfully booked.</p>
            <div class="text-start" style="width:100%;">
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted">Slot Number:</span>
                    <strong><?= htmlspecialchars($slot_label) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted">Date:</span>
                    <strong><?= htmlspecialchars($conf_date) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted">Time:</span>
                    <strong><?= htmlspecialchars($conf_time) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted">Billed Minutes</span>
                    <strong><?= $billed_mins ? htmlspecialchars($billed_mins) . ' min' : '—' ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Total Amount:</span>
                    <strong style="color:#16a34a;font-size:1.1rem;">&#8369;<?= htmlspecialchars($conf_amount) ?></strong>
                </div>
                <div style="font-size:0.85rem;color:#6b7280;margin-top:6px;">Rate: &#8369;30.00/hr — billed in 15-minute increments (minimum 15 minutes)</div>
            </div>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary rounded-3 px-4">Back to Dashboard</a>
                <a href="<?= BASE_URL ?>/user/receipt.php?booking_id=<?= (int)$cb['id'] ?>" class="btn rounded-3 px-4" style="background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);color:#fff;border:none;font-weight:600;box-shadow:0 2px 8px rgba(22, 163, 74, 0.15);">View Receipt</a>
            </div>
        </div>
    </div>
    </div>

<?php elseif (empty($vehicles)): ?>
    <div class="confirmation-center">
        <div class="no-vehicles-card" style="text-align:center; background:#f6fffa; border-radius:12px; padding:36px 28px; max-width:560px; width:100%; box-shadow:0 8px 24px rgba(22,163,74,0.06); border:1px solid #e6f7ee;">
            <div style="font-size:48px; color:#9ae6b4; margin-bottom:10px;"><i class="bi bi-car-front"></i></div>
            <h4 style="margin:0 0 8px 0; color:#374151; font-weight:700;">Please register a car first</h4>
            <p style="margin:0 0 12px 0; color:#6b7280;">You need to add a vehicle before you can book a parking slot.</p>
            <a href="<?= BASE_URL ?>/user/register-car.php" class="btn" style="background:linear-gradient(135deg,#16a34a 0%,#15803d 100%); color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:700;">Register a car</a>
        </div>
    </div>
<?php elseif ($step === 1): ?>
    <!-- Step 1: Select Slot - clean style from reference -->
    <div class="book-slot-card-wrap">
        <h5 class="fw-bold mb-4" style="color: #374151; font-size: 1.1rem;">Available Parking Slots</h5>
        <form method="get" action="<?= BASE_URL ?>/user/book.php" id="formStep1">
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="slot_id" id="selectedSlotId" value="">
            <div class="slot-grid mb-4" id="slotGrid">
                <?php foreach ($all_slots as $s): ?>
                    <?php $available = $s['status'] === 'available'; ?>
                    <button type="button" class="slot-btn slot-<?= $available ? 'available' : 'occupied' ?>" data-slot-id="<?= $available ? $s['id'] : '' ?>" data-slot-num="<?= htmlspecialchars(slotLabel($s['slot_number'])) ?>" <?= $available ? '' : 'disabled' ?>>
                        <?= htmlspecialchars(slotLabel($s['slot_number'])) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="selected-slot-box rounded-3 py-2 px-3 mb-4" id="selectedSlotBox" style="display: none; background: #dcfce7; color: #166534; font-size: 0.9rem;">
                <span class="text-muted">Selected Slot:</span> <strong id="selectedSlotLabel">—</strong>
            </div>
            <?php if (empty($available_slots)): ?>
                <p class="text-muted mb-0">No slots available. Try again later.</p>
            <?php else: ?>
                <button type="submit" class="btn-continue-slot w-100 py-3 rounded-3 border-0 fw-semibold" id="btnContinue" disabled>Continue to Time Selection</button>
            <?php endif; ?>
        </form>
    </div>
    <style>
        .book-slot-card-wrap { max-width: 100%; }
        /* denser slot grid: smaller buttons to reduce scrolling */
        .slot-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(56px, 1fr)); gap: 0.5rem; align-items: stretch; }
        @media (max-width: 992px) { .slot-grid { grid-template-columns: repeat(auto-fit, minmax(48px, 1fr)); } }
        @media (max-width: 576px) { .slot-grid { grid-template-columns: repeat(auto-fit, minmax(40px, 1fr)); gap: 0.4rem; } }
        .slot-btn {
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem; font-weight: 700; border: none; border-radius: 8px;
            transition: background .15s, color .15s, box-shadow .12s; cursor: pointer; padding: 0.25rem;
            min-height: 40px; min-width: 40px;
        }
        .slot-btn.slot-available { background: #dcfce7; color: #166534; }
        .slot-btn.slot-available:hover:not(:disabled) { background: #bbf7d0; box-shadow: 0 2px 6px rgba(34,197,94,.2); }
        .slot-btn.slot-available.selected { background: #86efac; color: #166534; }
        .slot-btn.slot-occupied { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; opacity: 0.8; }
        .btn-continue-slot { background: #22c55e; color: #fff; cursor: pointer; transition: background .2s, opacity .2s; }
        .btn-continue-slot:hover:not(:disabled) { background: #16a34a; color: #fff; }
        .btn-continue-slot:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
    <script>
    (function() {
        var form = document.getElementById('formStep1');
        var hidden = document.getElementById('selectedSlotId');
        var btnContinue = document.getElementById('btnContinue');
        var box = document.getElementById('selectedSlotBox');
        var label = document.getElementById('selectedSlotLabel');
        if (!form) return;
        form.querySelectorAll('.slot-btn[data-slot-id]').forEach(function(btn) {
            if (btn.disabled) return;
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-slot-id');
                var num = this.getAttribute('data-slot-num') || '';
                if (!id) return;
                form.querySelectorAll('.slot-btn.selected').forEach(function(b) { b.classList.remove('selected'); });
                this.classList.add('selected');
                hidden.value = id;
                if (label) label.textContent = num;
                if (box) box.style.display = 'block';
                if (btnContinue) btnContinue.disabled = false;
            });
        });
    })();
    </script>

<?php elseif ($step === 2 && $selected_slot_id): ?>
    <!-- Step 2: Choose Time - Select Booking Time format -->
    <div class="card border-0 rounded-3 shadow-sm" style="border:1px solid #e5f2e8;">
        <div class="card-body p-4" style="background:#fff;">
            <h5 class="fw-bold text-dark mb-4" style="font-size:1.15rem;">Select Booking Time</h5>
            <div id="hoursNotice" class="alert alert-danger" style="display:none;"></div>
            <form method="get" action="<?= BASE_URL ?>/user/book.php" id="formStep2" data-opening="<?= $opening_time_setting ? date('H:i', strtotime($opening_time_setting)) : '' ?>" data-closing="<?= $closing_time_setting ? date('H:i', strtotime($closing_time_setting)) : '' ?>" data-opening-friendly="<?= $opening_time_setting ? date('g:i A', strtotime($opening_time_setting)) : '' ?>" data-closing-friendly="<?= $closing_time_setting ? date('g:i A', strtotime($closing_time_setting)) : '' ?>">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="slot_id" value="<?= $selected_slot_id ?>">
                <div class="mb-4">
                    <label class="form-label fw-600" style="font-weight:600;color:#374151;">
                        <i class="bi bi-calendar3 me-2" style="color:#16a34a;"></i>Parking Date
                    </label>
                    <input type="date" name="parking_date" id="parkingDate" class="form-control" style="border-radius:10px;padding:0.85rem 1rem;border:1px solid #e5f2e8;background:#fafcfb;transition:all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;" min="<?= date('Y-m-d') ?>" required>
                    <small class="form-text text-muted">Cannot select past dates</small>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-600" style="font-weight:600;color:#374151;">
                        <i class="bi bi-clock me-2" style="color:#16a34a;"></i>Start Time
                    </label>
                    <input type="time" name="start_time" id="startTime" class="form-control" style="border-radius:10px;padding:0.85rem 1rem;border:1px solid #e5f2e8;background:#fafcfb;transition:all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;" required <?php if ($opening_time_setting && $closing_time_setting): ?>min="<?= date('H:i', strtotime($opening_time_setting)) ?>" max="<?= date('H:i', strtotime($closing_time_setting)) ?>"<?php endif; ?>>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-600" style="font-weight:600;color:#374151;">
                        <i class="bi bi-clock me-2" style="color:#16a34a;"></i>End Time
                    </label>
                    <input type="time" name="end_time" id="endTime" class="form-control" style="border-radius:10px;padding:0.85rem 1rem;border:1px solid #e5f2e8;background:#fafcfb;transition:all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;" required <?php if ($opening_time_setting && $closing_time_setting): ?>min="<?= date('H:i', strtotime($opening_time_setting)) ?>" max="<?= date('H:i', strtotime($closing_time_setting)) ?>"<?php endif; ?>>
                </div>
                <div class="rounded-3 py-3 px-3 mb-4" id="estimatedDurationBox" style="background: #dcfce7; color: #166534; border:1px solid #bbf7d0;">
                    <span class="small fw-semibold">Estimated Duration: </span><span id="estimatedDurationText">—</span>
                </div>
                <div class="d-flex justify-content-between gap-2 pt-2" style="border-top:1px solid #e5f2e8;padding-top:1.5rem;">
                    <a href="<?= BASE_URL ?>/user/book.php?step=1" class="btn btn-outline-secondary rounded-3 px-4">Back</a>
                    <button type="submit" class="btn rounded-3 px-4" style="background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);color:#fff;border:none;font-weight:600;box-shadow:0 2px 8px rgba(22, 163, 74, 0.15);">Review Booking</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function() {
        var form = document.getElementById('formStep2');
        var dateEl = form ? form.querySelector('input[name="parking_date"]') : null;
        var startEl = form ? form.querySelector('input[name="start_time"]') : null;
        var endEl = form ? form.querySelector('input[name="end_time"]') : null;
        var textEl = document.getElementById('estimatedDurationText');
        var notice = document.getElementById('hoursNotice');
        if (!form || !dateEl || !startEl || !endEl || !textEl) return;

        var opening = form.getAttribute('data-opening') || '';
        var closing = form.getAttribute('data-closing') || '';

        function parseTimeToMinutes(str) {
            if (!str || str.length < 4) return null;
            var parts = str.split(':');
            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        }
        
        function getTodayDate() {
            var today = new Date();
            var year = today.getFullYear();
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var day = String(today.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
        
        function getCurrentTime() {
            var now = new Date();
            var hours = String(now.getHours()).padStart(2, '0');
            var minutes = String(now.getMinutes()).padStart(2, '0');
            return hours + ':' + minutes;
        }

        var estBox = document.getElementById('estimatedDurationBox');
        var invalidBooking = false;

        function clearValidation() {
            invalidBooking = false;
            notice.style.display = 'none';
            if (estBox) { estBox.style.background = '#dcfce7'; estBox.style.color = '#166534'; }
        }

        function setValidation(msg) {
            invalidBooking = true;
            if (estBox) { estBox.style.background = '#fee2e2'; estBox.style.color = '#991b1b'; }
            textEl.textContent = msg;
            notice.textContent = msg;
            notice.style.display = 'block';
        }

        function updateDurationAndValidate() {
            clearValidation();
            
            // Validate date is not in the past
            var selectedDate = dateEl.value;
            var todayDate = getTodayDate();
            
            if (selectedDate && selectedDate < todayDate) {
                setValidation('Cannot book parking for past dates. Please select today or a future date.');
                return;
            }
            
            var start = parseTimeToMinutes(startEl.value);
            var end = parseTimeToMinutes(endEl.value);
            
            // If booking for today, validate start time is not in the past
            if (selectedDate === todayDate && start !== null) {
                var currentTime = getCurrentTime();
                var currentMinutes = parseTimeToMinutes(currentTime);
                
                if (start < currentMinutes) {
                    setValidation('Cannot book parking for past time. Please select a future time.');
                    return;
                }
            }
            
            if (start == null || end == null) { textEl.textContent = '—'; return; }
            var mins = end - start;
            if (mins <= 0) { 
                setValidation('Exit time must be after entry time.');
                return; 
            }
            var h = Math.floor(mins / 60);
            var m = mins % 60;
            var parts = [];
            if (h) parts.push(h === 1 ? '1 hour' : h + ' hours');
            if (m) parts.push(m === 1 ? '1 minute' : m + ' minutes');
            textEl.textContent = parts.length ? parts.join(' ') : '—';

            if (opening && closing) {
                var openMin = parseTimeToMinutes(opening);
                var closeMin = parseTimeToMinutes(closing);
                var windowMin = closeMin - openMin;
                var openingFriendly = form.getAttribute('data-opening-friendly') || opening;
                var closingFriendly = form.getAttribute('data-closing-friendly') || closing;
                if (start < openMin) {
                    setValidation('Entry time must be at or after opening time (' + openingFriendly + ').');
                    return;
                }
                if (end > closeMin) {
                    setValidation('Exit time must be at or before closing time (' + closingFriendly + ').');
                    return;
                }
                if (mins > windowMin) {
                    setValidation('Booking duration cannot exceed operating hours (' + Math.floor(windowMin/60) + 'h ' + (windowMin%60) + 'm).');
                    return;
                }
            }
        }

        dateEl.addEventListener('change', updateDurationAndValidate);
        dateEl.addEventListener('input', updateDurationAndValidate);
        startEl.addEventListener('change', updateDurationAndValidate);
        startEl.addEventListener('input', updateDurationAndValidate);
        endEl.addEventListener('change', updateDurationAndValidate);
        endEl.addEventListener('input', updateDurationAndValidate);
        updateDurationAndValidate();

        // prevent submission if invalid
        form.addEventListener('submit', function(e) {
            if (invalidBooking) {
                e.preventDefault();
                if (estBox) estBox.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        });
    })();
    </script>

<?php elseif ($step === 3 && $selected_slot_id): ?>
    <?php
    $display_date = $parking_date ? date('d/m/Y', strtotime($parking_date)) : '—';
    $display_start = $start_time ? (strlen($start_time) >= 5 ? substr($start_time, 0, 5) : $start_time) : '—';
    $display_end = $end_time ? (strlen($end_time) >= 5 ? substr($end_time, 0, 5) : $end_time) : '—';

    // Build back URL to preserve chosen date/time when returning to step 2
    $backParams = ['step' => 2, 'slot_id' => $selected_slot_id];
    if (!empty($parking_date)) $backParams['parking_date'] = $parking_date;
    if (!empty($start_time)) $backParams['start_time'] = $start_time;
    if (!empty($end_time)) $backParams['end_time'] = $end_time;
    $backUrl = BASE_URL . '/user/book.php?' . http_build_query($backParams);
    
    // Check which vehicles have conflicts for the selected time
    $vehicle_conflicts = [];
    if ($parking_date && $start_time && $end_time) {
        $entry_dt = $parking_date . ' ' . (strlen($start_time) === 5 ? $start_time . ':00' : substr($start_time, 0, 8));
        $exit_dt = $parking_date . ' ' . (strlen($end_time) === 5 ? $end_time . ':00' : substr($end_time, 0, 8));
        
        foreach ($vehicles as $v) {
            $conflictCheck = $pdo->prepare('
                SELECT b.id, ps.slot_number, b.planned_entry_time, b.exit_time
                FROM bookings b 
                JOIN parking_slots ps ON ps.id = b.parking_slot_id
                WHERE b.vehicle_id = ? 
                AND b.status IN (?, ?, ?)
                AND b.planned_entry_time IS NOT NULL
                AND b.exit_time IS NOT NULL
                AND b.planned_entry_time < ?
                AND b.exit_time > ?
                LIMIT 1
            ');
            $conflictCheck->execute([$v['id'], 'pending', 'active', 'confirmed', $exit_dt, $entry_dt]);
            $conflict = $conflictCheck->fetch();
            
            if ($conflict) {
                $vehicle_conflicts[$v['id']] = [
                    'slot' => slotLabel($conflict['slot_number']),
                    'start' => date('g:i A', strtotime($conflict['planned_entry_time'])),
                    'end' => date('g:i A', strtotime($conflict['exit_time']))
                ];
            }
        }
    }
    ?>
    <!-- Step 3: Confirm -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4">Confirm Booking</h5>
            <p class="mb-2">Slot: <strong><?= htmlspecialchars(slotLabel($selected_slot['slot_number'])) ?></strong></p>
            <?php if ($parking_date || $start_time || $end_time): ?>
            <p class="mb-2 text-muted small">Date: <?= htmlspecialchars($display_date) ?> · Start: <?= htmlspecialchars($display_start) ?> · End: <?= htmlspecialchars($display_end) ?></p>
            <?php endif; ?>
            
            <?php if (!empty($vehicle_conflicts)): ?>
            <div class="alert alert-warning" style="background:#fff7ed;border:1px solid #fed7aa;color:#92400e;border-radius:8px;padding:12px;margin-bottom:1rem;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Note:</strong> Some of your vehicles are already booked during this time. They are marked below.
            </div>
            <?php endif; ?>
            
            <form method="get" action="<?= BASE_URL ?>/user/book.php" id="vehicleSelectionForm">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="slot_id" value="<?= $selected_slot_id ?>">
                <?php if ($parking_date): ?><input type="hidden" name="parking_date" value="<?= htmlspecialchars($parking_date) ?>"><?php endif; ?>
                <?php if ($start_time): ?><input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>"><?php endif; ?>
                <?php if ($end_time): ?><input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time) ?>"><?php endif; ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Select Vehicle</label>
                    <select name="vehicle_id" id="vehicleSelect" class="form-select" required style="padding:0.75rem 1rem;border-radius:8px;">
                        <option value="">-- Choose car --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <?php 
                            $has_conflict = isset($vehicle_conflicts[$v['id']]);
                            $conflict_info = $has_conflict ? $vehicle_conflicts[$v['id']] : null;
                            ?>
                            <option value="<?= $v['id'] ?>" <?= $has_conflict ? 'disabled' : '' ?> data-conflict="<?= $has_conflict ? '1' : '0' ?>">
                                <?= htmlspecialchars($v['plate_number']) ?> (<?= htmlspecialchars($v['model'] ?? 'N/A') ?>)<?= $has_conflict ? ' - Already booked ' . $conflict_info['start'] . '-' . $conflict_info['end'] . ' at Slot ' . $conflict_info['slot'] : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Vehicles already booked during this time are unavailable</small>
                </div>
                
                <div id="conflictAlert" class="alert alert-danger" style="display:none;margin-top:1rem;border-radius:8px;">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <span id="conflictMessage"></span>
                </div>
                
                <div class="d-flex justify-content-between gap-2">
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary rounded-3 px-4">Back</a>
                    <button type="submit" class="btn btn-success rounded-3 px-4">Proceed to Payment</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    #vehicleSelect option:disabled {
        color: #9ca3af;
        background: #f3f4f6;
    }
    </style>
    
    <script>
    (function() {
        var form = document.getElementById('vehicleSelectionForm');
        var select = document.getElementById('vehicleSelect');
        var alert = document.getElementById('conflictAlert');
        var message = document.getElementById('conflictMessage');
        
        if (form && select) {
            select.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.getAttribute('data-conflict') === '1') {
                    var optionText = selectedOption.textContent;
                    var conflictMatch = optionText.match(/Already booked (.+)/);
                    if (conflictMatch) {
                        message.textContent = 'This vehicle is ' + conflictMatch[0].toLowerCase() + '. Please select a different vehicle.';
                        alert.style.display = 'block';
                    }
                } else {
                    alert.style.display = 'none';
                }
            });
            
            // Prevent form submission if conflict vehicle selected
            form.addEventListener('submit', function(e) {
                var selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.getAttribute('data-conflict') === '1') {
                    e.preventDefault();
                    message.textContent = 'Please select a vehicle that is not already booked.';
                    alert.style.display = 'block';
                    alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    })();
    </script>
<?php else: ?>
<?php endif; ?>
</div>
</div>

<?php if ($step === 4): ?>
    <?php
        $vehicle_id = (int) ($_GET['vehicle_id'] ?? $_POST['vehicle_id'] ?? 0);
        $slot_id = (int) ($_GET['slot_id'] ?? $_POST['slot_id'] ?? 0);
        $pd = trim($_GET['parking_date'] ?? $_POST['parking_date'] ?? '');
        $st = trim($_GET['start_time'] ?? $_POST['start_time'] ?? '');
        $et = trim($_GET['end_time'] ?? $_POST['end_time'] ?? '');
        // compute display values and amount
        $slot_number = '';
        if ($slot_id) {
            $s = $pdo->prepare('SELECT slot_number FROM parking_slots WHERE id = ?');
            $s->execute([$slot_id]);
            $row = $s->fetch();
            $slot_number = $row ? slotLabel($row['slot_number']) : '';
        }
        $display_date = $pd ? date('Y-m-d', strtotime($pd)) : '—';
        $display_start = $st ? (strlen($st) >= 5 ? substr($st,0,5) : $st) : '—';
        $display_end = $et ? (strlen($et) >= 5 ? substr($et,0,5) : $et) : '—';
        $hourly_rate_php = 30;
        $display_amount = round($hourly_rate_php * 0.25, 2);
        if ($pd && $st && $et) {
            $entry_dt = $pd . ' ' . (strlen($st) === 5 ? $st . ':00' : $st);
            $exit_dt = $pd . ' ' . (strlen($et) === 5 ? $et . ':00' : $et);
            $mins = max(0, (int) ((strtotime($exit_dt) - strtotime($entry_dt)) / 60));
            $min_increment = 15;
            $billed_mins = max($min_increment, (int) (ceil($mins / $min_increment) * $min_increment));
            $display_amount = round(($billed_mins / 60) * $hourly_rate_php, 2);
        }
    ?>

    <style>
    /* Styled payment step (matches reference) */
    .payment-step-wrap { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
    .payment-summary { flex:0 0 320px; background:#f0fbef; border-radius:12px; padding:1rem; border:1px solid #e6f9ea; }
    .payment-summary h6 { margin-bottom:.5rem; color:#065f46; }
    .payment-summary .row { display:flex; justify-content:space-between; padding:.45rem 0; border-bottom:1px solid rgba(0,0,0,0.03); }
    .payment-card { flex:1 1 520px; background:#ffffff; border-radius:12px; padding:1rem; border:1px solid #e6f2ea; }
    .payment-option { display:flex; gap:12px; align-items:center; padding:12px; border-radius:8px; border:1px solid #e6f2ea; margin-bottom:.75rem; cursor:pointer; }
    .payment-option .icon { width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.05rem; }
    .payment-option .meta { flex:1; }
    .payment-option .title { font-weight:700; color:#064e3b; }
    .payment-option .desc { font-size:.9rem; color:#6b7280; }
    .payment-option.selected { border-color:#10b981; box-shadow:0 4px 10px rgba(16,185,129,0.08); }
    .payment-note { background:#ecfdf5; border:1px solid #d1fae5; padding:12px; border-radius:8px; color:#065f46; margin-top:.5rem; }
    .payment-instructions { background:#fff7ed; border:1px solid #fff0d9; padding:12px; border-radius:8px; color:#92400e; margin-top:.5rem; }
    /* mobile wallet list */
    .mobile-wallet-wrapper { margin-top:0.75rem; }
    .wallet-list { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.5rem; }
    .wallet-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; border:1px solid #e6f2ea; cursor:pointer; }
    .wallet-item .wallet-icon { width:34px; height:34px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .wallet-item .wallet-label { font-weight:600; color:#064e3b; }
    .wallet-item.selected { border-color:#10b981; box-shadow:0 6px 14px rgba(16,185,129,0.06); }
    /* credit/debit subtypes */
    .card-type-list { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.5rem; }
    .card-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; border:1px solid #e6f2ea; cursor:pointer; min-width:180px; }
    .card-item .card-icon { width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .card-item .card-label { font-weight:600; color:#064e3b; }
    .card-item.selected { border-color:#10b981; box-shadow:0 6px 14px rgba(16,185,129,0.06); }
    .payment-actions { display:flex; gap:12px; justify-content:space-between; margin-top:1rem; }
    @media(max-width:900px){ .payment-step-wrap{flex-direction:column} .payment-summary{order:2} }
    </style>

    <div class="payment-step-wrap">
        <div class="payment-card">
            <h5 class="fw-bold mb-2">Select Payment Method</h5>
            <p class="text-muted mb-3">Choose how you'd like to pay for your parking</p>

            <div class="booking-summary mb-3" style="background:#f0fbef;border:1px solid #e6f9ea;padding:.75rem;border-radius:8px;">
                <h6 style="margin:0 0 .5rem;color:#065f46;font-size:0.95rem;">Booking Summary</h6>
                <div style="display:flex;justify-content:space-between;padding:.25rem 0;border-top:0;">
                    <div class="text-muted">Date</div><div><?= htmlspecialchars($display_date) ?></div>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.25rem 0;">
                    <div class="text-muted">Slot</div><div><?= htmlspecialchars($slot_number ?: '—') ?></div>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.25rem 0;">
                    <div class="text-muted">Time</div><div><?= htmlspecialchars($display_start . ' · ' . $display_end) ?></div>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.25rem 0;">
                    <div class="text-muted"><strong>Total Amount:</strong></div><div><strong style="color:#059669">&#8369;<?= htmlspecialchars(number_format($display_amount,2)) ?></strong></div>
                </div>
            </div>

            <form method="post" action="<?= BASE_URL ?>/user/book.php" id="paymentForm">
                <input type="hidden" name="submit_payment" value="1">
                <input type="hidden" name="slot_id" value="<?= htmlspecialchars($slot_id) ?>">
                <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($vehicle_id) ?>">
                <?php if ($pd): ?><input type="hidden" name="parking_date" value="<?= htmlspecialchars($pd) ?>"><?php endif; ?>
                <?php if ($st): ?><input type="hidden" name="start_time" value="<?= htmlspecialchars($st) ?>"><?php endif; ?>
                <?php if ($et): ?><input type="hidden" name="end_time" value="<?= htmlspecialchars($et) ?>"><?php endif; ?>

                <div class="payment-options">
                    <label class="payment-option" data-value="credit_card">
                        <div class="icon" style="background:#2563eb"><i class="bi bi-credit-card"></i></div>
                        <div class="meta"><div class="title">Credit Card</div><div class="desc">Visa, Mastercard, or American Express</div></div>
                        <input type="radio" name="payment_mode" value="credit_card" class="d-none">
                    </label>

                    <label class="payment-option" data-value="debit_card">
                        <div class="icon" style="background:#10b981"><i class="bi bi-bank2"></i></div>
                        <div class="meta"><div class="title">Debit Card</div><div class="desc">Direct bank debit</div></div>
                        <input type="radio" name="payment_mode" value="debit_card" class="d-none">
                    </label>

                    <label class="payment-option" data-value="mobile_wallet">
                        <div class="icon" style="background:#7c3aed"><i class="bi bi-phone"></i></div>
                        <div class="meta"><div class="title">Mobile Wallet</div><div class="desc">GCash, PayMaya, Apple/Google Pay</div></div>
                        <input type="radio" name="payment_mode" value="mobile_wallet" class="d-none">
                    </label>

                    <label class="payment-option selected" data-value="upon_parking">
                        <div class="icon" style="background:#f59e0b"><i class="bi bi-cash-stack"></i></div>
                        <div class="meta"><div class="title">Pay Cash Upon Parking</div><div class="desc">Pay at the parking lot entrance</div></div>
                        <input type="radio" name="payment_mode" value="upon_parking" class="d-none" checked>
                    </label>
                </div>
                
                <div class="credit-card-wrapper" style="display:none;">
                    <label class="form-label">Select Credit Card:</label>
                    <div class="card-type-list">
                        <label class="card-item" data-card="visa">
                            <input type="radio" name="credit_card_type" value="visa" class="d-none">
                            <div class="card-icon" style="background:#2563eb"><i class="bi bi-credit-card"></i></div>
                            <div class="card-label">Visa</div>
                        </label>
                        <label class="card-item" data-card="mastercard">
                            <input type="radio" name="credit_card_type" value="mastercard" class="d-none">
                            <div class="card-icon" style="background:#ef4444"><i class="bi bi-credit-card"></i></div>
                            <div class="card-label">Mastercard</div>
                        </label>
                        <label class="card-item" data-card="amex">
                            <input type="radio" name="credit_card_type" value="amex" class="d-none">
                            <div class="card-icon" style="background:#8b5cf6"><i class="bi bi-credit-card"></i></div>
                            <div class="card-label">American Express</div>
                        </label>
                    </div>
                </div>

                <div class="debit-card-wrapper" style="display:none;">
                    <label class="form-label">Select Debit Card:</label>
                    <div class="card-type-list">
                        <label class="card-item" data-card="visa_debit">
                            <input type="radio" name="debit_card_type" value="visa_debit" class="d-none">
                            <div class="card-icon" style="background:#06b6d4"><i class="bi bi-bank2"></i></div>
                            <div class="card-label">Visa Debit</div>
                        </label>
                        <label class="card-item" data-card="mastercard_debit">
                            <input type="radio" name="debit_card_type" value="mastercard_debit" class="d-none">
                            <div class="card-icon" style="background:#10b981"><i class="bi bi-bank2"></i></div>
                            <div class="card-label">Mastercard Debit</div>
                        </label>
                        <label class="card-item" data-card="local_bank">
                            <input type="radio" name="debit_card_type" value="local_bank" class="d-none">
                            <div class="card-icon" style="background:#06b6d4"><i class="bi bi-building"></i></div>
                            <div class="card-label">Local Bank Debit</div>
                        </label>
                    </div>
                </div>

                <div class="mobile-wallet-wrapper" style="display:none;">
                    <label class="form-label">Choose Mobile Wallet</label>
                    <div class="wallet-list">
                        <label class="wallet-item selected" data-wallet="gcash">
                            <input type="radio" name="mobile_wallet_type" value="gcash" class="d-none" checked>
                            <div class="wallet-icon" style="background:#06b6d4"><i class="bi bi-wallet2"></i></div>
                            <div class="wallet-label">GCash</div>
                        </label>
                        <label class="wallet-item" data-wallet="paymaya">
                            <input type="radio" name="mobile_wallet_type" value="paymaya" class="d-none">
                            <div class="wallet-icon" style="background:#7c3aed"><i class="bi bi-wallet2"></i></div>
                            <div class="wallet-label">PayMaya</div>
                        </label>
                        <label class="wallet-item" data-wallet="apple_pay">
                            <input type="radio" name="mobile_wallet_type" value="apple_pay" class="d-none">
                            <div class="wallet-icon" style="background:#111827"><i class="bi bi-apple"></i></div>
                            <div class="wallet-label">Apple Pay</div>
                        </label>
                        <label class="wallet-item" data-wallet="google_pay">
                            <input type="radio" name="mobile_wallet_type" value="google_pay" class="d-none">
                            <div class="wallet-icon" style="background:#4285f4"><i class="bi bi-google"></i></div>
                            <div class="wallet-label">Google Pay</div>
                        </label>
                    </div>
                </div>

                <div class="payment-note">
                    <strong>Secure Payment</strong>
                    <div class="small">Your payment information is encrypted and secure</div>
                </div>

                <div class="payment-instructions">
                    <strong>Cash Payment Instructions</strong>
                    <ul style="margin:6px 0 0 18px;padding:0;color:inherit">
                        <li>Your slot is reserved with this booking</li>
                        <li>Pay the full amount at the parking lot entrance</li>
                        <li>Show your booking confirmation to the attendant</li>
                        <li>Keep your receipt for reference</li>
                    </ul>
                </div>

                <div id="walletContactWrap" class="mb-3" style="display:none;">
                    <label class="form-label">Mobile Account / Reference</label>
                    <input type="text" id="walletContact" name="wallet_contact" class="form-control" placeholder="Phone number or transaction reference">
                </div>

                <div id="accountNumberWrap" class="mb-3 mt-3" style="display:none;">
                    <label class="form-label">Card Number <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" id="accountNumber" class="form-control" placeholder="Enter card number (10-19 digits)" maxlength="19">
                    <small class="form-text text-muted">Enter your credit/debit card number</small>
                </div>
                <div id="payerNameWrap" class="mb-3" style="display:none;">
                    <label class="form-label">Full Name (Cardholder/Payer Name) <span class="text-danger">*</span></label>
                    <input type="text" id="payerName" name="payer_name" class="form-control" placeholder="Enter full name as shown on card/ID">
                    <small class="form-text text-muted">Required for card and wallet payments</small>
                </div>

                <div class="payment-actions">
                    <a href="<?= BASE_URL ?>/user/book.php?step=3&slot_id=<?= $slot_id ?>&parking_date=<?= urlencode($pd) ?>&start_time=<?= urlencode($st) ?>&end_time=<?= urlencode($et) ?>" class="btn btn-outline-secondary rounded-3 px-4">Back</a>
                    <button type="submit" id="btnContinuePayment" class="btn btn-success rounded-3 px-4">Continue to Confirmation</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var options = document.querySelectorAll('.payment-option');
        var instructions = document.querySelector('.payment-instructions');
        var mobileWrapper = document.querySelector('.mobile-wallet-wrapper');
        var walletItems = document.querySelectorAll('.wallet-item');

        var creditWrapper = document.querySelector('.credit-card-wrapper');
        var debitWrapper = document.querySelector('.debit-card-wrapper');
            var walletContactWrap = document.getElementById('walletContactWrap');
        var walletContactInput = document.getElementById('walletContact');
        var payerNameWrap = document.getElementById('payerNameWrap');
        var payerNameInput = document.getElementById('payerName');
            var accountNumberWrap = document.getElementById('accountNumberWrap');
        var accountNumberInput = accountNumberWrap ? accountNumberWrap.querySelector('input[name="account_number"]') : null;
        var submitBtn = document.getElementById('btnContinuePayment');
        var paymentForm = submitBtn ? submitBtn.closest('form') : null;
        
        // Validation functions
        function validatePayerName(name) {
            if (!name || name.trim() === '') return 'Payer name is required.';
            if (!/^[a-zA-Z ]+$/.test(name)) return 'Payer name can only contain letters and spaces. Special characters and numbers are not allowed.';
            if (name.trim().length < 2) return 'Payer name must be at least 2 characters long.';
            if (name.length > 100) return 'Payer name is too long (maximum 100 characters).';
            return '';
        }
        
        function validateAccountNumber(number) {
            if (!number || number.trim() === '') return 'Account/Card number is required.';
            if (!/^[0-9]+$/.test(number)) return 'Account/Card number can only contain numbers. Special characters are not allowed.';
            if (number.length < 10) return 'Account/Card number must be at least 10 digits.';
            if (number.length > 19) return 'Account/Card number is too long (maximum 19 digits).';
            return '';
        }
        
        function validateWalletContact(contact) {
            if (!contact || contact.trim() === '') return 'Mobile wallet contact number is required.';
            const clean = contact.replace(/[^0-9]/g, '');
            if (!/^09[0-9]{9}$/.test(clean)) return 'Mobile wallet contact must be in format 09XXXXXXXXX (11 digits starting with 09).';
            return '';
        }
        
        function showFieldError(field, message) {
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
        
        function clearFieldError(field) {
            showFieldError(field, '');
        }
        
        // Real-time validation
        if (payerNameInput) {
            payerNameInput.addEventListener('blur', function() {
                if (this.parentElement.style.display !== 'none') {
                    const error = validatePayerName(this.value);
                    showFieldError(this, error);
                }
            });
            
            payerNameInput.addEventListener('input', function() {
                clearFieldError(this);
            });
        }
        
        if (accountNumberInput) {
            accountNumberInput.addEventListener('blur', function() {
                var paymentMode = document.querySelector('input[name="payment_mode"]:checked');
                if (paymentMode && (paymentMode.value === 'credit_card' || paymentMode.value === 'debit_card')) {
                    const error = validateAccountNumber(this.value);
                    showFieldError(this, error);
                }
            });
            
            accountNumberInput.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                clearFieldError(this);
            });
        }
        
        if (walletContactInput) {
            walletContactInput.addEventListener('blur', function() {
                if (this.parentElement.style.display !== 'none') {
                    const error = validateWalletContact(this.value);
                    showFieldError(this, error);
                }
            });
            
            walletContactInput.addEventListener('input', function() {
                clearFieldError(this);
            });
        }
        
        // Form submission validation
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                var hasError = false;
                var firstError = null;
                
                var paymentMode = document.querySelector('input[name="payment_mode"]:checked');
                
                if (!paymentMode) {
                    e.preventDefault();
                    alert('Please select a payment method.');
                    return false;
                }
                
                // Validate payer name - required for card/wallet payments only
                if (payerNameInput && paymentMode.value !== 'upon_parking') {
                    const error = validatePayerName(payerNameInput.value);
                    if (error) {
                        hasError = true;
                        showFieldError(payerNameInput, error);
                        if (!firstError) firstError = payerNameInput;
                    }
                }
                
                // Validate account number for credit/debit cards only
                if (paymentMode.value === 'credit_card' || paymentMode.value === 'debit_card') {
                    // Check card type selection
                    var cardTypeInput = paymentMode.value === 'credit_card' 
                        ? document.querySelector('input[name="credit_card_type"]:checked')
                        : document.querySelector('input[name="debit_card_type"]:checked');
                    
                    if (!cardTypeInput) {
                        hasError = true;
                        alert('Please select a card type.');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (accountNumberInput) {
                        const error = validateAccountNumber(accountNumberInput.value);
                        if (error) {
                            hasError = true;
                            showFieldError(accountNumberInput, error);
                            if (!firstError) firstError = accountNumberInput;
                        }
                    }
                }
                
                // Validate mobile wallet
                if (paymentMode.value === 'mobile_wallet') {
                    var walletType = document.querySelector('input[name="mobile_wallet_type"]:checked');
                    
                    if (!walletType) {
                        hasError = true;
                        alert('Please select a mobile wallet type.');
                        e.preventDefault();
                        return false;
                    }
                    
                    // Validate wallet contact for GCash and PayMaya only
                    if ((walletType.value === 'gcash' || walletType.value === 'paymaya') && walletContactInput) {
                        const error = validateWalletContact(walletContactInput.value);
                        if (error) {
                            hasError = true;
                            showFieldError(walletContactInput, error);
                            if (!firstError) firstError = walletContactInput;
                        }
                    }
                }
                
                // If there are errors, prevent submission
                if (hasError) {
                    e.preventDefault();
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                // Allow submission if no errors
                return true;
            });
        }

        function placeAfter(el, target) {
            try {
                target.parentNode.insertBefore(el, target.nextSibling);
            } catch (e) { /* ignore */ }
        }

        function updateInstructionsVisibility() {
            var sel = document.querySelector('input[name="payment_mode"]:checked');
            if (instructions) {
                if (sel && sel.value === 'upon_parking') instructions.style.display = ''; else instructions.style.display = 'none';
            }

            // hide all wrappers first
            if (creditWrapper) creditWrapper.style.display = 'none';
            if (debitWrapper) debitWrapper.style.display = 'none';
            if (mobileWrapper) mobileWrapper.style.display = 'none';

            // place the appropriate wrapper immediately after the selected payment option
            if (sel) {
                var optEl = document.querySelector('.payment-option[data-value="' + sel.value + '"]');
                if (optEl) {
                    if (sel.value === 'credit_card' && creditWrapper) { placeAfter(creditWrapper, optEl); creditWrapper.style.display = ''; }
                    if (sel.value === 'debit_card' && debitWrapper) { placeAfter(debitWrapper, optEl); debitWrapper.style.display = ''; }
                    if (sel.value === 'mobile_wallet' && mobileWrapper) { placeAfter(mobileWrapper, optEl); mobileWrapper.style.display = ''; }
                }
            }

            // wallet contact visibility (only when mobile wrapper is shown)
            var mobileSel = document.querySelector('input[name="mobile_wallet_type"]:checked');
            if (walletContactWrap) {
                if (mobileWrapper && mobileWrapper.style.display !== 'none' && mobileSel && (mobileSel.value === 'gcash' || mobileSel.value === 'paymaya')) {
                    walletContactWrap.style.display = '';
                } else {
                    walletContactWrap.style.display = 'none';
                }
            }

            // payer name visibility - show only for card/wallet payments (not needed for cash upon parking)
            if (payerNameWrap) {
                if (sel && sel.value !== 'upon_parking') payerNameWrap.style.display = ''; else payerNameWrap.style.display = 'none';
            }

            // account number visibility: hide for cash payments, show for card payments
            if (accountNumberWrap) {
                if (sel && (sel.value === 'credit_card' || sel.value === 'debit_card')) {
                    accountNumberWrap.style.display = '';
                } else {
                    accountNumberWrap.style.display = 'none';
                }
            }

            // determine form validity for enabling submit
            var valid = false;
            if (!sel) {
                valid = false;
            } else {
                // All payment methods require payer name
                var hasPayerName = payerNameInput && payerNameInput.value.trim().length > 0;
                
                if (sel.value === 'upon_parking') {
                    // Cash upon parking: no additional fields required
                    valid = true;
                }
                else if (sel.value === 'credit_card') {
                    var hasCardType = !!document.querySelector('input[name="credit_card_type"]:checked');
                    var hasAccountNum = accountNumberInput && accountNumberInput.value.trim().length >= 10;
                    valid = hasPayerName && hasCardType && hasAccountNum;
                } 
                else if (sel.value === 'debit_card') {
                    var hasCardType = !!document.querySelector('input[name="debit_card_type"]:checked');
                    var hasAccountNum = accountNumberInput && accountNumberInput.value.trim().length >= 10;
                    valid = hasPayerName && hasCardType && hasAccountNum;
                } 
                else if (sel.value === 'mobile_wallet') {
                    var msel = document.querySelector('input[name="mobile_wallet_type"]:checked');
                    if (!msel) {
                        valid = false;
                    } else {
                        if (msel.value === 'gcash' || msel.value === 'paymaya') {
                            // GCash/PayMaya: need payer name + wallet contact
                            valid = hasPayerName && walletContactInput && walletContactInput.value.trim().length > 0;
                        } else {
                            // Other wallets: just need payer name
                            valid = hasPayerName;
                        }
                    }
                }
                else {
                    // Default: just need payer name
                    valid = hasPayerName;
                }
            }
            if (submitBtn) submitBtn.disabled = !valid;
        }

        options.forEach(function(opt){
            opt.addEventListener('click', function(){
                options.forEach(function(o){
                    o.classList.remove('selected');
                    var r = o.querySelector('input[type="radio"]'); if(r) r.checked = false;
                });
                this.classList.add('selected');
                var radio = this.querySelector('input[type="radio"]'); if (radio) radio.checked = true;
                updateInstructionsVisibility();
            });
        });

        // wallet selection handling
        walletItems.forEach(function(w){
            w.addEventListener('click', function(){
                walletItems.forEach(function(x){ x.classList.remove('selected'); var r = x.querySelector('input[type="radio"]'); if(r) r.checked = false; });
                this.classList.add('selected');
                var r = this.querySelector('input[type="radio"]'); if (r) r.checked = true;
                updateInstructionsVisibility();
            });
        });

        // card selection handling (credit/debit)
        var creditItems = document.querySelectorAll('.credit-card-wrapper .card-item');
        var debitItems = document.querySelectorAll('.debit-card-wrapper .card-item');
        creditItems.forEach(function(ci){ ci.addEventListener('click', function(){ creditItems.forEach(function(x){ x.classList.remove('selected'); var r = x.querySelector('input[type="radio"]'); if(r) r.checked = false; }); this.classList.add('selected'); var r = this.querySelector('input[type="radio"]'); if(r) r.checked = true; updateInstructionsVisibility(); }); });
        debitItems.forEach(function(di){ di.addEventListener('click', function(){ debitItems.forEach(function(x){ x.classList.remove('selected'); var r = x.querySelector('input[type="radio"]'); if(r) r.checked = false; }); this.classList.add('selected'); var r = this.querySelector('input[type="radio"]'); if(r) r.checked = true; updateInstructionsVisibility(); }); });

        // also respond to programmatic changes (keyboard/tab) on payment radios
        document.querySelectorAll('input[name="payment_mode"]').forEach(function(r){
            r.addEventListener('change', function(){
                options.forEach(function(o){ var rv = o.getAttribute('data-value'); if (rv === r.value) o.classList.add('selected'); else o.classList.remove('selected'); });
                updateInstructionsVisibility();
            });
        });

        // respond to programmatic changes on mobile wallet radios
        document.querySelectorAll('input[name="mobile_wallet_type"]').forEach(function(r){
            r.addEventListener('change', function(){
                walletItems.forEach(function(x){ var w = x.getAttribute('data-wallet'); if (w === r.value) x.classList.add('selected'); else x.classList.remove('selected'); });
                updateInstructionsVisibility();
            });
        });

        // react to wallet contact input
        if (walletContactInput) {
            walletContactInput.addEventListener('input', function(){ updateInstructionsVisibility(); });
        }
        // react to payer name input
        if (payerNameInput) {
            payerNameInput.addEventListener('input', function(){ updateInstructionsVisibility(); });
        }
        // react to account number input
        if (accountNumberInput) {
            accountNumberInput.addEventListener('input', function(){ updateInstructionsVisibility(); });
        }

        // set initial visibility on load
        document.addEventListener('DOMContentLoaded', function(){ updateInstructionsVisibility(); });
        // also run now in case DOMContentLoaded already fired
        updateInstructionsVisibility();
    })();
    </script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
// Persist booking selections in sessionStorage so Back/Forward preserves values
(function(){
    try {
        var slotKey = 'booking_slot_id';
        var dateKey = 'booking_parking_date';
        var startKey = 'booking_start_time';
        var endKey = 'booking_end_time';

        // Restore values on load for Step 1 and Step 2
        function restoreBookingSelections() {
            // Step 1: if there's a selected slot in storage, mark it
            var slotGrid = document.getElementById('slotGrid');
            var storedSlot = sessionStorage.getItem(slotKey);
            if (slotGrid && storedSlot) {
                var btn = slotGrid.querySelector('.slot-btn[data-slot-id="' + storedSlot + '"]');
                if (btn && !btn.disabled) {
                    btn.classList.add('selected');
                    var hidden = document.getElementById('selectedSlotId');
                    if (hidden) hidden.value = storedSlot;
                    var label = document.getElementById('selectedSlotLabel');
                    if (label) label.textContent = btn.getAttribute('data-slot-num') || '';
                    var box = document.getElementById('selectedSlotBox'); if (box) box.style.display = 'block';
                    var cont = document.getElementById('btnContinue'); if (cont) cont.disabled = false;
                }
            }

            // Step 2: restore date/time inputs and dispatch events so duration updates
            var form2 = document.getElementById('formStep2');
            if (form2) {
                var parkingDate = form2.querySelector('input[name="parking_date"]');
                var start = form2.querySelector('input[name="start_time"]');
                var end = form2.querySelector('input[name="end_time"]');
                if (parkingDate && !parkingDate.value) {
                    var v = sessionStorage.getItem(dateKey); if (v) parkingDate.value = v;
                }
                if (start && !start.value) {
                    var v = sessionStorage.getItem(startKey); if (v) start.value = v;
                }
                if (end && !end.value) {
                    var v = sessionStorage.getItem(endKey); if (v) end.value = v;
                }
                // trigger input/change so other scripts recalc duration
                [parkingDate, start, end].forEach(function(el){ if (!el) return; try { el.dispatchEvent(new Event('input', {bubbles:true})); el.dispatchEvent(new Event('change', {bubbles:true})); } catch(e){} });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restoreBookingSelections);
        } else {
            restoreBookingSelections();
        }

        // Save when user selects slot (Step 1)
        document.addEventListener('click', function(e) {
            var t = e.target;
            if (!t) return;
            var btn = t.closest ? t.closest('.slot-btn[data-slot-id]') : null;
            if (btn && !btn.disabled) {
                var id = btn.getAttribute('data-slot-id');
                if (id) sessionStorage.setItem(slotKey, id);
            }
        });

        // Save date/time on change in Step 2
        document.addEventListener('input', function(e) {
            var t = e.target;
            if (!t) return;
            if (t.name === 'parking_date') sessionStorage.setItem(dateKey, t.value);
            if (t.name === 'start_time') sessionStorage.setItem(startKey, t.value);
            if (t.name === 'end_time') sessionStorage.setItem(endKey, t.value);
        }, true);

        // Clear storage when booking confirmed (on confirmation redirect, show_confirmation true)
        <?php if ($show_confirmation): ?>
            try { sessionStorage.removeItem('booking_slot_id'); sessionStorage.removeItem('booking_parking_date'); sessionStorage.removeItem('booking_start_time'); sessionStorage.removeItem('booking_end_time'); } catch(e){}
        <?php endif; ?>

    } catch (err) { /* ignore storage errors */ }
})();

</script>