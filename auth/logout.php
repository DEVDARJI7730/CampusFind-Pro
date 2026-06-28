<?php
/**
 * CampusFind Pro - Secure Authentication: Logout
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';

if (isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    logActivity($uid, 'LOGOUT', 'User logged out of the system.');
}

destroySession();
redirect('index.php');
