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
function sanitize(?string $data): string {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Get dynamic URL for user profile picture (supports both local uploads and remote Google OAuth URLs)
 */
function getAvatarUrl(?string $avatar): string {
    if (empty($avatar)) {
        return SITE_URL . '/uploads/default-avatar.png';
    }
    if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
        return $avatar;
    }
    return SITE_URL . '/uploads/' . $avatar;
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
function logActivity($userId, string $action, string $description): bool {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $db->insert('activity_logs', [
            'user_id' => $userId ? (string)$userId : null,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    } catch (Exception $e) {
        // Fallback silently to prevent blocking runtime if DB fails
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a notification to a specific user
 */
function addNotification($userId, string $title, string $message): bool {
    try {
        $db = Database::getInstance();
        $db->insert('notifications', [
            'user_id' => (string)$userId,
            'title' => $title,
            'message' => $message,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Sends a system email utilizing SMTP configurations, falling back to local files if keys are absent or fail.
 */
function sendSystemEmail(string $to, string $subject, string $messageHtml): bool {
    // 1. If Brevo.com API Key is configured, use Brevo's HTTP REST API (never blocked on Render, allows sending to anyone)
    $brevoApiKey = getenv('BREVO_API_KEY') ?: (defined('BREVO_API_KEY') ? BREVO_API_KEY : '');
    if (!empty($brevoApiKey) && $brevoApiKey !== 'YOUR_BREVO_API_KEY') {
        try {
            $senderEmail = getenv('BREVO_SENDER_EMAIL') ?: (defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : 'darjidev2504@gmail.com');
            $senderName = getenv('BREVO_SENDER_NAME') ?: (defined('BREVO_SENDER_NAME') ? BREVO_SENDER_NAME : 'CampusFind Pro');
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'sender' => [
                    'name' => $senderName,
                    'email' => $senderEmail
                ],
                'to' => [
                    ['email' => $to]
                ],
                'subject' => $subject,
                'htmlContent' => $messageHtml
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'api-key: ' . $brevoApiKey,
                'Content-Type: application/json',
                'accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
                return true;
            }
            throw new Exception("Brevo API returned status $http_code. Response: $response");
        } catch (Exception $e) {
            error_log("Brevo API dispatch failed: " . $e->getMessage());
            return writeToMockEmailLog($to, $subject, $messageHtml, "Brevo API Failure: " . $e->getMessage());
        }
    }

    // 2. If Resend.com API Key is configured, use Resend's HTTP REST API (never blocked on Render)
    $resendApiKey = getenv('RESEND_API_KEY') ?: (defined('RESEND_API_KEY') ? RESEND_API_KEY : '');
    if (!empty($resendApiKey) && $resendApiKey !== 'YOUR_RESEND_API_KEY') {
        try {
            $fromEmail = getenv('RESEND_FROM_EMAIL') ?: (defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'onboarding@resend.dev');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'from' => 'CampusFind Pro <' . $fromEmail . '>',
                'to' => [$to],
                'subject' => $subject,
                'html' => $messageHtml
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $resendApiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 || $http_code === 201) {
                return true;
            }
            throw new Exception("Resend API returned status $http_code. Response: $response");
        } catch (Exception $e) {
            error_log("Resend API dispatch failed: " . $e->getMessage());
            return writeToMockEmailLog($to, $subject, $messageHtml, "Resend API Failure: " . $e->getMessage());
        }
    }

    // 2. Fallback to standard SMTP Server if configured
    if (empty(SMTP_USER) || empty(SMTP_PASS) || SMTP_USER === 'YOUR_EMAIL_ADDRESS') {
        return writeToMockEmailLog($to, $subject, $messageHtml, "No SMTP credentials configured");
    }

    try {
        require_once __DIR__ . '/mail.php';
        $mailer = new SimpleSMTP(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE);
        return $mailer->send($to, $subject, $messageHtml);
    } catch (Exception $e) {
        error_log("SMTP dispatch failed: " . $e->getMessage());
        // Fallback to mock log so system doesn't crash during network/credential failures
        return writeToMockEmailLog($to, $subject, $messageHtml, "SMTP Failure: " . $e->getMessage());
    }
}

/**
 * Utility helper to append mock emails to uploads/mock_emails.log
 */
function writeToMockEmailLog(string $to, string $subject, string $messageHtml, string $reason): bool {
    $mock_email_dir = UPLOAD_PATH;
    if (!is_dir($mock_email_dir)) {
        @mkdir($mock_email_dir, 0777, true);
    }
    $mock_email_file = $mock_email_dir . '/mock_emails.log';
    
    // Strip HTML tags for clean log file viewing
    $plainText = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $messageHtml));
    
    $email_content = "[" . date('Y-m-d H:i:s') . "] [FALLBACK REASON: $reason]\n";
    $email_content .= "To: $to\n";
    $email_content .= "Subject: $subject\n";
    $email_content .= "Body:\n$plainText\n";
    $email_content .= "---------------------------------\n\n";
    
    return @file_put_contents($mock_email_file, $email_content, FILE_APPEND) !== false;
}
