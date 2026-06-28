<?php
/**
 * CampusFind Pro - Report Export Processing Engine
 * Generates Excel spreadsheet mockups (via HTML format headers) and printable PDF sheets.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Secure access
requireRole('admin');

$admin_id = $_SESSION['user_id'];
$scope = $_GET['scope'] ?? '';
$format = $_GET['format'] ?? '';

if (!in_array($scope, ['lost_items', 'found_items', 'claims', 'users']) || !in_array($format, ['pdf', 'excel'])) {
    die("Invalid request parameters.");
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Fetch relevant data
    $data = [];
    $title = '';
    $headers = [];

    if ($scope === 'lost_items') {
        $title = 'Active Lost Items Registry';
        $headers = ['ID', 'Title', 'Category', 'Reporter Name', 'Location', 'Lost Date', 'Reward ($)', 'Status', 'Registered On'];
        $stmt = $db->query("
            SELECT l.id, l.title, c.name as category, u.name as reporter, l.location, l.lost_date, l.reward, l.status, l.created_at
            FROM lost_items l
            JOIN categories c ON l.category_id = c.id
            JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
        ");
        $data = $stmt->fetchAll();
    } elseif ($scope === 'found_items') {
        $title = 'Logged Found Items Registry';
        $headers = ['ID', 'Title', 'Category', 'Reporter Name', 'Location', 'Found Date', 'Status', 'Registered On'];
        $stmt = $db->query("
            SELECT f.id, f.title, c.name as category, u.name as reporter, f.location, f.found_date, f.status, f.created_at
            FROM found_items f
            JOIN categories c ON f.category_id = c.id
            JOIN users u ON f.user_id = u.id
            ORDER BY f.created_at DESC
        ");
        $data = $stmt->fetchAll();
    } elseif ($scope === 'claims') {
        $title = 'Claim Request Auditing Logs';
        $headers = ['ID', 'Registry Type', 'Item Name', 'Claimer Student', 'Claim Date', 'Status', 'Admin Notes', 'Processed Date'];
        $stmt = $db->query("
            SELECT c.id, c.item_type, 
            CASE WHEN c.item_type = 'found' THEN f.title ELSE l.title END as item_title,
            u.name as claimer, c.created_at, c.status, c.admin_notes, c.processed_at
            FROM claims c
            JOIN users u ON c.claimer_id = u.id
            LEFT JOIN found_items f ON c.item_id = f.id AND c.item_type = 'found'
            LEFT JOIN lost_items l ON c.item_id = l.id AND c.item_type = 'lost'
            ORDER BY c.created_at DESC
        ");
        $data = $stmt->fetchAll();
    } else {
        $title = 'Registered Campus Students Registry';
        $headers = ['ID', 'Student ID', 'Full Name', 'Email Address', 'Phone Number', 'Status', 'Email Verified', 'Created Date'];
        $stmt = $db->query("
            SELECT id, student_id, name, email, phone, status, is_verified, created_at 
            FROM users 
            WHERE role = 'student' 
            ORDER BY created_at DESC
        ");
        $data = $stmt->fetchAll();
    }

    // 2. Archive Snapshot File on Disk (Static reporting snapshot)
    $report_dir = UPLOAD_PATH . '/reports';
    if (!is_dir($report_dir)) {
        mkdir($report_dir, 0777, true);
    }

    $filename_base = 'CF_' . $scope . '_' . time();
    $file_ext = ($format === 'excel') ? 'xls' : 'html';
    $relative_path = 'uploads/reports/' . $filename_base . '.' . $file_ext;
    $absolute_path = ROOT_PATH . '/' . $relative_path;

    // Build the report content markup
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo $title; ?> - Audit Export</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; padding: 20px; }
            h2 { color: #4f46e5; text-align: center; margin-bottom: 5px; }
            .meta { text-align: center; color: #666; font-size: 0.85rem; margin-bottom: 25px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; text-align: left; padding: 12px; border: 1px solid #cbd5e1; font-size: 0.85rem; }
            td { padding: 10px; border: 1px solid #e2e8f0; font-size: 0.85rem; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
            .print-btn { background: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: block; margin: 0 auto 20px auto; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <h2>CampusFind Pro - Audit Report</h2>
        <div class="meta">
            Scope: <strong><?php echo $title; ?></strong> | Generated On: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <?php if ($format === 'pdf'): ?>
            <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $head): ?>
                        <th><?php echo $head; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?php echo count($headers); ?>" style="text-align: center;">No records logged in database.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $col): ?>
                                <td><?php echo sanitize($col ?? 'N/A'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $report_content = ob_get_clean();

    // Write file snapshot to storage
    file_put_contents($absolute_path, $report_content);

    // Save report generation event in history log table
    $db_format = ($format === 'excel') ? 'xlsx' : 'pdf';
    $save_report = $db->prepare("
        INSERT INTO reports (admin_id, report_type, format, file_path) 
        VALUES (:admin, :type, :format, :path)
    ");
    $save_report->execute([
        ':admin' => $admin_id,
        ':type' => $scope,
        ':format' => $db_format,
        ':path' => $relative_path
    ]);

    logActivity($admin_id, 'GENERATE_REPORT', 'Generated export audit report: ' . $title);

    // 3. Dispatch Content
    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=" . $filename_base . ".xls");
    }
    echo $report_content;
    exit;

} catch (Exception $e) {
    error_log("Reports engine exception: " . $e->getMessage());
    die("A system error occurred while compiling the report files.");
}
