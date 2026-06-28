<?php
/**
 * CampusFind Pro - Submit Claim Request
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$claimer_id = $_SESSION['user_id'];
$item_type = $_GET['item_type'] ?? '';
$item_id = filter_var($_GET['item_id'] ?? '', FILTER_VALIDATE_INT);
$error = '';
$success = '';

if (!in_array($item_type, ['lost', 'found']) || !$item_id) {
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Fetch item details and check if claim is duplicate or belongs to owner
    if ($item_type === 'found') {
        $stmt = $db->prepare("SELECT * FROM found_items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $item_id]);
        $item = $stmt->fetch();
    } else {
        $stmt = $db->prepare("SELECT * FROM lost_items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $item_id]);
        $item = $stmt->fetch();
    }

    if (!$item) {
        redirect('index.php');
    }

    // Owner cannot claim their own item
    if ($item['user_id'] === $claimer_id) {
        $_SESSION['success_msg'] = 'You cannot submit a claim for an item you reported.';
        redirect('dashboard/index.php');
    }

    // Check if user already submitted a pending/approved claim for this item
    $claim_check = $db->prepare("SELECT id FROM claims WHERE item_type = :itype AND item_id = :iid AND claimer_id = :cid AND status IN ('pending', 'approved') LIMIT 1");
    $claim_check->execute([
        ':itype' => $item_type,
        ':iid' => $item_id,
        ':cid' => $claimer_id
    ]);
    if ($claim_check->fetch()) {
        $_SESSION['success_msg'] = 'You have already filed a claim request for this item.';
        redirect('dashboard/index.php');
    }

} catch (Exception $e) {
    error_log("Claim init failure: " . $e->getMessage());
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proof_desc = trim($_POST['proof_description'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (empty($proof_desc)) {
        $error = 'Please provide a detailed description of your proof.';
    } else {
        $proof_image = null;
        $upload_ok = true;

        // Image validation
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['proof_image']['tmp_name'];
            $fileName = $_FILES['proof_image']['name'];
            $fileSize = $_FILES['proof_image']['size'];
            $fileType = $_FILES['proof_image']['type'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

            if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMimeTypes)) {
                $error = 'Invalid image type. Only JPG, PNG, WEBP are allowed.';
                $upload_ok = false;
            } elseif ($fileSize > MAX_IMAGE_SIZE) {
                $error = 'Image exceeds 5MB size limit.';
                $upload_ok = false;
            } else {
                $proof_image = 'proof-' . time() . '-' . rand(1000, 9999) . '.' . $fileExtension;
                $dest_path = UPLOAD_PATH . '/' . $proof_image;
                
                if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                    $error = 'Failed to write uploaded proof image.';
                    $upload_ok = false;
                }
            }
        }

        if ($upload_ok) {
            try {
                // Insert claim
                $claim_stmt = $db->prepare("
                    INSERT INTO claims (item_type, item_id, claimer_id, proof_description, proof_image, status) 
                    VALUES (:itype, :iid, :cid, :desc, :img, 'pending')
                ");
                $claim_stmt->execute([
                    ':itype' => $item_type,
                    ':iid' => $item_id,
                    ':cid' => $claimer_id,
                    ':desc' => $proof_desc,
                    ':img' => $proof_image
                ]);

                // Log Activity
                logActivity($claimer_id, 'SUBMIT_CLAIM', 'Submitted a claim request for ' . $item_type . ' item: ' . $item['title']);

                // Send notification to the original reporter
                $notif_title = 'New claim filed for: ' . $item['title'];
                $notif_msg = "Another student has submitted a claim for the item you logged. Please wait for administrator verification.";
                addNotification($item['user_id'], $notif_title, $notif_msg);

                $_SESSION['success_msg'] = 'Claim request submitted successfully! Administration will review your proof.';
                redirect('dashboard/index.php');
            } catch (Exception $e) {
                error_log("Claim DB insert fail: " . $e->getMessage());
                $error = 'Failed to submit claim. Database error occurred.';
            }
        }
    }
}

$page_title = 'File Ownership Claim';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="btn btn-premium rounded p-3" style="background: rgba(79, 70, 229, 0.1); color: var(--accent-color); box-shadow: none;"><i class="fa-solid fa-shield-halved fs-4"></i></div>
                    <div>
                        <h3 class="font-heading fw-800 m-0">Submit Ownership Claim</h3>
                        <p class="text-secondary m-0" style="font-size: 0.9rem;">File a verification claim to establish ownership of: <strong><?php echo sanitize($item['title']); ?></strong>.</p>
                    </div>
                </div>

                <!-- Item Quick info -->
                <div class="card bg-light border-0 mb-4 p-3 rounded d-flex flex-row align-items-center gap-3" style="background: var(--bg-secondary) !important;">
                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-item.png'; ?>" class="rounded border" style="width: 80px; height: 80px; object-fit: cover;">
                    <div>
                        <h6 class="fw-700 m-0"><?php echo sanitize($item['title']); ?></h6>
                        <span class="text-muted" style="font-size: 0.8rem;"><i class="fa-solid fa-location-dot me-1"></i><?php echo sanitize($item['location']); ?></span>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="submit.php?item_type=<?php echo $item_type; ?>&item_id=<?php echo $item_id; ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Description of Proof -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Ownership proof description <span class="text-danger">*</span></label>
                        <textarea name="proof_description" class="form-control form-premium-control" rows="6" placeholder="Provide distinct features (serial number, purchase receipt, lock screen password description, contents inside, unique markings) to prove this belongs to you." required></textarea>
                    </div>

                    <!-- Proof Image (Optional) -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Proof image / Receipt (Optional, Max 5MB)</label>
                        <input class="form-control form-premium-control" type="file" name="proof_image" accept="image/*">
                        <div class="form-text text-muted" style="font-size: 0.8rem;">Upload receipts, matching pictures, or screenshot logs. Only JPG, PNG, WEBP.</div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-premium px-5 py-3"><i class="fa-solid fa-file-signature me-2"></i>File Claim Request</button>
                        <a href="<?php echo SITE_URL; ?>/<?php echo $item_type; ?>/view.php?id=<?php echo $item_id; ?>" class="btn btn-premium-outline px-4 py-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
