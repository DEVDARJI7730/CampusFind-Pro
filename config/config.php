<?php
/**
 * CampusFind Pro - Configuration File
 * Contains environment constants, database credentials, and path definitions.
 */

// Load .env file natively if it exists (for local development or custom servers)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $val = trim($val, '"\''); // Strip surrounding quotes
            
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Core App Settings
define('APP_NAME', 'CampusFind Pro');
define('APP_VERSION', '1.0.0');
define('PRODUCTION_URL', 'https://campusfind-pro.onrender.com');

// Base URL (Dynamically detects subdirectories like XAMPP /CampusFind-Pro/ or cloud root domains, supports CLI execution)
if (php_sapi_name() === 'cli') {
    define('SITE_URL', 'http://localhost/CampusFind-Pro');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
                 || ($_SERVER['SERVER_PORT'] ?? 80) == 443 
                 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) 
                ? "https://" : "http://";
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
define('MONGODB_URI', getenv('MONGODB_URI') ?: 'mongodb+srv://YOUR_DB_USER:YOUR_DB_PASSWORD@cluster0.jpkg68b.mongodb.net/campusfind_pro?retryWrites=true&w=majority');
define('MONGODB_DB', getenv('MONGODB_DB') ?: 'campusfind_pro');

// Security Settings
define('CSRF_TOKEN_KEY', 'cf_csrf_token');

// Google OAuth Credentials
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google-callback.php');

// SMTP Server Configuration (for real email notifications)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
define('SMTP_USER', getenv('SMTP_USER') ?: 'YOUR_EMAIL_ADDRESS');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'YOUR_APP_PASSWORD');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl'); // 'tls' or 'ssl'

// Resend.com API Configuration (for HTTP-based email sending on cloud environments)
define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: 'YOUR_RESEND_API_KEY');
define('RESEND_FROM_EMAIL', getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev');

// Brevo.com API Configuration (for HTTP-based email sending to any friend/address)
define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: 'YOUR_BREVO_API_KEY');
define('BREVO_SENDER_EMAIL', getenv('BREVO_SENDER_EMAIL') ?: 'darjidev2504@gmail.com');
define('BREVO_SENDER_NAME', getenv('BREVO_SENDER_NAME') ?: 'CampusFind Pro');

// Image Configuration
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB
$ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

// Load helper functions in all files if needed
require_once ROOT_PATH . '/includes/helpers.php';
