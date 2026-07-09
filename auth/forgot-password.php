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
            $db = Database::getInstance();
            // Lookup user by verification_code (used here as reset token)
            $user = $db->findOne('users', ['verification_code' => $token]);

            if ($user) {
                $new_hash = password_hash($password, PASSWORD_BCRYPT);
                // Update password and clear token
                $db->update('users', ['_id' => $user['_id']], ['password' => $new_hash, 'verification_code' => null]);

                logActivity((string)$user['_id'], 'PASSWORD_RESET', 'User reset their password successfully.');
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
            $db = Database::getInstance();
            $user = $db->findOne('users', ['email' => $email]);

            if ($user) {
                // Generate secure reset token
                $reset_token = bin2hex(random_bytes(16));
                
                // Store in verification_code column temporarily
                $db->update('users', ['_id' => $user['_id']], ['verification_code' => $reset_token]);

                // Dispatch password reset email
                $reset_link = SITE_URL . '/auth/forgot-password.php?token=' . $reset_token;
                $subject = 'CampusFind Pro Password Reset Link';
                $messageHtml = "
                    <div style='font-family: Arial, sans-serif; line-height: 1.6; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                        <h2 style='color: #4f46e5; margin-bottom: 20px; font-weight: 800;'>Reset Your Password</h2>
                        <p style='color: #475569;'>You are receiving this email because you requested a password reset for your CampusFind Pro account.</p>
                        <p style='color: #475569;'>Click the button below to secure a new password:</p>
                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='$reset_link' style='background: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; display: inline-block;'>Reset Password</a>
                        </div>
                        <p style='color: #475569;'>If the button doesn't work, copy and paste this URL into your browser:</p>
                        <p style='word-break: break-all; font-size: 0.9rem;'><a href='$reset_link'>$reset_link</a></p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
                        <p style='font-size: 0.8rem; color: #64748b; line-height: 1.4;'>If you did not request a password reset, you can safely ignore this email.</p>
                    </div>
                ";
                sendSystemEmail($email, $subject, $messageHtml);

                $success = 'A password reset link has been dispatched to your email address.';
            } else {
                // To mitigate user enumeration, we still show the success message
                $success = 'A password reset link has been dispatched to your email address.';
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
