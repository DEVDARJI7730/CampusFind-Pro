<?php
/**
 * CampusFind Pro - Admin: Audit Log Console
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];

// 1. Pagination Parameters
$limit = 15;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$query = trim($_GET['search'] ?? '');

try {
    $db = Database::getInstance();

    // 2. Construct Dynamic Search
    $filter = [];
    if (!empty($query)) {
        // Fetch users matching search query name
        $matched_users = $db->find('users', ['name' => ['$regex' => $query, '$options' => 'i']]);
        $user_ids = [];
        foreach ($matched_users as $mu) {
            $user_ids[] = (string)$mu['_id'];
        }

        $or_conditions = [
            ['action' => ['$regex' => $query, '$options' => 'i']],
            ['description' => ['$regex' => $query, '$options' => 'i']]
        ];
        if (!empty($user_ids)) {
            $or_conditions[] = ['user_id' => ['$in' => $user_ids]];
        }
        $filter['$or'] = $or_conditions;
    }

    // 3. Fetch count
    $total_logs = $db->count('activity_logs', $filter);
    $total_pages = ceil($total_logs / $limit);

    // 4. Fetch logs
    $options = [
        'sort' => ['created_at' => -1],
        'limit' => $limit,
        'skip' => $offset
    ];
    $raw_logs = $db->find('activity_logs', $filter, $options);
    
    // Fetch users for mapping to logs
    $users_list = $db->find('users');
    $userMap = [];
    foreach ($users_list as $u) {
        $userMap[(string)$u['_id']] = [
            'name' => $u['name'],
            'email' => $u['email']
        ];
    }

    $logs = [];
    foreach ($raw_logs as $log) {
        $uid = (string)($log['user_id'] ?? '');
        if (isset($userMap[$uid])) {
            $log['user_name'] = $userMap[$uid]['name'];
            $log['user_email'] = $userMap[$uid]['email'];
        } else {
            $log['user_name'] = null;
            $log['user_email'] = null;
        }
        $logs[] = $log;
    }

} catch (Exception $e) {
    error_log("Admin logs console failure: " . $e->getMessage());
    $logs = [];
    $total_pages = 1;
}

$page_title = 'System Audit Logs';
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
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="users.php"><i class="fa-solid fa-users me-2"></i>Users</a>
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
                        <a class="nav-link active fw-600 px-4 py-2" href="logs.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>System Logs</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2" data-aos="fade-up">
        <div>
            <h2 class="font-heading fw-800 m-0">Security Audit Logs</h2>
            <p class="text-secondary m-0">View student actions, moderator login events, and database transaction tracking.</p>
        </div>
        
        <!-- Search bar -->
        <form action="logs.php" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-premium-control" placeholder="Search actor, action..." value="<?php echo sanitize($query); ?>">
            <button type="submit" class="btn btn-premium"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="glass-panel p-4 animate-fade-in" data-aos="fade-up">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-clipboard-list fs-2 mb-2"></i>
                <p class="m-0">No system events matches search criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="text-muted" style="font-size: 0.85rem;">
                            <th>Timestamp</th>
                            <th>Actor / User</th>
                            <th>Action Type</th>
                            <th>Details & Context</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.9rem;">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size: 0.8rem;" class="text-secondary"><?php echo formatDate($log['created_at'], 'Y-m-d H:i:s'); ?></td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <strong class="text-primary d-block"><?php echo sanitize($log['user_name']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;"><?php echo sanitize($log['user_email']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted font-italic">SYSTEM / GUEST</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-secondary font-monospace" style="font-size: 0.75rem;">
                                        <?php echo sanitize($log['action']); ?>
                                    </span>
                                </td>
                                <td class="text-secondary" style="font-size: 0.85rem; line-height: 1.4; max-width: 300px; word-wrap: break-word;">
                                    <?php echo sanitize($log['description']); ?>
                                </td>
                                <td class="font-monospace text-muted" style="font-size: 0.8rem;"><?php echo sanitize($log['ip_address'] ?? '127.0.0.1'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Control -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4" aria-label="Logs pagination">
                    <ul class="pagination justify-content-center gap-2">
                        <!-- Prev page -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link glass-panel px-3 py-2 text-primary border-0" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($query); ?>" aria-label="Previous">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        </li>
                        <!-- Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link glass-panel px-3 py-2 <?php echo $page == $i ? 'bg-primary text-white' : 'text-primary'; ?> border-0" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($query); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <!-- Next page -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link glass-panel px-3 py-2 text-primary border-0" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($query); ?>" aria-label="Next">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
