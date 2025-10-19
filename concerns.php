<?php
// filepath: c:\xampp\htdocs\subdivision\admin\concerns.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

// Get filter parameter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get all concerns with resident information
if ($status_filter === 'all') {
    $concerns = $pdo->query("
        SELECT c.*, u.first_name, u.last_name, u.email 
        FROM concerns c 
        JOIN users u ON c.resident_id = u.id 
        ORDER BY c.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email 
        FROM concerns c 
        JOIN users u ON c.resident_id = u.id 
        WHERE c.status = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$status_filter]);
    $concerns = $stmt->fetchAll();
}

// Get all concerns for statistics (unfiltered)
$all_concerns = $pdo->query("
    SELECT c.*, u.first_name, u.last_name, u.email 
    FROM concerns c 
    JOIN users u ON c.resident_id = u.id 
    ORDER BY c.created_at DESC
")->fetchAll();

// Get statistics
$total_concerns = count($all_concerns);
$pending_concerns = count(array_filter($all_concerns, function($c) { return $c['status'] === 'pending'; }));
$working_concerns = count(array_filter($all_concerns, function($c) { return $c['status'] === 'in-progress'; }));
$resolved_concerns = count(array_filter($all_concerns, function($c) { return $c['status'] === 'resolved'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concerns Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #402222;
            --admin-secondary: #CCB099;
            --admin-success: #402222;
            --admin-warning: #402222;
            --admin-info: #CCB099;
            --admin-light: #CCB099;
            --admin-dark: #402222;
        }

        body {
            background: var(--admin-light);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--admin-primary) 0%, #4a2a2a 100%);
            min-height: 100vh;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1030;
            overflow-y: auto;
            padding-bottom: 50px;
        }

        .sidebar nav {
            padding-bottom: 40px;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg, var(--admin-secondary) 0%, transparent 100%);
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 20px;
            margin: 3px 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            display: block;
            width: calc(100% - 30px);
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
            transition: left 0.5s ease;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 1rem;
        }

        /* Sidebar Header Styling */
        .sidebar .p-4 {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar .p-4 h4 {
            font-weight: 700;
            font-size: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.5px;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }

        .sidebar .p-4 h4 .d-inline-block {
            line-height: 1.1;
        }

        .sidebar .logout-link {
            background: rgba(220, 53, 69, 0.1) !important;
            border: 1px solid rgba(220, 53, 69, 0.3) !important;
            color: #dc3545 !important;
            margin: 10px 15px 25px 15px;
            padding: 12px 20px !important;
            border-radius: 12px !important;
            width: calc(100% - 30px);
            display: block !important;
            text-align: left !important;
        }

        .sidebar .logout-link:hover {
            background: #dc3545 !important;
            color: white !important;
            border-color: #dc3545 !important;
            transform: translateX(3px);
        }

        @media (min-width: 992px) {
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                height: 100vh;
                overflow-y: auto;
                padding-bottom: 60px;
                width: 280px;
                z-index: 1050;
                top: 0;
                position: fixed;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar nav {
                padding-bottom: 50px;
                height: auto;
                min-height: calc(100vh - 120px);
                padding-top: 0;
            }
            
            .sidebar .nav-link {
                padding: 15px 20px;
                font-size: 0.95rem;
                margin-bottom: 2px;
            }
            
            .sidebar .p-4 {
                padding: 15px 20px !important;
                margin-bottom: 10px;
            }
            
            .sidebar .p-4 h4 {
                font-size: 1.3rem;
            }

            .main-content {
                margin-left: 0 !important;
                height: auto;
                min-height: calc(100vh - 70px);
                width: 100%;
                overflow-y: auto;
            }
        }

        .main-content {
            background: white;
            border-radius: 0;
            box-shadow: none;
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            left: 280px;
            right: 0;
            top: 0;
            z-index: 1;
        }

        @media (min-width: 768px) {
            .main-content {
                left: 280px;
                right: 0;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                left: 220px;
                right: 0;
            }
        }

        .stat-card {
            background: var(--admin-secondary);
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: transform 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            color: white;
            text-decoration: none;
        }

        .stat-card.active {
            ring: 3px solid #fff;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3), 0 10px 25px rgba(0,0,0,0.2);
            transform: translateY(-5px);
        }

        .concern-image {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .concern-image:hover {
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .concern-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .concern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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

        .stat-card.info {
            background: var(--admin-info);
            color: var(--admin-primary);
        }

        .concern-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }

        .concern-card:hover {
            transform: translateY(-2px);
        }

        .concern-row {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .concern-row:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }

        .concern-summary {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .concern-details {
            display: none;
            padding: 0 16px 16px 16px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }

        .concern-details.show {
            display: block;
        }

        .priority-indicator {
            width: 4px;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
            border-radius: 10px 0 0 10px;
        }

        .priority-high .priority-indicator {
            background: #dc3545;
        }

        .priority-medium .priority-indicator {
            background: #fd7e14;
        }

        .priority-low .priority-indicator {
            background: #28a745;
        }

        .concern-image-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .concern-image-thumb:hover {
            border-color: var(--admin-secondary);
            transform: scale(1.05);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .expand-icon {
            transition: transform 0.3s ease;
        }

        .expanded .expand-icon {
            transform: rotate(180deg);
        }

        .brand-title {
            color: var(--admin-primary);
            font-weight: bold;
        }

        .concern-image {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }

        /* Mobile Responsive Design */
        .mobile-header {
            display: none;
            background: var(--admin-primary);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
            position: relative;
        }

        .mobile-menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .mobile-menu-btn.active {
            background: rgba(255,255,255,0.2);
        }

        /* Mobile Navigation Dropdown */
        .mobile-nav-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--admin-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            z-index: 1025;
            opacity: 0;
            visibility: hidden;
        }

        .mobile-nav-dropdown.show {
            max-height: 400px;
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav-item {
            display: block;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 15px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .mobile-nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            padding-left: 35px;
        }

        .mobile-nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 4px solid var(--admin-secondary);
        }

        .mobile-nav-item:last-child {
            border-bottom: none;
        }

        .mobile-nav-item i {
            width: 20px;
            margin-right: 10px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }

        @media (max-width: 767.98px) {
            .mobile-header {
                display: flex;
                justify-content: between;
                align-items: center;
            }

            .sidebar-col {
                display: none !important;
            }

            .main-content {
                border-radius: 0;
                margin-top: 0;
                min-height: calc(100vh - 70px);
                padding: 15px !important;
            }

            .main-content-col {
                padding: 0 !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }

            .brand-title {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 15px;
                margin-bottom: 10px;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 10px !important;
            }

            .brand-title {
                font-size: 1.2rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .concern-row {
                margin-bottom: 10px;
            }

            .concern-summary {
                padding: 12px 15px 12px 20px;
                min-height: auto;
            }

            .concern-summary h6 {
                font-size: 0.95rem;
            }

            .concern-details {
                padding: 0 15px 15px 20px;
            }

            .concern-image-thumb {
                max-width: 80px;
                max-height: 60px;
            }

            .stat-card {
                text-align: center;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header d-md-none">
        <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="text-white mb-0">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </h5>
            <button class="mobile-menu-btn" onclick="toggleMobileNav()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation Dropdown -->
        <div class="mobile-nav-dropdown" id="mobileNavDropdown">
            <a href="dashboard.php" class="mobile-nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="residents.php" class="mobile-nav-item">
                <i class="fas fa-users"></i> Manage Residents
            </a>
            <a href="pending_residents.php" class="mobile-nav-item">
                <i class="fas fa-user-clock"></i> Pending Approvals
            </a>
            <a href="announcements.php" class="mobile-nav-item">
                <i class="fas fa-bullhorn"></i> Announcements
            </a>
            <a href="concerns.php" class="mobile-nav-item active">
                <i class="fas fa-exclamation-triangle"></i> Concerns
            </a>
            <a href="visitors.php" class="mobile-nav-item">
                <i class="fas fa-address-book"></i> Visitors
            </a>
            <a href="reports.php" class="mobile-nav-item">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleMobileNav()"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar-col">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 px-0 main-content-col">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="brand-title">Concerns Management</h2>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <a href="?status=all" class="card stat-card info <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $total_concerns; ?></h3>
                                        <p class="mb-0">Total Concerns</p>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="?status=pending" class="card stat-card warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $pending_concerns; ?></h3>
                                        <p class="mb-0">Pending</p>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="?status=in-progress" class="card stat-card danger <?php echo $status_filter === 'in-progress' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $working_concerns; ?></h3>
                                        <p class="mb-0">In Progress</p>
                                    </div>
                                    <i class="fas fa-cog fa-2x opacity-75"></i>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="?status=resolved" class="card stat-card success <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $resolved_concerns; ?></h3>
                                        <p class="mb-0">Resolved</p>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Filter Indicator -->
                    <?php if ($status_filter !== 'all'): ?>
                        <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <i class="fas fa-filter me-2"></i>
                                Showing <strong><?php echo ucfirst($status_filter === 'in-progress' ? 'In Progress' : $status_filter); ?></strong> concerns 
                                (<?php echo count($concerns); ?> results)
                            </div>
                            <a href="?status=all" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-times me-1"></i>Clear Filter
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Concerns List -->
                    <?php if (empty($concerns)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-4x text-muted mb-3"></i>
                            <?php if ($status_filter === 'all'): ?>
                                <h4 class="text-muted">No Concerns Reported</h4>
                                <p class="text-muted">All concerns have been addressed or no concerns have been reported yet.</p>
                            <?php else: ?>
                                <h4 class="text-muted">No <?php echo ucfirst($status_filter === 'in-progress' ? 'In Progress' : $status_filter); ?> Concerns</h4>
                                <p class="text-muted">There are currently no concerns with this status.</p>
                                <a href="?status=all" class="btn btn-primary">
                                    <i class="fas fa-list me-2"></i>View All Concerns
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="concerns-list">
                            <?php foreach ($concerns as $index => $concern): ?>
                                <div class="concern-row priority-<?php echo $concern['priority'] ?? 'medium'; ?>" onclick="toggleConcernDetails(<?php echo $index; ?>)">
                                    <div class="priority-indicator"></div>
                                    
                                    <div class="concern-summary">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <span class="badge bg-<?php echo $concern['status'] === 'pending' ? 'warning' : ($concern['status'] === 'in-progress' ? 'info' : 'success'); ?> status-badge">
                                                    <?php echo $concern['status'] === 'in-progress' ? 'In Progress' : ucfirst($concern['status']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($concern['title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($concern['first_name'] . ' ' . $concern['last_name']); ?> • 
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($concern['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (isset($concern['concern_image']) && $concern['concern_image']): ?>
                                                <i class="fas fa-image text-muted" title="Has image"></i>
                                            <?php endif; ?>
                                            <span class="badge bg-<?php echo ($concern['priority'] ?? 'medium') === 'high' ? 'danger' : (($concern['priority'] ?? 'medium') === 'medium' ? 'warning' : 'success'); ?> status-badge">
                                                <?php echo ucfirst($concern['priority'] ?? 'Medium'); ?>
                                            </span>
                                            <i class="fas fa-chevron-down expand-icon"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="concern-details" id="concern-details-<?php echo $index; ?>">
                                        <div class="row mt-3">
                                            <div class="col-md-8">
                                                <h6>Description</h6>
                                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($concern['description'])); ?></p>
                                                
                                                <?php if (isset($concern['progress_notes']) && $concern['progress_notes']): ?>
                                                    <h6>Progress Notes</h6>
                                                    <div class="p-3 bg-light rounded mb-3">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($concern['progress_notes'])); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-3">
                                                    <h6>Actions</h6>
                                                    <div class="d-flex gap-2">
                                                        <?php if ($concern['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-info" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#updateModal<?php echo $concern['id']; ?>"
                                                                    onclick="event.stopPropagation()">
                                                                <i class="fas fa-play me-1"></i> Start Progress
                                                            </button>
                                                        <?php elseif ($concern['status'] === 'in-progress'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#resolveModal<?php echo $concern['id']; ?>"
                                                                    onclick="event.stopPropagation()">
                                                                <i class="fas fa-check me-1"></i> Mark Resolved
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#updateModal<?php echo $concern['id']; ?>"
                                                                    onclick="event.stopPropagation()">
                                                                <i class="fas fa-edit me-1"></i> Update Progress
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check-circle me-1"></i>Resolved
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <h6>Contact Information</h6>
                                                <p class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-envelope me-1"></i>
                                                        <?php echo htmlspecialchars($concern['email']); ?>
                                                    </small>
                                                </p>
                                                
                                                <?php if (isset($concern['concern_image']) && $concern['concern_image']): ?>
                                                    <h6>Attached Image</h6>
                                                    <?php 
                                                    $image_path = $concern['concern_image'];
                                                    if (strpos($image_path, '../') !== 0) {
                                                        $image_path = '../' . $image_path;
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                         alt="Concern Image" 
                                                         class="concern-image-thumb" 
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image="<?php echo htmlspecialchars($image_path); ?>"
                                                         data-title="<?php echo htmlspecialchars($concern['title']); ?>"
                                                         onclick="showImage(this.getAttribute('data-image'), event)"
                                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2Y4ZjlmYSIgc3Ryb2tlPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSI4IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+';">
                                                    <small class="text-muted d-block mt-1">Click to expand</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile navigation toggle functionality
        function toggleMobileNav() {
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuIcon = menuBtn.querySelector('i');
            
            if (dropdown.classList.contains('show')) {
                // Close menu
                dropdown.classList.remove('show');
                menuBtn.classList.remove('active');
                overlay.classList.remove('show');
                menuIcon.className = 'fas fa-bars';
                document.body.style.overflow = '';
            } else {
                // Open menu
                dropdown.classList.add('show');
                menuBtn.classList.add('active');
                overlay.classList.add('show');
                menuIcon.className = 'fas fa-times';
                document.body.style.overflow = 'hidden';
            }
        }

        // Toggle concern details (expandable functionality)
        function toggleConcernDetails(index) {
            const detailsElement = document.getElementById(`concern-details-${index}`);
            const row = detailsElement.closest('.concern-row');
            const expandIcon = row.querySelector('.expand-icon');
            
            if (detailsElement.style.display === 'none' || detailsElement.style.display === '') {
                detailsElement.style.display = 'block';
                row.classList.add('expanded');
                expandIcon.style.transform = 'rotate(180deg)';
            } else {
                detailsElement.style.display = 'none';
                row.classList.remove('expanded');
                expandIcon.style.transform = 'rotate(0deg)';
            }
        }

        // Handle image modal display
        function showImage(imageSrc, event) {
            event.stopPropagation();
            
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('modalImageTitle');
            const downloadLink = document.getElementById('downloadLink');
            const imageModal = document.getElementById('imageModal');
            
            // Clear any existing modal instance
            const existingModal = bootstrap.Modal.getInstance(imageModal);
            if (existingModal) {
                existingModal.dispose();
            }
            
            // Set image data
            modalImage.src = imageSrc;
            modalTitle.textContent = 'Concern Image';
            downloadLink.href = imageSrc;
            downloadLink.download = 'concern_image.jpg';
            
            // Create fresh modal instance
            const modal = new bootstrap.Modal(imageModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            // Add cleanup event listeners
            imageModal.addEventListener('hidden.bs.modal', function() {
                const modalInstance = bootstrap.Modal.getInstance(imageModal);
                if (modalInstance) {
                    modalInstance.dispose();
                }
                // Remove any lingering backdrop
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                // Restore body scroll
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, { once: true });
            
            modal.show();
        }

        // Add click animation for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Auto-scroll to concerns list when filtering
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status && status !== 'all') {
            setTimeout(() => {
                const concernsList = document.querySelector('.concerns-list') || document.querySelector('.alert.alert-info');
                if (concernsList) {
                    concernsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 500);
        }

        // Initialize all concern details as hidden
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.concern-details').forEach(details => {
                details.style.display = 'none';
            });
            
            // Mobile navigation handling
            const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
            
            mobileNavItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // Immediately close the mobile nav before navigating
                    const dropdown = document.getElementById('mobileNavDropdown');
                    const menuBtn = document.querySelector('.mobile-menu-btn');
                    const overlay = document.querySelector('.sidebar-overlay');
                    const menuIcon = menuBtn.querySelector('i');
                    
                    // Close dropdown instantly
                    dropdown.classList.remove('show');
                    menuBtn.classList.remove('active');
                    overlay.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                    
                    // Add visual feedback that item was clicked
                    this.style.backgroundColor = 'rgba(255,255,255,0.3)';
                    
                    // Small delay to show the click feedback, then navigate
                    setTimeout(() => {
                        // Let the default navigation happen
                        window.location.href = this.href;
                    }, 100);
                    
                    // Prevent default to control the navigation timing
                    e.preventDefault();
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    const dropdown = document.getElementById('mobileNavDropdown');
                    const menuBtn = document.querySelector('.mobile-menu-btn');
                    const overlay = document.querySelector('.sidebar-overlay');
                    const menuIcon = menuBtn.querySelector('i');
                    
                    dropdown.classList.remove('show');
                    menuBtn.classList.remove('active');
                    overlay.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                }
            });
            
            // Close mobile nav when clicking outside
            document.addEventListener('click', function(e) {
                const mobileHeader = document.querySelector('.mobile-header');
                const dropdown = document.getElementById('mobileNavDropdown');
                
                if (!mobileHeader.contains(e.target) && dropdown.classList.contains('show')) {
                    toggleMobileNav();
                }
            });
            
            // Add global cleanup for any stuck modals
            document.addEventListener('click', function(e) {
                // If clicking outside any modal content, ensure cleanup
                if (e.target.classList.contains('modal-backdrop')) {
                    const modals = document.querySelectorAll('.modal.show');
                    modals.forEach(modal => {
                        const instance = bootstrap.Modal.getInstance(modal);
                        if (instance) {
                            instance.hide();
                        }
                    });
                }
            });
            
            // Safety cleanup on page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    // Clean up any stuck modal states
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                }
            });
        });
    </script>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="fas fa-image me-2"></i>Concern Image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="modalImage" src="" alt="Expanded Concern Image" class="img-fluid" style="max-height: 70vh; object-fit: contain;">
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <small class="text-muted" id="modalImageTitle"></small>
                    </div>
                    <a id="downloadLink" href="" download class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

