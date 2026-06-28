<?php
/**
 * CampusFind Pro - Admin: User Management console
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];
$msg = '';
$msg_class = 'success';

try {
    $db = Database::getInstance()->getConnection();

    // 1. Process Actions (Activate / Suspend / Delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $target_user_id = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!validateCSRFToken($csrf)) {
            $msg = 'Invalid security token.';
            $msg_class = 'danger';
        } elseif ($target_user_id) {
            if ($action === 'toggle_status') {
                // Fetch current status
                $status_stmt = $db->prepare("SELECT status, name FROM users WHERE id = :uid LIMIT 1");
                $status_stmt->execute([':uid' => $target_user_id]);
                $user = $status_stmt->fetch();

                if ($user) {
                    $new_status = ($user['status'] === 'active') ? 'suspended' : 'active';
                    $update_stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :uid");
                    $update_stmt->execute([':status' => $new_status, ':uid' => $target_user_id]);

                    logActivity($admin_id, 'USER_STATUS_CHANGE', "Toggled status of user " . $user['name'] . " to $new_status.");
                    $msg = "User status updated to $new_status successfully.";
                    $msg_class = 'success';
                }
            } elseif ($action === 'delete') {
                // Delete user
                $name_stmt = $db->prepare("SELECT name FROM users WHERE id = :uid LIMIT 1");
                $name_stmt->execute([':uid' => $target_user_id]);
                $uname = $name_stmt->fetch()['name'] ?? 'Unknown';

                $delete_stmt = $db->prepare("DELETE FROM users WHERE id = :uid");
                $delete_stmt->execute([':uid' => $target_user_id]);

                logActivity($admin_id, 'USER_DELETED', "Permanently deleted user: $uname");
                $msg = "User record permanently deleted.";
                $msg_class = 'danger';
            }
        }
    }

    // 2. Search & Fetch Students list
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM users WHERE role = 'student'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE :search OR email LIKE :search OR student_id LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Admin user management query failure: " . $e->getMessage());
    $users = [];
    $msg = 'Database connection issue.';
    $msg_class = 'danger';
}

$page_title = 'Manage Students';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Admin Navigation Tabs -->
    <div class="row mb-5" data-aos="fade-up">
        <div class="col-12">
            <div class="glass-panel p-3">
                <ul class="nav nav-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="dashboard.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-600 px-4 py-2" href="users.php"><i class="fa-solid fa-users me-2"></i>Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="items.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="claims.php"><i class="fa-solid fa-handshake-angle me-2"></i>Claims</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="reports.php"><i class="fa-solid fa-file-invoice me-2"></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="settings.php"><i class="fa-solid fa-gears me-2"></i>Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="logs.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>System Logs</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2" data-aos="fade-up">
        <div>
            <h2 class="font-heading fw-800 m-0">Student Registry</h2>
            <p class="text-secondary m-0">Moderate student accounts, toggle access restrictions, or remove invalid users.</p>
        </div>
        
        <!-- Search bar -->
        <form action="users.php" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-premium-control" placeholder="Search ID, name, email..." value="<?php echo sanitize($search); ?>">
            <button type="submit" class="btn btn-premium"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Students Table -->
    <div class="glass-panel p-4" data-aos="fade-up">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-users-slash fs-2 mb-2"></i>
                <p class="m-0">No registered students found matching query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="text-muted" style="font-size: 0.85rem;">
                            <th>Student Photo</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Status</th>
                            <th>Joined On</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.9rem;">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo sanitize($user['avatar']); ?>" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;" alt="Avatar">
                                </td>
                                <td class="fw-700 text-primary"><?php echo sanitize($user['student_id'] ?: 'N/A'); ?></td>
                                <td class="fw-600"><?php echo sanitize($user['name']); ?></td>
                                <td><?php echo sanitize($user['email']); ?></td>
                                <td>
                                    <?php 
                                    $st_badge = 'bg-success bg-opacity-10 text-success border-success';
                                    if ($user['status'] === 'suspended') {
                                        $st_badge = 'bg-danger bg-opacity-10 text-danger border-danger';
                                    } elseif ($user['status'] === 'pending') {
                                        $st_badge = 'bg-warning bg-opacity-10 text-warning border-warning';
                                    }
                                    ?>
                                    <span class="badge <?php echo $st_badge; ?> border px-2 py-1">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td class="text-end">
                                    <form action="users.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        
                                        <!-- Suspend / Activate Toggle -->
                                        <button type="submit" name="action" value="toggle_status" class="btn btn-sm btn-outline-warning me-1" title="<?php echo $user['status'] === 'active' ? 'Suspend Account' : 'Activate Account'; ?>">
                                            <i class="fa-solid <?php echo $user['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>

                                        <!-- Delete Account -->
                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" title="Delete Student Account" onclick="return confirm('WARNING: Are you sure you want to permanently delete this student account? All their reports and claims will be purged.')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
