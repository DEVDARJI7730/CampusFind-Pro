<?php
/**
 * CampusFind Pro - Search & Filter Lost Items
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

$page_title = 'Search Lost Items';

// 1. Pagination Parameters
$limit = 6;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 2. Filter Inputs
$query = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');
$date = trim($_GET['date'] ?? '');

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch categories for search filter dropdown
    $cat_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();

    // 3. Construct Dynamic Query
    $sql = "SELECT l.*, c.name as category_name 
            FROM lost_items l 
            JOIN categories c ON l.category_id = c.id 
            WHERE l.status = 'lost'";
    $count_sql = "SELECT COUNT(*) as total 
                  FROM lost_items l 
                  JOIN categories c ON l.category_id = c.id 
                  WHERE l.status = 'lost'";
    
    $params = [];

    if (!empty($query)) {
        $sql .= " AND (l.title LIKE :query OR l.description LIKE :query)";
        $count_sql .= " AND (l.title LIKE :query OR l.description LIKE :query)";
        $params[':query'] = '%' . $query . '%';
    }

    if (!empty($category)) {
        $sql .= " AND c.name = :category";
        $count_sql .= " AND c.name = :category";
        $params[':category'] = $category;
    }

    if (!empty($location)) {
        $sql .= " AND l.location LIKE :location";
        $count_sql .= " AND l.location LIKE :location";
        $params[':location'] = '%' . $location . '%';
    }

    if (!empty($date)) {
        $sql .= " AND l.lost_date = :date";
        $count_sql .= " AND l.lost_date = :date";
        $params[':date'] = $date;
    }

    // 4. Order and Limit
    $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
    
    // 5. Fetch count
    $stmt_count = $db->prepare($count_sql);
    $stmt_count->execute($params);
    $total_items = $stmt_count->fetch()['total'] ?? 0;
    $total_pages = ceil($total_items / $limit);

    // 6. Fetch items
    $stmt = $db->prepare($sql);
    // Bind limit & offset as integers since PDO requires them in prepared statements under strict mode
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $items = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Search Lost page query fail: " . $e->getMessage());
    $items = [];
    $total_pages = 1;
    $categories = [];
}

require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Page Header -->
    <div class="text-center max-width-600 mx-auto mb-5" data-aos="fade-up">
        <h1 class="font-heading fw-800 mb-2">Search Lost Items</h1>
        <p class="text-secondary">Browse listings reported lost by students on campus. Use filters to narrow search results.</p>
    </div>

    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3" data-aos="fade-right">
            <div class="glass-panel p-4 sticky-top" style="top: 100px; z-index: 10;">
                <h5 class="font-heading fw-700 mb-3"><i class="fa-solid fa-filter text-primary me-2"></i>Filter Database</h5>
                <form action="search.php" method="GET">
                    <!-- Text Query -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Keyword Search</label>
                        <input type="text" name="q" class="form-control form-premium-control" placeholder="Search title, desc..." value="<?php echo sanitize($query); ?>">
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Category</label>
                        <select name="category" class="form-select form-premium-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo sanitize($cat['name']); ?>" <?php echo $category === $cat['name'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Location</label>
                        <input type="text" name="location" class="form-control form-premium-control" placeholder="e.g. Science Hall" value="<?php echo sanitize($location); ?>">
                    </div>

                    <!-- Date -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Date Lost</label>
                        <input type="date" name="date" class="form-control form-premium-control" value="<?php echo sanitize($date); ?>">
                    </div>

                    <button type="submit" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-arrows-rotate me-2"></i>Apply Filters</button>
                    <a href="search.php" class="btn btn-premium-outline w-100 py-2 mt-2">Reset Filters</a>
                </form>
            </div>
        </div>

        <!-- Search Listings Grid -->
        <div class="col-lg-9" data-aos="fade-left">
            <?php if (empty($items)): ?>
                <div class="glass-panel p-5 text-center my-4">
                    <i class="fa-solid fa-magnifying-glass-minus text-muted fs-1 mb-3"></i>
                    <h5 class="text-secondary">No matching lost reports found.</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">Try adjusting the query parameters or categories.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($items as $item): ?>
                        <div class="col-md-6 col-12">
                            <div class="glass-card h-100 position-relative">
                                <span class="item-badge badge-lost">Lost</span>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-item.png'; ?>" class="w-100" style="height: 200px; object-fit: cover;" alt="<?php echo sanitize($item['title']); ?>">
                                
                                <div class="p-4">
                                    <span class="badge bg-secondary text-secondary mb-2" style="font-size: 0.8rem;"><?php echo sanitize($item['category_name']); ?></span>
                                    <h5 class="font-heading fw-700 mb-2 text-truncate"><?php echo sanitize($item['title']); ?></h5>
                                    <p class="text-secondary text-truncate-2 mb-3" style="font-size: 0.9rem;"><?php echo sanitize($item['description']); ?></p>
                                    
                                    <div class="d-flex flex-column gap-2 mb-4" style="font-size: 0.85rem; color: var(--text-muted);">
                                        <div><i class="fa-solid fa-location-dot me-2 text-primary"></i><?php echo sanitize($item['location']); ?></div>
                                        <div><i class="fa-regular fa-calendar me-2 text-primary"></i>Lost: <?php echo formatDate($item['lost_date']); ?></div>
                                        <?php if ($item['reward'] > 0): ?>
                                            <div class="fw-700 text-success"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Reward: $<?php echo number_format($item['reward'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-premium w-100">View details & scan</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Control -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-5" aria-label="Search navigation">
                        <ul class="pagination justify-content-center gap-2">
                            <!-- Prev page -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link glass-panel px-3 py-2 text-primary border-0" href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date=<?php echo urlencode($date); ?>" aria-label="Previous">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <!-- Page numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link glass-panel px-3 py-2 <?php echo $page == $i ? 'bg-primary text-white' : 'text-primary'; ?> border-0" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date=<?php echo urlencode($date); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <!-- Next page -->
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link glass-panel px-3 py-2 text-primary border-0" href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date=<?php echo urlencode($date); ?>" aria-label="Next">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
