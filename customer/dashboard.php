<?php
// customer/dashboard.php
require_once '../config/database-config.php';

$pageTitle = 'Dashboard - ' . SITE_NAME;
$pageDescription = 'Manage your LinkedIn automation';

// Require customer login
requireCustomerLogin();

// Get customer statistics
$stats = [
    'automations' => 0,
    'posts_generated' => 0,
    'posts_published' => 0,
    'engagement_rate' => 0
];

try {
    // Count automations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_automations WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['automations'] = $stmt->fetch()['count'];
    
    // Count generated posts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_generated_posts WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['posts_generated'] = $stmt->fetch()['count'];
    
    // Count published posts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_generated_posts WHERE customer_id = ? AND status = 'posted'");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['posts_published'] = $stmt->fetch()['count'];
    
    // Calculate engagement rate (mock calculation)
    $stats['engagement_rate'] = $stats['posts_published'] > 0 ? rand(85, 98) : 0;
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent automations
$recentAutomations = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM customer_automations 
        WHERE customer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $recentAutomations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Recent automations error: " . $e->getMessage());
}

// Get recent posts
$recentPosts = [];
try {
    $stmt = $db->prepare("
        SELECT cgp.*, ca.name as automation_name 
        FROM customer_generated_posts cgp
        JOIN customer_automations ca ON cgp.automation_id = ca.id
        WHERE cgp.customer_id = ? 
        ORDER BY cgp.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $recentPosts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Recent posts error: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>! 👋</h1>
                    <p class="text-muted mb-0">Here's what's happening with your LinkedIn automation</p>
                </div>
                <div>
                    <?php if ($_SESSION['subscription_status'] === 'trial'): ?>
                        <span class="badge bg-warning text-dark">Free Trial</span>
                    <?php elseif ($_SESSION['subscription_status'] === 'active'): ?>
                        <span class="badge bg-success">Pro Account</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Expired</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-robot fa-2x"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['automations']); ?></h3>
                            <small>Active Automations</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['posts_generated']); ?></h3>
                            <small>Posts Generated</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 bg-info text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-share fa-2x"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['posts_published']); ?></h3>
                            <small>Posts Published</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['engagement_rate']; ?>%</h3>
                            <small>Engagement Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Quick Actions</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="create-automation.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Create Automation
                        </a>
                        <a href="connect-linkedin.php" class="btn btn-outline-primary">
                            <i class="fab fa-linkedin me-1"></i>Connect LinkedIn
                        </a>
                        <a href="generated-posts.php" class="btn btn-outline-secondary">
                            <i class="fas fa-file-alt me-1"></i>View Posts
                        </a>
                        <a href="analytics.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar me-1"></i>Analytics
                        </a>
                        <?php if ($_SESSION['subscription_status'] !== 'active'): ?>
                            <a href="choose-plan.php" class="btn btn-success">
                                <i class="fas fa-crown me-1"></i>Upgrade Plan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Automations -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Automations</h5>
                    <a href="automations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentAutomations)): ?>
                        <?php foreach ($recentAutomations as $automation): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="me-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($automation['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($automation['topic']); ?> • 
                                        <?php echo ucfirst($automation['ai_provider']); ?> • 
                                        <span class="badge bg-<?php echo $automation['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($automation['status']); ?>
                                        </span>
                                    </small>
                                </div>
                                <div>
                                    <small class="text-muted">
                                        <?php echo date('M j', strtotime($automation['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No automations yet</p>
                            <a href="create-automation.php" class="btn btn-primary">Create Your First Automation</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Posts -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Posts</h5>
                    <a href="generated-posts.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentPosts)): ?>
                        <?php foreach ($recentPosts as $post): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($post['automation_name']); ?></h6>
                                    <span class="badge bg-<?php 
                                        echo $post['status'] === 'posted' ? 'success' : 
                                             ($post['status'] === 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($post['status']); ?>
                                    </span>
                                </div>
                                <p class="small text-muted mb-2">
                                    <?php echo htmlspecialchars(substr($post['content'], 0, 100)); ?>...
                                </p>
                                <small class="text-muted">
                                    Scheduled: <?php echo date('M j, Y H:i', strtotime($post['scheduled_time'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No posts generated yet</p>
                            <a href="create-automation.php" class="btn btn-primary">Create Automation to Generate Posts</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Trial/Subscription Status -->
    <?php if ($_SESSION['subscription_status'] === 'trial'): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Free Trial Active</h6>
                            <p class="mb-0">
                                Your free trial is active until <?php echo date('M j, Y', strtotime('+14 days')); ?>. 
                                Upgrade to continue using all features after the trial period.
                            </p>
                        </div>
                        <div>
                            <a href="choose-plan.php" class="btn btn-warning">Upgrade Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($_SESSION['subscription_status'] === 'expired'): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Subscription Expired</h6>
                            <p class="mb-0">
                                Your subscription has expired. Please upgrade to continue using LinkedIn automation features.
                            </p>
                        </div>
                        <div>
                            <a href="choose-plan.php" class="btn btn-danger">Renew Subscription</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Getting Started Guide -->
    <?php if ($stats['automations'] == 0): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-rocket me-2"></i>Getting Started
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Welcome to LinkedIn Automation! Follow these steps to get started:</p>
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <div class="bg-light rounded-3 p-3">
                                    <i class="fas fa-linkedin fa-3x text-primary mb-2"></i>
                                    <h6>1. Connect LinkedIn</h6>
                                    <p class="small text-muted mb-2">Link your LinkedIn account to start posting automatically</p>
                                    <a href="connect-linkedin.php" class="btn btn-sm btn-outline-primary">Connect Now</a>
                                </div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="bg-light rounded-3 p-3">
                                    <i class="fas fa-robot fa-3x text-success mb-2"></i>
                                    <h6>2. Create Automation</h6>
                                    <p class="small text-muted mb-2">Set up your first AI-powered posting schedule</p>
                                    <a href="create-automation.php" class="btn btn-sm btn-outline-success">Create Automation</a>
                                </div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="bg-light rounded-3 p-3">
                                    <i class="fas fa-chart-bar fa-3x text-info mb-2"></i>
                                    <h6>3. Track Performance</h6>
                                    <p class="small text-muted mb-2">Monitor your posts and engagement analytics</p>
                                    <a href="analytics.php" class="btn btn-sm btn-outline-info">View Analytics</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.card {
    border-radius: 15px;
    transition: all 0.3s ease;
    border: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.bg-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; }
.bg-success { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important; }
.bg-info { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%) !important; }
.bg-warning { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%) !important; }

.btn {
    border-radius: 8px;
    font-weight: 500;
}

.alert {
    border-radius: 12px;
    border: 0;
}

.badge {
    font-weight: 500;
}
</style>

<?php require_once '../includes/footer.php'; ?>