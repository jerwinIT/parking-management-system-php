# Parking Management System

A complete **Parking Management System** for academic / OJT projects. Manages parking slots, vehicles, bookings, monitoring, and payments with a clean, beginner-friendly interface.

---

## Technology Stack

| Layer      | Technology   |
|-----------|--------------|
| Backend   | PHP          |
| Database  | MySQL        |
| Frontend  | HTML, CSS, JavaScript |
| UI        | Bootstrap 5  |

---

## Folder Structure

```
PARKING_MANAGEMENT_SYSTEM/
├── config/
│   ├── database.php    # MySQL connection (PDO)
│   └── init.php        # Session, constants, auth helpers
├── auth/
│   ├── login.php       # Login page
│   ├── register.php    # User registration
│   └── logout.php      # Logout
├── includes/
│   ├── header.php      # Common layout + sidebar
│   └── footer.php      # Closing HTML + scripts
├── user/
│   ├── register-car.php   # Register car (plate, model, color)
│   ├── book.php           # Book a parking slot
│   └── booking-history.php # Booking history with status
├── admin/
│   ├── slots.php       # Manage total parking slots
│   ├── settings.php    # Opening & closing time
│   ├── monitor.php     # Monitor vehicles inside
│   └── exit-vehicle.php # Mark vehicle exited (free slot)
├── database/
│   └── parking_system.sql  # Full DB schema + sample data
├── index.php           # Dashboard (role-based)
├── parking-map.php     # Visual parking map
└── README.md
```

---

## Setup (XAMPP)

### 1. Start XAMPP

- Start **Apache** and **MySQL** from XAMPP Control Panel.

### 2. Create Database

- Open **phpMyAdmin**: http://localhost/phpmyadmin  
- Click **Import** → Choose file: `database/parking_system.sql`  
- Click **Go** to create the database and tables.

**Or** run in MySQL:

```bash
mysql -u root -p < database/parking_system.sql
```

(Default XAMPP password for root is empty.)

### 3. Configure Database (if needed)

Edit `config/database.php` if your MySQL user/password differ:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Run the Website

- Place the project in `htdocs`: `C:\xampp\htdocs\PARKING_MANAGEMENT_SYSTEM`
- Open: **http://localhost/PARKING_MANAGEMENT_SYSTEM**

---

## Sample Logins (Testing)

| Role   | Username | Password  |
|--------|----------|-----------|
| Admin  | `admin`  | `password` |
| User   | `driver1`| `password` |

After login:

- **Admin**: Dashboard, Manage Slots, Opening/Closing Time, Monitor Vehicles, Parking Map.
- **User**: Dashboard, Register Car, Book Slot, Booking History, Parking Map.

---

## Database Tables

| Table             | Purpose                          |
|-------------------|----------------------------------|
| `roles`           | admin, user                      |
| `users`           | Accounts (admin & drivers)       |
| `parking_settings`| Total slots, opening/closing time|
| `parking_slots`   | Each slot (available/occupied)   |
| `vehicles`        | Cars (plate, model, color)      |
| `bookings`        | Bookings (pending, parked, completed) |
| `payments`        | Payment record per booking       |

---

## Core Features

- **Login & session-based authentication**
- **Role-based access**: Admin vs User/Driver
- **Dashboard widgets**: Available, Occupied, Total slots (role-based content)
- **User**: Register car, book slot, view booking history (Pending / Parked / Completed)
- **Admin**: Set total slots, opening/closing time, monitor vehicles, mark exit (frees slot)
- **Parking map**: Visual grid of slots with status and vehicle info; refresh to update
- **No double booking**: Only available slots can be selected; slot status updated in transaction
- **Parking duration**: Calculated from entry/exit and shown in history and monitor

- **Dashboard widgets**: Total Slots, Currently Parked (real-time), Active Reservations (upcoming)
  
---

## Time-based bookings & scheduler

- The system now supports multiple time-based reservations per slot (same day). Slot occupancy is derived from booking time windows (`planned_entry_time` / `exit_time`) rather than a single-day lock on the slot.
- Helper functions live in `config/booking_helpers.php` and are included automatically via `config/init.php`.
- Two scheduler scripts are provided in `scripts/`:
	- `auto_start_bookings.php`: transitions bookings with `planned_entry_time <= NOW()` from `pending|confirmed` to `parked` and sets `entry_time = NOW()`. Run every minute (cron or Task Scheduler). Supports `--dry-run` to preview actions.
	- `auto_end_bookings.php`: existing script that auto-ends parked bookings based on their planned duration; keep running on a schedule as your environment requires.
- Dashboard metric definitions:
	- **Total Slots**: COUNT of `parking_slots`
	- **Currently Parked**: COUNT of `bookings` where `status = 'parked'`
	- **Active Reservations**: COUNT of `bookings` where `status IN ('pending','confirmed')` (upcoming reservations)

---

---

## Default Parking Hours

- Opening: **6:00 AM**  
- Closing: **10:00 PM**  

Change them in **Admin → Opening/Closing Time**.

---

## Notes for Beginners

- All PHP files that need auth include `config/init.php` and call `requireLogin()` or `requireAdmin()`.
- Sidebar is built in `includes/header.php` and changes by role (admin vs user).
- Passwords are hashed with `password_hash()` and checked with `password_verify()`.
- Slots are updated automatically when a user books (→ occupied) and when admin marks exit (→ available).

---

## License

For academic / OJT use only.

#BSU ipv4

