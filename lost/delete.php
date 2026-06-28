<?php
/**
 * CampusFind Pro - Secure Delete Lost Item Report
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$item_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$item_id) {
    redirect('dashboard/index.php');
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify ownership
    $stmt = $db->prepare("SELECT image, title FROM lost_items WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $item_id, ':uid' => $user_id]);
    $item = $stmt->fetch();

    if ($item) {
        // Delete image file from upload folder
        if (!empty($item['image'])) {
            $image_path = UPLOAD_PATH . '/' . $item['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM lost_items WHERE id = :id");
        $delete_stmt->execute([':id' => $item_id]);

        logActivity($user_id, 'DELETE_LOST_ITEM', 'Deleted lost item report: ' . $item['title']);
        $_SESSION['success_msg'] = 'Lost report deleted successfully!';
    } else {
        $_SESSION['success_msg'] = 'Unauthorized access or item not found.';
    }
} catch (Exception $e) {
    error_log("Lost item deletion failure: " . $e->getMessage());
    $_SESSION['success_msg'] = 'System error occurred while deleting.';
}

redirect('dashboard/index.php');
