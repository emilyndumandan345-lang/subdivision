<?php
// filepath: c:\xampp\htdocs\subdivision\admin\residents.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new SubdivisionManager();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_resident'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $manager = new SubdivisionManager();
        $stmt = $manager->pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, address, password, user_type, status) VALUES (?, ?, ?, ?, ?, ?, 'resident', 'approved')");
        if ($stmt->execute([$first_name, $last_name, $email, $phone, $address, $password])) {
            $message = 'Resident added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error adding resident.';
            $message_type = 'danger';
        }
    } elseif (isset($_POST['delete_resident'])) {
        $user_id = $_POST['user_id'];
        $manager = new SubdivisionManager();
        $stmt = $manager->pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'resident'");
        if ($stmt->execute([$user_id])) {
            $message = 'Resident deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting resident.';
            $message_type = 'danger';
        }
    } elseif (isset($_POST['update_resident'])) {
        $user_id = $_POST['user_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];

        $manager = new SubdivisionManager();
        $stmt = $manager->pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND user_type = 'resident'");
        if ($stmt->execute([$first_name, $last_name, $email, $phone, $address, $user_id])) {
            $message = 'Resident updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating resident.';
            $message_type = 'danger';
        }
    }
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$manager = new SubdivisionManager();

// Build query with search functionality
$query = "SELECT * FROM users WHERE user_type = 'resident' AND status = 'approved'";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param];
}

$query .= " ORDER BY first_name, last_name";

$stmt = $manager->pdo->prepare($query);
$stmt->execute($params);
$residents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - Admin Panel</title>
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        * {
            box-sizing: border-box;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--admin-primary) 0%, #4a2a2a 100%);
            min-height: 100vh;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 250px; /* match dashboard */
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

        /* Main Content Layout */
        .main-content {
            margin-left: 250px; /* match dashboard sidebar width */
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        /* Table Responsive Fixes */
.table-responsive {
    border: 0;
    margin: 0;
    padding: 0;
    width: 100%;
}

.table {
    margin-bottom: 0;
}

.table td, .table th {
    vertical-align: middle;
    padding: 0.75rem;
}

/* Search Bar Improvements */
.search-form-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.search-input-container {
    flex: 1;
    min-width: 200px;
}

.search-buttons-container {
    display: flex;
    gap: 8px;
}

/* Responsive Breakpoints */
@media (max-width: 991px) {
    .main-content {
        margin-left: 0;
    }
    
    .sidebar {
        width: 260px;
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}

@media (max-width: 768px) {
    .search-form-container {
        flex-direction: column;
        width: 100%;
    }
    
    .search-input-container,
    .search-buttons-container {
        width: 100%;
    }
    
    .search-buttons-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .table td, .table th {
        font-size: 0.875rem;
        padding: 0.5rem;
    }

    .table td:last-child {
        min-width: 100px;
        text-align: right;
    }
}

@media (max-width: 576px) {
    .brand-title {
        font-size: 1.25rem;
    }

    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 10px;
    }

    .btn-admin {
        width: 100%;
        margin-bottom: 10px;
    }

    .table thead {
        display: none;
    }

    .table tbody tr {
        display: block;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem 0;
    }

    .table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: none;
        padding: 0.5rem;
    }

    .table tbody td:before {
        content: attr(data-label);
        font-weight: bold;
        margin-right: 1rem;
    }
}

/* Mobile Header Fixes */
/* Floating sidebar toggle (mobile) */
.sidebar-toggle { position: fixed; top: 12px; left: 12px; z-index: 1040; background: var(--admin-primary); color: #fff; border: none; width: 44px; height: 44px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
.sidebar-toggle i { font-size: 18px; }

/* Sidebar Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1035;
}

.sidebar-overlay.show {
    display: block;
}

/* Admin Buttons */
.btn-admin {
    background: var(--admin-primary);
    color: #fff;
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.btn-admin:hover { background: #321d1c; color: #fff; }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button for Mobile -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="brand-title">Manage Residents</h2>
                <button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#addResidentModal">
                    <i class="fas fa-user-plus me-2"></i> Add New Resident
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="search-form-container">
                        <div class="search-input-container">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Search residents..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="search-buttons-container">
                            <button type="submit" class="btn btn-admin">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <a href="residents.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                    <?php if (!empty($search)): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                                (<?php echo count($residents); ?> found)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Residents Table -->
            <div class="card resident-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($residents)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No residents found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($residents as $resident): ?>
                                        <tr>
                                            <td data-label="Name">
                                                <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                            </td>
                                            <td data-label="Email">
                                                <?php echo htmlspecialchars($resident['email']); ?>
                                            </td>
                                            <td data-label="Phone">
                                                <?php echo htmlspecialchars($resident['phone']); ?>
                                            </td>
                                            <td data-label="Address">
                                                <?php echo htmlspecialchars($resident['address']); ?>
                                            </td>
                                            <td data-label="Joined">
                                                <?php echo date('M j, Y', strtotime($resident['created_at'])); ?>
                                            </td>
                                            <td data-label="Actions">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="editResident(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $resident['id']; ?>">
                                                        <button type="submit" name="delete_resident" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Delete this resident?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
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

    <!-- Add Resident Modal -->
    <div class="modal fade" id="addResidentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Resident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_resident" class="btn btn-admin">Add Resident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div class="modal fade" id="editResidentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Resident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_resident" class="btn btn-admin">Update Resident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editResident(resident) {
            document.getElementById('edit_user_id').value = resident.id;
            document.getElementById('edit_first_name').value = resident.first_name;
            document.getElementById('edit_last_name').value = resident.last_name;
            document.getElementById('edit_email').value = resident.email;
            document.getElementById('edit_phone').value = resident.phone;
            document.getElementById('edit_address').value = resident.address;
            
            var editModal = new bootstrap.Modal(document.getElementById('editResidentModal'));
            editModal.show();
        }

        // Enhanced search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            const searchForm = searchInput.closest('form');
            
            // Auto-focus on search input if there's a search term
            <?php if (!empty($search)): ?>
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            <?php endif; ?>
            
            // Allow Enter key to submit search
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchForm.submit();
                }
            });
            
            // Clear search with Escape key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    window.location.href = 'residents.php';
                }
            });
        });

        // Mobile navigation toggle functionality
        function toggleMobileNav() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const dropdown = document.getElementById('mobileNavDropdown');
            
            if (!sidebar || !overlay || !menuBtn || !dropdown) return;
            
            const isOpen = sidebar.classList.contains('show');
            
            if (isOpen) {
                // Close navigation
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                dropdown.classList.remove('show');
                menuBtn.querySelector('i').className = 'fas fa-bars';
                document.body.style.overflow = '';
            } else {
                // Open navigation
                sidebar.classList.add('show');
                overlay.classList.add('show');
                dropdown.classList.add('show');
                menuBtn.querySelector('i').className = 'fas fa-times';
                document.body.style.overflow = 'hidden';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const dropdown = document.getElementById('mobileNavDropdown');

            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleMobileNav();
                });
            }

            // Close sidebar when clicking overlay
            overlay && overlay.addEventListener('click', toggleMobileNav);

            // Close mobile nav when selecting an item
            document.querySelectorAll('.mobile-nav-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 991) {
                        sidebar && sidebar.classList.remove('show');
                        overlay && overlay.classList.remove('show');
                        dropdown && dropdown.classList.remove('show');
                        const icon = menuBtn && menuBtn.querySelector('i');
                        if (icon) icon.className = 'fas fa-bars';
                        document.body.style.overflow = '';
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991) {
                    sidebar && sidebar.classList.remove('show');
                    overlay && overlay.classList.remove('show');
                    dropdown && dropdown.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>
