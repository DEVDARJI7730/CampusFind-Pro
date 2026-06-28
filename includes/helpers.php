<?php
/**
 * CampusFind Pro - Helper Functions
 * General helpers for security, sanitization, logging, notifications, and redirections.
 */

// Start session if not already started (but let session.php handle secure lifecycle)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize user input to prevent XSS attacks
 */
function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token and store in session
 */
function generateCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Validate CSRF Token
 */
function validateCSRFToken(?string $token): bool {
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

/**
 * Secure Redirect
 */
function redirect(string $path): void {
    // If path is a relative path starting with / we prefix SITE_URL
    if (strpos($path, 'http') !== 0) {
        $path = SITE_URL . '/' . ltrim($path, '/');
    }
    header("Location: " . $path);
    exit;
}

/**
 * Flash Message Handler (Session Based)
 */
function flash(string $name = '', string $message = '', string $class = 'success'): ?string {
    if (!empty($name)) {
        if (!empty($message)) {
            if (isset($_SESSION[$name])) {
                unset($_SESSION[$name]);
            }
            if (isset($_SESSION[$name . '_class'])) {
                unset($_SESSION[$name . '_class']);
            }
            $_SESSION[$name] = $message;
            $_SESSION[$name . '_class'] = $class;
            return null;
        } elseif (isset($_SESSION[$name])) {
            $msgClass = $_SESSION[$name . '_class'] ?? 'success';
            $msg = $_SESSION[$name];
            unset($_SESSION[$name]);
            unset($_SESSION[$name . '_class']);
            return '<div class="alert alert-' . $msgClass . ' alert-dismissible fade show" role="alert">' . $msg . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    }
    return null;
}

/**
 * Format date helper
 */
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

/**
 * Generate a random unique QR token
 */
function generateQRToken(): string {
    return 'QR-' . strtoupper(bin2hex(random_bytes(8)));
}

/**
 * Log user/system activity into DB
 */
function logActivity(?int $userId, string $action, string $description): bool {
    try {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip)");
        return $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':description' => $description,
            ':ip' => $ip
        ]);
    } catch (Exception $e) {
        // Fallback silently to prevent blocking runtime if DB fails
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a notification to a specific user
 */
function addNotification(int $userId, string $title, string $message): bool {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (:user_id, :title, :message)");
        return $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message
        ]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}
