<?php
/**
 * CampusFind Pro - Secure Authentication: Verify Account
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// If user is already verified and logged in, redirect
if (isLoggedIn()) {
    redirect('dashboard/index.php');
}

$email = $_SESSION['verify_email'] ?? '';
if (empty($email)) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        try {
            $db = Database::getInstance();
            $user = $db->findOne('users', ['email' => $email]);

            if ($user && $user['verification_code'] === $code) {
                // Update verification status
                $db->update('users', ['_id' => $user['_id']], ['is_verified' => 1, 'verification_code' => null]);

                $userIdStr = (string)$user['_id'];
                // Create session
                $_SESSION['user_id'] = $userIdStr;
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = $user['avatar'];
                $_SESSION['last_activity'] = time();

                // Log activity
                logActivity($userIdStr, 'VERIFY_EMAIL', 'Email verified successfully.');

                unset($_SESSION['verify_email']);
                redirect('dashboard/index.php');
            } else {
                $error = 'Incorrect verification code. Please check your email inbox or simulated log.';
            }
        } catch (Exception $e) {
            error_log("Verification script fail: " . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}

$page_title = 'Verify Email';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8" data-aos="zoom-in">
            <div class="glass-panel p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="fa-regular fa-envelope-open fs-1 text-primary mb-3"></i>
                    <h3 class="font-heading fw-800">Verify Your Email</h3>
                    <p class="text-secondary" style="font-size: 0.9rem;">
                        We simulated sending a 6-digit code to <strong><?php echo sanitize($email); ?></strong>.<br>
                        Check the simulated log file: <br>
                        <code class="d-block mt-2 bg-dark text-white p-2 rounded" style="font-size: 0.8rem;">uploads/mock_emails.log</code>
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="verify.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Verification Code -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Verification Code</label>
                        <input type="text" name="code" class="form-control form-premium-control text-center fs-4 fw-800 letter-spacing-5" placeholder="000000" maxlength="6" required>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-premium w-100 py-3 mb-3"><i class="fa-solid fa-user-check me-2"></i>Verify Account</button>

                    <div class="text-center mt-3">
                        <span class="text-secondary" style="font-size: 0.9rem;">Wrong email? <a href="register.php" class="fw-600">Register with another</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
