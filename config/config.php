<?php
/**
 * CampusFind Pro - Configuration File
 * Contains environment constants, database credentials, and path definitions.
 */

// Core App Settings
define('APP_NAME', 'CampusFind Pro');
define('APP_VERSION', '1.0.0');

// Base URL (Dynamically detects subdirectories like XAMPP /CampusFind-Pro/ or cloud root domains, supports CLI execution)
if (php_sapi_name() === 'cli') {
    define('SITE_URL', 'http://localhost/CampusFind-Pro');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dirName = dirname($scriptName);
    $subDir = ($dirName === '/' || $dirName === '\\') ? '' : rtrim(str_replace('\\', '/', $dirName), '/');
    if (preg_match('#/(auth|dashboard|lost|found|claims|notifications|admin)$#', $subDir)) {
        $subDir = dirname($subDir);
    }
    $subDir = ($subDir === '/' || $subDir === '\\') ? '' : $subDir;
    define('SITE_URL', $protocol . $domainName . $subDir);
}

// Path Definitions
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');

// MongoDB Atlas Credentials (support environment variables for cloud deployments like Render)
define('MONGODB_URI', getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017');
define('MONGODB_DB', getenv('MONGODB_DB') ?: 'campusfind_pro');

// Security Settings
define('CSRF_TOKEN_KEY', 'cf_csrf_token');

// Google OAuth Credentials
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google-callback.php');

// SMTP Server Configuration (for real email notifications)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'YOUR_EMAIL_ADDRESS');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'YOUR_APP_PASSWORD');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // 'tls' or 'ssl'

// Image Configuration
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB
$ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

// Load helper functions in all files if needed
require_once ROOT_PATH . '/includes/helpers.php';
