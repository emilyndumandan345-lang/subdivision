<?php
// filepath: c:\xampp\htdocs\subdivision\admin\announcements.php
require_once 'admin_auth.php'; // This handles session and auth check
require_once '../config/database.php';
require_once '../includes/functions.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Get all announcements
$query = "
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as admin_name 
    FROM announcements a
    JOIN users u ON a.admin_id = u.id 
    ORDER BY a.created_at DESC, a.id DESC
";
$announcements = $pdo->query($query)->fetchAll();

// Initialize message variables
$message = '';
$message_type = 'info';

// Process POST actions for announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle post announcement
    if (isset($_POST['post_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target_audience = $_POST['target_audience'] ?? '';
        
        // Validate inputs
        if (empty($title) || empty($content) || empty($target_audience)) {
            $message = 'Please fill in all required fields';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (
                        title, 
                        content, 
                        target_audience, 
                        admin_id
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title,
                    $content,
                    $target_audience,
                    $_SESSION['user_id']
                ]);
                
                $message = 'Announcement posted successfully!';
                $message_type = 'success';
                
                // Refresh the announcements list
                $announcements = $pdo->query("
                    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as admin_name 
                    FROM announcements a
                    JOIN users u ON a.admin_id = u.id 
                    ORDER BY a.created_at DESC, a.id DESC
                ")->fetchAll();
            } catch (Exception $e) {
                $message = 'Error posting announcement: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    // Handle delete announcement
    if (isset($_POST['delete_announcement']) && isset($_POST['announcement_id'])) {
        $announcement_id = $_POST['announcement_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$announcement_id]);
            
            $message = 'Announcement deleted successfully!';
            $message_type = 'success';
            
            // Refresh the announcements list
            $announcements = $pdo->query("
                SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as admin_name 
                FROM announcements a
                JOIN users u ON a.admin_id = u.id 
                ORDER BY a.created_at DESC, a.id DESC
            ")->fetchAll();
        } catch (Exception $e) {
            $message = 'Error deleting announcement: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }

    // Update announcement
    if (isset($_POST['update_announcement'])) {
        $announcement_id = $_POST['edit_announcement_id'];
        $title = trim($_POST['edit_title']);
        $content = trim($_POST['edit_content']);
        $target_audience = $_POST['edit_target_audience'];
        
        if (empty($title) || empty($content) || empty($target_audience)) {
            $message = 'Please fill in all required fields';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE announcements 
                    SET title = ?, content = ?, target_audience = ? 
                    WHERE id = ? AND admin_id = ?
                ");
                $stmt->execute([$title, $content, $target_audience, $announcement_id, $_SESSION['user_id']]);
                
                $message = 'Announcement updated successfully!';
                $message_type = 'success';
                
                // Refresh the announcements list
                $announcements = $pdo->query("
                    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as admin_name 
                    FROM announcements a
                    JOIN users u ON a.admin_id = u.id 
                    ORDER BY a.created_at DESC, a.id DESC
                ")->fetchAll();
            } catch (Exception $e) {
                $message = 'Error updating announcement: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }

    // Add this to your existing POST handler section
    if (isset($_POST['post_reply'])) {
        $parent_id = $_POST['parent_id'];
        $announcement_id = $_POST['announcement_id'];
        $reply_text = trim($_POST['reply_text']);
        
        if (!empty($reply_text)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcement_comments (
                        announcement_id, user_id, parent_id, comment
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $announcement_id,
                    $_SESSION['user_id'],
                    $parent_id,
                    $reply_text
                ]);
                
                $message = 'Reply posted successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error posting reply: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin Panel</title>
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
                position: relative;
                left: 0;
                right: auto;
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

        .announcement-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
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

        .brand-title {
            color: var(--admin-primary);
            font-weight: bold;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .audience-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        /* Expandable Announcements Styles */
        .announcements-list {
            margin-top: 20px;
        }

        .announcement-row {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .announcement-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .announcement-details {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            margin-top: 10px;
            padding: 20px;
        }

        .announcement-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--admin-secondary);
        }

        /* Add these styles to your existing <style> section */
        .comment-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .replies-list {
            border-left: 2px solid var(--admin-secondary);
        }

        .reply-item {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-top: 8px;
        }

        .comment-content, .reply-content {
            white-space: pre-line;
        }

        @media (max-width: 768px) {
            .announcement-summary {
                padding: 12px 15px 12px 20px;
                min-height: 50px;
            }

            .announcement-summary h6 {
                font-size: 1rem;
            }

            .announcement-details {
                padding: 0 20px 15px 20px;
            }

            .announcement-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
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

            .btn-admin {
                width: 100%;
                margin-top: 10px;
            }

            .modal-dialog {
                margin: 10px;
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
            <a href="announcements.php" class="mobile-nav-item active">
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
                        <h2 class="brand-title">Announcements</h2>
                        <button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#postAnnouncementModal">
                            <i class="fas fa-bullhorn me-2"></i> Post Announcement
                        </button>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Announcements List -->
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No Announcements</h4>
                            <p class="text-muted">Post your first announcement to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="announcements-list">
                            <?php foreach ($announcements as $index => $announcement): ?>
                                <div class="announcement-row" onclick="toggleAnnouncementDetails(<?php echo $index; ?>)">
                                    <div class="audience-indicator <?php echo $announcement['target_audience']; ?>"></div>
                                    
                                    <div class="announcement-summary">
                                        <div class="flex-grow-1">
                                            <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge audience-badge bg-<?php 
                                                    echo $announcement['target_audience'] === 'residents' ? 'primary' : 
                                                        ($announcement['target_audience'] === 'security' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($announcement['admin_name']); ?> •
                                                    <i class="fas fa-calendar me-1 ms-2"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center">
                                            <!-- Add Edit Button -->
                                            <button class="btn btn-sm btn-outline-primary me-2 edit-btn" 
                                                    onclick="event.stopPropagation(); editAnnouncement(<?php 
                                                        echo htmlspecialchars(json_encode([
                                                            'id' => $announcement['id'],
                                                            'title' => $announcement['title'],
                                                            'content' => $announcement['content'],
                                                            'target_audience' => $announcement['target_audience']
                                                        ])); 
                                                    ?>)" 
                                                    title="Edit announcement">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Existing Delete Form -->
                                            <form method="POST" class="me-2" onclick="event.stopPropagation()">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                <button type="submit" name="delete_announcement" class="btn btn-sm btn-outline-danger delete-btn"
                                                        onclick="return confirm('Delete this announcement?')" title="Delete announcement">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <i class="fas fa-chevron-down expand-icon"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="announcement-details" id="announcement-details-<?php echo $index; ?>">
                                        <div class="announcement-meta">
                                            <div>
                                                <strong>Target Audience:</strong>
                                                <span class="badge bg-<?php 
                                                    echo $announcement['target_audience'] === 'residents' ? 'primary' : 
                                                        ($announcement['target_audience'] === 'security' ? 'warning' : 'info'); 
                                                ?> ms-1">
                                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <strong>Posted:</strong>
                                                <span class="text-muted"><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                            </div>
                                            <div>
                                                <strong>By:</strong>
                                                <span class="text-muted"><?php echo htmlspecialchars($announcement['admin_name']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="announcement-content">
                                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                        </div>

                                        <!-- Comments Section -->
                                        <div class="comments-section mt-4">
                                            <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Comments</h6>
                                            
                                            <!-- Comments List -->
                                            <?php
                                            $commentStmt = $pdo->prepare("
                                                SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, 
                                                       u.user_type, c.parent_id
                                                FROM announcement_comments c
                                                JOIN users u ON c.user_id = u.id
                                                WHERE c.announcement_id = ? AND c.parent_id IS NULL
                                                ORDER BY c.created_at DESC
                                            ");
                                            $commentStmt->execute([$announcement['id']]);
                                            $comments = $commentStmt->fetchAll();
                                            ?>

                                            <div class="comments-list">
                                                <?php if (empty($comments)): ?>
                                                    <p class="text-muted">No comments yet.</p>
                                                <?php else: ?>
                                                    <?php foreach ($comments as $comment): ?>
                                                        <div class="comment-item mb-3">
                                                            <div class="d-flex justify-content-between">
                                                                <div class="comment-header">
                                                                    <span class="fw-bold"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                                                                    <span class="badge bg-<?php echo $comment['user_type'] === 'admin' ? 'danger' : 'primary'; ?> ms-2">
                                                                        <?php echo ucfirst($comment['user_type']); ?>
                                                                    </span>
                                                                    <small class="text-muted ms-2">
                                                                        <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="comment-content mt-2">
                                                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                                            </div>
                                                            
                                                            <!-- Reply Button -->
                                                            <button class="btn btn-sm btn-outline-primary mt-2" 
                                                                    onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                                                                <i class="fas fa-reply"></i> Reply
                                                            </button>

                                                            <!-- Reply Form -->
                                                            <div class="reply-form mt-2" id="reply-form-<?php echo $comment['id']; ?>" style="display: none;">
                                                                <form method="POST" class="reply-comment-form">
                                                                    <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                                    <div class="input-group">
                                                                        <textarea name="reply_text" class="form-control form-control-sm" 
                                                                                  placeholder="Write a reply..." required></textarea>
                                                                        <button type="submit" name="post_reply" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-paper-plane"></i>
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>

                                                            <!-- Replies -->
                                                            <?php
                                                            $replyStmt = $pdo->prepare("
                                                                SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.user_type
                                                                FROM announcement_comments r
                                                                JOIN users u ON r.user_id = u.id
                                                                WHERE r.parent_id = ?
                                                                ORDER BY r.created_at ASC
                                                            ");
                                                            $replyStmt->execute([$comment['id']]);
                                                            $replies = $replyStmt->fetchAll();
                                                            ?>
                                                            
                                                            <?php if (!empty($replies)): ?>
                                                                <div class="replies-list ms-4 mt-2">
                                                                    <?php foreach ($replies as $reply): ?>
                                                                        <div class="reply-item border-start border-2 ps-3 mb-2">
                                                                            <div class="d-flex justify-content-between">
                                                                                <div>
                                                                                    <span class="fw-bold"><?php echo htmlspecialchars($reply['user_name']); ?></span>
                                                                                    <span class="badge bg-<?php echo $reply['user_type'] === 'admin' ? 'danger' : 'primary'; ?> ms-2">
                                                                                        <?php echo ucfirst($reply['user_type']); ?>
                                                                                    </span>
                                                                                    <small class="text-muted ms-2">
                                                                                        <?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                            <div class="reply-content mt-1">
                                                                                <?php echo nl2br(htmlspecialchars($reply['comment'])); ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
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

    <!-- Post Announcement Modal -->
    <div class="modal fade" id="postAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Post New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" name="target_audience" required>
                                <option value="">Select audience...</option>
                                <option value="residents">Residents Only</option>
                                <option value="security">Security Personnel Only</option>
                                <option value="all">All Users</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="content" rows="6" required 
                                      placeholder="Write your announcement here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="post_announcement" class="btn btn-admin">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_announcement_id" id="edit_announcement_id">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="edit_title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" name="edit_target_audience" id="edit_target_audience" required>
                                <option value="residents">Residents Only</option>
                                <option value="security">Security Personnel Only</option>
                                <option value="all">All Users</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="edit_content" id="edit_content" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_announcement" class="btn btn-primary">Update Announcement</button>
                    </div>
                </form>
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

        // Toggle announcement details (expandable functionality)
        function toggleAnnouncementDetails(index) {
            const detailsElement = document.getElementById(`announcement-details-${index}`);
            const row = detailsElement.closest('.announcement-row');
            const expandIcon = row.querySelector('.expand-icon');
            
            // Close all other open announcements first
            document.querySelectorAll('.announcement-details').forEach((details, idx) => {
                if (idx !== index && details.style.display === 'block') {
                    details.style.display = 'none';
                    details.closest('.announcement-row').classList.remove('expanded');
                    details.closest('.announcement-row').querySelector('.expand-icon').style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle the clicked announcement
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

        // Initialize all announcement details as hidden
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.announcement-details').forEach(details => {
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
            
            // Add smooth hover effects
            document.querySelectorAll('.announcement-row').forEach(row => {
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

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Edit announcement functionality
        function editAnnouncement(announcement) {
            // Populate the edit modal with announcement data
            document.getElementById('edit_announcement_id').value = announcement.id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_target_audience').value = announcement.target_audience;
            
            // Show the edit modal
            new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
        }

        // Handle edit announcement form submission
        document.addEventListener('submit', function(e) {
            if (e.target.id === 'editAnnouncementForm') {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                
                fetch('edit_announcement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the announcement in the list
                        const announcementRow = document.querySelector(`.announcement-row[data-id="${data.announcement.id}"]`);
                        const titleElement = announcementRow.querySelector('.announcement-summary h6');
                        const audienceBadge = announcementRow.querySelector('.audience-badge');
                        const dateElement = announcementRow.querySelector('.text-muted i.fas.fa-calendar');
                        
                        titleElement.textContent = data.announcement.title;
                        audienceBadge.textContent = data.announcement.target_audience.charAt(0).toUpperCase() + data.announcement.target_audience.slice(1);
                        dateElement.textContent = new Date(data.announcement.created_at).toLocaleString('default', { month: 'short', day: 'numeric', year: 'numeric' });
                        
                        // Close the modal
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editAnnouncementModal'));
                        editModal.hide();
                    } else {
                        alert('Error updating announcement: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the announcement. Please try again.');
                });
            }
        });

        // Add this to your existing script section
        function showReplyForm(commentId) {
            const replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm.style.display === 'none') {
                // Hide all other reply forms first
                document.querySelectorAll('.reply-form').forEach(form => {
                    form.style.display = 'none';
                });
                replyForm.style.display = 'block';
                replyForm.querySelector('textarea').focus();
            } else {
                replyForm.style.display = 'none';
            }
        }
    </script>
</body>
</html>
