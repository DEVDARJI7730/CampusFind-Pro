<?php
/**
 * CampusFind Pro - View Single Claim Request Details
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$claim_id) {
    redirect('dashboard/index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch claim details joining claimer details
    $stmt = $db->prepare("
        SELECT c.*, u.name as claimer_name, u.email as claimer_email, u.student_id as claimer_sid
        FROM claims c
        JOIN users u ON c.claimer_id = u.id
        WHERE c.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $claim_id]);
    $claim = $stmt->fetch();

    if (!$claim) {
        redirect('dashboard/index.php');
    }

    // Fetch related item details based on item_type
    $item = null;
    if ($claim['item_type'] === 'found') {
        $item_stmt = $db->prepare("SELECT f.*, c.name as category_name FROM found_items f JOIN categories c ON f.category_id = c.id WHERE f.id = :iid LIMIT 1");
        $item_stmt->execute([':iid' => $claim['item_id']]);
        $item = $item_stmt->fetch();
    } else {
        $item_stmt = $db->prepare("SELECT l.*, c.name as category_name FROM lost_items l JOIN categories c ON l.category_id = c.id WHERE l.id = :iid LIMIT 1");
        $item_stmt->execute([':iid' => $claim['item_id']]);
        $item = $item_stmt->fetch();
    }

    if (!$item) {
        redirect('dashboard/index.php');
    }

    // Authorisation check: current user must be the claimer OR the one who reported the item
    if ($claim['claimer_id'] !== $user_id && $item['user_id'] !== $user_id) {
        redirect('dashboard/index.php');
    }

} catch (Exception $e) {
    error_log("View claim failure: " . $e->getMessage());
    redirect('dashboard/index.php');
}

$page_title = 'Claim Request Detail';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row g-4 justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h3 class="font-heading fw-800 m-0">Claim Details</h3>
                    <a href="<?php echo SITE_URL; ?>/dashboard/index.php" class="btn btn-premium-outline btn-sm"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
                </div>

                <hr class="my-4 border-color">

                <div class="row g-4">
                    <!-- Claim State card -->
                    <div class="col-md-6">
                        <div class="glass-panel p-4 bg-opacity-25" style="background: var(--bg-secondary); border-color: var(--border-color);">
                            <span class="d-block text-muted mb-1" style="font-size: 0.8rem;">Claim Request Status</span>
                            <?php 
                            $status_class = 'badge-warning';
                            if ($claim['status'] === 'approved') $status_class = 'badge-found';
                            elseif ($claim['status'] === 'rejected') $status_class = 'badge-lost';
                            ?>
                            <span class="badge <?php echo $status_class; ?> px-3 py-2 text-uppercase fw-800 fs-7 mb-3"><?php echo ucfirst($claim['status']); ?></span>
                            
                            <span class="d-block text-muted mt-2" style="font-size: 0.85rem;">Submitted on:</span>
                            <span class="fw-600 text-secondary"><?php echo formatDate($claim['created_at'], 'M d, Y, g:i a'); ?></span>
                        </div>
                    </div>

                    <!-- Item quick preview card -->
                    <div class="col-md-6">
                        <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between border-color">
                            <div>
                                <span class="d-block text-muted mb-1" style="font-size: 0.8rem;">Associated Item</span>
                                <h6 class="fw-700 m-0"><?php echo sanitize($item['title']); ?></h6>
                                <span class="badge bg-secondary text-secondary mt-1" style="font-size: 0.75rem;"><?php echo sanitize($item['category_name']); ?></span>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/<?php echo $claim['item_type']; ?>/view.php?id=<?php echo $item['id']; ?>" class="btn btn-premium-outline btn-sm w-100 mt-3">View Item Details</a>
                        </div>
                    </div>
                </div>

                <!-- Proof Description -->
                <h5 class="font-heading fw-700 mt-5 mb-3">Ownership Verification Proof</h5>
                <div class="p-3 bg-light rounded text-secondary border mb-4" style="background: var(--bg-secondary) !important; font-size: 0.95rem; line-height: 1.6; white-space: pre-line;">
                    <?php echo sanitize($claim['proof_description']); ?>
                </div>

                <!-- Proof Image if uploaded -->
                <?php if (!empty($claim['proof_image'])): ?>
                    <h5 class="font-heading fw-700 mb-3">Proof Attachments</h5>
                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $claim['proof_image']; ?>" class="rounded border mb-4 img-fluid" style="max-height: 350px; object-fit: cover;" alt="Proof">
                <?php endif; ?>

                <!-- Admin notes / remarks -->
                <div class="glass-panel p-4 mt-5" style="border-left: 4px solid var(--accent-color);">
                    <h6 class="font-heading fw-700 mb-2"><i class="fa-solid fa-clipboard-user text-primary me-2"></i>Administration Decision Remarks</h6>
                    <p class="text-secondary m-0" style="font-size: 0.9rem; line-height: 1.6;">
                        <?php echo !empty($claim['admin_notes']) ? sanitize($claim['admin_notes']) : 'This claim is currently pending administrative review. You will receive a system alert once processed.'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
