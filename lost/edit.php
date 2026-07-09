<?php
/**
 * CampusFind Pro - Edit Lost Item
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$item_id = trim($_GET['id'] ?? '');
$error = '';
$success = '';

if (!$item_id) {
    redirect('dashboard/index.php');
}

try {
    $db = Database::getInstance();
    
    // Fetch categories
    $categories = $db->find('categories', [], ['sort' => ['name' => 1]]);

    // Fetch item details
    $item = $db->findOne('lost_items', ['_id' => toObjectId($item_id), 'user_id' => toObjectId($user_id)]);

    if (!$item) {
        // Item doesn't exist or doesn't belong to this student
        redirect('dashboard/index.php');
    }
} catch (Exception $e) {
    error_log("Edit Lost page load failure: " . $e->getMessage());
    redirect('dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $lost_date = $_POST['lost_date'] ?? '';
    $reward = filter_var($_POST['reward'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $status = $_POST['status'] ?? 'lost';
    $csrf = $_POST['csrf_token'] ?? '';

    // Validate Input
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (empty($title) || empty($description) || empty($category_id) || empty($location) || empty($lost_date)) {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($status, ['lost', 'claimed', 'cancelled'])) {
        $error = 'Invalid status value.';
    } else {
        $image_name = $item['image'];
        $upload_ok = true;

        // Image validation (if new uploaded)
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
                $image_name = 'lost-' . time() . '-' . rand(1000, 9999) . '.' . $fileExtension;
                $dest_path = UPLOAD_PATH . '/' . $image_name;
                
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Delete old image if not null
                    if (!empty($item['image'])) {
                        $old_image_path = UPLOAD_PATH . '/' . $item['image'];
                        if (file_exists($old_image_path)) {
                            unlink($old_image_path);
                        }
                    }
                } else {
                    $error = 'Failed to write uploaded image to storage.';
                    $upload_ok = false;
                }
            }
        }

        if ($upload_ok) {
            try {
                $db->update('lost_items', ['_id' => toObjectId($item_id), 'user_id' => toObjectId($user_id)], [
                    'category_id' => toObjectId($category_id),
                    'title' => $title,
                    'description' => $description,
                    'location' => $location,
                    'lost_date' => $lost_date,
                    'image' => $image_name,
                    'reward' => $reward ?: 0.00,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                logActivity($user_id, 'EDIT_LOST_ITEM', 'Updated lost item: ' . $title);

                $_SESSION['success_msg'] = 'Lost report updated successfully!';
                redirect('dashboard/index.php');
            } catch (Exception $e) {
                error_log("Lost report DB update failure: " . $e->getMessage());
                $error = 'Database error. Failed to save updates.';
            }
        }
    }
}

$page_title = 'Edit Lost Report';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="btn btn-premium rounded p-3" style="background: rgba(99, 102, 241, 0.1); color: var(--accent-color); box-shadow: none;"><i class="fa-solid fa-pen-to-square fs-4"></i></div>
                    <div>
                        <h3 class="font-heading fw-800 m-0">Edit Lost Report</h3>
                        <p class="text-secondary m-0" style="font-size: 0.9rem;">Modify the item specifications, status, or locations.</p>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="edit.php?id=<?php echo $item['_id']; ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="row g-3">
                        <!-- Title -->
                        <div class="col-md-8 mb-3">
                            <label class="form-label text-secondary fw-500">Item Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-premium-control" value="<?php echo sanitize($item['title']); ?>" required>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary fw-500">Report Status</label>
                            <select name="status" class="form-select form-premium-control">
                                <option value="lost" <?php echo $item['status'] === 'lost' ? 'selected' : ''; ?>>Lost (Active)</option>
                                <option value="claimed" <?php echo $item['status'] === 'claimed' ? 'selected' : ''; ?>>Claimed (Resolved)</option>
                                <option value="cancelled" <?php echo $item['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Category -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select form-premium-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['_id']; ?>" <?php echo $item['category_id'] == $cat['_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Lost -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Date Lost <span class="text-danger">*</span></label>
                            <input type="date" name="lost_date" class="form-control form-premium-control" value="<?php echo $item['lost_date']; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Location Lost -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Approximate Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-premium-control" value="<?php echo sanitize($item['location']); ?>" required>
                        </div>

                        <!-- Reward -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Reward ($)</label>
                            <input type="number" step="0.01" name="reward" class="form-control form-premium-control" value="<?php echo $item['reward']; ?>" min="0">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Detailed Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-premium-control" rows="5" required><?php echo sanitize($item['description']); ?></textarea>
                    </div>

                    <!-- Existing Image Preview & Upload -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Item Image (Leave empty to keep current)</label>
                        <?php if (!empty($item['image'])): ?>
                            <div class="mb-3">
                                <span class="d-block text-muted mb-2" style="font-size: 0.85rem;">Current Image:</span>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image']; ?>" alt="Preview" class="rounded border" style="max-height: 150px; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <input class="form-control form-premium-control" type="file" name="image" accept="image/*">
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-premium px-5 py-3"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
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
