<?php
/**
 * CampusFind Pro - Admin: Items Moderation
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];
$msg = $_SESSION['admin_msg'] ?? '';
$msg_class = $_SESSION['admin_msg_class'] ?? 'success';
unset($_SESSION['admin_msg'], $_SESSION['admin_msg_class']);

try {
    $db = Database::getInstance();

    // 1. Process Moderator Actions (Deletes)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $item_type = $_POST['item_type'] ?? '';
        $item_id = trim($_POST['item_id'] ?? '');
        $action = $_POST['action'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!validateCSRFToken($csrf)) {
            $msg = 'Invalid security token.';
            $msg_class = 'danger';
        } elseif ($item_id && in_array($item_type, ['lost', 'found'])) {
            if ($action === 'delete') {
                if ($item_type === 'found') {
                    // Fetch image name
                    $item = $db->findOne('found_items', ['_id' => toObjectId($item_id)]);
                    
                    if ($item) {
                        if (!empty($item['image'])) {
                            $img_path = UPLOAD_PATH . '/' . $item['image'];
                            if (file_exists($img_path)) unlink($img_path);
                        }
                        $db->delete('found_items', ['_id' => toObjectId($item_id)]);
                        logActivity($admin_id, 'MODERATOR_DELETE_FOUND', 'Admin permanently deleted found item: ' . $item['title']);
                        $msg = 'Found item report moderated and removed successfully.';
                        $msg_class = 'success';
                    }
                } else {
                    // Fetch image name
                    $item = $db->findOne('lost_items', ['_id' => toObjectId($item_id)]);
                    
                    if ($item) {
                        if (!empty($item['image'])) {
                            $img_path = UPLOAD_PATH . '/' . $item['image'];
                            if (file_exists($img_path)) unlink($img_path);
                        }
                        $db->delete('lost_items', ['_id' => toObjectId($item_id)]);
                        logActivity($admin_id, 'MODERATOR_DELETE_LOST', 'Admin permanently deleted lost item: ' . $item['title']);
                        $msg = 'Lost item report moderated and removed successfully.';
                        $msg_class = 'success';
                    }
                }
            }
        }
    }

    // Fetch categories
    $categories = $db->find('categories', [], ['sort' => ['name' => 1]]);
    $categoryMap = [];
    foreach ($categories as $cat) {
        $categoryMap[(string)$cat['_id']] = $cat['name'];
    }

    // Fetch users for mapping
    $users_list = $db->find('users');
    $userMap = [];
    foreach ($users_list as $u) {
        $userMap[(string)$u['_id']] = $u['name'];
    }

    $type_filter = $_GET['type'] ?? 'all';
    $cat_filter = $_GET['category'] ?? '';
    $query = trim($_GET['search'] ?? '');

    // Construct filter for lost items
    $lost_filter = [];
    if (!empty($cat_filter)) {
        $catDoc = $db->findOne('categories', ['name' => $cat_filter]);
        if ($catDoc) {
            $lost_filter['category_id'] = toObjectId($catDoc['_id']);
        } else {
            $lost_filter['category_id'] = new MongoDB\BSON\ObjectId(); // no match
        }
    }
    if (!empty($query)) {
        $lost_filter['$or'] = [
            ['title' => ['$regex' => $query, '$options' => 'i']],
            ['description' => ['$regex' => $query, '$options' => 'i']]
        ];
    }

    // Construct filter for found items
    $found_filter = [];
    if (!empty($cat_filter)) {
        $catDoc = $db->findOne('categories', ['name' => $cat_filter]);
        if ($catDoc) {
            $found_filter['category_id'] = toObjectId($catDoc['_id']);
        } else {
            $found_filter['category_id'] = new MongoDB\BSON\ObjectId(); // no match
        }
    }
    if (!empty($query)) {
        $found_filter['$or'] = [
            ['title' => ['$regex' => $query, '$options' => 'i']],
            ['description' => ['$regex' => $query, '$options' => 'i']]
        ];
    }

    $raw_lost = [];
    $raw_found = [];

    if ($type_filter === 'all' || $type_filter === 'lost') {
        $raw_lost = $db->find('lost_items', $lost_filter);
    }
    if ($type_filter === 'all' || $type_filter === 'found') {
        $raw_found = $db->find('found_items', $found_filter);
    }

    $items = [];
    foreach ($raw_lost as $itm) {
        $items[] = [
            'item_type' => 'lost',
            'id' => $itm['_id'],
            'title' => $itm['title'],
            'location' => $itm['location'],
            'reported_date' => $itm['lost_date'],
            'status' => $itm['status'],
            'created_at' => $itm['created_at'],
            'category_name' => $categoryMap[(string)$itm['category_id']] ?? 'Others',
            'reporter_name' => $userMap[(string)$itm['user_id']] ?? 'Unknown'
        ];
    }
    foreach ($raw_found as $itm) {
        $items[] = [
            'item_type' => 'found',
            'id' => $itm['_id'],
            'title' => $itm['title'],
            'location' => $itm['location'],
            'reported_date' => $itm['found_date'],
            'status' => $itm['status'],
            'created_at' => $itm['created_at'],
            'category_name' => $categoryMap[(string)$itm['category_id']] ?? 'Others',
            'reporter_name' => $userMap[(string)$itm['user_id']] ?? 'Unknown'
        ];
    }

    // Sort by created_at DESC
    usort($items, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

} catch (Exception $e) {
    error_log("Admin items moderator failure: " . $e->getMessage());
    $items = [];
    $categories = [];
}

$page_title = 'Items Moderation';
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
                        <a class="nav-link active fw-600 px-4 py-2" href="items.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Items</a>
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

    <!-- Header Actions & Filters -->
    <div class="glass-panel p-4 mb-4" data-aos="fade-up">
        <form action="items.php" method="GET" class="row g-3 align-items-end">
            <!-- Search field -->
            <div class="col-lg-4 col-md-6">
                <label class="form-label text-secondary fw-500">Search Item Title</label>
                <input type="text" name="search" class="form-control form-premium-control" placeholder="Search keywords..." value="<?php echo sanitize($query); ?>">
            </div>

            <!-- Item Type Filter -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary fw-500">Registry Type</label>
                <select name="type" class="form-select form-premium-control">
                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Items (Lost & Found)</option>
                    <option value="lost" <?php echo $type_filter === 'lost' ? 'selected' : ''; ?>>Lost Registry Only</option>
                    <option value="found" <?php echo $type_filter === 'found' ? 'selected' : ''; ?>>Found Registry Only</option>
                </select>
            </div>

            <!-- Category Filter -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary fw-500">Category</label>
                <select name="category" class="form-select form-premium-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $cat_filter === $cat['name'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit -->
            <div class="col-lg-2 col-md-6">
                <button type="submit" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-filter me-2"></i>Filter</button>
            </div>
        </form>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Items Moderation List -->
    <div class="glass-panel p-4" data-aos="fade-up">
        <?php if (empty($items)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-folder-open fs-2 mb-2"></i>
                <p class="m-0">No lost or found items logged match filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="text-muted" style="font-size: 0.85rem;">
                            <th>Type</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Reporter</th>
                            <th>Location</th>
                            <th>Reported Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.9rem;">
                        <?php foreach ($items as $itm): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $itm['item_type'] === 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                        <?php echo ucfirst($itm['item_type']); ?>
                                    </span>
                                </td>
                                <td class="fw-700 text-secondary"><?php echo sanitize($itm['title']); ?></td>
                                <td><?php echo sanitize($itm['category_name']); ?></td>
                                <td><?php echo sanitize($itm['reporter_name']); ?></td>
                                <td><?php echo sanitize($itm['location']); ?></td>
                                <td><?php echo formatDate($itm['reported_date']); ?></td>
                                <td>
                                    <span class="badge bg-secondary text-secondary">
                                        <?php echo ucfirst($itm['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/<?php echo $itm['item_type']; ?>/view.php?id=<?php echo $itm['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Registry Details"><i class="fa-solid fa-eye"></i></a>
                                    
                                    <form action="items.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="item_type" value="<?php echo $itm['item_type']; ?>">
                                        <input type="hidden" name="item_id" value="<?php echo $itm['id']; ?>">
                                        
                                        <!-- Moderation Delete -->
                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" title="Moderate Delete Report" onclick="return confirm('WARNING: Are you sure you want to delete this reported item? All matching claims will be deleted.')">
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
