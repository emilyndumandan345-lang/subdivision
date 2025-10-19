<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

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
            border-radius: 20px 0 0 0;
            box-shadow: -5px 0 20px rgba(0,0,0,0.08);
            height: 100vh;
            overflow-y: auto;
            position: relative;
            z-index: 1;
            margin-left: 280px;
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

        /* Mobile Responsive Design */
        .mobile-header {
            display: none;
            background: var(--admin-primary);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1045;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            user-select: none;
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
            z-index: 1050;
            min-width: 40px;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .mobile-menu-btn:focus {
            outline: none;
            background: rgba(255,255,255,0.2);
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
            z-index: 1040;
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
            position: relative;
            z-index: 1041;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .mobile-nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            padding-left: 35px;
        }

        .mobile-nav-item:focus {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            outline: none;
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
            z-index: 1030;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
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

            .sidebar-overlay {
                display: block;
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
        }

        /* Expandable Visitors Styles */
        .visitors-list {
            margin-top: 20px;
        }

        .visitor-row {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
            position: relative;
        }

        .visitor-row:hover {
            border-color: var(--admin-primary);
            box-shadow: 0 4px 12px rgba(64, 34, 34, 0.15);
            transform: translateY(-1px);
        }

        .visitor-row.expanded {
            border-color: var(--admin-primary);
            box-shadow: 0 6px 20px rgba(64, 34, 34, 0.2);
        }

        .status-indicator {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            transition: all 0.3s ease;
        }

        .status-indicator.pre-registered {
            background: linear-gradient(to bottom, #ffc107, #e0a800);
        }

        .status-indicator.checked-in {
            background: linear-gradient(to bottom, #28a745, #1e7e34);
        }

        .status-indicator.checked-out {
            background: linear-gradient(to bottom, #6c757d, #495057);
        }

        .visitor-summary {
            padding: 15px 20px 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 60px;
        }

        .visitor-summary h6 {
            margin: 0;
            font-weight: 600;
            color: var(--admin-primary);
            font-size: 1.1rem;
        }

        .visitor-summary .text-muted {
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .visitor-details {
            display: none;
            padding: 0 25px 20px 25px;
            border-top: 1px solid #f1f3f4;
            background: #fafbfc;
        }

        .visitor-details.show {
            display: block;
        }

        .expand-icon {
            transition: transform 0.3s ease;
            color: var(--admin-primary);
            margin-left: 10px;
        }

        .visitor-row.expanded .expand-icon {
            transform: rotate(180deg);
        }

        .visitor-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .visitor-info-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid var(--admin-secondary);
        }

        .visitor-info-label {
            font-weight: 600;
            color: var(--admin-primary);
            font-size: 0.85rem;
            margin-bottom: 4px;
        }

        .visitor-info-value {
            color: #495057;
            font-size: 0.95rem;
        }

        .visitor-times {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .time-entry {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .time-in {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .time-out {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .visitor-summary {
                padding: 12px 15px 12px 20px;
                min-height: 50px;
            }

            .visitor-summary h6 {
                font-size: 1rem;
            }

            .visitor-details {
                padding: 0 20px 15px 20px;
            }

            .visitor-info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .visitor-times {
                flex-direction: column;
                gap: 8px;
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
            <button class="mobile-menu-btn" type="button" id="mobileMenuBtn">
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
            <a href="concerns.php" class="mobile-nav-item">
                <i class="fas fa-exclamation-triangle"></i> Concerns
            </a>
            <a href="visitors.php" class="mobile-nav-item active">
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar-col">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 px-0 main-content-col">
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
                            <?php if (empty($visitors)): ?>
                                <div class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-users fa-4x mb-3"></i>
                                        <h4>No visitors found</h4>
                                        <?php if (!empty($search)): ?>
                                            <p>Try adjusting your search terms</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="visitors-list">
                                    <?php foreach ($visitors as $index => $visitor): ?>
                                        <div class="visitor-row" onclick="toggleVisitorDetails(<?php echo $index; ?>)">
                                            <div class="status-indicator <?php echo strtolower(str_replace('-', '-', $visitor['status'])); ?>"></div>
                                            
                                            <div class="visitor-summary">
                                                <div class="flex-grow-1">
                                                    <h6><?php echo htmlspecialchars($visitor['visitor_name']); ?></h6>
                                                    <div class="d-flex align-items-center gap-2">
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
                                                        <span class="badge <?php echo $status_class; ?> status-badge">
                                                            <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $visitor['status']))); ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            <i class="fas fa-home me-1"></i>
                                                            Visiting <?php echo htmlspecialchars($visitor['resident_name']); ?> •
                                                            <i class="fas fa-calendar me-1 ms-2"></i>
                                                            <?php echo date('M j, Y', strtotime($visitor['visit_date'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($visitor['visitor_phone'])): ?>
                                                        <i class="fas fa-phone text-muted me-2" title="Has phone number"></i>
                                                    <?php endif; ?>
                                                    <span class="badge bg-info status-badge me-2">
                                                        <?php echo htmlspecialchars($visitor['purpose']); ?>
                                                    </span>
                                                    <i class="fas fa-chevron-down expand-icon"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="visitor-details" id="visitor-details-<?php echo $index; ?>">
                                                <div class="visitor-info-grid">
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Visitor Name</div>
                                                        <div class="visitor-info-value"><?php echo htmlspecialchars($visitor['visitor_name']); ?></div>
                                                    </div>
                                                    
                                                    <?php if (!empty($visitor['visitor_phone'])): ?>
                                                        <div class="visitor-info-item">
                                                            <div class="visitor-info-label">Phone Number</div>
                                                            <div class="visitor-info-value"><?php echo htmlspecialchars($visitor['visitor_phone']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Visiting</div>
                                                        <div class="visitor-info-value"><?php echo htmlspecialchars($visitor['resident_name']); ?></div>
                                                    </div>
                                                    
                                                    <?php if (!empty($visitor['house_number']) || !empty($visitor['block_lot'])): ?>
                                                        <div class="visitor-info-item">
                                                            <div class="visitor-info-label">Address</div>
                                                            <div class="visitor-info-value"><?php echo htmlspecialchars(trim($visitor['house_number'] . ' ' . $visitor['block_lot'])); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Visit Date</div>
                                                        <div class="visitor-info-value"><?php echo date('M j, Y', strtotime($visitor['visit_date'])); ?></div>
                                                    </div>
                                                    
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Visit Time</div>
                                                        <div class="visitor-info-value"><?php echo date('g:i A', strtotime($visitor['visit_date'])); ?></div>
                                                    </div>
                                                    
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Purpose</div>
                                                        <div class="visitor-info-value">
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($visitor['purpose']); ?></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="visitor-info-item">
                                                        <div class="visitor-info-label">Status</div>
                                                        <div class="visitor-info-value">
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $visitor['status']))); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if ((isset($visitor['time_in']) && !empty($visitor['time_in'])) || (isset($visitor['time_out']) && !empty($visitor['time_out']))): ?>
                                                    <div class="visitor-times">
                                                        <?php if (isset($visitor['time_in']) && !empty($visitor['time_in'])): ?>
                                                            <div class="time-entry time-in">
                                                                <i class="fas fa-sign-in-alt me-1"></i>
                                                                Check In: <?php echo date('g:i A', strtotime($visitor['time_in'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (isset($visitor['time_out']) && !empty($visitor['time_out'])): ?>
                                                            <div class="time-entry time-out">
                                                                <i class="fas fa-sign-out-alt me-1"></i>
                                                                Check Out: <?php echo date('g:i A', strtotime($visitor['time_out'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

        // Toggle visitor details (expandable functionality)
        function toggleVisitorDetails(index) {
            const detailsElement = document.getElementById(`visitor-details-${index}`);
            const row = detailsElement.closest('.visitor-row');
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

        // Initialize all visitor details as hidden
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.visitor-details').forEach(details => {
                details.style.display = 'none';
            });
            
            // Add smooth hover effects
            document.querySelectorAll('.visitor-row').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('expanded')) {
                        this.style.transform = 'translateY(-2px)';
                    }
                });
                
                row.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('expanded')) {
                        this.style.transform = 'translateY(0)';
                    }
                });
            });
        });

        // Mobile navigation toggle functionality
        function toggleMobileNav() {
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuIcon = menuBtn.querySelector('i');
            
            if (dropdown && menuBtn && overlay && menuIcon) {
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    menuBtn.classList.remove('active');
                    overlay.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                } else {
                    dropdown.classList.add('show');
                    menuBtn.classList.add('active');
                    overlay.classList.add('show');
                    menuIcon.className = 'fas fa-times';
                    document.body.style.overflow = 'hidden';
                }
            }
        }

        // Setup mobile navigation
        document.addEventListener('DOMContentLoaded', function() {
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
            
            // Force initial hidden state
            if (dropdown) dropdown.classList.remove('show');
            if (menuBtn) menuBtn.classList.remove('active');
            if (overlay) overlay.classList.remove('show');
            if (menuIcon) menuIcon.className = 'fas fa-bars';
            document.body.style.overflow = '';
            
            // Add event listeners
            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleMobileNav();
                });
            }
            
            // Handle mobile nav items
            const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
            mobileNavItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    const dropdown = document.getElementById('mobileNavDropdown');
                    const menuBtn = document.querySelector('.mobile-menu-btn');
                    const overlay = document.querySelector('.sidebar-overlay');
                    const menuIcon = menuBtn.querySelector('i');
                    
                    dropdown.classList.remove('show');
                    menuBtn.classList.remove('active');
                    overlay.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                    
                    this.style.backgroundColor = 'rgba(255,255,255,0.3)';
                    setTimeout(() => window.location.href = this.href, 100);
                    e.preventDefault();
                    e.stopPropagation();
                });
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                const mobileHeader = document.querySelector('.mobile-header');
                const dropdown = document.getElementById('mobileNavDropdown');
                if (!mobileHeader.contains(e.target) && dropdown.classList.contains('show')) {
                    toggleMobileNav();
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    const dropdown = document.getElementById('mobileNavDropdown');
                    const menuBtn = document.querySelector('.mobile-menu-btn');
                    const overlay = document.querySelector('.sidebar-overlay');
                    const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
                    
                    if (dropdown) dropdown.classList.remove('show');
                    if (menuBtn) menuBtn.classList.remove('active');
                    if (overlay) overlay.classList.remove('show');
                    if (menuIcon) menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>