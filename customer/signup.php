<?php
// customer/signup.php
require_once '../config/database-config.php';
require_once '../config/oauth-config.php';

$pageTitle = 'Sign Up - ' . SITE_NAME;
$pageDescription = 'Create your LinkedIn automation account';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectTo('dashboard.php');
}

$error = '';
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'country' => sanitizeInput($_POST['country'] ?? DEFAULT_COUNTRY),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'terms' => isset($_POST['terms'])
    ];
    
    // Validation
    if (empty($formData['name']) || empty($formData['email']) || empty($formData['password'])) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($formData['password']) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $error = 'Passwords do not match';
    } elseif (!$formData['terms']) {
        $error = 'Please accept the Terms of Service';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$formData['email']]);
            
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists';
            } else {
                // Create new customer
                $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
                $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . TRIAL_PERIOD_DAYS . ' days'));
                
                $stmt = $db->prepare("
                    INSERT INTO customers (
                        name, email, password, country, phone, 
                        subscription_status, trial_ends_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'trial', ?, NOW())
                ");
                
                $result = $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hashedPassword,
                    $formData['country'],
                    $formData['phone'],
                    $trialEndsAt
                ]);
                
                if ($result) {
                    $customerId = $db->lastInsertId();
                    
                    // Log activity
                    logCustomerActivity($customerId, 'account_created', 'New account created');
                    
                    // Auto-login the user
                    $_SESSION['customer_id'] = $customerId;
                    $_SESSION['customer_name'] = $formData['name'];
                    $_SESSION['customer_email'] = $formData['email'];
                    $_SESSION['customer_country'] = $formData['country'];
                    $_SESSION['customer_status'] = 'active';
                    $_SESSION['subscription_status'] = 'trial';
                    
                    $_SESSION['success_message'] = 'Welcome! Your account has been created successfully.';
                    
                    // Redirect to plan selection instead of dashboard
                    redirectTo('choose-plan.php');
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
            
        } catch (Exception $e) {
            logError("Signup error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
    <div class="container py-3">
        <div class="alert alert-warning">
            <h6>OAuth Debug Info</h6>
            <p><strong>LinkedIn Redirect URI:</strong> <?php echo htmlspecialchars(LINKEDIN_REDIRECT_URI); ?></p>
            <p><strong>LinkedIn Login URL:</strong> <small><?php echo htmlspecialchars(getLinkedInLoginUrl()); ?></small></p>
            <p><strong>Google Redirect URI:</strong> <?php echo htmlspecialchars(GOOGLE_REDIRECT_URI); ?></p>
            <p><strong>Google Login URL:</strong> <small><?php echo htmlspecialchars(getGoogleLoginUrl()); ?></small></p>
            <p class="mb-0">Copy the exact Redirect URI (including scheme, host and path) into your provider's app settings.</p>
        </div>
    </div>
<?php endif; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fab fa-linkedin fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">Create Your Account</h2>
                        <p class="text-muted">Start your 14-day free trial today</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="signupForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" 
                                               placeholder="John Doe" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                                               placeholder="john@example.com" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Minimum 8 characters" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', 'toggleIcon1')">
                                            <i class="fas fa-eye" id="toggleIcon1"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <small id="passwordStrength" class="text-muted">Password strength: </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirm your password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                            <i class="fas fa-eye" id="toggleIcon2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="country" class="form-label">Country *</label>
                                    <select class="form-select" id="country" name="country" required>
                                        <option value="us" <?php echo (($formData['country'] ?? getCustomerCountry()) === 'us') ? 'selected' : ''; ?>>🇺🇸 United States</option>
                                        <option value="in" <?php echo (($formData['country'] ?? getCustomerCountry()) === 'in') ? 'selected' : ''; ?>>🇮🇳 India</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" 
                                               placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" target="_blank">Terms of Service</a> and 
                                <a href="#" target="_blank">Privacy Policy</a> *
                            </label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter">
                            <label class="form-check-label" for="newsletter">
                                Subscribe to our newsletter for tips and updates
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" onclick="showLoading(this)">
                                <i class="fas fa-rocket me-2"></i>Start Free Trial
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                14-day free trial • No credit card required • Cancel anytime
                            </small>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <!-- OAuth Signup Options -->
                    <div class="text-center mb-3">
                        <p class="text-muted mb-3">Or sign up with:</p>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo getGoogleLoginUrl(); ?>" class="btn btn-outline-danger btn-lg">
                                <i class="fab fa-google me-2"></i>Sign up with Google
                            </a>
                            
                            <a href="<?php echo getLinkedInLoginUrl(); ?>" class="btn btn-outline-primary btn-lg">
                                <i class="fab fa-linkedin me-2"></i>Sign up with LinkedIn
                            </a>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account?</p>
                        <a href="login.php" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthText = document.getElementById('passwordStrength');
    
    if (password.length === 0) {
        strengthText.textContent = 'Password strength: ';
        strengthText.className = 'text-muted';
        return;
    }
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    if (strength <= 2) {
        strengthText.textContent = 'Password strength: Weak';
        strengthText.className = 'text-danger';
    } else if (strength <= 3) {
        strengthText.textContent = 'Password strength: Medium';
        strengthText.className = 'text-warning';
    } else {
        strengthText.textContent = 'Password strength: Strong';
        strengthText.className = 'text-success';
    }
});

// Form validation
document.getElementById('signupForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;
    
    if (name.length < 2) {
        e.preventDefault();
        alert('Please enter your full name');
        return;
    }
    
    if (!validateEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return;
    }
    
    if (!terms) {
        e.preventDefault();
        alert('Please accept the Terms of Service');
        return;
    }
});

// Email availability check (optional)
let emailCheckTimeout;
document.getElementById('email').addEventListener('input', function() {
    const email = this.value.trim();
    const emailGroup = this.closest('.input-group');
    
    // Clear previous timeout
    clearTimeout(emailCheckTimeout);
    
    // Remove previous feedback
    const existingFeedback = emailGroup.parentNode.querySelector('.email-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    if (email && validateEmail(email)) {
        emailCheckTimeout = setTimeout(() => {
            // Here you could add AJAX call to check email availability
            // For now, we'll just validate format
            const feedback = document.createElement('div');
            feedback.className = 'email-feedback small text-success mt-1';
            feedback.innerHTML = '<i class="fas fa-check me-1"></i>Email format is valid';
            emailGroup.parentNode.appendChild(feedback);
        }, 1000);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>