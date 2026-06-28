<?php
/**
 * CampusFind Pro - Notifications List & Inbox
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure Access
requireLogin();

$user_id = $_SESSION['user_id'];
$notifications = [];

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch notifications
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([':uid' => $user_id]);
    $notifications = $stmt->fetchAll();

    // Mark all as read as the student reads them
    $update_stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
    $update_stmt->execute([':uid' => $user_id]);
} catch (Exception $e) {
    error_log("Notifications fetch failure: " . $e->getMessage());
}

$page_title = 'My Notifications';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="font-heading fw-800 m-0">Inbox Alerts</h1>
                    <p class="text-secondary m-0">Read notifications regarding your reported items and claims.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/dashboard/index.php" class="btn btn-premium-outline btn-sm"><i class="fa-solid fa-arrow-left me-2"></i>My Dashboard</a>
            </div>

            <hr class="my-4 border-color">

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="glass-panel p-5 text-center">
                    <i class="fa-regular fa-bell-slash text-muted fs-1 mb-3"></i>
                    <h5 class="text-secondary">Your inbox is empty.</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">You will receive system alerts when claims are processed or comments logged.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="glass-panel p-4 <?php echo $notif['is_read'] == 0 ? 'border-primary bg-primary bg-opacity-5' : ''; ?>" style="transition: var(--transition-smooth); border-left: 4px solid <?php echo $notif['is_read'] == 0 ? 'var(--accent-color)' : 'var(--border-color)'; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                                <h6 class="fw-700 m-0 text-primary">
                                    <?php echo sanitize($notif['title']); ?>
                                    <?php if ($notif['is_read'] == 0): ?>
                                        <span class="badge bg-primary ms-2" style="font-size: 0.65rem;">New</span>
                                    <?php endif; ?>
                                </h6>
                                <span class="text-muted" style="font-size: 0.8rem;"><i class="fa-regular fa-clock me-1"></i><?php echo formatDate($notif['created_at'], 'M d, Y, g:i a'); ?></span>
                            </div>
                            <p class="text-secondary m-0" style="font-size: 0.9rem; line-height: 1.5;"><?php echo sanitize($notif['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
