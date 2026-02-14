-- ============================================================
-- PARKING MANAGEMENT SYSTEM - DATABASE SCHEMA
-- For XAMPP MySQL - Run this file in phpMyAdmin or MySQL
-- ============================================================

-- Create database
CREATE DATABASE IF NOT EXISTS parking_management_db;
USE parking_management_db;

-- ============================================================
-- TABLE: roles
-- Stores user roles (Admin, User/Driver)
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: users
-- Stores user accounts (admin and drivers)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL DEFAULT 2,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: parking_settings
-- Global settings: total slots, opening/closing time
-- ============================================================
CREATE TABLE IF NOT EXISTS parking_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_slots INT NOT NULL DEFAULT 20,
    opening_time TIME NOT NULL DEFAULT '06:00:00',
    closing_time TIME NOT NULL DEFAULT '22:00:00',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: parking_slots
-- Individual parking slots (Available / Occupied)
-- ============================================================
CREATE TABLE IF NOT EXISTS parking_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slot_number VARCHAR(10) NOT NULL UNIQUE,
    slot_row INT NOT NULL,
    slot_column INT NOT NULL,
    status ENUM('available', 'occupied') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: vehicles
-- Cars registered by users (plate, model, color)
-- ============================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    model VARCHAR(100),
    color VARCHAR(50),
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_plate (plate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: bookings
-- Parking slot bookings (Pending, Parked, Completed)
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    parking_slot_id INT NOT NULL,
    status ENUM('pending', 'parked', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    booked_at DATETIME NOT NULL,
    entry_time DATETIME NULL,
    exit_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (parking_slot_id) REFERENCES parking_slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: payments
-- Payment records linked to bookings
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'refunded') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50),
    account_number VARCHAR(255) NULL,
    payment_subtype VARCHAR(100) NULL,
    wallet_contact VARCHAR(255) NULL,
    payer_name VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INSERT DEFAULT DATA
-- ============================================================

-- Roles
INSERT INTO roles (role_name, description) VALUES
('admin', 'System administrator - full access'),
('user', 'Driver / User - can book parking slots');

-- Parking settings (single row)
INSERT INTO parking_settings (total_slots, opening_time, closing_time) VALUES
(20, '06:00:00', '22:00:00');

-- Create 20 parking slots (4 rows x 5 columns)
INSERT INTO parking_slots (slot_number, slot_row, slot_column, status) VALUES
('A1', 1, 1, 'available'), ('A2', 1, 2, 'available'), ('A3', 1, 3, 'available'), ('A4', 1, 4, 'available'), ('A5', 1, 5, 'available'),
('B1', 2, 1, 'available'), ('B2', 2, 2, 'available'), ('B3', 2, 3, 'available'), ('B4', 2, 4, 'available'), ('B5', 2, 5, 'available'),
('C1', 3, 1, 'available'), ('C2', 3, 2, 'available'), ('C3', 3, 3, 'available'), ('C4', 3, 4, 'available'), ('C5', 3, 5, 'available'),
('D1', 4, 1, 'available'), ('D2', 4, 2, 'available'), ('D3', 4, 3, 'available'), ('D4', 4, 4, 'available'), ('D5', 4, 5, 'available');

-- Admin user (password: password)
-- Sample user (password: password)
-- Hash below = password_hash('password', PASSWORD_DEFAULT)
INSERT INTO users (role_id, username, email, password, full_name, phone) VALUES
(1, 'admin', 'admin@parking.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', '09171234567'),
(2, 'driver1', 'driver1@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', '09181234567');

-- Sample vehicle for driver1
INSERT INTO vehicles (user_id, plate_number, model, color) VALUES
(2, 'ABC-1234', 'Toyota Vios', 'White');

-- Sample booking (completed)
INSERT INTO bookings (user_id, vehicle_id, parking_slot_id, status, booked_at, entry_time, exit_time) VALUES
(2, 1, 1, 'completed', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY + INTERVAL 3 HOUR);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
