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
    $claim_id = filter_var($_POST['claim_id'] ?? '', FILTER_VALIDATE_INT);
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
        $db = Database::getInstance()->getConnection();
        
        // Fetch claim
        $claim_stmt = $db->prepare("SELECT * FROM claims WHERE id = :id LIMIT 1");
        $claim_stmt->execute([':id' => $claim_id]);
        $claim = $claim_stmt->fetch();

        if (!$claim) {
            $_SESSION['admin_msg'] = 'Claim request not found.';
            $_SESSION['admin_msg_class'] = 'danger';
            redirect('admin/claims.php');
        }

        // Fetch related item title to inject in notifications
        $item_title = 'Item';
        if ($claim['item_type'] === 'found') {
            $item_stmt = $db->prepare("SELECT title FROM found_items WHERE id = :iid LIMIT 1");
            $item_stmt->execute([':iid' => $claim['item_id']]);
            $item = $item_stmt->fetch();
            if ($item) $item_title = $item['title'];
        } else {
            $item_stmt = $db->prepare("SELECT title FROM lost_items WHERE id = :iid LIMIT 1");
            $item_stmt->execute([':iid' => $claim['item_id']]);
            $item = $item_stmt->fetch();
            if ($item) $item_title = $item['title'];
        }

        // Start Transaction for consistency
        $db->beginTransaction();

        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // Update claim record
        $update_claim = $db->prepare("
            UPDATE claims 
            SET status = :status, admin_notes = :notes, processed_by = :admin, processed_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $update_claim->execute([
            ':status' => $new_status,
            ':notes' => $admin_notes ?: null,
            ':admin' => $admin_id,
            ':id' => $claim_id
        ]);

        if ($action === 'approve') {
            // Update item status to claimed/returned
            if ($claim['item_type'] === 'found') {
                $update_item = $db->prepare("UPDATE found_items SET status = 'claimed' WHERE id = :iid");
            } else {
                $update_item = $db->prepare("UPDATE lost_items SET status = 'claimed' WHERE id = :iid");
            }
            $update_item->execute([':iid' => $claim['item_id']]);

            // Notify Claimer
            $notif_title = 'Claim Approved: ' . $item_title;
            $notif_msg = "Congratulations! Your ownership claim for '$item_title' has been approved. Please visit the Campus Security Office (Gate 4) to collect it. Remarks: " . ($admin_notes ?: 'None');
            addNotification($claim['claimer_id'], $notif_title, $notif_msg);

            // Audit Log
            logActivity($admin_id, 'CLAIM_APPROVED', "Approved claim request #$claim_id for $item_title by student ID: " . $claim['claimer_id']);
            $_SESSION['admin_msg'] = "Claim #$claim_id successfully approved.";
            $_SESSION['admin_msg_class'] = 'success';
        } else {
            // Notify Claimer of rejection
            $notif_title = 'Claim Rejected: ' . $item_title;
            $notif_msg = "Your claim request for '$item_title' has been rejected. Reason/Remarks: " . ($admin_notes ?: 'Inadequate proof provided. Please contact Security Office for inquiries.');
            addNotification($claim['claimer_id'], $notif_title, $notif_msg);

            // Audit Log
            logActivity($admin_id, 'CLAIM_REJECTED', "Rejected claim request #$claim_id for $item_title by student ID: " . $claim['claimer_id']);
            $_SESSION['admin_msg'] = "Claim #$claim_id successfully rejected.";
            $_SESSION['admin_msg_class'] = 'warning';
        }

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Claims processing exception: " . $e->getMessage());
        $_SESSION['admin_msg'] = 'Database transaction error occurred.';
        $_SESSION['admin_msg_class'] = 'danger';
    }
}

redirect('admin/claims.php');
