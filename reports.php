<?php
// filepath: c:\xampp\htdocs\subdivision\admin\reports.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

// Get comprehensive statistics
$stats = [];

// Resident statistics
$stats['total_residents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'resident' AND status = 'approved'")->fetchColumn();
$stats['pending_residents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'resident' AND status = 'pending'")->fetchColumn();

// Visitor statistics
$stats['today_visitors'] = $pdo->query("SELECT COUNT(*) FROM visitors WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
$stats['month_visitors'] = $pdo->query("SELECT COUNT(*) FROM visitors WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())")->fetchColumn();
$stats['total_visitors'] = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();

// Concern statistics
$stats['total_concerns'] = $pdo->query("SELECT COUNT(*) FROM concerns")->fetchColumn();
$stats['pending_concerns'] = $pdo->query("SELECT COUNT(*) FROM concerns WHERE status = 'pending'")->fetchColumn();
$stats['working_concerns'] = $pdo->query("SELECT COUNT(*) FROM concerns WHERE status = 'working'")->fetchColumn();
$stats['resolved_concerns'] = $pdo->query("SELECT COUNT(*) FROM concerns WHERE status = 'done'")->fetchColumn();

// Announcement statistics
$stats['total_announcements'] = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();

// Monthly visitor trends (last 6 months)
$monthly_visitors = $pdo->query("
    SELECT 
        DATE_FORMAT(visit_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM visitors 
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Daily visitors this month
$daily_visitors = $pdo->query("
    SELECT 
        DATE(visit_date) as date,
        COUNT(*) as count
    FROM visitors 
    WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())
    GROUP BY DATE(visit_date)
    ORDER BY date
")->fetchAll();

// Recent activities
$recent_residents = $pdo->query("
    SELECT first_name, last_name, created_at 
    FROM users 
    WHERE user_type = 'resident' AND status = 'approved' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$recent_concerns = $pdo->query("
    SELECT c.title, c.status, c.created_at, u.first_name, u.last_name
    FROM concerns c 
    JOIN users u ON c.resident_id = u.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            }            .main-content {
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
            }            text-overflow: ellipsis;
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

        .stat-card.info {
            background: var(--admin-info);
            color: var(--admin-primary);
        }

        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }

        .report-card:hover {
            transform: translateY(-2px);
        }

        .brand-title {
            color: var(--admin-primary);
            font-weight: bold;
        }

        .chart-container {
            position: relative;
            height: 300px;
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
            <a href="visitors.php" class="mobile-nav-item">
                <i class="fas fa-address-book"></i> Visitors
            </a>
            <a href="reports.php" class="mobile-nav-item active">
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
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="brand-title">Reports & Analytics</h2>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>

                    <!-- Overview Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $stats['total_residents']; ?></h3>
                                        <p class="mb-0">Total Residents</p>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $stats['month_visitors']; ?></h3>
                                        <p class="mb-0">Monthly Visitors</p>
                                    </div>
                                    <i class="fas fa-address-book fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $stats['total_concerns']; ?></h3>
                                        <p class="mb-0">Total Concerns</p>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?php echo $stats['total_announcements']; ?></h3>
                                        <p class="mb-0">Announcements</p>
                                    </div>
                                    <i class="fas fa-bullhorn fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header bg-transparent border-0">
                                    <h5 class="card-title">
                                        <i class="fas fa-chart-line text-primary me-2"></i>
                                        Monthly Visitor Trends
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="monthlyVisitorsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header bg-transparent border-0">
                                    <h5 class="card-title">
                                        <i class="fas fa-chart-pie text-success me-2"></i>
                                        Concerns Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="concernsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header bg-transparent border-0">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-plus text-success me-2"></i>
                                        Recent Residents
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_residents)): ?>
                                        <p class="text-muted text-center py-3">No recent residents</p>
                                    <?php else: ?>
                                        <?php foreach ($recent_residents as $resident): ?>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></strong>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($resident['created_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header bg-transparent border-0">
                                    <h5 class="card-title">
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        Recent Concerns
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_concerns)): ?>
                                        <p class="text-muted text-center py-3">No recent concerns</p>
                                    <?php else: ?>
                                        <?php foreach ($recent_concerns as $concern): ?>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($concern['title']); ?></strong>
                                                    <p class="mb-0 text-muted small">
                                                        By <?php echo htmlspecialchars($concern['first_name'] . ' ' . $concern['last_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php echo $concern['status'] === 'pending' ? 'warning' : ($concern['status'] === 'working' ? 'info' : 'success'); ?>">
                                                        <?php echo ucfirst($concern['status']); ?>
                                                    </span>
                                                    <br><small class="text-muted">
                                                        <?php echo date('M j', strtotime($concern['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Visitors Chart
        const monthlyCtx = document.getElementById('monthlyVisitorsChart').getContext('2d');
        const monthlyVisitorsChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_visitors, 'month')); ?>,
                datasets: [{
                    label: 'Visitors',
                    data: <?php echo json_encode(array_column($monthly_visitors, 'count')); ?>,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Concerns Status Chart
        const concernsCtx = document.getElementById('concernsChart').getContext('2d');
        const concernsChart = new Chart(concernsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Resolved'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending_concerns']; ?>,
                        <?php echo $stats['working_concerns']; ?>,
                        <?php echo $stats['resolved_concerns']; ?>
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#0dcaf0',
                        '#198754'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
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
