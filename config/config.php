<?php
/**
 * CampusFind Pro - Configuration File
 * Contains environment constants, database credentials, and path definitions.
 */

// ==========================================
// Core App Settings
// ==========================================
define('APP_NAME', 'CampusFind Pro');
define('APP_VERSION', '1.0.0');

// ==========================================
// Base URL
// ==========================================
$protocol = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
) ? "https://" : "http://";

$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';

define('SITE_URL', $protocol . $domainName . '/CampusFind-Pro');

// ==========================================
// Path Definitions
// ==========================================
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');

// ==========================================
// Database Credentials
// ==========================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'campusfind_pro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ==========================================
// Security
// ==========================================
define('CSRF_TOKEN_KEY', 'cf_csrf_token');

// ==========================================
// Image Upload Settings
// ==========================================
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB

$ALLOWED_IMAGE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'image/webp'
];

// ==========================================
// Load Helper Functions
// ==========================================
require_once ROOT_PATH . '/includes/helpers.php';