<?php
// customer/logout.php
require_once '../config/database-config.php';

// Log the logout activity if user is logged in
if (isCustomerLoggedIn()) {
    try {
        // Remove remember me token if exists
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $db->prepare("DELETE FROM customer_sessions WHERE session_token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
            
            // Clear the cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Log logout activity
        logCustomerActivity($_SESSION['customer_id'], 'logout', 'User logged out');
        
    } catch (Exception $e) {
        error_log("Logout cleanup error: " . $e->getMessage());
    }
}

// Destroy session
session_start();
session_unset();
session_destroy();

// Clear any session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login with logout message
header('Location: login.php?message=logged_out');
exit();
?>

<?php
// customer/check-session.php - AJAX endpoint to check session status
require_once '../config/database-config.php';

header('Content-Type: application/json');

// Check if customer is logged in
if (!isCustomerLoggedIn()) {
    echo json_encode(['logged_in' => false, 'redirect' => 'login.php']);
    exit();
}

// Check if trial has expired
try {
    $stmt = $db->prepare("
        SELECT subscription_status, trial_ends_at 
        FROM customers 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Customer not found or inactive
        session_unset();
        session_destroy();
        echo json_encode(['logged_in' => false, 'redirect' => 'login.php']);
        exit();
    }
    
    // Check if trial expired
    $trialExpired = false;
    if ($customer['subscription_status'] === 'trial' && 
        $customer['trial_ends_at'] && 
        strtotime($customer['trial_ends_at']) < time()) {
        
        // Update status to expired
        $stmt = $db->prepare("UPDATE customers SET subscription_status = 'expired' WHERE id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        
        $_SESSION['subscription_status'] = 'expired';
        $trialExpired = true;
    }
    
    echo json_encode([
        'logged_in' => true,
        'trial_expired' => $trialExpired,
        'subscription_status' => $customer['subscription_status'],
        'user_name' => $_SESSION['customer_name'] ?? 'User'
    ]);
    
} catch (Exception $e) {
    error_log("Session check error: " . $e->getMessage());
    echo json_encode(['logged_in' => true, 'error' => 'Unable to verify session']);
}
?>

<?php
// customer/remember-login.php - Handle remember me functionality
require_once '../config/database-config.php';

// Check for remember me cookie on login pages
if (!isCustomerLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $token = $_COOKIE['remember_token'];
        
        // Get customer from remember token
        $stmt = $db->prepare("
            SELECT cs.customer_id, c.name, c.email, c.country, c.status, c.subscription_status
            FROM customer_sessions cs
            JOIN customers c ON cs.customer_id = c.id
            WHERE cs.session_token = ? AND cs.expires_at > NOW() AND c.status = 'active'
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Auto-login user
            session_regenerate_id(true);
            
            $_SESSION['customer_id'] = $result['customer_id'];
            $_SESSION['customer_name'] = $result['name'];
            $_SESSION['customer_email'] = $result['email'];
            $_SESSION['customer_country'] = $result['country'];
            $_SESSION['customer_status'] = $result['status'];
            $_SESSION['subscription_status'] = $result['subscription_status'];
            $_SESSION['login_time'] = time();
            
            // Log auto-login activity
            logCustomerActivity($result['customer_id'], 'auto_login', 'User auto-logged in via remember token');
            
            // Extend remember token
            $newExpires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $db->prepare("UPDATE customer_sessions SET expires_at = ? WHERE session_token = ?");
            $stmt->execute([$newExpires, $token]);
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            // Invalid or expired token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
    } catch (Exception $e) {
        error_log("Remember login error: " . $e->getMessage());
        // Clear invalid cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}
?>

<?php 
// Enhanced database-config.php functions - Add these to your existing file

// Enhanced session check function
function isCustomerLoggedIn() {
    if (!isset($_SESSION['customer_id']) || !isset($_SESSION['customer_email'])) {
        return false;
    }
    
    // Check session timeout (optional)
    if (defined('SESSION_TIMEOUT') && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            return false;
        }
    }
    
    return true;
}

// Enhanced customer login requirement
function requireCustomerLogin($redirectUrl = null) {
    if (!isCustomerLoggedIn()) {
        $redirect = $redirectUrl ?: $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit();
    }
}

// Clean expired sessions (call this periodically)
function cleanExpiredSessions() {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM customer_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        
        error_log("Cleaned " . $stmt->rowCount() . " expired sessions");
        
    } catch (Exception $e) {
        error_log("Clean expired sessions error: " . $e->getMessage());
    }
}

// Get customer details
function getCustomerDetails($customerId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT id, name, email, country, status, subscription_status, 
                   trial_ends_at, created_at, updated_at
            FROM customers 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Get customer details error: " . $e->getMessage());
        return false;
    }
}

// Update last activity
function updateLastActivity($customerId) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE customers SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$customerId]);
        
    } catch (Exception $e) {
        error_log("Update last activity error: " . $e->getMessage());
    }
}

// Check if customer has specific permission
function hasPermission($permission) {
    if (!isCustomerLoggedIn()) {
        return false;
    }
    
    // For now, all active customers have basic permissions
    // You can extend this for role-based permissions
    $subscriptionStatus = $_SESSION['subscription_status'] ?? 'trial';
    
    switch ($permission) {
        case 'create_automation':
            return in_array($subscriptionStatus, ['trial', 'active']);
        case 'unlimited_posts':
            return $subscriptionStatus === 'active';
        case 'analytics':
            return in_array($subscriptionStatus, ['trial', 'active']);
        default:
            return true;
    }
}

// Enhanced error logging with context
function logError($message, $context = [], $file = 'error.log') {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] $message";
    
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }
    
    if (isCustomerLoggedIn()) {
        $logMessage .= " | Customer: {$_SESSION['customer_id']} ({$_SESSION['customer_email']})";
    }
    
    $logMessage .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logMessage .= PHP_EOL;
    
    $logPath = __DIR__ . '/../logs/' . $file;
    
    if (!is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
}
?>