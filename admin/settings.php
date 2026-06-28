<?php
/**
 * CampusFind Pro - Admin Settings Configuration Console
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];
$msg = '';
$msg_class = 'success';

try {
    $db = Database::getInstance()->getConnection();

    // 1. Process Settings Form Submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        
        if (!validateCSRFToken($csrf)) {
            $msg = 'Invalid security token.';
            $msg_class = 'danger';
        } else {
            // Retrieve key-value pairs
            $settings_to_update = [
                'site_name' => trim($_POST['site_name'] ?? 'CampusFind Pro'),
                'contact_email' => filter_var($_POST['contact_email'] ?? '', FILTER_VALIDATE_EMAIL),
                'session_timeout' => filter_var($_POST['session_timeout'] ?? 1800, FILTER_VALIDATE_INT),
                'require_verification' => isset($_POST['require_verification']) ? '1' : '0'
            ];

            if (!$settings_to_update['contact_email']) {
                $msg = 'Please enter a valid contact email.';
                $msg_class = 'danger';
            } elseif (!$settings_to_update['session_timeout'] || $settings_to_update['session_timeout'] < 60) {
                $msg = 'Session timeout must be at least 60 seconds.';
                $msg_class = 'danger';
            } else {
                // Loop update queries
                $update_stmt = $db->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = :key");
                
                foreach ($settings_to_update as $key => $value) {
                    $update_stmt->execute([
                        ':val' => (string)$value,
                        ':key' => $key
                    ]);
                }

                logActivity($admin_id, 'SYSTEM_SETTINGS_UPDATE', 'Admin updated platform configurations settings.');
                $msg = 'System configurations updated successfully.';
                $msg_class = 'success';
            }
        }
    }

    // 2. Fetch current settings keys
    $settings_stmt = $db->query("SELECT * FROM settings");
    $settings_raw = $settings_stmt->fetchAll();
    
    // Key-map values
    $config_settings = [];
    foreach ($settings_raw as $set) {
        $config_settings[$set['setting_key']] = $set['setting_value'];
    }

} catch (Exception $e) {
    error_log("Settings query failure: " . $e->getMessage());
    $config_settings = [];
    $msg = 'Database connectivity issue.';
    $msg_class = 'danger';
}

$page_title = 'System Configuration';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Admin Navigation Tabs -->
    <div class="row mb-5" data-aos="fade-up">
        <div class="col-12">
            <div class="glass-panel p-3">
                <ul class="nav nav-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="dashboard.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="users.php"><i class="fa-solid fa-users me-2"></i>Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="items.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="claims.php"><i class="fa-solid fa-handshake-angle me-2"></i>Claims</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="reports.php"><i class="fa-solid fa-file-invoice me-2"></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-600 px-4 py-2" href="settings.php"><i class="fa-solid fa-gears me-2"></i>Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="logs.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>System Logs</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <h2 class="font-heading fw-800 m-0">Platform Settings</h2>
            <p class="text-secondary m-0">Manage global lost & found business logics, email verification requirements, and security timeouts.</p>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Settings form panels -->
    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="glass-panel p-4 p-md-5">
                <h5 class="font-heading fw-700 mb-4 text-primary"><i class="fa-solid fa-sliders me-2"></i>System Parameters</h5>
                
                <form action="settings.php" method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Site Name -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Platform Portal Name</label>
                        <input type="text" name="site_name" class="form-control form-premium-control" value="<?php echo sanitize($config_settings['site_name'] ?? 'CampusFind Pro'); ?>" required>
                    </div>

                    <!-- Contact Email -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Global Support Contact Email</label>
                        <input type="email" name="contact_email" class="form-control form-premium-control" value="<?php echo sanitize($config_settings['contact_email'] ?? 'support@campusfindpro.edu'); ?>" required>
                    </div>

                    <!-- Session Timeout -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Session Idle Timeout (Seconds)</label>
                        <input type="number" name="session_timeout" class="form-control form-premium-control" value="<?php echo sanitize($config_settings['session_timeout'] ?? '1800'); ?>" min="60" required>
                        <div class="form-text text-muted" style="font-size: 0.8rem;">Forces automatic student logout if inactive for specified period. (Default 1800s = 30 minutes).</div>
                    </div>

                    <!-- Require Email verification -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" name="require_verification" class="form-check-input" id="requireVerif" <?php echo ($config_settings['require_verification'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-secondary fw-600" for="requireVerif">Require Student Email Verification</label>
                        <div class="form-text text-muted" style="font-size: 0.8rem;">If checked, newly registered students must enter the 6-digit confirmation code simulation before accessing the dashboard.</div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-premium px-5 py-3 mt-3"><i class="fa-regular fa-floppy-disk me-2"></i>Save Configuration</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
