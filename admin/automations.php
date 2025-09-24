<?php
// admin/automations.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = intval($_POST['customer_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $aiProvider = $_POST['ai_provider'] ?? 'gemini';
    $postTime = $_POST['post_time'] ?? '09:00';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $daysOfWeek = implode(',', $_POST['days_of_week'] ?? []);
    $contentTemplate = trim($_POST['content_template'] ?? '');
    $hashtags = trim($_POST['hashtags'] ?? '');
    
    if (empty($name) || empty($topic) || $customerId <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO customer_automations (
                    customer_id, name, topic, ai_provider, post_time, 
                    start_date, end_date, days_of_week, content_template, 
                    hashtags, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $success = $stmt->execute([
                $customerId, $name, $topic, $aiProvider, $postTime,
                $startDate, $endDate, $daysOfWeek, $contentTemplate, $hashtags
            ]);
            
            if ($success) {
                $message = 'Automation created successfully!';
                
                // Generate posts for the next 5 days
                generatePostsForAutomation($db->lastInsertId(), $db);
            } else {
                $error = 'Failed to create automation';
            }
        } catch (Exception $e) {
            error_log("Create automation error: " . $e->getMessage());
            $error = 'An error occurred while creating automation';
        }
    }
}

// Get customers for dropdown
$customers = [];
try {
    $stmt = $db->prepare("SELECT id, name, email FROM customers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $automations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Get automations error: " . $e->getMessage());
}

// Function to generate posts for automation
function generatePostsForAutomation($automationId, $db) {
    try {
        // Get automation details
        $stmt = $db->prepare("SELECT * FROM customer_automations WHERE id = ?");
        $stmt->execute([$automationId]);
        $automation = $stmt->fetch();
        
        if (!$automation) return;
        
        $daysOfWeek = explode(',', $automation['days_of_week']);
        $startDate = new DateTime($automation['start_date']);
        $endDate = new DateTime($automation['end_date']);
        $postTime = $automation['post_time'];
        
        // Generate posts for next 5 days or until end date
        $currentDate = new DateTime();
        $endGenerationDate = min($endDate, (new DateTime())->add(new DateInterval('P5D')));
        
        while ($currentDate <= $endGenerationDate) {
            $dayOfWeek = $currentDate->format('N'); // 1=Monday, 7=Sunday
            
            if (in_array($dayOfWeek, $daysOfWeek) || in_array('daily', $daysOfWeek)) {
                $scheduledTime = $currentDate->format('Y-m-d') . ' ' . $postTime . ':00';
                
                // Generate AI content
                $content = generateAIContent($automation['topic'], $automation['ai_provider'], $automation['content_template']);
                
                if ($automation['hashtags']) {
                    $content .= "\n\n" . $automation['hashtags'];
                }
                
                // Insert generated post
                $stmt = $db->prepare("
                    INSERT INTO customer_generated_posts (
                        customer_id, automation_id, content, scheduled_time, status
                    ) VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $automation['customer_id'], 
                    $automationId, 
                    $content, 
                    $scheduledTime
                ]);
            }
            
            $currentDate->add(new DateInterval('P1D'));
        }
    } catch (Exception $e) {
        error_log("Generate posts error: " . $e->getMessage());
    }
}

// Function to generate AI content
function generateAIContent($topic, $provider, $template = '') {
    // Get API keys from settings
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT gemini_api_key, chatgpt_api_key FROM api_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        if ($provider === 'gemini' && !empty($settings['gemini_api_key'])) {
            return generateGeminiContent($topic, $settings['gemini_api_key'], $template);
        } elseif ($provider === 'chatgpt' && !empty($settings['chatgpt_api_key'])) {
            return generateChatGPTContent($topic, $settings['chatgpt_api_key'], $template);
        }
    } catch (Exception $e) {
        error_log("AI content generation error: " . $e->getMessage());
    }
    
    // Fallback content if AI fails
    return "🚀 Exciting insights about " . $topic . "!\n\nStay tuned for more updates and industry insights. What are your thoughts on this topic?\n\n#LinkedIn #" . str_replace(' ', '', $topic);
}

function generateGeminiContent($topic, $apiKey, $template) {
    $prompt = $template ?: "Create a professional LinkedIn post about: $topic. Make it engaging, informative, and include relevant emojis. Keep it under 300 characters.";
    
    $data = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    
    throw new Exception("Gemini API error: " . $response);
}

function generateChatGPTContent($topic, $apiKey, $template) {
    $prompt = $template ?: "Create a professional LinkedIn post about: $topic. Make it engaging, informative, and include relevant emojis. Keep it under 300 characters.";
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 300
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    throw new Exception("ChatGPT API error: " . $response);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Automations - Admin Panel</title>
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
                            <a class="nav-link active" href="automations.php">
                                <i class="fas fa-robot me-2"></i>Automations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="api-settings.php">
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
                    <h1 class="h2">LinkedIn Automations</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAutomationModal">
                            <i class="fas fa-plus me-1"></i>Create New Automation
                        </button>
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
                
                <!-- Existing Automations -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Existing Automations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($automations)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Name</th>
                                            <th>Topic</th>
                                            <th>AI Provider</th>
                                            <th>Schedule</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($automations as $automation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($automation['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($automation['name']); ?></td>
                                                <td><?php echo htmlspecialchars($automation['topic']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $automation['ai_provider'] === 'gemini' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst($automation['ai_provider']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo $automation['post_time']; ?><br>
                                                        <?php echo date('M j', strtotime($automation['start_date'])); ?> - 
                                                        <?php echo date('M j', strtotime($automation['end_date'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $automation['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($automation['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary btn-sm" onclick="viewPosts(<?php echo $automation['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteAutomation(<?php echo $automation['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No automations found. Create your first automation!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Automation Modal -->
    <div class="modal fade" id="createAutomationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New LinkedIn Automation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Select Customer *</label>
                                    <select name="customer_id" class="form-select" required>
                                        <option value="">Choose customer...</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>">
                                                <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Automation Name *</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., Tech Industry Updates" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Topic/Theme *</label>
                                    <input type="text" name="topic" class="form-control" placeholder="e.g., Artificial Intelligence, Marketing Tips" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">AI Provider</label>
                                    <select name="ai_provider" class="form-select">
                                        <option value="gemini">Google Gemini</option>
                                        <option value="chatgpt">ChatGPT</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Post Time</label>
                                    <input type="time" name="post_time" class="form-control" value="09:00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Days of Week</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="1" id="monday">
                                    <label class="form-check-label" for="monday">Mon</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="2" id="tuesday">
                                    <label class="form-check-label" for="tuesday">Tue</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="3" id="wednesday">
                                    <label class="form-check-label" for="wednesday">Wed</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="4" id="thursday">
                                    <label class="form-check-label" for="thursday">Thu</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="5" id="friday">
                                    <label class="form-check-label" for="friday">Fri</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="6" id="saturday">
                                    <label class="form-check-label" for="saturday">Sat</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="7" id="sunday">
                                    <label class="form-check-label" for="sunday">Sun</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content Template (Optional)</label>
                            <textarea name="content_template" class="form-control" rows="3" 
                                placeholder="Custom prompt for AI. Leave empty to use default template."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hashtags (Optional)</label>
                            <input type="text" name="hashtags" class="form-control" 
                                placeholder="e.g., #Technology #AI #Innovation">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-robot me-1"></i>Create Automation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPosts(automationId) {
            window.location.href = 'posts.php?automation_id=' + automationId;
        }
        
        function deleteAutomation(automationId) {
            if (confirm('Are you sure you want to delete this automation?')) {
                window.location.href = 'delete-automation.php?id=' + automationId;
            }
        }
        
        // Pre-select weekdays by default
        document.addEventListener('DOMContentLoaded', function() {
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].forEach(day => {
                document.getElementById(day).checked = true;
            });
        });
    </script>
</body>
</html>