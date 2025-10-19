<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For database connection debugging
try {
    // Your database connection code
    echo "Database connected successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<?php
session_start();

// Debug to see what's in the session
error_log("Session data: " . print_r($_SESSION, true));

// Remove the first check and keep only this one consistent check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit;
}

// Continue with regular dashboard code...
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

try {
    $manager = new SubdivisionManager();
    
    // Get statistics using direct queries for now
    $conn = (new Database())->getConnection();
    $pending_residents = $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'resident' AND status = 'pending'")->fetchColumn();
    $total_residents = $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'resident' AND status = 'approved'")->fetchColumn();
    $total_concerns = $conn->query("SELECT COUNT(*) FROM concerns WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $total_visitors_today = $conn->query("SELECT COUNT(*) FROM visitors WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
    $total_announcements = $conn->query("SELECT COUNT(*) FROM announcements WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

    // Get recent activities
    $recent_concerns = $conn->query("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as full_name 
        FROM concerns c 
        JOIN users u ON c.resident_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    $recent_visitors = $conn->query("
        SELECT v.*, CONCAT(u.first_name, ' ', u.last_name) as resident_name 
        FROM visitors v 
        JOIN users u ON v.resident_id = u.id 
        ORDER BY v.visit_date DESC, v.created_at DESC 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    error_log("[v0] Dashboard error: " . $e->getMessage());
    $pending_residents = $total_residents = $total_concerns = $total_visitors_today = $total_announcements = 0;
    $recent_concerns = $recent_visitors = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Subdivision Management</title>
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
            width: 220px; /* <-- changed from 280px to 220px */
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

        .sidebar .nav-link:last-child {
            margin-bottom: 20px;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .sidebar .nav-link:last-child:hover {
            background: #dc3545;
            color: white !important;
            border-color: #dc3545;
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
            
            .dashboard-container {
                padding: 15px;
                min-height: calc(100vh - 70px);
                height: auto;
            }
            
            .mobile-header {
                display: block !important;
            }
            
            body {
                overflow-x: hidden;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
            
            .dashboard-header h2 {
                font-size: 1.5rem;
            }
            
            .recent-activities-row {
                flex-direction: row !important;
                gap: 8px !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .recent-activities-row .col-md-6 {
                padding: 0 !important;
                flex: 1 !important;
                max-width: 50% !important;
                min-width: 0 !important;
            }
            
            /* Mobile specific adjustments for side-by-side panels */
            .activity-card {
                font-size: 0.85rem;
                min-height: 280px;
            }
            
            .activity-card .card-title {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }
            
            .activity-card .card-header {
                padding: 10px 12px;
            }
            
            .activity-card .card-body {
                padding: 10px 12px;
            }
            
            .activity-item {
                margin-bottom: 8px;
                padding: 8px 0;
            }
            
            .activity-item strong {
                font-size: 0.8rem;
            }
            
            .activity-item .text-muted {
                font-size: 0.75rem;
            }
            
            .activity-badge {
                font-size: 0.7rem;
                padding: 2px 6px;
            }
            
            .activity-date {
                font-size: 0.7rem;
                padding: 2px 6px;
            }
            
            .view-all-btn {
                font-size: 0.75rem;
                padding: 6px 12px;
            }
        }

        /* Force side-by-side on all screen sizes */
        @media (max-width: 576px) {
            .recent-activities-row {
                flex-direction: row !important;
                gap: 5px !important;
            }
            
            .recent-activities-row .col-md-6 {
                max-width: 50% !important;
                min-width: 0 !important;
                flex: 1 !important;
            }
            
            .activity-card {
                font-size: 0.8rem !important;
                min-height: 260px !important;
            }
            
            .activity-card .card-title {
                font-size: 0.85rem !important;
            }
            
            .activity-item strong {
                font-size: 0.75rem !important;
            }
            
            .activity-item .text-muted {
                font-size: 0.7rem !important;
            }
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
            color: white !important;
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

        .sidebar .p-4 h4 .d-inline-block div {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .sidebar .p-4 h4 i {
            color: var(--admin-secondary);
            margin-right: 12px;
            font-size: 1.2rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .main-content {
            background: white;
            border-radius: 0;
            box-shadow: none;
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            left: 220px;   /* <-- changed from 280px to 220px */
            top: 0;
            right: 0;
            z-index: 1;
            width: calc(100% - 220px); /* <-- changed from 280px to 220px */
            min-width: 0;
            margin-left: 0;
            padding: 0;
        }

        @media (min-width: 992px) {
            .main-content {
                left: 220px; /* <-- changed from 280px to 220px */
                right: 0;
                margin-left: 0;
            }
        }

        .dashboard-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header {
            flex-shrink: 0;
            margin-bottom: 15px;
        }

        .dashboard-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: visible;
            align-items: center;
            width: 100%;
        }

        .dashboard-content > .row {
            width: 100%;
            max-width: 1200px;
        }

        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
                height: calc(100vh - 70px);
            }
            
            .dashboard-container {
                padding: 15px;
                height: calc(100vh - 70px);
            }
        }

        .main-content-col {
            position: relative;
            padding: 0;
        }

        .sidebar-col {
            position: relative;
            z-index: 2;
        }

        .stat-card {
            background: var(--admin-secondary);
            border-radius: 12px;
            padding: 15px;
            color: white;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            height: 100%;
            min-height: 80px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        a .stat-card {
            color: inherit;
            text-decoration: none;
        }

        a .stat-card:hover {
            color: inherit;
            text-decoration: none;
        }

        a:hover .stat-card {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            text-decoration: none;
        }

        .stat-card.danger {
            background: #402222;
            color: white;
        }

        .stat-card.danger:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.3);
            color: #ffffff !important;
        }

        .stat-card.danger:hover h3,
        .stat-card.danger:hover p {
            color: #ffffff !important;
        }

        .stat-card.success {
            background: #402222;
            color: white;
        }

        .stat-card.success:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(40, 167, 69, 0.3);
            color: #ffffff !important;
        }

        .stat-card.success:hover h3,
        .stat-card.success:hover p {
            color: #ffffff !important;
        }

        .stat-card.warning {
            background: #402222;
            color: white;
        }

        .stat-card.warning:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(255, 193, 7, 0.3);
            color: #ffffff !important;
        }

        .stat-card.warning:hover h3,
        .stat-card.warning:hover p {
            color: #ffffff !important;
        }

        .stat-card.info {
            background: #CCB099;
            color: #402222;
        }

        .stat-card.info:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(204, 176, 153, 0.3);
            color: #2c1810 !important;
        }

        .stat-card.info:hover h3,
        .stat-card.info:hover p {
            color: #2c1810 !important;
        }

        .stat-card h3 {
            font-weight: bold;
            font-size: 2rem;
        }

        .stat-card p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .stat-card i {
            opacity: 0.7;
            font-size: 1.8rem !important;
        }

        .activity-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(64, 34, 34, 0.06);
            border: 1px solid rgba(204, 176, 153, 0.15);
            transition: all 0.3s ease;
            height: 100%;
            min-height: 250px;
            max-height: 300px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
        }

        .activity-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(64, 34, 34, 0.15);
            border-color: var(--admin-secondary);
        }

        .activity-card .card-header {
            background: transparent;
            border: none;
            padding: 20px 25px 10px;
        }

        .activity-card .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--admin-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .activity-card .card-title i {
            font-size: 1.3rem;
            margin-right: 10px;
            padding: 6px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--admin-secondary) 0%, #d4c5b0 100%);
            color: var(--admin-primary);
        }

        .activity-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 25px 20px;
            overflow: hidden;
        }

        .activity-list {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 5px;
        }

        .activity-list::-webkit-scrollbar {
            width: 4px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: rgba(204, 176, 153, 0.1);
            border-radius: 2px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: rgba(204, 176, 153, 0.4);
            border-radius: 2px;
        }

        .activity-list::-webkit-scrollbar-thumb:hover {
            background: rgba(204, 176, 153, 0.6);
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(204, 176, 153, 0.2);
            transition: all 0.3s ease;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .activity-item:hover {
            background: rgba(204, 176, 153, 0.05);
            padding: 10px 8px;
            margin: 0 -8px 6px;
        }

        .activity-item:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }

        .activity-item strong {
            color: var(--admin-primary);
            font-weight: 600;
            font-size: 1.05rem;
        }

        .activity-item .text-muted {
            color: #6c757d !important;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .activity-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Custom badge colors */
        .activity-badge.bg-success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .activity-badge.bg-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14) !important;
            color: #212529 !important;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
        }

        .activity-badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
        }

        .activity-badge.bg-info {
            background: linear-gradient(135deg, #17a2b8, #007bff) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(23, 162, 184, 0.3);
        }

        /* Additional badge colors for site-wide use */
        .badge.bg-primary, .activity-badge.bg-primary {
            background: linear-gradient(135deg, #6f42c1, #5a32a3) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(111, 66, 193, 0.3);
        }

        .badge.bg-info, .badge.bg-cyan {
            background: linear-gradient(135deg, #e83e8c, #fd7e14) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(232, 62, 140, 0.3);
        }

        /* Custom badge colors */
        .badge.bg-teal {
            background: linear-gradient(135deg, #20c997, #28a745) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(32, 201, 151, 0.3);
        }

        .badge.bg-indigo {
            background: linear-gradient(135deg, #6610f2, #495057) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(102, 16, 242, 0.3);
        }

        /* Override Bootstrap badge defaults */
        .badge {
            padding: 0.4em 0.8em !important;
            font-size: 0.75em !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        .activity-date {
            background: rgba(204, 176, 153, 0.15);
            color: var(--admin-primary);
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 25px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.3;
        }

        .view-all-btn {
            background: linear-gradient(135deg, var(--admin-primary) 0%, #4a2a2a 100%);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 3px 12px rgba(64, 34, 34, 0.2);
            width: 100%;
            flex-shrink: 0;
        }

        .view-all-container {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid rgba(204, 176, 153, 0.1);
            flex-shrink: 0;
        }

        /* Quick Actions Card */
        .quick-actions-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(64, 34, 34, 0.05);
            border: 1px solid rgba(204, 176, 153, 0.12);
            transition: all 0.3s ease;
            max-height: 100px;
        }

        .quick-actions-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border-radius: 15px 15px 0 0;
        }

        .quick-actions-card .card-header {
            background: transparent;
            border: none;
            padding: 10px 15px 5px;
        }

        .quick-actions-card .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--admin-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .quick-actions-card .card-title i {
            font-size: 1.1rem;
            margin-right: 6px;
            padding: 4px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--admin-secondary) 0%, #d4c5b0 100%);
            color: var(--admin-primary);
        }

        .btn-quick-action {
            background: rgba(204, 176, 153, 0.1);
            border: 1px solid rgba(204, 176, 153, 0.2);
            border-radius: 10px;
            padding: 8px 6px;
            color: var(--admin-primary);
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 55px;
            width: 100%;
            font-size: 0.85rem;
        }

        .btn-quick-action:hover {
            background: var(--admin-primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(64, 34, 34, 0.2);
            border-color: var(--admin-primary);
        }

        .btn-quick-action i {
            font-size: 1rem;
            display: block;
            margin-bottom: 2px;
        }

        .btn-quick-action small {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1;
        }

        .view-all-btn:hover {
            background: linear-gradient(135deg, var(--admin-secondary) 0%, #d4c5b0 100%);
            color: var(--admin-primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(64, 34, 34, 0.3);
        }

        .activity-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .activity-card .card-body > *:last-child {
            margin-top: auto;
        }

        /* Equal height panels */
        .recent-activities-row {
            display: flex;
            align-items: stretch;
            justify-content: center;
            margin: 0 auto;
            max-width: 1000px;
            gap: 20px;
        }

        .recent-activities-row .col-md-6 {
            display: flex;
            padding: 0;
            flex: 1;
            max-width: 480px;
            min-width: 280px;
        }

        /* Mobile Responsiveness for Activity Cards */
        @media (max-width: 768px) {
            .activity-card {
                min-height: 320px;
                max-height: 400px;
                margin-bottom: 15px;
            }
            
            .activity-card .card-header {
                padding: 15px 20px 8px;
            }
            
            .activity-card .card-body {
                padding: 0 20px 15px;
            }
            
            .activity-card .card-title {
                font-size: 1.1rem;
            }
            
            .activity-card .card-title i {
                font-size: 1.2rem;
                margin-right: 8px;
                padding: 5px;
            }
            
            .activity-item {
                padding: 8px 0;
                margin-bottom: 5px;
            }
            
            .activity-item:hover {
                padding: 8px 6px;
                margin: 0 -6px 5px;
            }
            
            .view-all-btn {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
            
            .recent-activities-row {
                flex-direction: column;
                gap: 15px;
                max-width: 100%;
            }
            
            .recent-activities-row .col-md-6 {
                padding: 0;
                flex: 1;
                max-width: 100%;
                min-width: auto;
                max-width: none;
            }
            
            .empty-state {
                padding: 20px 15px;
            }
            
            .empty-state i {
                font-size: 2rem;
                margin-bottom: 8px;
            }
            
            .activity-list {
                margin-bottom: 10px;
            }
            
            .view-all-container {
                padding-top: 8px;
            }
        }

        /* Quick Actions Mobile Responsive */
        @media (max-width: 768px) {
            .quick-actions-card {
                max-height: 90px;
                margin-bottom: 15px;
            }
            
            .quick-actions-card .card-header {
                padding: 8px 12px 3px;
            }
            
            .quick-actions-card .card-body {
                padding: 3px 12px 8px !important;
            }
            
            .quick-actions-card .card-title {
                font-size: 0.95rem;
            }
            
            .quick-actions-card .card-title i {
                font-size: 1rem;
                padding: 3px;
            }
            
            .btn-quick-action {
                min-height: 45px;
                padding: 6px 4px;
                border-radius: 8px;
            }
            
            .btn-quick-action i {
                font-size: 0.9rem;
                margin-bottom: 1px;
            }
            
            .btn-quick-action small {
                font-size: 0.65rem;
            }
        }

        .brand-title {
            color: var(--admin-primary);
            font-weight: bold;
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

        /* Mobile Responsive Design */
        .mobile-header {
            display: block;
            background: var(--admin-primary);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1045;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            user-select: none;
        }

        @media (min-width: 768px) {
            .mobile-header {
                display: none;
            }
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
            z-index: 1050;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: rgba(255, 255, 255, 0.2);
            touch-action: manipulation;
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
            background: linear-gradient(180deg, var(--admin-primary) 0%, #4a2a2a 100%);
            border-radius: 0 0 15px 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            border-top: 2px solid var(--admin-secondary);
        }

        .mobile-nav-dropdown.show {
            max-height: 400px;
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav-item {
            display: block;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
            position: relative;
            z-index: 1041;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .mobile-nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            text-decoration: none;
            padding-left: 35px;
            transform: translateX(5px);
        }

        .mobile-nav-item:focus {
            background: rgba(255,255,255,0.15);
            color: white;
            text-decoration: none;
            outline: none;
        }

        .mobile-nav-item.active {
            background: var(--admin-secondary);
            color: var(--admin-primary);
            font-weight: 600;
            border-left: 4px solid var(--admin-secondary);
        }

        .mobile-nav-item:last-child {
            border-bottom: none;
            border-radius: 0 0 15px 15px;
        }

        .mobile-nav-item i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 0.9rem;
        }

        .logout-link-mobile {
            background: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
            border-top: 1px solid rgba(220, 53, 69, 0.3) !important;
        }

        .logout-link-mobile:hover {
            background: #dc3545 !important;
            color: white !important;
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

            .stat-card {
                padding: 20px;
                margin-bottom: 15px;
            }

            .stat-card h3 {
                font-size: 1.6rem;
            }

            .stat-card i {
                font-size: 1.5rem !important;
            }

            .activity-card .card-body {
                padding: 15px;
            }

            .brand-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 10px !important;
            }

            .stat-card {
                padding: 15px;
                text-align: center;
            }

            .stat-card h3 {
                font-size: 1.4rem;
            }

            .stat-card .row {
                align-items: center;
                justify-content: center;
            }

            .brand-title {
                font-size: 1.2rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
            }

            .activity-card .list-group-item {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }

        /* Tablet optimizations */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar .nav-link {
                padding: 12px 15px;
                font-size: 0.9rem;
            }

            .stat-card {
                padding: 20px;
            }

            .main-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header d-md-none">
        <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="text-white mb-0">
                <i class="fas fa-shield-alt me-2"></i>
                <span class="d-inline-block">
                    <div style="line-height: 1;">Admin</div>
                    <div style="line-height: 1;">Panel</div>
                </span>
            </h5>
            <button class="mobile-menu-btn" type="button" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation Dropdown -->
        <div class="mobile-nav-dropdown" id="mobileNavDropdown">
            <a href="dashboard.php" class="mobile-nav-item active">
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
            <a href="reports.php" class="mobile-nav-item">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="../logout.php" class="mobile-nav-item logout-link-mobile">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="brand-title mb-0">Admin Dashboard</h2>
                    <div class="text-muted d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('F j, Y'); ?>
                        </div>
                        <div>
                            <i class="fas fa-clock me-2"></i>
                            <span id="current-time"><?php echo date('g:i:s A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-2">
                    <div class="col-lg-3 col-md-6 mb-2">
                            <a href="pending_residents.php" class="text-decoration-none">
                                <div class="stat-card danger">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0"><?php echo $pending_residents; ?></h3>
                                            <p class="mb-0 small">Pending Residents</p>
                                        </div>
                                        <i class="fas fa-user-clock fa-2x"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="residents.php" class="text-decoration-none">
                                <div class="stat-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0"><?php echo $total_residents; ?></h3>
                                            <p class="mb-0 small">Total Residents</p>
                                        </div>
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="concerns.php?date=<?php echo date('Y-m-d'); ?>" class="text-decoration-none">
                                <div class="stat-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0"><?php echo $total_concerns; ?></h3>
                                            <p class="mb-0 small">Today's Concerns</p>
                                        </div>
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="visitors.php?date=<?php echo date('Y-m-d'); ?>" class="text-decoration-none">
                                <div class="stat-card info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-0"><?php echo $total_visitors_today; ?></h3>
                                            <p class="mb-0 small">Today's Visitors</p>
                                        </div>
                                        <i class="fas fa-address-book fa-2x"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="row recent-activities-row mb-2">
                        <div class="col-md-6 mb-2">
                            <div class="card activity-card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Recent Concerns
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_concerns)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-clipboard-list"></i>
                                            <p class="mb-0">No recent concerns</p>
                                            <small>All good in the neighborhood!</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="activity-list">
                                            <?php foreach ($recent_concerns as $concern): ?>
                                                <div class="activity-item d-flex justify-content-between align-items-center">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <strong><?php echo htmlspecialchars($concern['full_name']); ?></strong>
                                                        </div>
                                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars(substr($concern['description'], 0, 60)) . '...'; ?></p>
                                                    </div>
                                                    <div class="ms-3 text-end">
                                                        <span class="activity-date d-block">
                                                            <?php echo date('M j', strtotime($concern['created_at'])); ?>
                                                        </span>
                                                        <small class="text-muted d-block">
                                                            <?php echo date('g:i A', strtotime($concern['created_at'])); ?>
                                                        </small>
                                                        <span class="activity-badge bg-<?php echo $concern['status'] === 'pending' ? 'warning' : ($concern['status'] === 'working' ? 'info' : 'success'); ?>">
                                                            <?php echo ucfirst($concern['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="view-all-container text-center">
                                        <a href="concerns.php" class="btn view-all-btn">
                                            <i class="fas fa-eye me-2"></i>View All Concerns
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <div class="card activity-card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-friends"></i>
                                        Recent Visitors
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_visitors)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-door-open"></i>
                                            <p class="mb-0">No recent visitors</p>
                                            <small>Quiet day at the subdivision</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="activity-list">
                                            <?php foreach ($recent_visitors as $visitor): ?>
                                                <div class="activity-item d-flex justify-content-between align-items-center">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <strong><?php echo htmlspecialchars($visitor['visitor_name'] ?? 'Unknown Visitor'); ?></strong>
                                                            <?php if (!empty($visitor['status'])): ?>
                                                                <span class="activity-badge bg-<?php echo $visitor['status'] === 'approved' ? 'success' : ($visitor['status'] === 'pending' ? 'warning' : 'secondary'); ?> ms-2">
                                                                    <?php echo ucfirst($visitor['status']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="mb-0 text-muted">
                                                            <?php if (!empty($visitor['resident_name'])): ?>
                                                                Visiting: <?php echo htmlspecialchars($visitor['resident_name']); ?>
                                                            <?php else: ?>
                                                                <em>Resident information not available</em>
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if (!empty($visitor['purpose'])): ?>
                                                            <small class="text-muted">
                                                                Purpose: <?php echo htmlspecialchars(substr($visitor['purpose'], 0, 30)) . (strlen($visitor['purpose']) > 30 ? '...' : ''); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ms-3 text-end">
                                                        <span class="activity-date d-block">
                                                            <?php 
                                                            if (!empty($visitor['visit_date'])) {
                                                                echo date('M j', strtotime($visitor['visit_date'])); 
                                                            } else {
                                                                echo 'No date';
                                                            }
                                                            ?>
                                                        </span>
                                                        <?php if (!empty($visitor['visit_time'])): ?>
                                                            <small class="text-muted d-block">
                                                                <?php echo date('g:i A', strtotime($visitor['visit_time'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="view-all-container text-center">
                                        <a href="visitors.php" class="btn view-all-btn">
                                            <i class="fas fa-users me-2"></i>View All Visitors
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card quick-actions-card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body" style="padding: 5px 15px 10px;">
                                    <div class="row g-1">
                                        <div class="col-6 col-md-3">
                                            <a href="pending_residents.php" class="btn btn-quick-action">
                                                <i class="fas fa-user-check mb-1"></i>
                                                <small>Approve</small>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <a href="announcements.php" class="btn btn-quick-action">
                                                <i class="fas fa-bullhorn mb-1"></i>
                                                <small>Announce</small>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <a href="residents.php" class="btn btn-quick-action">
                                                <i class="fas fa-user-plus mb-1"></i>
                                                <small>Add Resident</small>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <a href="reports.php" class="btn btn-quick-action">
                                                <i class="fas fa-chart-line mb-1"></i>
                                                <small>Reports</small>
                                            </a>
                                        </div>
                                    </div>
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
        // Mobile navigation toggle functionality
        function toggleMobileNav() {
            console.log('Toggle mobile nav clicked'); // Debug log
            
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const overlay = document.getElementById('sidebarOverlay');
            const sidebar = document.getElementById('sidebar');
            const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
            
            console.log('Elements status:', {
                dropdown: dropdown ? dropdown.classList.contains('show') : 'not found',
                menuBtn: !!menuBtn,
                overlay: !!overlay,
                sidebar: sidebar ? sidebar.classList.contains('show') : 'not found',
                menuIcon: !!menuIcon
            });
            
            if (menuBtn && menuIcon && sidebar) {
                if (sidebar.classList.contains('show')) {
                    // Close sidebar
                    sidebar.classList.remove('show');
                    menuBtn.classList.remove('active');
                    if (overlay) overlay.classList.remove('show');
                    if (dropdown) dropdown.classList.remove('show');
                    menuIcon.className = 'fas fa-bars';
                    document.body.style.overflow = '';
                    console.log('Sidebar closed');
                } else {
                    // Open sidebar
                    sidebar.classList.add('show');
                    menuBtn.classList.add('active');
                    if (overlay) overlay.classList.add('show');
                    // Don't show dropdown on mobile, show sidebar instead
                    menuIcon.className = 'fas fa-times';
                    document.body.style.overflow = 'hidden';
                    console.log('Sidebar opened');
                }
            } else {
                console.error('Missing mobile nav elements:', {
                    sidebar: !!sidebar,
                    menuBtn: !!menuBtn,
                    menuIcon: !!menuIcon
                });
            }
        }

        // Function to close mobile navigation
        function closeMobileNav() {
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.querySelector('.sidebar-overlay');
            const sidebar = document.getElementById('sidebar');
            const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
            
            if (dropdown) dropdown.classList.remove('show');
            if (menuBtn) menuBtn.classList.remove('active');
            if (overlay) overlay.classList.remove('show');
            if (sidebar) sidebar.classList.remove('show');
            if (menuIcon) menuIcon.className = 'fas fa-bars';
            document.body.style.overflow = '';
        }

        // Ensure dropdown is hidden on page load and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up mobile nav');
            
            const dropdown = document.getElementById('mobileNavDropdown');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const overlay = document.getElementById('sidebarOverlay');
            const sidebar = document.getElementById('sidebar');
            const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
            
            console.log('Mobile elements found:', {
                dropdown: !!dropdown,
                menuBtn: !!menuBtn,
                overlay: !!overlay,
                sidebar: !!sidebar,
                menuIcon: !!menuIcon
            });
            
            // Syntax check - line 1574 area
            
            // Force initial hidden state
            if (dropdown) dropdown.classList.remove('show');
            if (menuBtn) menuBtn.classList.remove('active');
            if (overlay) overlay.classList.remove('show');
            if (sidebar) sidebar.classList.remove('show');
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
                
                // Add touch event for better mobile support
                menuBtn.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Menu button touched');
                    setTimeout(() => toggleMobileNav(), 50);
                }, { passive: false });
            }
            
            console.log('Mobile nav setup complete');
            
            // Close mobile nav when clicking outside
            document.addEventListener('click', function(e) {
                const mobileHeader = document.querySelector('.mobile-header');
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                
                if (!mobileHeader.contains(e.target) && 
                    !sidebar.contains(e.target) && 
                    sidebar.classList.contains('show')) {
                    toggleMobileNav();
                }
            });
            
            // Close sidebar when clicking overlay
            if (overlay) {
                overlay.addEventListener('click', function() {
                    toggleMobileNav();
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    const dropdown = document.getElementById('mobileNavDropdown');
                    const menuBtn = document.getElementById('mobileMenuBtn');
                    const overlay = document.getElementById('sidebarOverlay');
                    const sidebar = document.getElementById('sidebar');
                    const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
                    
                    if (dropdown) dropdown.classList.remove('show');
                    if (menuBtn) menuBtn.classList.remove('active');
                    if (overlay) overlay.classList.remove('show');
                    if (sidebar) sidebar.classList.remove('show');
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
            
            // Add click animation for stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
            
            // Real-time clock update
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                document.getElementById('current-time').textContent = timeString;
            }
            
            // Update clock every second
            setInterval(updateClock, 1000);
            updateClock(); // Initial call
        });
    </script>
</body>
</html>