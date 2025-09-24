<?php
// customer/create-automation.php
require_once '../config/database-config.php';

$pageTitle = 'Create Automation - ' . SITE_NAME;
$pageDescription = 'Create your LinkedIn automation';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $aiProvider = $_POST['ai_provider'] ?? 'gemini';
    $postTime = $_POST['post_time'] ?? '09:00';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $daysOfWeek = implode(',', $_POST['days_of_week'] ?? []);
    $contentTemplate = trim($_POST['content_template'] ?? '');
    $hashtags = trim($_POST['hashtags'] ?? '');
    
    if (empty($name) || empty($topic)) {
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
            
            $result = $stmt->execute([
                $_SESSION['customer_id'], $name, $topic, $aiProvider, $postTime,
                $startDate, $endDate, $daysOfWeek, $contentTemplate, $hashtags
            ]);
            
            if ($result) {
                $success = 'Automation created successfully! Posts will be generated automatically.';
                
                // Clear form data
                $name = $topic = $contentTemplate = $hashtags = '';
            } else {
                $error = 'Failed to create automation. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Create automation error: " . $e->getMessage());
            $error = 'An error occurred while creating automation';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white py-4">
                    <div class="text-center">
                        <h2 class="fw-bold mb-1">
                            <i class="fas fa-robot me-2"></i>Create LinkedIn Automation
                        </h2>
                        <p class="mb-0 opacity-75">Set up AI-powered content generation for your LinkedIn</p>
                    </div>
                </div>
                
                <div class="card-body p-5">
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
                    
                    <form method="POST">
                        <!-- Basic Information -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Automation Name *</label>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                               placeholder="e.g., Tech Industry Updates" required>
                                        <small class="form-text text-muted">Give your automation a descriptive name</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Content Topic *</label>
                                        <input type="text" name="topic" class="form-control" 
                                               value="<?php echo htmlspecialchars($topic ?? ''); ?>"
                                               placeholder="e.g., Artificial Intelligence, Marketing Tips" required>
                                        <small class="form-text text-muted">What should your posts be about?</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Settings -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-brain me-2"></i>AI Settings
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">AI Provider</label>
                                        <select name="ai_provider" class="form-select">
                                            <option value="gemini">🤖 Google Gemini (Recommended)</option>
                                            <option value="chatgpt">💬 ChatGPT</option>
                                        </select>
                                        <small class="form-text text-muted">Choose your preferred AI for content generation</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Content Template (Optional)</label>
                                        <textarea name="content_template" class="form-control" rows="3" 
                                                  placeholder="Custom instructions for AI (e.g., 'Write in a professional tone with industry insights')"><?php echo htmlspecialchars($contentTemplate ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Schedule Settings -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>Schedule Settings
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Post Time</label>
                                        <input type="time" name="post_time" class="form-control" value="09:00">
                                        <small class="form-text text-muted">Best times: 8-10 AM or 12-2 PM</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" 
                                               value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control" 
                                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Post on which days?</label>
                                <div class="d-flex gap-3 flex-wrap mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="1" id="monday" checked>
                                        <label class="form-check-label" for="monday">Monday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="2" id="tuesday" checked>
                                        <label class="form-check-label" for="tuesday">Tuesday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="3" id="wednesday" checked>
                                        <label class="form-check-label" for="wednesday">Wednesday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="4" id="thursday" checked>
                                        <label class="form-check-label" for="thursday">Thursday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="5" id="friday" checked>
                                        <label class="form-check-label" for="friday">Friday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="6" id="saturday">
                                        <label class="form-check-label" for="saturday">Saturday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="7" id="sunday">
                                        <label class="form-check-label" for="sunday">Sunday</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Weekdays are pre-selected for optimal engagement</small>
                            </div>
                        </div>
                        
                        <!-- Additional Settings -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-hashtag me-2"></i>Additional Settings
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Hashtags (Optional)</label>
                                <input type="text" name="hashtags" class="form-control" 
                                       value="<?php echo htmlspecialchars($hashtags ?? ''); ?>"
                                       placeholder="e.g., #Technology #AI #Innovation #LinkedIn">
                                <small class="form-text text-muted">Add relevant hashtags to increase reach (3-5 recommended)</small>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="mb-4">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-eye me-2"></i>What happens next?
                                </h6>
                                <ul class="mb-0 small">
                                    <li>AI will generate unique posts based on your topic and settings</li>
                                    <li>Posts will be scheduled according to your time preferences</li>
                                    <li>You can review and edit posts before they're published</li>
                                    <li>Analytics will track engagement and performance</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket me-2"></i>Create Automation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tips Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Pro Tips for Better Results
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Content Topics:</h6>
                            <ul class="small">
                                <li>Be specific (e.g., "Machine Learning in Healthcare" vs "Technology")</li>
                                <li>Focus on your industry expertise</li>
                                <li>Include trending topics in your field</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Optimal Posting:</h6>
                            <ul class="small">
                                <li>Weekdays generally perform better than weekends</li>
                                <li>Best times: 8-10 AM, 12-2 PM, 5-6 PM</li>
                                <li>Consistency is key - stick to your schedule</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 15px;
    transition: all 0.3s ease;
    border: 0;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #0077b5;
    box-shadow: 0 0 0 0.2rem rgba(0, 119, 181, 0.25);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
    transform: translateY(-2px);
}

.form-check-input:checked {
    background-color: #0077b5;
    border-color: #0077b5;
}

.alert {
    border-radius: 12px;
    border: 0;
}
</style>

<?php require_once '../includes/footer.php'; ?>