<?php
// customer/oauth/google-callback.php
require_once '../../config/database-config.php';
require_once '../../config/oauth-config.php';

try {
    // Check for errors
    if (isset($_GET['error'])) {
        throw new Exception('OAuth authorization was denied or failed: ' . $_GET['error']);
    }
    
    // Verify state parameter
    if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        throw new Exception('Invalid state parameter. Possible CSRF attack.');
    }
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('Authorization code not received');
    }
    
    // Exchange code for access token
    $tokenResponse = exchangeGoogleCodeForToken($code);
    
    if (!isset($tokenResponse['access_token'])) {
        throw new Exception('Failed to obtain access token from Google');
    }
    
    // Get user information
    $userInfo = getGoogleUserInfo($tokenResponse['access_token']);
    
    if (!isset($userInfo['email']) || !isset($userInfo['name'])) {
        throw new Exception('Failed to retrieve user information from Google');
    }
    
    // Create or update customer
    $customer = createOrUpdateOAuthCustomer('google', $userInfo, getCustomerCountry());
    
    // Clean up session
    unset($_SESSION['oauth_state']);
    
    // Set success message
    if (strpos($customer['email'], '@') !== false) {
        $_SESSION['success_message'] = "Welcome back! You've successfully logged in with Google.";
    } else {
        $_SESSION['success_message'] = "Welcome! Your account has been created and your 14-day free trial has started.";
    }
    
    // Redirect to dashboard
    redirectTo('../dashboard.php');
    
} catch (Exception $e) {
    logError("Google OAuth callback error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Google login failed: ' . $e->getMessage();
    redirectTo('../login.php');
}
?>