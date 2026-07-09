<?php
/**
 * CampusFind Pro - Admin: Claims Moderation Queue
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];

// Get messaging alerts from process.php
$msg = $_SESSION['admin_msg'] ?? '';
$msg_class = $_SESSION['admin_msg_class'] ?? 'success';
unset($_SESSION['admin_msg'], $_SESSION['admin_msg_class']);

// Retrieve filters
$status_filter = $_GET['status'] ?? 'pending';

try {
    $db = Database::getInstance();

    // Fetch claims according to status filter
    $raw_claims = $db->find('claims', ['status' => $status_filter], ['sort' => ['created_at' => -1]]);
    $claims = [];
    foreach ($raw_claims as $clm) {
        $claimer = $db->findOne('users', ['_id' => toObjectId($clm['claimer_id'])]);
        $clm['claimer_name'] = $claimer['name'] ?? 'Unknown';
        $clm['claimer_email'] = $claimer['email'] ?? '';
        $clm['claimer_sid'] = $claimer['student_id'] ?? '';

        $item = null;
        if ($clm['item_type'] === 'found') {
            $item = $db->findOne('found_items', ['_id' => toObjectId($clm['item_id'])]);
        } else {
            $item = $db->findOne('lost_items', ['_id' => toObjectId($clm['item_id'])]);
        }

        $clm['item_title'] = $item['title'] ?? 'Unknown';
        $clm['item_location'] = $item['location'] ?? 'Unknown';
        $clm['item_image'] = $item['image'] ?? 'default-item.png';

        $claims[] = $clm;
    }

} catch (Exception $e) {
    error_log("Admin claims console fetch fail: " . $e->getMessage());
    $claims = [];
}

$page_title = 'Claims Moderation';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Admin Navigation Tabs -->
    <div class="row mb-5" data-aos="fade-up">
        <div class="col-12">
            <div class="glass-panel p-3">
                <ul class="nav nav-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="dashboard.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="users.php"><i class="fa-solid fa-users me-2"></i>Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="items.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-600 px-4 py-2" href="claims.php"><i class="fa-solid fa-handshake-angle me-2"></i>Claims</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="reports.php"><i class="fa-solid fa-file-invoice me-2"></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="settings.php"><i class="fa-solid fa-gears me-2"></i>Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="logs.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>System Logs</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2" data-aos="fade-up">
        <div>
            <h2 class="font-heading fw-800 m-0">Claims Queue Moderation</h2>
            <p class="text-secondary m-0">Verify user proof descriptions and approve or reject ownership transfers.</p>
        </div>
        
        <!-- Tab status filter pills -->
        <div class="btn-group glass-panel p-1" role="group">
            <a href="claims.php?status=pending" class="btn btn-sm px-3 rounded-start <?php echo $status_filter === 'pending' ? 'btn-premium' : 'btn-light bg-transparent text-secondary border-0'; ?>">Pending</a>
            <a href="claims.php?status=approved" class="btn btn-sm px-3 <?php echo $status_filter === 'approved' ? 'btn-premium' : 'btn-light bg-transparent text-secondary border-0'; ?>">Approved</a>
            <a href="claims.php?status=rejected" class="btn btn-sm px-3 rounded-end <?php echo $status_filter === 'rejected' ? 'btn-premium' : 'btn-light bg-transparent text-secondary border-0'; ?>">Rejected</a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Claims Moderation Queue Cards -->
    <div class="row g-4" data-aos="fade-up">
        <?php if (empty($claims)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <div class="glass-panel p-5">
                    <i class="fa-solid fa-square-check fs-2 mb-2 text-success"></i>
                    <p class="m-0">No claim requests registered under state: <strong><?php echo ucfirst($status_filter); ?></strong>.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($claims as $clm): ?>
                <div class="col-12">
                    <div class="glass-panel p-4">
                        <div class="row g-3 align-items-center">
                            <!-- Claimer & Item info -->
                            <div class="col-lg-3 col-md-4">
                                <span class="text-muted d-block" style="font-size: 0.8rem;">Claimer Account</span>
                                <strong class="text-primary d-block"><?php echo sanitize($clm['claimer_name']); ?></strong>
                                <span class="text-secondary d-block" style="font-size: 0.85rem;">ID: <?php echo sanitize($clm['claimer_sid']); ?></span>
                                <span class="text-muted d-block" style="font-size: 0.8rem;"><?php echo sanitize($clm['claimer_email']); ?></span>
                            </div>
                            
                            <!-- Item quick summary -->
                            <div class="col-lg-4 col-md-5">
                                <span class="text-muted d-block" style="font-size: 0.8rem;">Claimed Item</span>
                                <strong class="text-secondary d-block"><?php echo sanitize($clm['item_title']); ?></strong>
                                <span class="text-muted d-block" style="font-size: 0.85rem;"><i class="fa-solid fa-location-dot me-1 text-primary"></i><?php echo sanitize($clm['item_location']); ?></span>
                                <span class="badge bg-secondary text-secondary mt-1 text-uppercase" style="font-size: 0.7rem;"><?php echo $clm['item_type']; ?></span>
                            </div>

                            <!-- Submission Date -->
                            <div class="col-lg-2 col-md-3">
                                <span class="text-muted d-block" style="font-size: 0.8rem;">Submitted Date</span>
                                <span class="text-secondary fw-500 d-block"><?php echo formatDate($clm['created_at']); ?></span>
                                <span class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($clm['created_at'], 'g:i a'); ?></span>
                            </div>

                            <!-- Review Button Trigger -->
                            <div class="col-lg-3 col-md-12 text-lg-end">
                                <button class="btn btn-premium w-100 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#claimReview-<?php echo $clm['_id']; ?>">
                                    <i class="fa-solid fa-clipboard-check me-2"></i>Review Proof Detail
                                </button>
                            </div>
                        </div>

                        <!-- Collapse review options panel -->
                        <div class="collapse mt-4 pt-4 border-top border-color" id="claimReview-<?php echo $clm['_id']; ?>">
                            <div class="row g-4">
                                <!-- Proof explanation -->
                                <div class="col-md-7">
                                    <h6 class="font-heading fw-700 text-secondary mb-2">Claimer Proof Statement</h6>
                                    <p class="p-3 bg-light rounded text-secondary border mb-3" style="background: var(--bg-secondary) !important; font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">
                                        <?php echo sanitize($clm['proof_description']); ?>
                                    </p>
                                    
                                    <?php if (!empty($clm['proof_image'])): ?>
                                        <h6 class="font-heading fw-700 text-secondary mb-2">Proof Image Attachment</h6>
                                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $clm['proof_image']; ?>" alt="Proof" class="rounded border shadow-sm img-fluid" style="max-height: 250px; object-fit: cover;">
                                    <?php endif; ?>
                                </div>

                                <!-- Decisions options -->
                                <div class="col-md-5">
                                    <div class="glass-panel p-4 bg-opacity-25" style="background: var(--bg-secondary);">
                                        <h6 class="font-heading fw-700 text-secondary mb-3">Moderator Action Panel</h6>
                                        
                                        <?php if ($clm['status'] === 'pending'): ?>
                                            <form action="<?php echo SITE_URL; ?>/claims/process.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="claim_id" value="<?php echo $clm['_id']; ?>">

                                                <div class="mb-3">
                                                    <label class="form-label text-secondary fw-500" style="font-size: 0.85rem;">Decision Remarks / Pick Up instructions</label>
                                                    <textarea name="admin_notes" class="form-control form-premium-control" rows="3" placeholder="Enter remarks (e.g. Please bring student card to Gate 4 office)"></textarea>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="action" value="approve" class="btn btn-premium w-50 py-2 btn-success border-success" style="background: var(--color-success);"><i class="fa-solid fa-check me-1"></i>Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-premium w-50 py-2 btn-danger border-danger" style="background: var(--color-danger);" onclick="return confirm('Reject this claim request?')"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="mb-2">
                                                <span class="text-muted" style="font-size: 0.85rem;">Review Remarks:</span>
                                                <p class="text-secondary font-italic" style="font-size: 0.9rem;"><?php echo !empty($clm['admin_notes']) ? sanitize($clm['admin_notes']) : 'No remarks recorded.'; ?></p>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.8rem;">Processed On:</span>
                                            <strong class="text-secondary d-block" style="font-size: 0.85rem;"><?php echo formatDate($clm['processed_at']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
