<?php
// customer/login.php - Fixed version
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database-config.php';
require_once '../config/oauth-config.php';

$pageTitle = 'Login - ' . SITE_NAME;
$pageDescription = 'Login to your LinkedIn automation account';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$email = '';

// Check for success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'password_reset':
            $success = 'Password reset successful! Please login with your new password.';
            break;
        case 'account_verified':
            $success = 'Account verified successfully! You can now login.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check user credentials - Fixed the query
            $stmt = $db->prepare("
                SELECT id, name, email, password, country, status, subscription_status, trial_ends_at 
                FROM customers 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute(array($email));
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && password_verify($password, $customer['password'])) {
                // Login successful
                session_regenerate_id(true); // Security best practice
                
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['customer_country'] = $customer['country'];
                $_SESSION['customer_status'] = $customer['status'];
                $_SESSION['subscription_status'] = $customer['subscription_status'];
                $_SESSION['login_time'] = time();
                
                // Handle remember me
                if ($remember) {
                    $token = generateToken(32);
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO customer_sessions (customer_id, session_token, expires_at) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute(array($customer['id'], $token, $expires));
                        
                        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
                    } catch (Exception $e) {
                        error_log("Remember me token creation failed: " . $e->getMessage());
                    }
                }
                
                // Log activity
                logCustomerActivity($customer['id'], 'login', 'User logged in successfully');
                
                // Check if trial expired
                if ($customer['subscription_status'] === 'trial' && 
                    $customer['trial_ends_at'] && 
                    strtotime($customer['trial_ends_at']) < time()) {
                    
                    $stmt = $db->prepare("UPDATE customers SET subscription_status = 'expired' WHERE id = ?");
                    $stmt->execute(array($customer['id']));
                    $_SESSION['subscription_status'] = 'expired';
                }
                
                // Redirect to intended page or dashboard
                $redirectUrl = $_GET['redirect'] ?? 'dashboard.php';
                
                if ($_SESSION['subscription_status'] === 'expired') {
                    $_SESSION['warning_message'] = 'Your free trial has expired. Please select a plan to continue.';
                    $redirectUrl = 'choose-plan.php';
                }
                
                header("Location: $redirectUrl");
                exit();
                
            } else {
                $error = 'Invalid email or password';
                error_log("Failed login attempt for email: $email from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fab fa-linkedin fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">Welcome Back</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <a href="forgot-password.php" class="text-decoration-none small">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </div>
                    </form>
                    
                    <div class="alert alert-info">
                        <h6>Test Account:</h6>
                        <small><strong>Email:</strong> mr.abhishek525@gmail.coim<br>
                        <strong>Password:</strong> (Use your actual password or create a new account)</small>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account?</p>
                        <a href="signup.php" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Form handling with proper loading state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const loginBtn = document.getElementById('loginBtn');
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    // Basic validation
    if (!email || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
        return;
    }
    
    if (!email.includes('@')) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
    }
    
    // Show loading state
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
    loginBtn.disabled = true;
});

// Auto-focus email field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('email').focus();
});
</script>

<?php require_once '../includes/footer.php'; ?>