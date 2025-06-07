<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in as customer
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];

// Fetch only the current user's queries
$queries = [];
try {
    $stmt = $conn->prepare("
        SELECT q.*, u.name as resolved_by_name 
        FROM queries q
        LEFT JOIN users u ON q.resolved_by = u.id
        WHERE q.user_id = ?
        ORDER BY q.status, q.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $queries = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching queries: " . $e->getMessage());
    $_SESSION['query_error'] = "Error loading your queries";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Queries - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .query-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        .query-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .badge-pending { background-color: var(--warning-color); color: #000; }
        .badge-in_progress { background-color: var(--primary-color); }
        .badge-resolved { background-color: var(--secondary-color); }
        
        .badge-low { background-color: var(--secondary-color); }
        .badge-medium { background-color: var(--warning-color); color: #000; }
        .badge-high { background-color: var(--danger-color); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Support Queries</h1>
            <a href="submit_query.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Query
            </a>
        </div>
        
        <?php if (isset($_SESSION['query_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['query_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['query_error']); endif; ?>
        
        <?php if (isset($_SESSION['query_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['query_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['query_success']); endif; ?>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">All My Queries</h5>
            </div>
            <div class="card-body">
                <?php if (count($queries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queries as $query): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($query['subject']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $query['status'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $query['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($query['priority']); ?>">
                                            <?php echo ucfirst($query['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($query['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#queryModal<?php echo $query['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        You haven't submitted any queries yet. 
                        <a href="submit_query.php" class="alert-link">Submit your first query</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Query Detail Modals -->
    <?php foreach ($queries as $query): ?>
    <div class="modal fade" id="queryModal<?php echo $query['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Query Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Subject:</h6>
                        <p><?php echo htmlspecialchars($query['subject']); ?></p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Status:</h6>
                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '_', $query['status'])); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $query['status'])); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <h6>Priority:</h6>
                            <span class="badge badge-<?php echo strtolower($query['priority']); ?>">
                                <?php echo ucfirst($query['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Submitted:</h6>
                        <p><?php echo date('M j, Y g:i A', strtotime($query['created_at'])); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Your Message:</h6>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($query['message'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($query['status'] === 'resolved' && !empty($query['response'])): ?>
                    <div class="mb-3">
                        <h6>Support Response:</h6>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($query['response'])); ?>
                        </div>
                        <small class="text-muted">
                            Resolved by <?php echo htmlspecialchars($query['resolved_by_name'] ?? 'Staff'); ?> 
                            on <?php echo date('M j, Y g:i A', strtotime($query['resolved_at'])); ?>
                        </small>
                    </div>
                    <?php elseif ($query['status'] === 'in_progress'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Our support team is currently working on your query.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        Your query is pending review by our support team.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>