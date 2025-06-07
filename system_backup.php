<?php
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

// Define backup directory
$backupDir = __DIR__.'/../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_backup'])) {
    try {
        $backupFileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . $backupFileName;
        
        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $output = '';
        
        // Loop through tables
        foreach ($tables as $table) {
            // Table structure
            $output .= "--\n-- Table structure for table `$table`\n--\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $createTable = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $createTable->fetch_row();
            $output .= $row[1] . ";\n\n";
            
            // Table data
            $output .= "--\n-- Dumping data for table `$table`\n--\n";
            $data = $conn->query("SELECT * FROM `$table`");
            
            while ($row = $data->fetch_row()) {
                $output .= "INSERT INTO `$table` VALUES(";
                for ($i = 0; $i < count($row); $i++) {
                    $row[$i] = addslashes($row[$i]);
                    $row[$i] = str_replace("\n", "\\n", $row[$i]);
                    if (isset($row[$i])) {
                        $output .= "'" . $row[$i] . "'";
                    } else {
                        $output .= "NULL";
                    }
                    if ($i < (count($row) - 1)) {
                        $output .= ",";
                    }
                }
                $output .= ");\n";
            }
            $output .= "\n";
        }
        
        // Save to file
        $handle = fopen($backupPath, 'w+');
        fwrite($handle, $output);
        fclose($handle);
        
        $success = "Backup created successfully: " . $backupFileName;
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        $error = "Failed to create backup: " . $e->getMessage();
    }
}

// Handle backup restore
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_backup'])) {
    $backupFile = $_POST['backup_file'] ?? '';
    $backupPath = $backupDir . $backupFile;
    
    if (empty($backupFile)) {
        $error = "Please select a backup file";
    } elseif (!file_exists($backupPath)) {
        $error = "Backup file not found";
    } else {
        try {
            $conn->autocommit(false);
            
            // Temporary variable to store current query
            $templine = '';
            
            // Read backup file line by line
            $lines = file($backupPath);
            
            foreach ($lines as $line) {
                // Skip comments
                if (substr($line, 0, 2) == '--' || $line == '') {
                    continue;
                }
                
                // Add line to current segment
                $templine .= $line;
                
                // If it has a semicolon at the end, execute the query
                if (substr(trim($line), -1, 1) == ';') {
                    $conn->query($templine) or die('Error performing query: ' . $conn->error);
                    $templine = '';
                }
            }
            
            $conn->commit();
            $success = "Backup restored successfully: " . $backupFile;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Restore error: " . $e->getMessage());
            $error = "Failed to restore backup: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    }
}

// Handle backup download
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['download_backup'])) {
    $backupFile = $_POST['backup_file'] ?? '';
    $backupPath = $backupDir . $backupFile;
    
    if (empty($backupFile)) {
        $error = "Please select a backup file";
    } elseif (!file_exists($backupPath)) {
        $error = "Backup file not found";
    } else {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backupPath));
        readfile($backupPath);
        exit;
    }
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_backup'])) {
    $backupFile = $_POST['backup_file'] ?? '';
    $backupPath = $backupDir . $backupFile;
    
    if (empty($backupFile)) {
        $error = "Please select a backup file";
    } elseif (!file_exists($backupPath)) {
        $error = "Backup file not found";
    } else {
        if (unlink($backupPath)) {
            $success = "Backup deleted successfully: " . $backupFile;
        } else {
            $error = "Failed to delete backup file";
        }
    }
}

// Get list of backup files
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backupFiles[] = $file;
        }
    }
    rsort($backupFiles); // Sort by newest first
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Backup - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .backup-card {
            margin-bottom: 20px;
        }
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .backup-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .backup-item:last-child {
            border-bottom: none;
        }
        .file-size {
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>System Backup</h1>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Create Backup Card -->
            <div class="col-md-6">
                <div class="card backup-card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Create New Backup</h5>
                    </div>
                    <div class="card-body">
                        <p>Create a complete backup of the entire database. This will include all tables and data.</p>
                        <form method="POST">
                            <div class="d-grid">
                                <button type="submit" name="create_backup" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Create Backup Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Backup Statistics Card -->
            <div class="col-md-6">
                <div class="card backup-card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Backup Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-database fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Total Backups</h6>
                                        <h3 class="mb-0"><?= count($backupFiles) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-hdd fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Storage Used</h6>
                                        <h3 class="mb-0">
                                            <?php
                                            $totalSize = 0;
                                            foreach ($backupFiles as $file) {
                                                $totalSize += filesize($backupDir . $file);
                                            }
                                            echo round($totalSize / (1024 * 1024), 2) . ' MB';
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Files List -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-file-archive me-2"></i>Available Backups</h5>
            </div>
            <div class="card-body">
                <?php if (empty($backupFiles)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> No backup files found.
                    </div>
                <?php else: ?>
                    <div class="backup-list">
                        <?php foreach ($backupFiles as $file): ?>
                            <div class="backup-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-alt me-2"></i>
                                    <strong><?= htmlspecialchars($file) ?></strong>
                                    <div class="file-size">
                                        <?= round(filesize($backupDir . $file) / (1024 * 1024), 2) ?> MB
                                        - <?= date('M j, Y H:i:s', filemtime($backupDir . $file)) ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file) ?>">
                                        <button type="submit" name="download_backup" class="btn btn-sm btn-outline-primary" 
                                                title="Download Backup">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file) ?>">
                                        <button type="submit" name="restore_backup" class="btn btn-sm btn-outline-success" 
                                                title="Restore Backup" onclick="return confirm('WARNING: This will overwrite all current data. Continue?')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file) ?>">
                                        <button type="submit" name="delete_backup" class="btn btn-sm btn-outline-danger" 
                                                title="Delete Backup" onclick="return confirm('Are you sure you want to delete this backup?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Backup Instructions -->
        <div class="card mt-4 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Backup Instructions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-check-circle text-success me-2"></i>Best Practices</h6>
                        <ul>
                            <li>Create backups regularly (at least weekly)</li>
                            <li>Store backups in a secure location</li>
                            <li>Test restoring from backups periodically</li>
                            <li>Keep multiple backup versions</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Notes</h6>
                        <ul>
                            <li>Restoring will overwrite all current data</li>
                            <li>Backups contain sensitive information - protect them</li>
                            <li>Large databases may take time to backup/restore</li>
                            <li>Consider automated backup solutions for production</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

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