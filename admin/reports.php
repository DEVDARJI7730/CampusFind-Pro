<?php
/**
 * CampusFind Pro - Admin: PDF & Excel Reports Console
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];
$reports_history = [];

try {
    $db = Database::getInstance();
    
    // Fetch generated reports log history
    $reports_raw = $db->find('reports', [], ['sort' => ['created_at' => -1], 'limit' => 10]);
    $reports_history = [];
    foreach ($reports_raw as $report) {
        $user = null;
        try {
            $user = $db->findOne('users', ['_id' => new MongoDB\BSON\ObjectId($report['admin_id'])]);
        } catch (Exception $e) {}
        $report['admin_name'] = $user['name'] ?? 'System';
        $reports_history[] = $report;
    }
} catch (Exception $e) {
    error_log("Reports history fetch failure: " . $e->getMessage());
}

$page_title = 'Reports Centre';
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
                        <a class="nav-link active fw-600 px-4 py-2" href="reports.php"><i class="fa-solid fa-file-invoice me-2"></i>Reports</a>
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
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <h2 class="font-heading fw-800 m-0">Reports Operations Center</h2>
            <p class="text-secondary m-0">Generate institutional Lost & Found auditing files. Select data source and export formats.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Generate Report Panel -->
        <div class="col-lg-5" data-aos="fade-right">
            <div class="glass-panel p-4">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-file-export text-primary me-2"></i>Generate Audit Export</h5>
                
                <form action="export.php" method="GET" target="_blank">
                    <!-- Report Scope -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-500">Select Audit Scope</label>
                        <select name="scope" class="form-select form-premium-control" required>
                            <option value="lost_items">Active Lost Items List</option>
                            <option value="found_items">Logged Found Items List</option>
                            <option value="claims">All Claims Requests & Status</option>
                            <option value="users">Registered Students list</option>
                        </select>
                    </div>

                    <!-- Export Format -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-500">Export Format Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmtPdf" value="pdf" checked>
                                <label class="form-check-label text-secondary fw-500" for="fmtPdf">
                                    <i class="fa-solid fa-file-pdf text-danger me-1"></i>PDF Format
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmtExcel" value="excel">
                                <label class="form-check-label text-secondary fw-500" for="fmtExcel">
                                    <i class="fa-solid fa-file-excel text-success me-1"></i>Excel (XLSX) Format
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-cloud-arrow-down me-2"></i>Generate and Download</button>
                </form>
            </div>
        </div>

        <!-- Reports History Table -->
        <div class="col-lg-7" data-aos="fade-left">
            <div class="glass-panel p-4 h-100">
                <h5 class="font-heading fw-700 mb-4"><i class="fa-solid fa-history text-primary me-2"></i>Recent Generated History</h5>
                
                <?php if (empty($reports_history)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-regular fa-folder-open fs-2 mb-2"></i>
                        <p class="m-0">No exports logged in registry. Generate one above!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-muted" style="font-size: 0.85rem;">
                                    <th>Report Scope</th>
                                    <th>Format</th>
                                    <th>Actor</th>
                                    <th>Created On</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.9rem;">
                                <?php foreach ($reports_history as $rep): ?>
                                    <tr>
                                        <td class="fw-700 text-secondary"><?php echo sanitize(str_replace('_', ' ', strtoupper($rep['report_type']))); ?></td>
                                        <td>
                                            <span class="badge <?php echo $rep['format'] === 'pdf' ? 'bg-danger bg-opacity-10 text-danger border-danger' : 'bg-success bg-opacity-10 text-success border-success'; ?> border px-2 py-1">
                                                <?php echo strtoupper($rep['format']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($rep['admin_name']); ?></td>
                                        <td><?php echo formatDate($rep['created_at']); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo SITE_URL . '/' . sanitize($rep['file_path']); ?>" class="btn btn-sm btn-outline-primary" download title="Download Report"><i class="fa-solid fa-download"></i></a>
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
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
