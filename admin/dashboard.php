<?php
/**
 * CampusFind Pro - Admin Panel Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure Access: Admin only
requireRole('admin');

$admin_id = $_SESSION['user_id'];

// Fetch overall platform statistics
$total_students = 0;
$total_lost = 0;
$total_found = 0;
$pending_claims = 0;
$approved_claims = 0;
$rejected_claims = 0;

try {
    $db = Database::getInstance();
    
    // Stats query
    $total_students = $db->count('users', ['role' => 'student']);
    $total_lost = $db->count('lost_items');
    $total_found = $db->count('found_items');
    
    $pending_claims = $db->count('claims', ['status' => 'pending']);
    $approved_claims = $db->count('claims', ['status' => 'approved']);
    $rejected_claims = $db->count('claims', ['status' => 'rejected']);

    // Fetch recent pending claims
    $raw_claims = $db->find('claims', ['status' => 'pending'], ['sort' => ['created_at' => -1], 'limit' => 5]);
    $recent_claims = [];
    foreach ($raw_claims as $claim) {
        $claimer = $db->findOne('users', ['_id' => toObjectId($claim['claimer_id'])]);
        $claim['claimer_name'] = $claimer['name'] ?? 'Unknown';
        $claim['claimer_email'] = $claimer['email'] ?? '';
        
        $item_title = 'Unknown Item';
        if ($claim['item_type'] === 'found') {
            $item = $db->findOne('found_items', ['_id' => toObjectId($claim['item_id'])]);
            if ($item) $item_title = $item['title'];
        } else {
            $item = $db->findOne('lost_items', ['_id' => toObjectId($claim['item_id'])]);
            if ($item) $item_title = $item['title'];
        }
        $claim['item_title'] = $item_title;
        $recent_claims[] = $claim;
    }

    // Fetch recent logs
    $raw_logs = $db->find('activity_logs', [], ['sort' => ['created_at' => -1], 'limit' => 5]);
    $recent_logs = [];
    foreach ($raw_logs as $log) {
        if (!empty($log['user_id'])) {
            $user = $db->findOne('users', ['_id' => toObjectId($log['user_id'])]);
            $log['user_name'] = $user['name'] ?? 'SYSTEM';
        } else {
            $log['user_name'] = 'SYSTEM';
        }
        $recent_logs[] = $log;
    }

} catch (Exception $e) {
    error_log("Admin dashboard query failed: " . $e->getMessage());
    $recent_claims = [];
    $recent_logs = [];
}

$page_title = 'Admin Console';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>

<div class="container py-5 flex-grow-1">
    <!-- Admin Section Navigation Tabs -->
    <div class="row mb-5" data-aos="fade-up">
        <div class="col-12">
            <div class="glass-panel p-3">
                <ul class="nav nav-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link active fw-600 px-4 py-2" href="dashboard.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="users.php"><i class="fa-solid fa-users me-2"></i>Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="items.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-secondary fw-600 px-4 py-2" href="claims.php">
                            <i class="fa-solid fa-handshake-angle me-2"></i>Claims
                            <?php if ($pending_claims > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $pending_claims; ?></span>
                            <?php endif; ?>
                        </a>
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

    <!-- Overview Counters -->
    <div class="row g-4 mb-5" data-aos="fade-up">
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100 border-start border-primary border-4" style="border-radius: var(--border-radius-md);">
                <span class="text-secondary fw-600 d-block mb-2">Total Students</span>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fs-1 fw-800"><?php echo $total_students; ?></div>
                    <div class="btn btn-premium rounded p-3"><i class="fa-solid fa-users fs-5"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100 border-start border-danger border-4" style="border-radius: var(--border-radius-md);">
                <span class="text-secondary fw-600 d-block mb-2">Lost Reports</span>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fs-1 fw-800"><?php echo $total_lost; ?></div>
                    <div class="btn btn-premium rounded p-3 bg-danger" style="box-shadow: none;"><i class="fa-solid fa-clipboard-question fs-5"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100 border-start border-success border-4" style="border-radius: var(--border-radius-md);">
                <span class="text-secondary fw-600 d-block mb-2">Found Logs</span>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fs-1 fw-800"><?php echo $total_found; ?></div>
                    <div class="btn btn-premium rounded p-3 bg-success" style="box-shadow: none;"><i class="fa-solid fa-hand-holding-hand fs-5"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-panel p-4 h-100 border-start border-warning border-4" style="border-radius: var(--border-radius-md);">
                <span class="text-secondary fw-600 d-block mb-2">Pending Claims</span>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fs-1 fw-800 text-warning"><?php echo $pending_claims; ?></div>
                    <div class="btn btn-premium rounded p-3 bg-warning" style="box-shadow: none;"><i class="fa-solid fa-clock-rotate-left fs-5"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-5" data-aos="fade-up">
        <!-- Analytics Chart -->
        <div class="col-lg-8">
            <div class="glass-panel p-4 h-100">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-chart-simple text-primary me-2"></i>Monthly Item Trends (Simulated)</h5>
                <div class="chart-container" style="height: 350px;">
                    <canvas id="itemsMonthlyChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Donut Resolution rate -->
        <div class="col-lg-4">
            <div class="glass-panel p-4 h-100">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-circle-nodes text-primary me-2"></i>Claim Resolution Rate</h5>
                <div class="chart-container" style="height: 350px;">
                    <canvas id="claimsResolutionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Claims & Activity Lists -->
    <div class="row g-4">
        <!-- Pending Claims Table -->
        <div class="col-lg-7" data-aos="fade-right">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="font-heading fw-700 m-0"><i class="fa-solid fa-handshake-angle text-primary me-2"></i>Pending claims queue</h5>
                    <a href="claims.php" class="btn btn-premium-outline btn-sm">Process queue</a>
                </div>
                
                <?php if (empty($recent_claims)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-square-check fs-2 mb-2"></i>
                        <p class="m-0">All claims successfully processed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-muted" style="font-size: 0.85rem;">
                                    <th>Claimer</th>
                                    <th>Item</th>
                                    <th>Filed On</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.9rem;">
                                <?php foreach ($recent_claims as $claim): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-700 d-block text-primary"><?php echo sanitize($claim['claimer_name']); ?></span>
                                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo sanitize($claim['claimer_email']); ?></span>
                                        </td>
                                        <td class="fw-600"><?php echo sanitize($claim['item_title']); ?></td>
                                        <td><?php echo formatDate($claim['created_at']); ?></td>
                                        <td class="text-end">
                                            <a href="claims.php?id=<?php echo $claim['_id']; ?>" class="btn btn-premium btn-sm px-3 py-1">Review Claim</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Log Feed -->
        <div class="col-lg-5" data-aos="fade-left">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="font-heading fw-700 m-0"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Audit Log Feed</h5>
                    <a href="logs.php" class="btn btn-premium-outline btn-sm">Full log</a>
                </div>
                
                <div class="timeline ps-2">
                    <?php if (empty($recent_logs)): ?>
                        <p class="text-muted text-center py-4 m-0">No system events logged.</p>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-700 text-primary" style="font-size: 0.85rem;"><?php echo sanitize($log['action']); ?></span>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($log['created_at'], 'H:i a'); ?></span>
                                </div>
                                <p class="text-secondary m-0" style="font-size: 0.8rem; line-height: 1.4;"><?php echo sanitize($log['description']); ?></p>
                                <span class="text-muted" style="font-size: 0.75rem;">Actor: <?php echo $log['user_name'] ? sanitize($log['user_name']) : 'SYSTEM'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load dashboard Analytics -->
<?php
$extra_js = "
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Line Chart (Items Monthly trend)
        const lineCtx = document.getElementById('itemsMonthlyChart').getContext('2d');
        const itemsMonthlyData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [
                {
                    label: 'Lost Reports',
                    data: [12, 19, 15, 8, 22, 17, $total_lost],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Found Logs',
                    data: [9, 14, 18, 11, 15, 20, $total_found],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        };

        let itemsChart = new Chart(lineCtx, {
            type: 'line',
            data: itemsMonthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') } }
                },
                scales: {
                    x: { grid: { color: 'var(--border-color)' }, ticks: { color: 'var(--text-secondary)' } },
                    y: { grid: { color: 'var(--border-color)' }, ticks: { color: 'var(--text-secondary)' } }
                }
            }
        });

        // Resolution Pie chart
        const pieCtx = document.getElementById('claimsResolutionChart').getContext('2d');
        let resolutionChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [$pending_claims, $approved_claims, $rejected_claims],
                    backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') } }
                }
            }
        });

        window.addEventListener('themeChanged', () => {
            const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-primary');
            itemsChart.options.plugins.legend.labels.color = textColor;
            itemsChart.update();
            
            resolutionChart.options.plugins.legend.labels.color = textColor;
            resolutionChart.update();
        });
    });
</script>
";
require_once dirname(__DIR__) . '/includes/footer.php';
?>
