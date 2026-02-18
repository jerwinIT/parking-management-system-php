# Endpoints Reference

This file documents the main HTTP endpoints in the Parking Management System, their methods, parameters, and notes.

| Path | Method | Params (query / body) | Notes |
|---|---:|---|---|
| /index.php | GET | — | Dashboard (Total Slots, Currently Parked, Active Reservations) |
| /landing.php | GET | — | Public landing page |
| /parking-map.php | GET | date (optional) | Parking map view; uses helpers to compute occupancy |
| /auth/register.php | GET / POST | POST: name, email, password, password_confirm | User registration; sends verification email |
| /auth/login.php | GET / POST | POST: email, password | Login endpoint; creates session |
| /auth/logout.php | GET | — | Destroys session and redirects |
| /auth/verify.php | GET | token | Email verification link handler |
| /auth/resend_verification.php | POST | email | Resend verification email |
| /user/book.php | GET / POST | GET: slot_id, date; POST: vehicle_id, slot_id, date, start_time, end_time, save_booking | Booking UI and form handler. Also supports AJAX: `?action=slot_bookings&slot_id={id}&date={YYYY-MM-DD}` returns JSON reserved time ranges for a slot. Server enforces overlap checks. |
| /user/booking-details.php | GET | id | View booking details |
| /user/booking-history.php | GET | — | List of user's bookings |
| /user/cancel-booking.php | POST / GET | id | Cancel a booking (user) |
| /user/end-parking.php | POST | booking_id | End an active parked booking (checkout) |
| /user/register-car.php | GET / POST | POST: save_vehicle=1, vehicle_id (optional), plate_number, vehicle_type, owner_name, owner_phone, owner_email, model, year, color | Manage vehicles (list/add/edit). Server-side guard prevents plate changes when vehicle has active/parked bookings. Add and edit are handled by the same page; edit uses `vehicle_id`. |
| /user/delete-car.php | GET | id | Remove a vehicle (confirm) |
| /user/profile.php | GET / POST | POST: name, email, password (optional) | Account settings; validation mirrors registration rules |
| /user/receipt.php | GET | id | View/print receipt for booking/payment |
| /admin/monitor.php | GET | — | Real-time admin monitor view |
| /admin/slots.php | GET / POST | POST: slot actions (create/update) | Manage parking slots |
| /admin/exit-vehicle.php | POST | booking_id | Admin force-end a parked booking |
| /admin/parking-history.php | GET | — | Admin booking history view |
| /admin/settings.php | GET / POST | Various settings form fields | Admin configuration page |
| /admin/view-receipt.php | GET | id | Admin view of receipts/payments |
| /scripts/auto_start_bookings.php | CLI | --dry-run (optional) | CLI script to transition bookings with planned_entry_time <= NOW() to `parked` and set `entry_time`. Intended to run via cron/task scheduler every minute. |
| /scripts/auto_end_bookings.php | CLI | --dry-run (optional) | CLI script to end bookings based on exit_time/status. |

## JSON / AJAX endpoints (summary)
- `/user/book.php?action=slot_bookings&slot_id={id}&date={YYYY-MM-DD}`
  - Method: GET
  - Response: JSON array of { start: "YYYY-MM-DD HH:MM:SS", end: "YYYY-MM-DD HH:MM:SS" }
  - Used by the booking UI to render reserved times and prevent conflicts.

## Notes & Conventions
- Most pages are server-rendered and accept form POSTs rather than a separate REST API.
- Booking status values used across endpoints: `pending`, `confirmed`, `parked`.
- Use prepared statements (PDO) for all DB access; endpoint handlers validate inputs server-side.
- Scheduler scripts are CLI PHP and should be executed with the system PHP binary (e.g., `php scripts/auto_start_bookings.php`).

If you want, I can expand this file into a machine-readable OpenAPI/Swagger spec or add example requests/responses for the key JSON endpoints.
