<?php
// filepath: c:\xampp\htdocs\subdivision\admin\pending_residents.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$message_type = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = 'Resident approved successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error approving resident.';
            $message_type = 'danger';
        }
    } elseif (isset($_POST['reject_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = 'Resident application rejected and removed.';
            $message_type = 'success';
        } else {
            $message = 'Error rejecting resident.';
            $message_type = 'danger';
        }
    }
}

// Get pending residents with evidence photos
try {
    // First, try with the enhanced schema
    $query = "
        SELECT u.*, r.resident_type, r.id_photo, r.proof_of_residency 
        FROM users u 
        LEFT JOIN residents r ON u.id = r.user_id 
        WHERE u.user_type = 'resident' 
        AND u.status = 'pending'
        ORDER BY u.created_at DESC
    ";
    $pending_residents = $pdo->query($query)->fetchAll();
} catch (Exception $e) {
    // If evidence_photos column doesn't exist, fall back to basic query
    $pending_residents = $pdo->query("
        SELECT *, profile_image as photo_evidence
        FROM users 
        WHERE user_type = 'resident' AND status = 'pending' 
        ORDER BY created_at DESC
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Residents - Admin Panel</title>
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
            background: var(--admin-primary);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative;
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

        .main-content {
            background: white;
            border-radius: 20px 0 0 20px;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
            min-height: 100vh;
        }

        .resident-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 10px;
        }

        .resident-card:hover {
            transform: translateY(-2px);
        }

        .resident-row {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .resident-row:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }

        .resident-summary {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .resident-details {
            display: none;
            padding: 0 16px 16px 16px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }

        .resident-details.show {
            display: block;
        }

        .evidence-photos {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0;
        }

        .evidence-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .evidence-thumb:hover {
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

        .btn-admin {
            background: var(--admin-primary);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            background: var(--admin-secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(64, 34, 34, 0.4);
            color: var(--admin-primary);
        }

        .btn-approve {
            background: var(--admin-success);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-approve:hover {
            background: var(--admin-secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(64, 34, 34, 0.4);
            color: var(--admin-primary);
        }

        .btn-reject {
            background: var(--admin-secondary);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-reject:hover {
            background: var(--admin-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(64, 34, 34, 0.4);
            color: var(--admin-secondary);
        }

        .brand-title {
            color: var(--admin-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
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
            <a href="pending_residents.php" class="mobile-nav-item active">
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
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="brand-title">Pending Resident Approvals</h2>
                        <a href="dashboard.php" class="btn btn-admin">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($pending_residents)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-check fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No Pending Approvals</h4>
                            <p class="text-muted">All resident applications have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="residents-list">
                            <?php foreach ($pending_residents as $index => $resident): ?>
                                <div class="resident-row" onclick="toggleDetails(<?php echo $index; ?>)">
                                    <div class="resident-summary">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-warning rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">
                                                    <?php echo htmlspecialchars($resident['full_name'] ?? ($resident['first_name'] . ' ' . $resident['last_name'])); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($resident['email']); ?> • 
                                                    Applied: <?php echo date('M j, Y', strtotime($resident['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge bg-warning text-dark">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                            <i class="fas fa-chevron-down expand-icon"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="resident-details" id="details-<?php echo $index; ?>">
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <h6>Personal Information</h6>
                                                <p class="mb-1"><strong>Full Name:</strong> <?php echo htmlspecialchars($resident['full_name'] ?? ($resident['first_name'] . ' ' . $resident['last_name'])); ?></p>
                                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($resident['email']); ?></p>
                                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($resident['phone'] ?? 'Not provided'); ?></p>
                                                <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($resident['address'] ?? 'Not provided'); ?></p>
                                                <?php if (!empty($resident['emergency_contact'])): ?>
                                                    <p class="mb-1"><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($resident['emergency_contact']); ?></p>
                                                    <p class="mb-1"><strong>Emergency Phone:</strong> <?php echo htmlspecialchars($resident['emergency_phone'] ?? 'Not provided'); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6>Supporting Evidence</h6>
                                                <?php if (!empty($resident['id_photo']) || !empty($resident['proof_of_residency'])): ?>
                                                    <div class="mt-2">
                                                        <div class="row g-2">
                                                            <?php if (!empty($resident['id_photo'])): ?>
                                                                <div class="col-6">
                                                                    <button type="button" class="btn btn-outline-info w-100 view-document" 
                                                                        data-image="../<?php echo $resident['id_photo']; ?>"
                                                                        data-title="ID Photo">
                                                                        <i class="fas fa-id-card me-1"></i> View ID
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($resident['proof_of_residency'])): ?>
                                                                <div class="col-6">
                                                                    <button type="button" class="btn btn-outline-info w-100 view-document" 
                                                                        data-image="../<?php echo $resident['proof_of_residency']; ?>"
                                                                        data-title="Proof of Residency">
                                                                        <i class="fas fa-file me-1"></i> View Proof
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted">No evidence photos uploaded</p>
                                                <?php endif; ?>

                                                <div class="mt-3">
                                                    <h6>Actions</h6>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-approve flex-fill" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#approveModal<?php echo $resident['id']; ?>"
                                                                onclick="event.stopPropagation()">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-reject flex-fill" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rejectModal<?php echo $resident['id']; ?>"
                                                                onclick="event.stopPropagation()">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="resident-info">
                                            <strong>Type:</strong> <?php echo ucfirst($resident['resident_type'] ?? 'Not specified'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Image Modal -->
                        <div class="modal fade" id="imageModal" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Evidence Photo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <img id="modalImage" src="" class="img-fluid" alt="Evidence photo">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add this new modal for viewing images -->
                        <div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="imageViewerTitle">View Document</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center p-0">
                                        <img id="imageViewer" src="" alt="Document Preview" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
                                    </div>
                                </div>
                            </div>
                        </div>
                            
                            <!-- Confirmation Modals -->
                            <?php foreach ($pending_residents as $resident): ?>
                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?php echo $resident['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-check-circle me-2"></i>Confirm Approval
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center mb-3">
                                                    <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                                                    <h6>Are you sure you want to approve this resident?</h6>
                                                    <p class="text-muted mb-0">
                                                        <strong><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></strong><br>
                                                        <?php echo htmlspecialchars($resident['email']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $resident['id']; ?>">
                                                    <button type="submit" name="approve_user" class="btn btn-success">
                                                        <i class="fas fa-check me-1"></i> Yes, Approve
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $resident['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Rejection
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center mb-3">
                                                    <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                                                    <h6>Are you sure you want to reject this application?</h6>
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-warning me-1"></i>
                                                        <strong>Warning:</strong> This will permanently delete the application.
                                                    </div>
                                                    <p class="text-muted mb-0">
                                                        <strong><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></strong><br>
                                                        <?php echo htmlspecialchars($resident['email']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $resident['id']; ?>">
                                                    <button type="submit" name="reject_user" class="btn btn-danger">
                                                        <i class="fas fa-trash me-1"></i> Yes, Reject & Delete
                                                    </button>
                                                </form>
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
        function toggleDetails(index) {
            const details = document.getElementById('details-' + index);
            const row = details.closest('.resident-row');
            const icon = row.querySelector('.expand-icon');
            
            if (details.classList.contains('show')) {
                details.classList.remove('show');
                row.classList.remove('expanded');
            } else {
                details.classList.add('show');
                row.classList.add('expanded');
            }
        }
        
        function showImage(src, event) {
            event.stopPropagation();
            document.getElementById('modalImage').src = src;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }

        // Mobile navigation toggle functionality
        function toggleMobileNav() {
            console.log('Toggle mobile nav clicked');
            
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuIcon = menuBtn.querySelector('i');
            
            if (dropdown && menuBtn && overlay && menuIcon) {
                if (dropdown.classList.contains('show')) {
                    // Close menu
                    dropdown.classList.remove('show');
                    menuBtn.classList.remove('active');
                    overlay.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                    console.log('Menu closed');
                } else {
                    // Open menu
                    dropdown.classList.add('show');
                    menuBtn.classList.add('active');
                    overlay.classList.add('show');
                    menuIcon.className = 'fas fa-times';
                    document.body.style.overflow = 'hidden';
                    console.log('Menu opened');
                }
            } else {
                console.error('Missing mobile nav elements');
            }
        }

        // Ensure dropdown is hidden on page load and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up mobile nav');
            
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
            
            // Add click event listener to menu button
            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Menu button clicked');
                    toggleMobileNav();
                });
                
                // Add touch event for mobile devices
                menuBtn.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    console.log('Menu button touched');
                    toggleMobileNav();
                }, { passive: false });
            }
            
            console.log('Mobile nav setup complete');
            
            // Close mobile nav when clicking outside
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
                    const menuIcon = menuBtn.querySelector('i');
                    
                    if (dropdown) dropdown.classList.remove('show');
                    if (menuBtn) menuBtn.classList.remove('active');
                    if (overlay) overlay.classList.remove('show');
                    if (menuIcon) menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                }
            });
            
            // Add touch events for mobile nav items
            const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
            mobileNavItems.forEach(item => {
                // Add click event with debugging
                item.addEventListener('click', function(e) {
                    console.log('Nav item clicked:', this.textContent.trim());
                    
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
                    
                    // Navigate after a brief moment
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 100);
                    
                    // Prevent default to control the navigation timing
                    e.preventDefault();
                    e.stopPropagation();
                });
                
                // Add touch event for better mobile support
                item.addEventListener('touchstart', function(e) {
                    console.log('Nav item touched:', this.textContent.trim());
                    this.style.backgroundColor = 'rgba(255,255,255,0.2)';
                }, { passive: true });
                
                item.addEventListener('touchend', function(e) {
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 200);
                }, { passive: true });
            });
            
            // Image viewer modal setup
            const imageViewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
            
            // Handle document view buttons
            document.querySelectorAll('.view-document').forEach(button => {
                button.addEventListener('click', function() {
                    const imagePath = this.dataset.image;
                    const imageTitle = this.dataset.title;
                    
                    // Update modal content
                    document.getElementById('imageViewerTitle').textContent = imageTitle;
                    document.getElementById('imageViewer').src = imagePath;
                    
                    // Show modal
                    imageViewerModal.show();
                });
            });
        });
    </script>
</body>
</html>
