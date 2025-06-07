<?php
// system_logs.php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not admin
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Set default filter values
$log_type = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// Build base query
$query = "SELECT * FROM system_logs WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM system_logs WHERE 1=1";
$params = [];
$types = '';

// Apply filters
if ($log_type !== 'all') {
    $query .= " AND log_type = ?";
    $count_query .= " AND log_type = ?";
    $params[] = $log_type;
    $types .= 's';
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND log_time BETWEEN ? AND ?";
    $count_query .= " AND log_time BETWEEN ? AND ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
    $types .= 'ss';
}

if (!empty($search)) {
    $query .= " AND (message LIKE ? OR user_id LIKE ? OR ip_address LIKE ?)";
    $count_query .= " AND (message LIKE ? OR user_id LIKE ? OR ip_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_logs = $total_result['total'];
$total_pages = ceil($total_logs / $per_page);

// Add sorting and pagination
$query .= " ORDER BY log_time DESC LIMIT ? OFFSET ?";
$offset = ($page - 1) * $per_page;
$params[] = $per_page;
    $params[] = $offset;
$types .= 'ii';

// Execute main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Get log type counts for filter tabs
$type_counts_query = $conn->query("
    SELECT 
        log_type,
        COUNT(*) as count
    FROM system_logs
    GROUP BY log_type
    ORDER BY count DESC
");
$type_counts = $type_counts_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-card {
            border-left: 4px solid;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
        }
        
        .log-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .log-error { border-left-color: #e74a3b; }
        .log-warning { border-left-color: #f6c23e; }
        .log-info { border-left-color: #36b9cc; }
        .log-success { border-left-color: #1cc88a; }
        .log-debug { border-left-color: #858796; }
        
        .badge-error { background-color: #e74a3b; }
        .badge-warning { background-color: #f6c23e; color: #000; }
        .badge-info { background-color: #36b9cc; }
        .badge-success { background-color: #1cc88a; }
        .badge-debug { background-color: #858796; }
        
        .log-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .log-details.show {
            max-height: 500px;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Logs</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Log Type</label>
                            <select id="type" name="type" class="form-select">
                                <option value="all" <?= $log_type === 'all' ? 'selected' : '' ?>>All Types</option>
                                <?php foreach ($type_counts as $type_count): ?>
                                    <option value="<?= $type_count['log_type'] ?>" <?= $log_type === $type_count['log_type'] ? 'selected' : '' ?>>
                                        <?= ucfirst($type_count['log_type']) ?> (<?= $type_count['count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search logs..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="system_logs.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-sync-alt me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Logs Summary Cards -->
                <div class="row mb-4">
                    <?php 
                    $summary_types = [
                        ['type' => 'error', 'icon' => 'exclamation-circle', 'color' => 'danger'],
                        ['type' => 'warning', 'icon' => 'exclamation-triangle', 'color' => 'warning'],
                        ['type' => 'info', 'icon' => 'info-circle', 'color' => 'info'],
                        ['type' => 'success', 'icon' => 'check-circle', 'color' => 'success'],
                        ['type' => 'debug', 'icon' => 'bug', 'color' => 'secondary']
                    ];
                    
                    foreach ($summary_types as $summary): 
                        $count_query = $conn->prepare("SELECT COUNT(*) as count FROM system_logs WHERE log_type = ?");
                        $count_query->bind_param('s', $summary['type']);
                        $count_query->execute();
                        $count = $count_query->get_result()->fetch_assoc()['count'];
                    ?>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="text-<?= $summary['color'] ?> mb-2">
                                    <i class="fas fa-<?= $summary['icon'] ?> fa-3x"></i>
                                </div>
                                <h5 class="card-title"><?= ucfirst($summary['type']) ?></h5>
                                <h2 class="mb-0"><?= $count ?></h2>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Logs Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (count($logs) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>IP Address</th>
                                            <th>Message</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                        <tr class="log-card log-<?= $log['log_type'] ?>" 
                                            onclick="toggleDetails('log-details-<?= $log['id'] ?>')"
                                            style="cursor: pointer;">
                                            <td>
                                                <span class="badge badge-<?= $log['log_type'] ?>">
                                                    <?= ucfirst($log['log_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y H:i:s', strtotime($log['log_time'])) ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <?= htmlspecialchars($log['user_id']) ?>
                                                <?php else: ?>
                                                    System
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                            <td><?= htmlspecialchars(substr($log['message'], 0, 50)) ?><?= strlen($log['message']) > 50 ? '...' : '' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="event.stopPropagation();toggleDetails('log-details-<?= $log['id'] ?>')">
                                                    <i class="fas fa-ellipsis-h"></i> Details
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="p-0 border-0">
                                                <div id="log-details-<?= $log['id'] ?>" class="log-details bg-light p-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Full Message:</h6>
                                                            <pre><?= htmlspecialchars($log['message']) ?></pre>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Additional Data:</h6>
                                                            <pre><?= !empty($log['context']) ? htmlspecialchars(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)) : 'No additional data' ?></pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <nav aria-label="Logs pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Next</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                No logs found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle log details
        function toggleDetails(id) {
            const element = document.getElementById(id);
            element.classList.toggle('show');
        }
        
        // Export logs to CSV
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export_logs.php?' + params.toString();
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn = null;
?>