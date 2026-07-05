<?php
/**
 * CampusFind Pro - Secure Authentication: Login
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

// Handle redirect url
$redirect_to = $_SESSION['redirect_url'] ?? '';

// Catch potential session messages
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'timeout') {
        $error = 'Your session has expired due to inactivity. Please log in again.';
    } elseif ($_GET['error'] === 'hijack') {
        $error = 'Security warning: Session mismatch. Please re-authenticate.';
    }
}

// Processing POST Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF
    if (!validateCSRFToken($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (!$email || empty($password)) {
        $error = 'Please enter a valid email address and password.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $_POST['email']]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been suspended. Please contact admin.';
                } else {
                    // Check email verification config
                    $req_verif_stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'require_verification' LIMIT 1");
                    $req_verif = $req_verif_stmt->fetch()['setting_value'] ?? '0';

                    if ($req_verif === '1' && $user['is_verified'] == 0) {
                        $_SESSION['verify_email'] = $user['email'];
                        redirect('auth/verify.php');
                    }

                    // Successful login: Establish session keys
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_avatar'] = $user['avatar'];
                    $_SESSION['last_activity'] = time();

                    // Log activity
                    logActivity($user['id'], 'LOGIN', 'User successfully logged into the system.');

                    // Admin specific updates
                    if ($user['role'] === 'admin') {
                        $admin_stmt = $db->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE user_id = :uid");
                        $admin_stmt->execute([':uid' => $user['id']]);
                        
                        $_SESSION['admin_level'] = 'moderator';
                        $admin_info_stmt = $db->prepare("SELECT admin_level FROM admins WHERE user_id = :uid LIMIT 1");
                        $admin_info_stmt->execute([':uid' => $user['id']]);
                        $admin_info = $admin_info_stmt->fetch();
                        if ($admin_info) {
                            $_SESSION['admin_level'] = $admin_info['admin_level'];
                        }

                        redirect('admin/dashboard.php');
                    } else {
                        // Redirect student to dashboard or previous URL
                        if (!empty($redirect_to)) {
                            unset($_SESSION['redirect_url']);
                            header("Location: " . $redirect_to);
                            exit;
                        }
                        redirect('dashboard/index.php');
                    }
                }
            } else {
                // To mitigate timing attacks, we use the same response for wrong user or wrong pass
                $error = 'Invalid email or password.';
                logActivity(null, 'LOGIN_FAILED', 'Failed login attempt for: ' . sanitize($_POST['email'] ?? 'unknown'));
            }
        } catch (Exception $e) {
            error_log("Login script fail: " . $e->getMessage());
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

$page_title = 'Sign In';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8" data-aos="zoom-in">
            <div class="glass-panel p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="fa-solid fa-lock fs-1 text-primary mb-3"></i>
                    <h3 class="font-heading fw-800">Welcome Back</h3>
                    <p class="text-secondary" style="font-size: 0.9rem;">Sign in to manage your lost & found reports.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert"><?php echo $success; ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Email Input -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-regular fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control form-premium-control border-start-0 ps-0" placeholder="name@university.edu" value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <label class="form-label text-secondary fw-500 m-0">Password</label>
                            <a href="forgot-password.php" class="small text-decoration-none">Forgot Password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" name="password" class="form-control form-premium-control border-start-0 ps-0" placeholder="••••••••" required>
                        </div>
                    </div>

                    <!-- Remember me / Role toggles -->
                    <div class="mb-4 form-check d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label text-secondary" style="font-size: 0.85rem;" for="rememberMe">Remember Session</label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-premium w-100 py-3 mb-3"><i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Sign In</button>

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
                        Sign In with Google
                    </a>

                    <div class="text-center mt-3">
                        <span class="text-secondary" style="font-size: 0.9rem;">New student? <a href="register.php" class="fw-600">Register account</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
