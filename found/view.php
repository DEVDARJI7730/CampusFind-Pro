<?php
/**
 * CampusFind Pro - View Found Item Detail & QR Code
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

$item_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$item_id) {
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch found item details joining category and user details
    $stmt = $db->prepare("
        SELECT f.*, c.name as category_name, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone
        FROM found_items f
        JOIN categories c ON f.category_id = c.id
        JOIN users u ON f.user_id = u.id
        WHERE f.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $item_id]);
    $item = $stmt->fetch();

    if (!$item) {
        redirect('index.php');
    }
} catch (Exception $e) {
    error_log("Found view failure: " . $e->getMessage());
    redirect('index.php');
}

$page_title = sanitize($item['title']);
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row g-4 justify-content-center">
        <!-- Main Detail Box -->
        <div class="col-lg-8" data-aos="fade-right">
            <div class="glass-panel p-4 p-md-5 h-100">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <span class="badge bg-secondary text-secondary mb-2" style="font-size: 0.8rem;"><?php echo sanitize($item['category_name']); ?></span>
                        <h2 class="font-heading fw-800 m-0"><?php echo sanitize($item['title']); ?></h2>
                    </div>
                    <span class="badge badge-found px-3 py-2 text-uppercase fs-7"><?php echo ucfirst($item['status']); ?></span>
                </div>

                <hr class="my-4 border-color">

                <div class="row g-4">
                    <!-- Image -->
                    <div class="col-md-6">
                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-item.png'; ?>" alt="<?php echo sanitize($item['title']); ?>" class="w-100 rounded border shadow-sm" style="max-height: 320px; object-fit: cover;">
                    </div>
                    
                    <!-- Specifications -->
                    <div class="col-md-6 d-flex flex-column justify-content-between">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <span class="d-block text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-location-dot me-2 text-success"></i>Found Location:</span>
                                <span class="fw-600 fs-5 text-success"><?php echo sanitize($item['location']); ?></span>
                            </div>
                            <div>
                                <span class="d-block text-muted" style="font-size: 0.85rem;"><i class="fa-regular fa-calendar me-2 text-success"></i>Date Found:</span>
                                <span class="fw-600 text-secondary"><?php echo formatDate($item['found_date']); ?></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-4">
                            <?php if (isLoggedIn()): ?>
                                <?php if ($_SESSION['user_id'] === $item['user_id']): ?>
                                    <!-- Owner Actions -->
                                    <div class="d-flex gap-2">
                                        <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-premium w-50 py-3"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Report</a>
                                        <a href="delete.php?id=<?php echo $item['id']; ?>" class="btn btn-premium-outline w-50 py-3 text-danger border-danger" onclick="return confirm('Delete this found report log?')"><i class="fa-solid fa-trash me-2"></i>Delete</a>
                                    </div>
                                <?php else: ?>
                                    <!-- Visitor Actions -->
                                    <?php if ($item['status'] === 'found'): ?>
                                        <a href="<?php echo SITE_URL; ?>/claims/submit.php?item_type=found&item_id=<?php echo $item['id']; ?>" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-handshake-angle me-2"></i>Claim This Item</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100 py-3" disabled><i class="fa-solid fa-circle-check me-2"></i>Item Claimed & Returned</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-right-to-bracket me-2"></i>Login to File Claim</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <h5 class="font-heading fw-700 mt-5 mb-3">Item Description & Marks</h5>
                <p class="text-secondary" style="line-height: 1.7; font-size: 0.95rem; white-space: pre-line;"><?php echo sanitize($item['description']); ?></p>

                <!-- Reporter details card (only for logged in users) -->
                <?php if (isLoggedIn()): ?>
                    <div class="glass-panel p-4 mt-5 bg-opacity-25" style="background: var(--bg-secondary);">
                        <h6 class="font-heading fw-700 text-secondary mb-3"><i class="fa-solid fa-circle-user text-success me-2"></i>Turned In By</h6>
                        <div class="row g-2" style="font-size: 0.9rem;">
                            <div class="col-md-6">
                                <span class="text-muted">Name:</span> <strong class="text-secondary"><?php echo sanitize($item['reporter_name']); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Email:</span> <strong class="text-secondary"><?php echo sanitize($item['reporter_email']); ?></strong>
                            </div>
                            <?php if (!empty($item['reporter_phone'])): ?>
                                <div class="col-md-12">
                                    <span class="text-muted">Phone:</span> <strong class="text-secondary"><?php echo sanitize($item['reporter_phone']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar (QR Registry details) -->
        <div class="col-lg-4" data-aos="fade-left">
            <div class="glass-panel p-4 text-center">
                <h5 class="font-heading fw-700 mb-3"><i class="fa-solid fa-qrcode text-success me-2"></i>QR Code Registry</h5>
                <p class="text-muted mb-4" style="font-size: 0.85rem;">Scan this QR code with any mobile camera to view this item registry instantly.</p>
                
                <!-- QR Code Box -->
                <div class="d-inline-block bg-white p-3 rounded shadow-sm border mb-4">
                    <div id="qrcode"></div>
                </div>
                
                <div class="text-muted mt-2" style="font-size: 0.8rem;">
                    Token ID: <span class="font-monospace fw-700"><?php echo sanitize($item['qr_token']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Script to render QR Code client-side
$item_view_url = SITE_URL . '/found/view.php?id=' . $item['id'];
$extra_js = "
<script>
    document.addEventListener('DOMContentLoaded', () => {
        new QRCode(document.getElementById('qrcode'), {
            text: '$item_view_url',
            width: 180,
            height: 180,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    });
</script>
";
require_once dirname(__DIR__) . '/includes/footer.php';
?>
