<?php
// admin/api-settings.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Get current API settings
$settings = [];
try {
    $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch() ?: [];
} catch (Exception $e) {
    error_log("Get API settings error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
    $chatgptApiKey = trim($_POST['chatgpt_api_key'] ?? '');
    $linkedinClientId = trim($_POST['linkedin_client_id'] ?? '');
    $linkedinClientSecret = trim($_POST['linkedin_client_secret'] ?? '');
    $razorpayKeyId = trim($_POST['razorpay_key_id'] ?? '');
    $razorpayKeySecret = trim($_POST['razorpay_key_secret'] ?? '');
    
    try {
        // Update or insert API settings
        $stmt = $db->prepare("
            INSERT INTO api_settings (
                id, gemini_api_key, chatgpt_api_key, linkedin_client_id, 
                linkedin_client_secret, razorpay_key_id, razorpay_key_secret, updated_at
            ) VALUES (1, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                gemini_api_key = VALUES(gemini_api_key),
                chatgpt_api_key = VALUES(chatgpt_api_key),
                linkedin_client_id = VALUES(linkedin_client_id),
                linkedin_client_secret = VALUES(linkedin_client_secret),
                razorpay_key_id = VALUES(razorpay_key_id),
                razorpay_key_secret = VALUES(razorpay_key_secret),
                updated_at = NOW()
        ");
        
        $success = $stmt->execute([
            $geminiApiKey, $chatgptApiKey, $linkedinClientId,
            $linkedinClientSecret, $razorpayKeyId, $razorpayKeySecret
        ]);
        
        if ($success) {
            $message = 'API settings updated successfully!';
            
            // Refresh settings
            $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->fetch() ?: [];
        } else {
            $error = 'Failed to update API settings';
        }
    } catch (Exception $e) {
        error_log("Update API settings error: " . $e->getMessage());
        $error = 'An error occurred while updating settings';
    }
}

// Test API connection functions
function testGeminiAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    
    $data = [
        'contents' => [
            ['parts' => [['text' => 'Test connection. Reply with "OK"']]]
        ]
    ];
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return ['status' => true, 'message' => 'Connected successfully'];
        }
    }
    
    return ['status' => false, 'message' => 'Connection failed: ' . $httpCode];
}

function testChatGPTAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Test connection. Reply with "OK"']
        ],
        'max_tokens' => 10
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return ['status' => true, 'message' => 'Connected successfully'];
        }
    }
    
    return ['status' => false, 'message' => 'Connection failed: ' . $httpCode];
}

// Handle AJAX test requests
if (isset($_POST['test_api'])) {
    $provider = $_POST['test_api'];
    $apiKey = $_POST['api_key'] ?? '';
    
    if ($provider === 'gemini') {
        $result = testGeminiAPI($apiKey);
    } elseif ($provider === 'chatgpt') {
        $result = testChatGPTAPI($apiKey);
    } else {
        $result = ['status' => false, 'message' => 'Unknown provider'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 20px;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.1);
        }
        .api-card {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .api-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 10px;
        }
        .status-success { background-color: #28a745; }
        .status-error { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white fw-bold">
                            <i class="fas fa-shield-alt me-2"></i>Admin Panel
                        </h5>
                        <small class="text-white-50">LinkedIn Automation</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="automations.php">
                                <i class="fas fa-robot me-2"></i>Automations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="api-settings.php">
                                <i class="fas fa-cog me-2"></i>API Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="posts.php">
                                <i class="fas fa-newspaper me-2"></i>Generated Posts
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">API Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <small class="text-muted">Configure your API keys and external services</small>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <!-- AI APIs -->
                        <div class="col-lg-6">
                            <div class="card api-card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-brain me-2"></i>AI APIs
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            Google Gemini API Key
                                            <span class="status-indicator status-unknown" id="gemini-status"></span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" name="gemini_api_key" class="form-control" 
                                                   value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>"
                                                   placeholder="Enter your Gemini API key" id="gemini-key">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('gemini-key')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" type="button" onclick="testAPI('gemini')">
                                                <i class="fas fa-plug"></i> Test
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            OpenAI (ChatGPT) API Key
                                            <span class="status-indicator status-unknown" id="chatgpt-status"></span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" name="chatgpt_api_key" class="form-control" 
                                                   value="<?php echo htmlspecialchars($settings['chatgpt_api_key'] ?? ''); ?>"
                                                   placeholder="Enter your OpenAI API key" id="chatgpt-key">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('chatgpt-key')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" type="button" onclick="testAPI('chatgpt')">
                                                <i class="fas fa-plug"></i> Test
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Social Media APIs -->
                        <div class="col-lg-6">
                            <div class="card api-card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fab fa-linkedin me-2"></i>LinkedIn API
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">LinkedIn Client ID</label>
                                        <input type="text" name="linkedin_client_id" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['linkedin_client_id'] ?? ''); ?>"
                                               placeholder="Enter LinkedIn Client ID">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">LinkedIn Client Secret</label>
                                        <div class="input-group">
                                            <input type="password" name="linkedin_client_secret" class="form-control" 
                                                   value="<?php echo htmlspecialchars($settings['linkedin_client_secret'] ?? ''); ?>"
                                                   placeholder="Enter LinkedIn Client Secret" id="linkedin-secret">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('linkedin-secret')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Create an app at <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Payment Gateway -->
                        <div class="col-lg-6">
                            <div class="card api-card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-credit-card me-2"></i>Payment Gateway
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Razorpay Key ID</label>
                                        <input type="text" name="razorpay_key_id" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['razorpay_key_id'] ?? ''); ?>"
                                               placeholder="Enter Razorpay Key ID">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Razorpay Key Secret</label>
                                        <div class="input-group">
                                            <input type="password" name="razorpay_key_secret" class="form-control" 
                                                   value="<?php echo htmlspecialchars($settings['razorpay_key_secret'] ?? ''); ?>"
                                                   placeholder="Enter Razorpay Key Secret" id="razorpay-secret">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('razorpay-secret')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Get your keys from <a href="https://dashboard.razorpay.com/app/keys" target="_blank">Razorpay Dashboard</a>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Status -->
                        <div class="col-lg-6">
                            <div class="card api-card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fas fa-server me-2"></i>System Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6 mb-3">
                                            <div class="border rounded p-3">
                                                <i class="fas fa-database fa-2x text-primary mb-2"></i>
                                                <h6>Database</h6>
                                                <span class="badge bg-success">Connected</span>
                                            </div>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <div class="border rounded p-3">
                                                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                                <h6>Cron Jobs</h6>
                                                <span class="badge bg-success">Active</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-3">
                                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                                <h6>SSL</h6>
                                                <span class="badge bg-success">Secure</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-3">
                                                <i class="fas fa-tachometer-alt fa-2x text-warning mb-2"></i>
                                                <h6>Performance</h6>
                                                <span class="badge bg-success">Good</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Save API Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- API Usage Guide -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Setup Guide
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Google Gemini Setup:</h6>
                                        <ol class="small">
                                            <li>Visit <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                                            <li>Create a new API key</li>
                                            <li>Copy the key and paste it above</li>
                                            <li>Click "Test" to verify connection</li>
                                        </ol>
                                        
                                        <h6>OpenAI ChatGPT Setup:</h6>
                                        <ol class="small">
                                            <li>Visit <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></li>
                                            <li>Create a new secret key</li>
                                            <li>Copy the key and paste it above</li>
                                            <li>Ensure you have billing set up</li>
                                        </ol>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>LinkedIn API Setup:</h6>
                                        <ol class="small">
                                            <li>Create app at <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a></li>
                                            <li>Request Marketing Developer Platform access</li>
                                            <li>Add your redirect URIs</li>
                                            <li>Copy Client ID and Secret</li>
                                        </ol>
                                        
                                        <h6>Razorpay Setup:</h6>
                                        <ol class="small">
                                            <li>Sign up at <a href="https://razorpay.com" target="_blank">Razorpay</a></li>
                                            <li>Complete KYC verification</li>
                                            <li>Generate API keys from dashboard</li>
                                            <li>Use test keys for development</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        function testAPI(provider) {
            const keyInput = document.getElementById(provider + '-key');
            const statusIndicator = document.getElementById(provider + '-status');
            const apiKey = keyInput.value.trim();
            
            if (!apiKey) {
                alert('Please enter the API key first');
                return;
            }
            
            // Show loading
            statusIndicator.className = 'status-indicator status-unknown';
            
            // Create form data
            const formData = new FormData();
            formData.append('test_api', provider);
            formData.append('api_key', apiKey);
            
            fetch('api-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    statusIndicator.className = 'status-indicator status-success';
                    alert('✅ ' + data.message);
                } else {
                    statusIndicator.className = 'status-indicator status-error';
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                statusIndicator.className = 'status-indicator status-error';
                alert('❌ Connection test failed: ' + error.message);
            });
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>