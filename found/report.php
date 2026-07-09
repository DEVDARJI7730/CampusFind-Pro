<?php
/**
 * CampusFind Pro - Report Found Item
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $db = Database::getInstance();
    // Fetch categories
    $categories = $db->find('categories', [], ['sort' => ['name' => 1]]);
} catch (Exception $e) {
    error_log("Report Found categories fetch fail: " . $e->getMessage());
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $found_date = $_POST['found_date'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    // Validate Input
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (empty($title) || empty($description) || empty($category_id) || empty($location) || empty($found_date)) {
        $error = 'All fields except Image are required.';
    } else {
        $image_name = null;
        $upload_ok = true;

        // Image validation
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileSize = $_FILES['image']['size'];
            $fileType = $_FILES['image']['type'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

            if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMimeTypes)) {
                $error = 'Invalid image format. Use JPG, PNG, or WEBP.';
                $upload_ok = false;
            } elseif ($fileSize > MAX_IMAGE_SIZE) {
                $error = 'Image exceeds 5MB size limit.';
                $upload_ok = false;
            } else {
                $image_name = 'found-' . time() . '-' . rand(1000, 9999) . '.' . $fileExtension;
                
                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0777, true);
                }
                
                $dest_path = UPLOAD_PATH . '/' . $image_name;
                if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                    $error = 'Failed to write uploaded image to storage.';
                    $upload_ok = false;
                }
            }
        }

        if ($upload_ok) {
            try {
                $qr_token = generateQRToken();
                
                $item_document = [
                    'user_id' => toObjectId($user_id),
                    'category_id' => toObjectId($category_id),
                    'title' => $title,
                    'description' => $description,
                    'location' => $location,
                    'found_date' => $found_date,
                    'image' => $image_name,
                    'status' => 'found',
                    'qr_token' => $qr_token,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $db->insert('found_items', $item_document);

                // Log Activity
                logActivity($user_id, 'REPORT_FOUND_ITEM', 'Reported found item: ' . $title);

                $_SESSION['success_msg'] = 'Found item logged successfully!';
                redirect('dashboard/index.php');
            } catch (Exception $e) {
                error_log("Found report DB insert failure: " . $e->getMessage());
                $error = 'Failed to save found item report.';
            }
        }
    }
}

$page_title = 'Report Found Item';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="btn btn-premium rounded p-3" style="background: rgba(16, 185, 129, 0.1); color: var(--color-success); box-shadow: none;"><i class="fa-solid fa-hand-holding-hand fs-4"></i></div>
                    <div>
                        <h3 class="font-heading fw-800 m-0">Report Found Item</h3>
                        <p class="text-secondary m-0" style="font-size: 0.9rem;">Turn in details of an item you found on campus so the owner can claim it.</p>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="report.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="row g-3">
                        <!-- Title -->
                        <div class="col-12 mb-3">
                            <label class="form-label text-secondary fw-500">Item Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-premium-control" placeholder="e.g. Leather Wallet, Blue Backpack" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Category -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select form-premium-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['_id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Found -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Date Found <span class="text-danger">*</span></label>
                            <input type="date" name="found_date" class="form-control form-premium-control" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Location Found -->
                        <div class="col-12 mb-3">
                            <label class="form-label text-secondary fw-500">Location Found <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-premium-control" placeholder="e.g. Student Center Cafeteria table, Engineering Room 101" required>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Description / Identifying Marks <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-premium-control" rows="5" placeholder="List details that only the real owner would know (contents inside, specific cards, stickers, model details) but avoid giving away ALL details so claimants can verify." required></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Item Image (Max 5MB)</label>
                        <input class="form-control form-premium-control" type="file" name="image" accept="image/*">
                        <div class="form-text text-muted" style="font-size: 0.8rem;">Uploading an image helps the owner recognise the item quickly. Only JPG, PNG, WEBP.</div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-premium w-100 py-3 text-white"><i class="fa-solid fa-paper-plane me-2"></i>Report Item</button>
                        <a href="<?php echo SITE_URL; ?>/dashboard/index.php" class="btn btn-premium-outline px-4 py-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
