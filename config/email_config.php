<?php
// Optional email configuration for the Parking Management System.
// Copy this file to set SMTP credentials for development or production.
// Uncomment and edit the blocks below with your SMTP provider details.

// -----------------------------
// Example: Mailtrap (development)
// -----------------------------
/*
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-mailtrap-username');
define('SMTP_PASS', 'your-mailtrap-password');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl' or null
define('MAIL_FROM', 'no-reply@yourdomain.com');
*/

// -----------------------------
// Example: Gmail (less recommended)
// -----------------------------
/*
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
define('MAIL_FROM', 'your-email@gmail.com');
*/

// If you prefer not to configure SMTP here, leave this file as-is.
// Toggle email verification requirement for new accounts.
// Set to false to allow immediate login after registration (no verification required).
if (!defined('REQUIRE_EMAIL_VERIFICATION')) {
	define('REQUIRE_EMAIL_VERIFICATION', false);
}

// Returning early prevents accidental execution if this file is included directly.
return;

?>