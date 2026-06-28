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
                        // Simulate email notification
                        // Save simulated email to temporary file in uploads/mock_emails.log
                        $mock_email_dir = UPLOAD_PATH;
                        if (!is_dir($mock_email_dir)) {
                            mkdir($mock_email_dir, 0777, true);
                        }
                        $mock_email_file = $mock_email_dir . '/mock_emails.log';
                        $email_content = "[" . date('Y-m-d H:i:s') . "] To: $email\nSubject: CampusFind Pro Email Verification\nCode: $verification_code\n---------------------------------\n";
                        file_put_contents($mock_email_file, $email_content, FILE_APPEND);

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
