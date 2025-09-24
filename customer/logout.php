<?php
// customer/logout.php
require_once '../config/database-config.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// If remember token present, delete from DB
try {
    if (!empty($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        if (isset($db)) {
            $stmt = $db->prepare("DELETE FROM customer_sessions WHERE session_token = ?");
            $stmt->execute(array($token));
        }
        // Expire cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
} catch (Exception $e) {
    // Log but continue with logout
    logError('Logout (remember token) cleanup error: ' . $e->getMessage());
}

// Log activity if customer id exists
if (isset($_SESSION['customer_id'])) {
    $customerId = $_SESSION['customer_id'];
    logCustomerActivity($customerId, 'logout', 'User logged out');
}

// Clear session data
$_SESSION = array();

// Destroy session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login with a message
if (function_exists('redirectTo')) {
    $_SESSION['success_message'] = 'You have been logged out successfully.';
    redirectTo('login.php');
} else {
    header('Location: login.php');
    exit();
}

?>
