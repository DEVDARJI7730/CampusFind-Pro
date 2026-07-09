<?php
/**
 * CampusFind Pro - Claims Processing Action (Approve / Reject)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access: Admin Role Only
requireRole('admin');

$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = trim($_POST['claim_id'] ?? '');
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf)) {
        $_SESSION['admin_msg'] = 'Invalid security token.';
        $_SESSION['admin_msg_class'] = 'danger';
        redirect('admin/claims.php');
    }

    if (!$claim_id || !in_array($action, ['approve', 'reject'])) {
        $_SESSION['admin_msg'] = 'Invalid claim parameters.';
        $_SESSION['admin_msg_class'] = 'danger';
        redirect('admin/claims.php');
    }

    try {
        $db = Database::getInstance();
        
        // Fetch claim
        $claim = $db->findOne('claims', ['_id' => toObjectId($claim_id)]);

        if (!$claim) {
            $_SESSION['admin_msg'] = 'Claim request not found.';
            $_SESSION['admin_msg_class'] = 'danger';
            redirect('admin/claims.php');
        }

        // Fetch related item title to inject in notifications
        $item_title = 'Item';
        if ($claim['item_type'] === 'found') {
            $item = $db->findOne('found_items', ['_id' => toObjectId($claim['item_id'])]);
            if ($item) $item_title = $item['title'];
        } else {
            $item = $db->findOne('lost_items', ['_id' => toObjectId($claim['item_id'])]);
            if ($item) $item_title = $item['title'];
        }

        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // Update claim record
        $db->update('claims', ['_id' => toObjectId($claim_id)], [
            'status' => $new_status,
            'admin_notes' => $admin_notes ?: null,
            'processed_by' => toObjectId($admin_id),
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($action === 'approve') {
            // Update item status to claimed/returned
            if ($claim['item_type'] === 'found') {
                $db->update('found_items', ['_id' => toObjectId($claim['item_id'])], ['status' => 'claimed']);
            } else {
                $db->update('lost_items', ['_id' => toObjectId($claim['item_id'])], ['status' => 'claimed']);
            }

            // Notify Claimer
            $notif_title = 'Claim Approved: ' . $item_title;
            $notif_msg = "Congratulations! Your ownership claim for '$item_title' has been approved. Please visit the Campus Security Office (Gate 4) to collect it. Remarks: " . ($admin_notes ?: 'None');
            addNotification($claim['claimer_id'], $notif_title, $notif_msg);

            // Audit Log
            logActivity($admin_id, 'CLAIM_APPROVED', "Approved claim request #$claim_id for $item_title by student ID: " . $claim['claimer_id']);
            $_SESSION['admin_msg'] = "Claim successfully approved.";
            $_SESSION['admin_msg_class'] = 'success';
        } else {
            // Notify Claimer of rejection
            $notif_title = 'Claim Rejected: ' . $item_title;
            $notif_msg = "Your claim request for '$item_title' has been rejected. Reason/Remarks: " . ($admin_notes ?: 'Inadequate proof provided. Please contact Security Office for inquiries.');
            addNotification($claim['claimer_id'], $notif_title, $notif_msg);

            // Audit Log
            logActivity($admin_id, 'CLAIM_REJECTED', "Rejected claim request #$claim_id for $item_title by student ID: " . $claim['claimer_id']);
            $_SESSION['admin_msg'] = "Claim successfully rejected.";
            $_SESSION['admin_msg_class'] = 'warning';
        }
    } catch (Exception $e) {
        error_log("Claims processing exception: " . $e->getMessage());
        $_SESSION['admin_msg'] = 'Database processing error occurred.';
        $_SESSION['admin_msg_class'] = 'danger';
    }
}

redirect('admin/claims.php');
