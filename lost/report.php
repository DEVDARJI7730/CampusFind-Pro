<?php
/**
 * CampusFind Pro - Report Lost Item
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
    // Fetch categories for dropdown
    $categories = $db->find('categories', [], ['sort' => ['name' => 1]]);
} catch (Exception $e) {
    error_log("Report Lost categories fetch fail: " . $e->getMessage());
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $lost_date = $_POST['lost_date'] ?? '';
    $reward = filter_var($_POST['reward'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $csrf = $_POST['csrf_token'] ?? '';

    // Validate Input
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (empty($title) || empty($description) || empty($category_id) || empty($location) || empty($lost_date)) {
        $error = 'All fields except Reward and Image are required.';
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
                $error = 'Invalid image type. Only JPG, JPEG, PNG, and WEBP formats are accepted.';
                $upload_ok = false;
            } elseif ($fileSize > MAX_IMAGE_SIZE) {
                $error = 'Image exceeds maximum allowed size of 5MB.';
                $upload_ok = false;
            } else {
                $image_name = 'lost-' . time() . '-' . rand(1000, 9999) . '.' . $fileExtension;
                
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
                    'lost_date' => $lost_date,
                    'image' => $image_name,
                    'reward' => $reward ?: 0.00,
                    'status' => 'lost',
                    'qr_token' => $qr_token,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $item_id = $db->insert('lost_items', $item_document);

                // Log Activity
                logActivity($user_id, 'REPORT_LOST_ITEM', 'Reported lost item: ' . $title);

                // Add welcoming alert
                $_SESSION['success_msg'] = 'Lost item reported successfully!';
                redirect('dashboard/index.php');
            } catch (Exception $e) {
                error_log("Lost report DB insert failure: " . $e->getMessage());
                $error = 'Failed to record lost item details in database.';
            }
        }
    }
}

$page_title = 'Report Lost Item';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="btn btn-premium rounded p-3" style="background: rgba(239, 68, 68, 0.1); color: var(--color-danger); box-shadow: none;"><i class="fa-solid fa-clipboard-question fs-4"></i></div>
                    <div>
                        <h3 class="font-heading fw-800 m-0">Report Lost Item</h3>
                        <p class="text-secondary m-0" style="font-size: 0.9rem;">Provide exact description and location details to help finders return your item.</p>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="report.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="row g-3">
                        <!-- Item Title -->
                        <div class="col-12 mb-3">
                            <label class="form-label text-secondary fw-500">Item Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-premium-control" placeholder="e.g. iPhone 13 Pro Max (Blue)" required>
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

                        <!-- Date Lost -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Date Lost <span class="text-danger">*</span></label>
                            <input type="date" name="lost_date" class="form-control form-premium-control" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Location Lost -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Approximate Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-premium-control" placeholder="e.g. Science Hall Room 304, Library 2nd Floor" required>
                        </div>

                        <!-- Reward (Optional) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Reward ($, Optional)</label>
                            <input type="number" step="0.01" name="reward" class="form-control form-premium-control" placeholder="0.00" min="0">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Detailed Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-premium-control" rows="5" placeholder="Specify colors, serial numbers, case markings, stickers, lock screen wallpaper details..." required></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Item Image (Max 5MB)</label>
                        <input class="form-control form-premium-control" type="file" name="image" accept="image/*">
                        <div class="form-text text-muted" style="font-size: 0.8rem;">Uploading an image improves finding rate by 80%. Only JPG, PNG, WEBP files.</div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-premium px-5 py-3"><i class="fa-solid fa-paper-plane me-2"></i>Report Item</button>
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
