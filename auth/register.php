<?php
/**
 * CampusFind Pro - Secure Authentication: Register
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard/index.php');
    }
}

$error = '';
$success = '';

// Processing POST Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    // Validate Input
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($student_id) || empty($name) || !$email || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields and enter a valid email.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email already exists
            $email_stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $email_stmt->execute([':email' => $email]);
            if ($email_stmt->fetch()) {
                $error = 'This email address is already registered.';
            } else {
                // Check if student ID already exists
                $sid_stmt = $db->prepare("SELECT id FROM users WHERE student_id = :sid LIMIT 1");
                $sid_stmt->execute([':sid' => $student_id]);
                if ($sid_stmt->fetch()) {
                    $error = 'This Student ID is already registered.';
                } else {
                    // Hash Password
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    
                    // Generate verification code
                    $verification_code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

                    // Get require_verification value
                    $req_verif_stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'require_verification' LIMIT 1");
                    $req_verif = $req_verif_stmt->fetch()['setting_value'] ?? '0';
                    $is_verified = ($req_verif === '1') ? 0 : 1;

                    // Insert user
                    $insert_stmt = $db->prepare("INSERT INTO users (student_id, name, email, password, phone, role, is_verified, verification_code, status) 
                                                 VALUES (:student_id, :name, :email, :password, :phone, 'student', :is_verified, :verif_code, 'active')");
                    $insert_stmt->execute([
                        ':student_id' => $student_id,
                        ':name' => $name,
                        ':email' => $email,
                        ':password' => $password_hash,
                        ':phone' => $phone ?: null,
                        ':is_verified' => $is_verified,
                        ':verif_code' => $verification_code
                    ]);

                    $user_id = $db->lastInsertId();

                    // Log activity
                    logActivity($user_id, 'REGISTER', 'New student registered successfully: ' . $email);

                    // Add dynamic welcoming notification
                    addNotification($user_id, 'Welcome to CampusFind Pro', "Hello $name, welcome to the platform! Start logging lost or found items instantly.");

                    if ($req_verif === '1') {
                        // Dispatch verification email notification
                        $subject = 'CampusFind Pro Email Verification';
                        $messageHtml = "
                            <div style='font-family: Arial, sans-serif; line-height: 1.6; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                                <h2 style='color: #4f46e5; margin-bottom: 20px; font-weight: 800;'>Welcome to CampusFind Pro!</h2>
                                <p style='color: #475569;'>Thank you for registering on our lost & found portal.</p>
                                <p style='color: #475569;'>Your OTP verification code is:</p>
                                <div style='background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: inline-block; letter-spacing: 2px; font-size: 1.6rem; font-weight: bold; color: #4f46e5; margin: 10px 0;'>$verification_code</div>
                                <p style='color: #475569;'>Please enter this code in the portal to verify your account.</p>
                                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
                                <p style='font-size: 0.8rem; color: #64748b; line-height: 1.4;'>If you did not register for an account, you can safely ignore this email.</p>
                            </div>
                        ";
                        sendSystemEmail($email, $subject, $messageHtml);

                        $_SESSION['verify_email'] = $email;
                        redirect('auth/verify.php');
                    } else {
                        // Directly redirect to login with success message
                        $_SESSION['success_msg'] = 'Registration completed! Please sign in below.';
                        redirect('auth/login.php');
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Registration script fail: " . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

// Generate Google Login URL
$oauth_state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $oauth_state;
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $oauth_state
]);

$page_title = 'Register';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 my-4 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-10" data-aos="zoom-in">
            <div class="glass-panel p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="fa-solid fa-user-plus fs-1 text-primary mb-3"></i>
                    <h3 class="font-heading fw-800">Student Registration</h3>
                    <p class="text-secondary" style="font-size: 0.9rem;">Join the lost & found portal to search and report items.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="register.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="row g-3">
                        <!-- Student ID -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Student ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-id-card-clip text-muted"></i></span>
                                <input type="text" name="student_id" class="form-control form-premium-control border-start-0 ps-0" placeholder="e.g. STU12345" value="<?php echo isset($_POST['student_id']) ? sanitize($_POST['student_id']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-regular fa-user text-muted"></i></span>
                                <input type="text" name="name" class="form-control form-premium-control border-start-0 ps-0" placeholder="John Doe" value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Campus Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-regular fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control form-premium-control border-start-0 ps-0" placeholder="john.doe@university.edu" value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Phone Number (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="text" name="phone" class="form-control form-premium-control border-start-0 ps-0" placeholder="+1 (555) 000-0000" value="<?php echo isset($_POST['phone']) ? sanitize($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-key text-muted"></i></span>
                                <input type="password" name="password" class="form-control form-premium-control border-start-0 ps-0" placeholder="Min. 8 characters" required>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary fw-500">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-check text-muted"></i></span>
                                <input type="password" name="confirm_password" class="form-control form-premium-control border-start-0 ps-0" placeholder="Retype password" required>
                            </div>
                        </div>
                    </div>

                    <!-- Terms checkbox -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="termsCheck" required>
                        <label class="form-check-label text-secondary" style="font-size: 0.85rem;" for="termsCheck">I agree to the university's Lost & Found Policies.</label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-premium w-100 py-3 mb-3"><i class="fa-solid fa-user-plus me-2"></i>Register Account</button>

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1 border-color">
                        <span class="px-2 text-muted" style="font-size: 0.8rem;">or</span>
                        <hr class="flex-grow-1 border-color">
                    </div>

                    <a href="<?php echo $google_login_url; ?>" class="btn btn-premium-outline w-100 py-3 mb-3 d-flex align-items-center justify-content-center gap-2" style="background: rgba(255, 255, 255, 0.4); border-color: var(--border-color); color: var(--text-primary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20px" height="20px">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.45-4.69H24v8.89h12.62c-.54 2.87-2.17 5.31-4.61 6.94l7.19 5.57c4.21-3.88 6.5-9.6 6.5-16.72z"/>
                            <path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.98-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.19-5.57c-2 1.34-4.55 2.13-7.7 2.13-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                        </svg>
                        Register with Google
                    </a>

                    <div class="text-center mt-3">
                        <span class="text-secondary" style="font-size: 0.9rem;">Already have an account? <a href="login.php" class="fw-600">Sign In</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
