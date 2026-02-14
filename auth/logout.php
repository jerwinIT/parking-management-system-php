<?php
/**
 * Logout - Destroy session and redirect to login
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
