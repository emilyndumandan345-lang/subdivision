<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with search functionality
if (!empty($search)) {
    $query = "SELECT v.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                     r.house_number, r.block_lot
              FROM visitors v 
              LEFT JOIN users u ON v.resident_id = u.id 
              LEFT JOIN residents r ON u.id = r.user_id
              WHERE v.visitor_name LIKE :search 
                 OR v.visitor_phone LIKE :search 
                 OR v.purpose LIKE :search
                 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search
                 OR v.status LIKE :search
              ORDER BY v.visit_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':search', '%' . $search . '%');
    $stmt->execute();
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $query = "SELECT v.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                     r.house_number, r.block_lot
              FROM visitors v 
              LEFT JOIN users u ON v.resident_id = u.id 
              LEFT JOIN residents r ON u.id = r.user_id
              ORDER BY v.visit_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics calculations
$total_visitors = count($visitors);
$pre_registered = count(array_filter($visitors, function($v) { return $v['status'] === 'pre-registered'; }));
$checked_in = count(array_filter($visitors, function($v) { return $v['status'] === 'checked-in'; }));
$checked_out = count(array_filter($visitors, function($v) { return $v['status'] === 'checked-out'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitors Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #402222;
            --admin-secondary: #CCB099;
            --admin-success: #28a745;
            --admin-warning: #ffc107;
        }

        .btn-admin {
            background: var(--admin-primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            background: #2d1717;
            transform: translateY(-2px);
            color: white;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--admin-primary), #5d3333);
            min-height: 100vh;
            padding: 20px 0;
        }

        .sidebar-nav .nav-link {
            color: var(--admin-secondary);
            margin: 5px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .main-content {
            background: white;
            border-radius: 20px 0 0 20px;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
            min-height: 100vh;
        }

        .stat-card {
            background: var(--admin-secondary);
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: transform 0.3s ease;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.danger {
            background: var(--admin-primary);
        }

        .stat-card.success {
            background: var(--admin-success);
        }

        .stat-card.warning {
            background: var(--admin-warning);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="text-center mb-4">
                    <h4 class="text-white">Admin Panel</h4>
                    <small class="text-light">Welcome, <?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']); ?></small>
                </div>
                
                <ul class="nav flex-column sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="residents.php">
                            <i class="fas fa-users me-2"></i>Residents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="visitors.php">
                            <i class="fas fa-user-friends me-2"></i>Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pending_residents.php">
                            <i class="fas fa-user-clock me-2"></i>Pending Residents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="concerns.php">
                            <i class="fas fa-exclamation-triangle me-2"></i>Concerns
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="announcements.php">
                            <i class="fas fa-bullhorn me-2"></i>Announcements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="mb-4">
                        <h2 class="mb-0">
                            <i class="fas fa-user-friends me-3" style="color: var(--admin-primary);"></i>
                            Visitors Management
                        </h2>
                        <p class="text-muted">Manage and monitor all subdivision visitors</p>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4><?php echo $total_visitors; ?></h4>
                                        <p class="mb-0">Total Visitors</p>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4><?php echo $checked_in; ?></h4>
                                        <p class="mb-0">Checked In</p>
                                    </div>
                                    <i class="fas fa-sign-in-alt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4><?php echo $pre_registered; ?></h4>
                                        <p class="mb-0">Pre-registered</p>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4><?php echo $checked_out; ?></h4>
                                        <p class="mb-0">Checked Out</p>
                                    </div>
                                    <i class="fas fa-sign-out-alt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-center">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Search visitors by name, phone, purpose, resident, or status..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-admin w-100">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="visitors.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </form>
                            <?php if (!empty($search)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                                        (<?php echo count($visitors); ?> visitors found)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Visitors Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-address-book text-info me-2"></i>
                                All Visitors
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="visitorsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Visitor Name</th>
                                            <th>Visiting</th>
                                            <th>Visit Date</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Check In/Out</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($visitors)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fas fa-users fa-3x mb-3"></i>
                                                        <br>No visitors found
                                                        <?php if (!empty($search)): ?>
                                                            <br><small>Try adjusting your search terms</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($visitors as $visitor): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-user-circle text-muted me-2"></i>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($visitor['visitor_name']); ?></strong>
                                                                <?php if (!empty($visitor['visitor_phone'])): ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($visitor['visitor_phone']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($visitor['resident_name']); ?></strong>
                                                        <?php if (!empty($visitor['house_number']) || !empty($visitor['block_lot'])): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars(trim($visitor['house_number'] . ' ' . $visitor['block_lot'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-calendar-alt text-primary me-1"></i>
                                                        <?php echo date('M j, Y', strtotime($visitor['visit_date'])); ?>
                                                        <br><small class="text-muted"><?php echo date('g:i A', strtotime($visitor['visit_date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($visitor['purpose']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch(strtolower($visitor['status'])) {
                                                            case 'pre-registered':
                                                                $status_class = 'bg-warning';
                                                                break;
                                                            case 'checked-in':
                                                                $status_class = 'bg-success';
                                                                break;
                                                            case 'checked-out':
                                                                $status_class = 'bg-secondary';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-primary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $visitor['status']))); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (isset($visitor['time_in']) && !empty($visitor['time_in'])): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-sign-in-alt me-1"></i>
                                                                <?php echo date('g:i A', strtotime($visitor['time_in'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (isset($visitor['time_out']) && !empty($visitor['time_out'])): ?>
                                                            <br><small class="text-danger">
                                                                <i class="fas fa-sign-out-alt me-1"></i>
                                                                <?php echo date('g:i A', strtotime($visitor['time_out'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                    </div>

                    <!-- Search Bar -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-center">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Search visitors by name, phone, purpose, resident, or status..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-admin w-100">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="visitors.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </form>
                            <?php if (!empty($search)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                                        (<?php echo count($visitors); ?> visitors found)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Visitors Table -->
                    <div class="card visitor-card">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-address-book text-info me-2"></i>
                                All Visitors
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="visitorsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Visitor Name</th>
                                            <th>Visiting</th>
                                            <th>Visit Date</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Check In/Out</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($visitors)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    No visitors found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($visitors as $visitor): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($visitor['visitor_name']); ?></strong>
                                                        <?php if ($visitor['visitor_phone']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($visitor['visitor_phone']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($visitor['resident_name']); ?></strong>
                                                        <?php if (!empty($visitor['house_number']) || !empty($visitor['block_lot'])): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars(trim($visitor['house_number'] . ' ' . $visitor['block_lot'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($visitor['visit_date'])); ?>
                                                        <?php if (isset($visitor['time_in']) && $visitor['time_in']): ?>
                                                            <br><small class="text-muted">In: <?php echo date('g:i A', strtotime($visitor['time_in'])); ?></small>
                                                        <?php endif; ?>
                                                        <?php if (isset($visitor['time_out']) && $visitor['time_out']): ?>
                                                            <br><small class="text-muted">Out: <?php echo date('g:i A', strtotime($visitor['time_out'])); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($visitor['purpose']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $visitor['status'] === 'pre-registered' ? 'info' : 
                                                                ($visitor['status'] === 'checked-in' ? 'warning' : 'success'); 
                                                        ?>">
                                                            <?php echo str_replace(['-', '_'], ' ', ucfirst($visitor['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (isset($visitor['time_in']) && $visitor['time_in']): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-sign-in-alt me-1"></i>
                                                                <?php echo date('g:i A', strtotime($visitor['time_in'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (isset($visitor['time_out']) && $visitor['time_out']): ?>
                                                            <br><small class="text-danger">
                                                                <i class="fas fa-sign-out-alt me-1"></i>
                                                                <?php echo date('g:i A', strtotime($visitor['time_out'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus search input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value === '') {
                searchInput.focus();
            }
        });

        // Handle Enter key for search
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });

        // Handle Escape key to clear search
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput.value !== '') {
                    window.location.href = 'visitors.php';
                }
            }
        });

        // Highlight search terms in the table
        function highlightSearchTerms() {
            const searchTerm = "<?php echo addslashes($search); ?>";
            if (searchTerm) {
                const cells = document.querySelectorAll('#visitorsTable td');
                cells.forEach(cell => {
                    if (cell.textContent && !cell.querySelector('button')) {
                        const regex = new RegExp(`(${searchTerm})`, 'gi');
                        cell.innerHTML = cell.textContent.replace(regex, '<mark>$1</mark>');
                    }
                });
            }
        }

        // Call highlight function after page loads
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);
    </script>
</body>
</html>
