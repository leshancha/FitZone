<?php
// manage_queries.php
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
$admin_id = $_SESSION['admin_id'];
$success = '';
$error = '';

// Handle query resolution
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['resolve_query'])) {
        $query_id = $_POST['query_id'];
        $response = trim($_POST['response']);
        
        if (empty($response)) {
            $error = "Response cannot be empty";
        } else {
            try {
                $conn->autocommit(false);
                
                $stmt = $conn->prepare("
                    UPDATE queries 
                    SET status = 'resolved', 
                        response = ?, 
                        resolved_by = ?, 
                        resolved_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("sii", $response, $admin_id, $query_id);
                $stmt->execute();
                
                $conn->commit();
                $success = "Query resolved successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Query resolution error: " . $e->getMessage());
                $error = "Failed to resolve query. Please try again.";
            } finally {
                $conn->autocommit(true);
            }
        }
    } elseif (isset($_POST['change_priority'])) {
        $query_id = $_POST['query_id'];
        $priority = $_POST['priority'];
        
        try {
            $stmt = $conn->prepare("UPDATE queries SET priority = ? WHERE id = ?");
            $stmt->bind_param("si", $priority, $query_id);
            $stmt->execute();
            $success = "Priority updated successfully!";
        } catch (Exception $e) {
            error_log("Priority update error: " . $e->getMessage());
            $error = "Failed to update priority. Please try again.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Build base query
$query = "SELECT q.*, u.name as user_name, u.email as user_email 
          FROM queries q
          LEFT JOIN users u ON q.user_id = u.id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND q.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($priority_filter !== 'all') {
    $query .= " AND q.priority = ?";
    $params[] = $priority_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $query .= " AND (q.subject LIKE ? OR q.message LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Add sorting
$query .= " ORDER BY 
            CASE WHEN q.priority = 'high' THEN 1 
                 WHEN q.priority = 'medium' THEN 2
                 ELSE 3 END,
            q.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$queries = $result->fetch_all(MYSQLI_ASSOC);

// Get counts for filter tabs
$count_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM queries
");
$counts = $count_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customer Queries - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge-high { background-color: #e74a3b; }
        .badge-medium { background-color: #f6c23e; color: #000; }
        .badge-low { background-color: #1cc88a; }
        
        .filter-tabs .nav-link {
            border-radius: 0;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
        
        .filter-tabs .nav-link.active {
            border-bottom: 3px solid var(--bs-primary);
        }
        
        .query-card {
            transition: all 0.2s;
            border-left: 4px solid;
        }
        
        .query-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .query-card.pending { border-left-color: #f6c23e; }
        .query-card.in_progress { border-left-color: #4e73df; }
        .query-card.resolved { border-left-color: #1cc88a; }
        
        .priority-select {
            border: none;
            background: transparent;
            font-weight: bold;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .priority-select.high { color: #e74a3b; background-color: rgba(231, 74, 59, 0.1); }
        .priority-select.medium { color: #f6c23e; background-color: rgba(246, 194, 62, 0.1); }
        .priority-select.low { color: #1cc88a; background-color: rgba(28, 200, 138, 0.1); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Customer Queries</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Print</button>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filter Tabs -->
                <ul class="nav nav-tabs filter-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" 
                           href="?status=all&priority=<?= $priority_filter ?>&search=<?= urlencode($search_term) ?>">
                            All <span class="badge bg-secondary"><?= $counts['total'] ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" 
                           href="?status=pending&priority=<?= $priority_filter ?>&search=<?= urlencode($search_term) ?>">
                            Pending <span class="badge bg-warning"><?= $counts['pending'] ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'in_progress' ? 'active' : '' ?>" 
                           href="?status=in_progress&priority=<?= $priority_filter ?>&search=<?= urlencode($search_term) ?>">
                            In Progress <span class="badge bg-primary"><?= $counts['in_progress'] ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status_filter === 'resolved' ? 'active' : '' ?>" 
                           href="?status=resolved&priority=<?= $priority_filter ?>&search=<?= urlencode($search_term) ?>">
                            Resolved <span class="badge bg-success"><?= $counts['resolved'] ?></span>
                        </a>
                    </li>
                </ul>
                
                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="priority" class="form-label">Priority</label>
                                <select id="priority" name="priority" class="form-select">
                                    <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>All Priorities</option>
                                    <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by subject, message or customer name" value="<?= htmlspecialchars($search_term) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                            <input type="hidden" name="status" value="<?= $status_filter ?>">
                        </form>
                    </div>
                </div>
                
                <!-- Queries List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (count($queries) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Subject</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($queries as $query): ?>
                                        <tr class="query-card <?= $query['status'] ?>">
                                            <td>
                                                <div><?= htmlspecialchars($query['user_name'] ?? 'Guest') ?></div>
                                                <?php if ($query['user_email']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($query['user_email']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($query['subject']) ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="query_id" value="<?= $query['id'] ?>">
                                                    <select name="priority" class="priority-select <?= $query['priority'] ?>"
                                                            onchange="this.form.submit()">
                                                        <option value="high" <?= $query['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                                        <option value="medium" <?= $query['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                                        <option value="low" <?= $query['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                                    </select>
                                                    <input type="hidden" name="change_priority" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    match($query['status']) {
                                                        'pending' => 'warning',
                                                        'in_progress' => 'primary',
                                                        'resolved' => 'success',
                                                        default => 'secondary'
                                                    }
                                                ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $query['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y g:i a', strtotime($query['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#queryModal<?= $query['id'] ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($query['status'] !== 'resolved'): ?>
                                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" 
                                                        data-bs-target="#resolveModal<?= $query['id'] ?>">
                                                    <i class="fas fa-check"></i> Resolve
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination would go here -->
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-4">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                No queries found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Query Detail Modals -->
    <?php foreach ($queries as $query): ?>
    <div class="modal fade" id="queryModal<?= $query['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Query Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Customer:</h6>
                        <p><?= htmlspecialchars($query['user_name'] ?? 'Guest') ?></p>
                        <?php if ($query['user_email']): ?>
                        <p><small class="text-muted"><?= htmlspecialchars($query['user_email']) ?></small></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Subject:</h6>
                        <p><?= htmlspecialchars($query['subject']) ?></p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <h6>Priority:</h6>
                            <span class="badge badge-<?= strtolower($query['priority']) ?>">
                                <?= ucfirst($query['priority']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <h6>Status:</h6>
                            <span class="badge bg-<?= 
                                match($query['status']) {
                                    'pending' => 'warning',
                                    'in_progress' => 'primary',
                                    'resolved' => 'success',
                                    default => 'secondary'
                                }
                            ?>">
                                <?= ucfirst(str_replace('_', ' ', $query['status'])) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <h6>Submitted:</h6>
                            <p><?= date('M j, Y g:i A', strtotime($query['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Message:</h6>
                        <div class="bg-light p-3 rounded">
                            <?= nl2br(htmlspecialchars($query['message'])) ?>
                        </div>
                    </div>
                    
                    <?php if ($query['status'] === 'resolved'): ?>
                    <div class="mb-3">
                        <h6>Response:</h6>
                        <div class="bg-light p-3 rounded">
                            <?= nl2br(htmlspecialchars($query['response'])) ?>
                        </div>
                        <p class="text-muted mt-2">
                            <small>Resolved by admin on <?= date('M j, Y g:i A', strtotime($query['resolved_at'])) ?></small>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Resolve Query Modal -->
    <?php if ($query['status'] !== 'resolved'): ?>
    <div class="modal fade" id="resolveModal<?= $query['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Query</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <h6>Customer:</h6>
                            <p><?= htmlspecialchars($query['user_name'] ?? 'Guest') ?></p>
                            <?php if ($query['user_email']): ?>
                            <p><small class="text-muted"><?= htmlspecialchars($query['user_email']) ?></small></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Subject:</h6>
                            <p><?= htmlspecialchars($query['subject']) ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Message:</h6>
                            <div class="bg-light p-3 rounded">
                                <?= nl2br(htmlspecialchars($query['message'])) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Your Response:</h6>
                            <textarea name="response" class="form-control" rows="5" required></textarea>
                        </div>
                        
                        <input type="hidden" name="query_id" value="<?= $query['id'] ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="resolve_query" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Submit Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn = null;
?>