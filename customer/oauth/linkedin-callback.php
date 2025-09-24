<?php
// customer/oauth/linkedin-callback.php
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
    $tokenResponse = exchangeLinkedInCodeForToken($code);
    
    if (!isset($tokenResponse['access_token'])) {
        throw new Exception('Failed to obtain access token from LinkedIn');
    }
    
    // Get user information
    $userInfo = getLinkedInUserInfo($tokenResponse['access_token']);
    
    // Validate user info
    if (!isset($userInfo['email']['elements'][0]['handle~']['emailAddress']) || 
        !isset($userInfo['profile']['firstName']['localized'])) {
        throw new Exception('Failed to retrieve user information from LinkedIn');
    }
    
    // Create or update customer
    $customer = createOrUpdateOAuthCustomer('linkedin', $userInfo, getCustomerCountry());
    
    // Store LinkedIn access token for future LinkedIn API usage
    try {
        $stmt = $db->prepare("
            INSERT INTO customer_linkedin_tokens (customer_id, access_token, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            access_token = VALUES(access_token), 
            updated_at = NOW()
        ");
        $stmt->execute([$customer['id'], $tokenResponse['access_token']]);
    } catch (Exception $e) {
        // Don't fail the login if we can't store the token
        logError("Failed to store LinkedIn token: " . $e->getMessage());
    }
    
    // Clean up session
    unset($_SESSION['oauth_state']);
    
    // Set success message
    if (isset($customer['existing'])) {
        $_SESSION['success_message'] = "Welcome back! You've successfully logged in with LinkedIn.";
    } else {
        $_SESSION['success_message'] = "Welcome! Your account has been created and your LinkedIn is connected. Your 14-day free trial has started.";
    }
    
    // Redirect to dashboard
    redirectTo('../dashboard.php');
    
} catch (Exception $e) {
    logError("LinkedIn OAuth callback error: " . $e->getMessage());
    $_SESSION['error_message'] = 'LinkedIn login failed: ' . $e->getMessage();
    redirectTo('../login.php');
}
?>