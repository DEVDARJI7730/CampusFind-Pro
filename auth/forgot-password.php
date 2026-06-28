<?php
/**
 * CampusFind Pro - Secure Authentication: Forgot & Reset Password
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// 1. Process password reset form (when token is in URL)
if (!empty($token) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            // Lookup user by verification_code (used here as reset token)
            $stmt = $db->prepare("SELECT * FROM users WHERE verification_code = :token LIMIT 1");
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();

            if ($user) {
                $new_hash = password_hash($password, PASSWORD_BCRYPT);
                // Update password and clear token
                $update_stmt = $db->prepare("UPDATE users SET password = :pass, verification_code = NULL WHERE id = :uid");
                $update_stmt->execute([':pass' => $new_hash, ':uid' => $user['id']]);

                logActivity($user['id'], 'PASSWORD_RESET', 'User reset their password successfully.');
                $success = 'Your password has been reset successfully. You can now login.';
                // Clear token so form doesn't show again
                $token = '';
            } else {
                $error = 'Invalid or expired password reset link. Please request a new one.';
            }
        } catch (Exception $e) {
            error_log("Password reset fail: " . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}

// 2. Process forgot password request (sends reset email simulation)
if (empty($token) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token.';
    } elseif (!$email) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate secure reset token
                $reset_token = bin2hex(random_bytes(16));
                
                // Store in verification_code column temporarily
                $update_stmt = $db->prepare("UPDATE users SET verification_code = :token WHERE id = :uid");
                $update_stmt->execute([':token' => $reset_token, ':uid' => $user['id']]);

                // Simulate Email Notification
                $reset_link = SITE_URL . '/auth/forgot-password.php?token=' . $reset_token;
                
                $mock_email_dir = UPLOAD_PATH;
                if (!is_dir($mock_email_dir)) {
                    mkdir($mock_email_dir, 0777, true);
                }
                $mock_email_file = $mock_email_dir . '/mock_emails.log';
                $email_content = "[" . date('Y-m-d H:i:s') . "] To: $email\nSubject: CampusFind Pro Password Reset Link\nLink: $reset_link\n---------------------------------\n";
                file_put_contents($mock_email_file, $email_content, FILE_APPEND);

                $success = 'A password reset link has been generated. Check `uploads/mock_emails.log` to access it.';
            } else {
                // To mitigate user enumeration, we still show the success message
                $success = 'A password reset link has been generated. Check `uploads/mock_emails.log` to access it.';
            }
        } catch (Exception $e) {
            error_log("Forgot password fail: " . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}

$page_title = empty($token) ? 'Forgot Password' : 'Reset Password';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8" data-aos="zoom-in">
            <div class="glass-panel p-4 p-md-5">
                
                <?php if (empty($token)): ?>
                    <!-- Request Reset Link Section -->
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-circle-question fs-1 text-primary mb-3"></i>
                        <h3 class="font-heading fw-800">Forgot Password?</h3>
                        <p class="text-secondary" style="font-size: 0.9rem;">Enter your email to receive a password reset link simulation.</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form action="forgot-password.php" method="POST" onsubmit="return validateForm(this)">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-4">
                            <label class="form-label text-secondary fw-500">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-regular fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control form-premium-control border-start-0 ps-0" placeholder="name@university.edu" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-premium w-100 py-3 mb-3"><i class="fa-solid fa-paper-plane me-2"></i>Send Reset Link</button>

                        <div class="text-center mt-3">
                            <span class="text-secondary" style="font-size: 0.9rem;">Back to <a href="login.php" class="fw-600">Sign In</a></span>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Enter New Password Section (Token present) -->
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-key fs-1 text-primary mb-3"></i>
                        <h3 class="font-heading fw-800">Reset Password</h3>
                        <p class="text-secondary" style="font-size: 0.9rem;">Create a strong, secure new password for your account.</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form action="forgot-password.php?token=<?php echo sanitize($token); ?>" method="POST" onsubmit="return validateForm(this)">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-500">New Password</label>
                            <input type="password" name="password" class="form-control form-premium-control" placeholder="At least 8 characters" required>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-500">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control form-premium-control" placeholder="Retype password" required>
                        </div>

                        <button type="submit" class="btn btn-premium w-100 py-3 mb-3"><i class="fa-solid fa-check me-2"></i>Save Password</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
