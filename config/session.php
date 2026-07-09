<?php
/**
 * CampusFind Pro - Session Security Manager
 * Handles secure session parameters, session timeout, hijacking checks, and role checks.
 */

// Load DB & configs if not already loaded (since session depends on config and database)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie params
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// 1. Session Hijacking Countermeasures
if (isset($_SESSION['user_id'])) {
    $userAgentHash = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown_agent');
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $userAgentHash;
    } elseif ($_SESSION['fingerprint'] !== $userAgentHash) {
        // IP or Agent changed during active session -> security violation
        destroySession();
        redirect('auth/login.php?error=hijack');
    }
}

// 2. Session Idle Timeout Check
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    $timeout = 1800; // 30 minutes in seconds
    
    // Check settings table if available
    try {
        $db = Database::getInstance();
        $res = $db->findOne('settings', ['setting_key' => 'session_timeout']);
        if ($res) {
            $timeout = (int)$res['setting_value'];
        }
    } catch (Exception $e) {
        // Fallback silently to 1800
    }

    if (time() - $_SESSION['last_activity'] > $timeout) {
        destroySession();
        redirect('auth/login.php?error=timeout');
    }
}
// Update activity timestamp
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

/**
 * Completely destroy session
 */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Require active authentication
 */
function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('auth/login.php');
    }
}

/**
 * Require specific user role (student, admin)
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        redirect('index.php?error=unauthorized');
    }
}

/**
 * Helper to check role checks directly
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStudent(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}
