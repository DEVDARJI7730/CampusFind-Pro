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
    $db = Database::getInstance()->getConnection();

    // 1. Process Moderator Actions (Deletes)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $item_type = $_POST['item_type'] ?? '';
        $item_id = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!validateCSRFToken($csrf)) {
            $msg = 'Invalid security token.';
            $msg_class = 'danger';
        } elseif ($item_id && in_array($item_type, ['lost', 'found'])) {
            if ($action === 'delete') {
                if ($item_type === 'found') {
                    // Fetch image name
                    $img_stmt = $db->prepare("SELECT image, title FROM found_items WHERE id = :id LIMIT 1");
                    $img_stmt->execute([':id' => $item_id]);
                    $item = $img_stmt->fetch();
                    
                    if ($item) {
                        if (!empty($item['image'])) {
                            $img_path = UPLOAD_PATH . '/' . $item['image'];
                            if (file_exists($img_path)) unlink($img_path);
                        }
                        $db->prepare("DELETE FROM found_items WHERE id = :id")->execute([':id' => $item_id]);
                        logActivity($admin_id, 'MODERATOR_DELETE_FOUND', 'Admin permanently deleted found item: ' . $item['title']);
                        $msg = 'Found item report moderated and removed successfully.';
                        $msg_class = 'success';
                    }
                } else {
                    // Fetch image name
                    $img_stmt = $db->prepare("SELECT image, title FROM lost_items WHERE id = :id LIMIT 1");
                    $img_stmt->execute([':id' => $item_id]);
                    $item = $img_stmt->fetch();
                    
                    if ($item) {
                        if (!empty($item['image'])) {
                            $img_path = UPLOAD_PATH . '/' . $item['image'];
                            if (file_exists($img_path)) unlink($img_path);
                        }
                        $db->prepare("DELETE FROM lost_items WHERE id = :id")->execute([':id' => $item_id]);
                        logActivity($admin_id, 'MODERATOR_DELETE_LOST', 'Admin permanently deleted lost item: ' . $item['title']);
                        $msg = 'Lost item report moderated and removed successfully.';
                        $msg_class = 'success';
                    }
                }
            }
        }
    }

    // Fetch categories for search filter
    $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

    // 2. Fetch all reported items
    // Since they are in separate tables, we can fetch lost items and found items, or display them in two tabs, or UNION them!
    // A UNION query with columns aliased is extremely clean and matches enterprise database standards!
    $type_filter = $_GET['type'] ?? 'all';
    $cat_filter = $_GET['category'] ?? '';
    $query = trim($_GET['search'] ?? '');

    $union_queries = [];
    $params = [];

    if ($type_filter === 'all' || $type_filter === 'lost') {
        $lost_sql = "SELECT 'lost' as item_type, l.id, l.title, l.location, l.lost_date as reported_date, l.status, l.created_at, c.name as category_name, u.name as reporter_name 
                     FROM lost_items l 
                     JOIN categories c ON l.category_id = c.id 
                     JOIN users u ON l.user_id = u.id 
                     WHERE 1=1";
        if (!empty($cat_filter)) {
            $lost_sql .= " AND c.name = :category_l";
            $params[':category_l'] = $cat_filter;
        }
        if (!empty($query)) {
            $lost_sql .= " AND (l.title LIKE :search_l OR l.description LIKE :search_l)";
            $params[':search_l'] = '%' . $query . '%';
        }
        $union_queries[] = $lost_sql;
    }

    if ($type_filter === 'all' || $type_filter === 'found') {
        $found_sql = "SELECT 'found' as item_type, f.id, f.title, f.location, f.found_date as reported_date, f.status, f.created_at, c.name as category_name, u.name as reporter_name 
                      FROM found_items f 
                      JOIN categories c ON f.category_id = c.id 
                      JOIN users u ON f.user_id = u.id 
                      WHERE 1=1";
        if (!empty($cat_filter)) {
            $found_sql .= " AND c.name = :category_f";
            $params[':category_f'] = $cat_filter;
        }
        if (!empty($query)) {
            $found_sql .= " AND (f.title LIKE :search_f OR f.description LIKE :search_f)";
            $params[':search_f'] = '%' . $query . '%';
        }
        $union_queries[] = $found_sql;
    }

    $final_sql = implode(" UNION ", $union_queries) . " ORDER BY created_at DESC";
    $stmt = $db->prepare($final_sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

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
