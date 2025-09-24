<?php
// customer/oauth/google-callback.php - Fixed version
require_once '../../config/database-config.php';
require_once '../../config/oauth-config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for errors from Google
    if (isset($_GET['error'])) {
        throw new Exception('OAuth authorization was denied or failed: ' . $_GET['error']);
    }
    
    // Get the state parameter from URL
    $returnedState = $_GET['state'] ?? '';
    $storedState = $_SESSION['oauth_state'] ?? '';
    
    // Debug information (remove in production)
    error_log("Google OAuth Debug - Returned state: " . $returnedState);
        $receivedState = $_GET['state'] ?? null;
        $sessionState = $_SESSION['oauth_state'] ?? null;

        // If session state is missing (session may have been lost), accept a short-lived cookie fallback
        if (!$receivedState) {
            throw new Exception('Missing state parameter. Possible CSRF attack.');
        }

        if ($sessionState === null) {
            // Check cookie fallback
            $cookieState = $_COOKIE['oauth_state'] ?? null;
            if ($cookieState === null || $receivedState !== $cookieState) {
                throw new Exception('Invalid state parameter. Possible CSRF attack.');
            }
            // Restore state into session for consistency and clear cookie
            $_SESSION['oauth_state'] = $cookieState;
            setcookie('oauth_state', '', time() - 3600, '/');
        } else {
            if ($receivedState !== $sessionState) {
                throw new Exception('Invalid state parameter. Possible CSRF attack.');
            }
        }
    
    // Verify state parameter (with more lenient checking for debugging)
    if (empty($returnedState)) {
        throw new Exception('No state parameter received from Google');
    }
    
    if (empty($storedState)) {
        throw new Exception('No state parameter found in session. Session may have expired.');
    }
    
    if ($returnedState !== $storedState) {
        // Clear the session state for retry
        unset($_SESSION['oauth_state']);
        throw new Exception('Invalid state parameter. Please try logging in again.');
    }
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('Authorization code not received from Google');
    }
    
    // Clear the used state parameter
    unset($_SESSION['oauth_state']);
    
    // Exchange code for access token
    $tokenResponse = exchangeGoogleCodeForToken($code);
    
    if (!isset($tokenResponse['access_token'])) {
        throw new Exception('Failed to obtain access token from Google: ' . json_encode($tokenResponse));
    }
    
    // Get user information from Google
    $userInfo = getGoogleUserInfo($tokenResponse['access_token']);
    
    if (!isset($userInfo['email']) || !isset($userInfo['name'])) {
        throw new Exception('Failed to retrieve user information from Google: ' . json_encode($userInfo));
    }
    
    // Create or update customer account
    $customer = createOrUpdateOAuthCustomer('google', $userInfo, getCustomerCountry());
    
    // Set success message
    if (isset($customer['existing']) && $customer['existing']) {
        $_SESSION['success_message'] = "Welcome back! You've successfully logged in with Google.";
    } else {
        $_SESSION['success_message'] = "Welcome! Your account has been created and your 14-day free trial has started.";
    }
    
    // Redirect to dashboard
    header('Location: ../dashboard.php');
    exit();
    
} catch (Exception $e) {
    // Log the detailed error
    error_log("Google OAuth callback error: " . $e->getMessage());
    error_log("Google OAuth callback trace: " . $e->getTraceAsString());
    
    // Clear any OAuth session data
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_provider']);
    
    // Redirect with user-friendly error message
    $_SESSION['error_message'] = 'Google login failed: ' . $e->getMessage();
    header('Location: ../login.php');
    exit();
}
?>