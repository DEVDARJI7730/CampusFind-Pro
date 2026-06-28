<?php
/**
 * CampusFind Pro - Edit Student Profile & Photo Upload
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
    $db = Database::getInstance()->getConnection();
    
    // Fetch fresh user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Profile query failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } else {
        // Mode 1: Edit Profile Details (Name, Phone)
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = 'Name cannot be empty.';
            } else {
                try {
                    $update_stmt = $db->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :uid");
                    $update_stmt->execute([
                        ':name' => $name,
                        ':phone' => $phone ?: null,
                        ':uid' => $user_id
                    ]);

                    $_SESSION['user_name'] = $name;
                    $success = 'Profile details updated successfully.';
                    logActivity($user_id, 'PROFILE_UPDATE', 'User updated name and phone settings.');
                    
                    // Refresh data
                    $stmt->execute([':uid' => $user_id]);
                    $user = $stmt->fetch();
                } catch (Exception $e) {
                    error_log("Profile update fail: " . $e->getMessage());
                    $error = 'Database error. Please try again.';
                }
            }
        }
        
        // Mode 2: Change Password
        elseif (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Incorrect current password.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                try {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $update_stmt = $db->prepare("UPDATE users SET password = :pass WHERE id = :uid");
                    $update_stmt->execute([
                        ':pass' => $new_hash,
                        ':uid' => $user_id
                    ]);

                    $success = 'Password changed successfully.';
                    logActivity($user_id, 'PASSWORD_CHANGE', 'User changed their account password.');
                } catch (Exception $e) {
                    error_log("Password update fail: " . $e->getMessage());
                    $error = 'Database error. Please try again.';
                }
            }
        }

        // Mode 3: Upload Profile Picture
        elseif (isset($_POST['upload_avatar'])) {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['avatar']['tmp_name'];
                $fileName = $_FILES['avatar']['name'];
                $fileSize = $_FILES['avatar']['size'];
                $fileType = $_FILES['avatar']['type'];
                
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                // Set constraints
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

                if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMimeTypes)) {
                    $error = 'Invalid image type. Only JPG, JPEG, PNG, and WEBP formats are accepted.';
                } elseif ($fileSize > MAX_IMAGE_SIZE) {
                    $error = 'Image exceeds maximum allowed size of 5MB.';
                } else {
                    // Unique filename to prevent collision
                    $newFileName = 'avatar-' . $user_id . '-' . time() . '.' . $fileExtension;
                    
                    if (!is_dir(UPLOAD_PATH)) {
                        mkdir(UPLOAD_PATH, 0777, true);
                    }
                    
                    $dest_path = UPLOAD_PATH . '/' . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        try {
                            // Delete old image if not default-avatar.png
                            if ($user['avatar'] !== 'default-avatar.png') {
                                $old_image_path = UPLOAD_PATH . '/' . $user['avatar'];
                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }

                            // Update DB
                            $update_stmt = $db->prepare("UPDATE users SET avatar = :avatar WHERE id = :uid");
                            $update_stmt->execute([
                                ':avatar' => $newFileName,
                                ':uid' => $user_id
                            ]);

                            $_SESSION['user_avatar'] = $newFileName;
                            $success = 'Profile picture updated successfully.';
                            logActivity($user_id, 'AVATAR_UPLOAD', 'User uploaded a new profile picture.');

                            // Refresh data
                            $stmt->execute([':uid' => $user_id]);
                            $user = $stmt->fetch();
                        } catch (Exception $e) {
                            error_log("Avatar db update failure: " . $e->getMessage());
                            $error = 'Failed to save avatar image in database.';
                        }
                    } else {
                        $error = 'Failed to write file to storage. Check folder permissions.';
                    }
                }
            } else {
                $error = 'No file was uploaded or file upload encountered an error.';
            }
        }
    }
}

$page_title = 'Edit Profile';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <div class="row g-4 justify-content-center" data-aos="fade-up">
        <div class="col-lg-10">
            <h1 class="font-heading fw-800 mb-2">Account Settings</h1>
            <p class="text-secondary mb-5">Manage your identity credentials, contact settings, and security passwords.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Photo Upload Column -->
        <div class="col-lg-3 col-md-4" data-aos="fade-right">
            <div class="glass-panel p-4 text-center">
                <h5 class="font-heading fw-700 mb-4">Profile Photo</h5>
                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo sanitize($user['avatar']); ?>" alt="Profile avatar" class="rounded-circle border border-primary border-3 mb-4 img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <input class="form-control form-control-sm form-premium-control" id="avatarInput" type="file" name="avatar" accept="image/*" required>
                    </div>
                    
                    <button type="submit" name="upload_avatar" class="btn btn-premium btn-sm w-100 py-2"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Photo</button>
                </form>
            </div>
        </div>

        <!-- Form Details Columns -->
        <div class="col-lg-7 col-md-8" data-aos="fade-left">
            <!-- Profile Details Form -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-regular fa-id-card text-primary me-2"></i>Edit Details</h5>
                
                <form action="profile.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <!-- Student ID (Read Only) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Student ID (Immutable)</label>
                            <input type="text" class="form-control form-premium-control bg-opacity-25" value="<?php echo sanitize($user['student_id']); ?>" readonly>
                        </div>
                        
                        <!-- Email (Read Only) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Email Address (Immutable)</label>
                            <input type="email" class="form-control form-premium-control bg-opacity-25" value="<?php echo sanitize($user['email']); ?>" readonly>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Full Name</label>
                            <input type="text" name="name" class="form-control form-premium-control" value="<?php echo sanitize($user['name']); ?>" required>
                        </div>
                        
                        <!-- Phone Number -->
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-secondary fw-500">Phone Number</label>
                            <input type="text" name="phone" class="form-control form-premium-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-premium px-4"><i class="fa-regular fa-floppy-disk me-2"></i>Save Details</button>
                </form>
            </div>

            <!-- Password Change Form -->
            <div class="glass-panel p-4">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Security Credentials</h5>
                
                <form action="profile.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Current Password</label>
                        <input type="password" name="current_password" class="form-control form-premium-control" placeholder="••••••••" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">New Password</label>
                            <input type="password" name="new_password" class="form-control form-premium-control" placeholder="Min. 8 characters" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-secondary fw-500">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control form-premium-control" placeholder="Retype password" required>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-premium px-4"><i class="fa-solid fa-key me-2"></i>Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
