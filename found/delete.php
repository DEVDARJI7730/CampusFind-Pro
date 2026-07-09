<?php
/**
 * CampusFind Pro - Secure Delete Found Item Report
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$item_id = trim($_GET['id'] ?? '');

if (!$item_id) {
    redirect('dashboard/index.php');
}

try {
    $db = Database::getInstance();

    // Verify ownership
    $item = $db->findOne('found_items', ['_id' => toObjectId($item_id), 'user_id' => toObjectId($user_id)]);

    if ($item) {
        // Delete image file from upload folder
        if (!empty($item['image'])) {
            $image_path = UPLOAD_PATH . '/' . $item['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // Delete from database
        $db->delete('found_items', ['_id' => toObjectId($item_id)]);

        logActivity($user_id, 'DELETE_FOUND_ITEM', 'Deleted found item report: ' . $item['title']);
        $_SESSION['success_msg'] = 'Found item report deleted successfully!';
    } else {
        $_SESSION['success_msg'] = 'Unauthorized access or item not found.';
    }
} catch (Exception $e) {
    error_log("Found item deletion failure: " . $e->getMessage());
    $_SESSION['success_msg'] = 'System error occurred while deleting.';
}

redirect('dashboard/index.php');
