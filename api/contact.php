<?php
/**
 * CampusFind Pro - Contact Support Form API
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Read raw post body
$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$message = trim($input['message'] ?? '');

if (empty($name) || !$email || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid name, email, and message body.']);
    exit;
}

try {
    $db = Database::getInstance();
    $db->insert('support_messages', [
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'status' => 'unread',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode(['success' => true, 'message' => 'Support message recorded successfully.']);
} catch (Exception $e) {
    error_log("Contact API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database operation failed. Please try again later.']);
}
