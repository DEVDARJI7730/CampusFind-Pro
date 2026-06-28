<?php
/**
 * CampusFind Pro - Student Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('student');

$user_id = $_SESSION['user_id'];
$success = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

// Initialize counts
$lost_count = 0;
$found_count = 0;
$pending_claims = 0;
$approved_claims = 0;

try {
    $db = Database::getInstance()->getConnection();

    // 1. Fetch counts
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM lost_items WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $lost_count = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM found_items WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $found_count = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM claims WHERE claimer_id = :uid AND status = 'pending'");
    $stmt->execute([':uid' => $user_id]);
    $pending_claims = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM claims WHERE claimer_id = :uid AND status = 'approved'");
    $stmt->execute([':uid' => $user_id]);
    $approved_claims = $stmt->fetch()['cnt'];

    // 2. Fetch student's own lost reports
    $lost_stmt = $db->prepare("SELECT l.*, c.name as category_name FROM lost_items l JOIN categories c ON l.category_id = c.id WHERE l.user_id = :uid ORDER BY l.created_at DESC");
    $lost_stmt->execute([':uid' => $user_id]);
    $my_lost_items = $lost_stmt->fetchAll();

    // 3. Fetch student's own found reports
    $found_stmt = $db->prepare("SELECT f.*, c.name as category_name FROM found_items f JOIN categories c ON f.category_id = c.id WHERE f.user_id = :uid ORDER BY f.created_at DESC");
    $found_stmt->execute([':uid' => $user_id]);
    $my_found_items = $found_stmt->fetchAll();

    // 4. Fetch claims submitted by this student
    // We join found_items or lost_items to display claimant item details. Since claims has item_type we check dynamically.
    $claims_stmt = $db->prepare("
        SELECT c.*, 
        CASE 
            WHEN c.item_type = 'found' THEN f.title 
            WHEN c.item_type = 'lost' THEN l.title 
        END as item_title,
        CASE 
            WHEN c.item_type = 'found' THEN f.location 
            WHEN c.item_type = 'lost' THEN l.location 
        END as item_location
        FROM claims c
        LEFT JOIN found_items f ON c.item_id = f.id AND c.item_type = 'found'
        LEFT JOIN lost_items l ON c.item_id = l.id AND c.item_type = 'lost'
        WHERE c.claimer_id = :uid
        ORDER BY c.created_at DESC
    ");
    $claims_stmt->execute([':uid' => $user_id]);
    $my_claims = $claims_stmt->fetchAll();

    // 5. Fetch recent activity logs
    $log_stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
    $log_stmt->execute([':uid' => $user_id]);
    $my_logs = $log_stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard query failure: " . $e->getMessage());
    $my_lost_items = [];
    $my_found_items = [];
    $my_claims = [];
    $my_logs = [];
}

$page_title = 'Student Dashboard';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Header Greeting -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-5" data-aos="fade-up">
        <div>
            <h1 class="font-heading fw-800 m-0">Dashboard</h1>
            <p class="text-secondary m-0">Welcome back, <?php echo sanitize($_SESSION['user_name']); ?>! Manage and track your items here.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/lost/report.php" class="btn btn-premium btn-sm"><i class="fa-solid fa-plus me-2"></i>Report Lost Item</a>
            <a href="<?php echo SITE_URL; ?>/found/report.php" class="btn btn-premium-outline btn-sm"><i class="fa-solid fa-hand-holding-hand me-2"></i>Report Found Item</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Overview Stats Widget Cards -->
    <div class="row g-4 mb-5" data-aos="fade-up">
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary fw-600">Lost Reports</span>
                    <i class="fa-solid fa-clipboard-question text-danger fs-4"></i>
                </div>
                <div class="fs-2 fw-800"><?php echo $lost_count; ?></div>
                <span class="text-muted" style="font-size: 0.8rem;">Submitted by you</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary fw-600">Found Logs</span>
                    <i class="fa-solid fa-clipboard-check text-success fs-4"></i>
                </div>
                <div class="fs-2 fw-800"><?php echo $found_count; ?></div>
                <span class="text-muted" style="font-size: 0.8rem;">Turned in by you</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary fw-600">Pending Claims</span>
                    <i class="fa-solid fa-clock-rotate-left text-warning fs-4"></i>
                </div>
                <div class="fs-2 fw-800"><?php echo $pending_claims; ?></div>
                <span class="text-muted" style="font-size: 0.8rem;">Awaiting verification</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary fw-600">Approved Claims</span>
                    <i class="fa-solid fa-circle-check text-info fs-4"></i>
                </div>
                <div class="fs-2 fw-800"><?php echo $approved_claims; ?></div>
                <span class="text-muted" style="font-size: 0.8rem;">Items successfully reunited</span>
            </div>
        </div>
    </div>

    <!-- Content Sections (Tabs layout or Grid) -->
    <div class="row g-4">
        <!-- Main Panel: Lists of reports -->
        <div class="col-lg-8" data-aos="fade-right">
            <div class="glass-panel p-4 mb-4">
                <h4 class="font-heading fw-700 mb-4"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>My Reported Items</h4>
                
                <ul class="nav nav-tabs border-color mb-3" id="itemsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-600 border-0 bg-transparent text-primary" id="lost-tab" data-bs-toggle="tab" data-bs-target="#lost" type="button" role="tab">Lost Items (<?php echo count($my_lost_items); ?>)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-600 border-0 bg-transparent text-secondary" id="found-tab" data-bs-toggle="tab" data-bs-target="#found" type="button" role="tab">Found Items (<?php echo count($my_found_items); ?>)</button>
                    </li>
                </ul>

                <div class="tab-content" id="itemsTabContent">
                    <!-- Lost Items Tab -->
                    <div class="tab-pane fade show active" id="lost" role="tabpanel">
                        <?php if (empty($my_lost_items)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-box-open fs-3 mb-2"></i>
                                <p class="m-0">You have not reported any lost items.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr class="text-muted" style="font-size: 0.85rem;">
                                            <th>Item Title</th>
                                            <th>Category</th>
                                            <th>Lost Date</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 0.9rem;">
                                        <?php foreach ($my_lost_items as $item): ?>
                                            <tr>
                                                <td class="fw-600"><?php echo sanitize($item['title']); ?></td>
                                                <td><?php echo sanitize($item['category_name']); ?></td>
                                                <td><?php echo formatDate($item['lost_date']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $item['status'] === 'lost' ? 'badge-lost' : 'badge-claimed'; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo SITE_URL; ?>/lost/view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-eye"></i></a>
                                                    <a href="<?php echo SITE_URL; ?>/lost/edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary me-1"><i class="fa-solid fa-pen"></i></a>
                                                    <a href="<?php echo SITE_URL; ?>/lost/delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this report?')"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Found Items Tab -->
                    <div class="tab-pane fade" id="found" role="tabpanel">
                        <?php if (empty($my_found_items)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-box-open fs-3 mb-2"></i>
                                <p class="m-0">You have not logged any found items.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr class="text-muted" style="font-size: 0.85rem;">
                                            <th>Item Title</th>
                                            <th>Category</th>
                                            <th>Found Date</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 0.9rem;">
                                        <?php foreach ($my_found_items as $item): ?>
                                            <tr>
                                                <td class="fw-600"><?php echo sanitize($item['title']); ?></td>
                                                <td><?php echo sanitize($item['category_name']); ?></td>
                                                <td><?php echo formatDate($item['found_date']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $item['status'] === 'found' ? 'badge-found' : 'badge-claimed'; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo SITE_URL; ?>/found/view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-eye"></i></a>
                                                    <a href="<?php echo SITE_URL; ?>/found/edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary me-1"><i class="fa-solid fa-pen"></i></a>
                                                    <a href="<?php echo SITE_URL; ?>/found/delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this report?')"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Claim Requests Panel -->
            <div class="glass-panel p-4">
                <h4 class="font-heading fw-700 mb-4"><i class="fa-solid fa-handshake-angle text-primary me-2"></i>My Claim Requests</h4>
                <?php if (empty($my_claims)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-handshake-slash fs-3 mb-2"></i>
                        <p class="m-0">You haven't filed any claims yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-muted" style="font-size: 0.85rem;">
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Submission Date</th>
                                    <th>Status</th>
                                    <th>Admin Remarks</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.9rem;">
                                <?php foreach ($my_claims as $claim): ?>
                                    <tr>
                                        <td class="fw-600"><?php echo sanitize($claim['item_title']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $claim['item_type'] === 'found' ? 'badge-found' : 'badge-lost'; ?>">
                                                <?php echo ucfirst($claim['item_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($claim['created_at']); ?></td>
                                        <td>
                                            <?php 
                                            $st_class = 'bg-warning bg-opacity-10 text-warning border-warning';
                                            if ($claim['status'] === 'approved') {
                                                $st_class = 'bg-success bg-opacity-10 text-success border-success';
                                            } elseif ($claim['status'] === 'rejected') {
                                                $st_class = 'bg-danger bg-opacity-10 text-danger border-danger';
                                            }
                                            ?>
                                            <span class="badge <?php echo $st_class; ?> border px-2 py-1">
                                                <?php echo ucfirst($claim['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted" style="font-size: 0.85rem;">
                                            <?php echo !empty($claim['admin_notes']) ? sanitize($claim['admin_notes']) : 'No remarks yet'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Charts & Activity logs -->
        <div class="col-lg-4" data-aos="fade-left">
            <!-- Graphical stats -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="font-heading fw-700 mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>My Statistics</h5>
                <div class="chart-container">
                    <canvas id="studentClaimsChart"></canvas>
                </div>
            </div>

            <!-- Activity Logs Timeline -->
            <div class="glass-panel p-4">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Activity</h5>
                <?php if (empty($my_logs)): ?>
                    <p class="text-muted text-center py-3 m-0" style="font-size: 0.9rem;">No actions recorded yet.</p>
                <?php else: ?>
                    <div class="timeline ps-2">
                        <?php foreach ($my_logs as $log): ?>
                            <div class="timeline-item">
                                <div class="fw-700 text-primary" style="font-size: 0.85rem;"><?php echo sanitize($log['action']); ?></div>
                                <p class="text-secondary m-0" style="font-size: 0.8rem; line-height: 1.4;"><?php echo sanitize($log['description']); ?></p>
                                <span class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($log['created_at'], 'M d, g:i a'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js scripts -->
<?php
$extra_js = "
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('studentClaimsChart').getContext('2d');
        const claimsData = {
            labels: ['Lost Reports', 'Found Logs', 'Pending Claims', 'Approved Claims'],
            datasets: [{
                data: [$lost_count, $found_count, $pending_claims, $approved_claims],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.75)',
                    'rgba(16, 185, 129, 0.75)',
                    'rgba(245, 158, 11, 0.75)',
                    'rgba(6, 182, 212, 0.75)'
                ],
                borderColor: [
                    '#ef4444',
                    '#10b981',
                    '#f59e0b',
                    '#06b6d4'
                ],
                borderWidth: 1
            }]
        };

        const config = {
            type: 'doughnut',
            data: claimsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                            font: {
                                family: 'Plus Jakarta Sans',
                                size: 11
                            }
                        }
                    }
                }
            }
        };

        let currentChart = new Chart(ctx, config);

        // Update charts colors on theme changes
        window.addEventListener('themeChanged', () => {
            currentChart.options.plugins.legend.labels.color = getComputedStyle(document.documentElement).getPropertyValue('--text-primary');
            currentChart.update();
        });
    });
</script>
";
require_once dirname(__DIR__) . '/includes/footer.php';
?>
