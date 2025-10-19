<?php
session_start();
require_once 'config/database.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $response = ['success' => false, 'message' => ''];
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get form data
        $firstName = htmlspecialchars(trim($_POST['first_name']));
        $lastName = htmlspecialchars(trim($_POST['last_name']));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = htmlspecialchars(trim($_POST['phone']));
        $address = htmlspecialchars(trim($_POST['address']));
        $username = htmlspecialchars(trim($_POST['username']));
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $residentType = $_POST['resident_type'];
        
        // Handle file uploads
        if ($_FILES['id_photo']['error'] === UPLOAD_ERR_OK && 
            $_FILES['proof_of_residency']['error'] === UPLOAD_ERR_OK) {
            
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Save ID Photo
            $idPhotoName = uniqid() . '_' . basename($_FILES['id_photo']['name']);
            $idPhotoPath = $uploadDir . $idPhotoName;
            move_uploaded_file($_FILES['id_photo']['tmp_name'], $idPhotoPath);
            
            // Save Proof of Residency
            $proofName = uniqid() . '_' . basename($_FILES['proof_of_residency']['name']);
            $proofPath = $uploadDir . $proofName;
            move_uploaded_file($_FILES['proof_of_residency']['tmp_name'], $proofPath);
        } else {
            throw new Exception('Both ID and Proof of Residency are required.');
        }
        
        // Start transaction to ensure both inserts succeed
        $conn->beginTransaction();
        
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (
                first_name, last_name, email, phone, address, username, 
                password, user_type, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 'resident', 'pending'
            )
        ");
        $stmt->execute([
            $firstName, $lastName, $email, $phone, $address, $username,
            $password
        ]);
        
        // Get the inserted user ID
        $userId = $conn->lastInsertId();
        
        // Insert resident-specific information
        $stmt = $conn->prepare("
            INSERT INTO residents (
                user_id, resident_type, id_photo, proof_of_residency
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $residentType,
            $idPhotoPath,
            $proofPath
        ]);
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "Registration successful! Please wait for admin approval.";
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn)) {
            $conn->rollBack();
        }
        $response['message'] = $e->getMessage();
        error_log("Registration error: " . $e->getMessage());
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $response = ['success' => false, 'message' => '', 'redirect' => ''];
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Query to get user with resident information if available
        $stmt = $conn->prepare("
            SELECT 
                u.*,
                COALESCE(r.resident_type, '') as resident_type,
                COALESCE(r.id_photo, '') as id_photo,
                COALESCE(r.proof_of_residency, '') as proof_of_residency
            FROM users u 
            LEFT JOIN residents r ON u.id = r.user_id 
            WHERE u.username = ? 
            LIMIT 1
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists
        if (!$user) {
            throw new Exception('Invalid username or password.');
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Invalid username or password.');
        }

        // ✅ FIX: Only check status for residents
        // Admin and Security users don't need approval status
        if ($user['user_type'] === 'resident') {
            // Make sure status field exists and check it
            if (!isset($user['status']) || empty($user['status'])) {
                throw new Exception('Account status is not set. Please contact administrator.');
            }
            
            if ($user['status'] !== 'approved') {
                if ($user['status'] === 'pending') {
                    throw new Exception('Your account is still pending approval.');
                } elseif ($user['status'] === 'rejected') {
                    throw new Exception('Your account has been rejected.');
                } else {
                    throw new Exception('Your account is not active.');
                }
            }
        }

        // Login successful - Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['logged_in'] = true;
        
        // Set resident type for residents
        if ($user['user_type'] === 'resident') {
            $_SESSION['resident_type'] = $user['resident_type'];
        }

        // Set redirect based on user type
        switch($user['user_type']) {
            case 'admin':
                $response['redirect'] = './admin/dashboard.php';
                break;
            case 'security':
                $response['redirect'] = './security/dashboard.php';
                break;
            case 'resident':
                $response['redirect'] = './resident/dashboard.php';
                break;
            default:
                $response['redirect'] = './index.php';
        }

        $response['success'] = true;
        $response['message'] = 'Login successful!';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>South Pacific Subdivision</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #402222;
            --secondary-color: #CCB099;
            --light-bg: #f8f9fa;
            --text-color: #212529;
        }
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }
        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        .modal-body {
            padding: 2rem;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(64, 34, 34, 0.15);
            border-color: var(--primary-color);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #5a3232;
            border-color: #5a3232;
            transform: translateY(-2px);
        }
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, #402222 0%, #6b3d3d 100%);
            padding: 5rem 0;
            overflow: hidden;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            padding: 2rem;
        }
        .hero-content h1,
        .hero-content p {
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.7);
        }
        .auth-buttons .btn {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
            border-width: 2px;
        }
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(64, 34, 34, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .feature-icon i {
            color: #402222;
        }
        .card {
            border: none;
            transition: transform 0.3s ease;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .card:hover {
            transform: translateY(-5px);
        }
        #about {
            background-color: #f8f9fa;
        }
        #about img {
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .toggle-password {
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
        .input-group .form-control {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        #agreeTermsBtn {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        #agreeTermsBtn:hover {
            background-color: #5a3232;
            border-color: #5a3232;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #402222;">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-home me-2"></i>
                South Pacific Subdivision
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content text-white">
                        <h1 class="display-4 mb-4">Welcome to South Pacific Subdivision</h1>
                        <p class="lead mb-4">Experience modern living in a secure and peaceful community.</p>
                        <div class="auth-buttons">
                            <button class="btn btn-custom me-2" style="background: var(--secondary-color); color: var(--primary-color);" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                            <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#registerModal">
                                <i class="fas fa-user-plus me-2"></i>Register as Resident
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div id="subdivisionCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#subdivisionCarousel" data-bs-slide-to="0" class="active"></button>
                            <button type="button" data-bs-target="#subdivisionCarousel" data-bs-slide-to="1"></button>
                            <button type="button" data-bs-target="#subdivisionCarousel" data-bs-slide-to="2"></button>
                        </div>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="images/1.jpg" class="d-block w-100" alt="Subdivision View 1" style="height: 400px; object-fit: cover;">
                            </div>
                            <div class="carousel-item">
                                <img src="images/2.jpg" class="d-block w-100" alt="Subdivision View 2" style="height: 400px; object-fit: cover;">
                            </div>
                            <div class="carousel-item">
                                <img src="images/3.jpg" class="d-block w-100" alt="Subdivision View 3" style="height: 400px; object-fit: cover;">
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#subdivisionCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#subdivisionCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Features</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-shield-alt fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">24/7 Security</h5>
                            <p class="card-text">Round-the-clock security personnel and CCTV surveillance for your peace of mind.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-tree fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Green Spaces</h5>
                            <p class="card-text">Beautiful parks and landscaped areas for recreation and relaxation.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-home fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Modern Living</h5>
                            <p class="card-text">Contemporary home designs with complete amenities for comfortable living.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">About South Pacific Subdivision</h2>
                    <p class="lead mb-4">A premier residential community that offers modern living spaces in a secure and peaceful environment.</p>
                    <p class="mb-4">Located in a prime location, South Pacific Subdivision provides easy access to essential establishments while maintaining a serene atmosphere for its residents.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Strategic Location</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Complete Amenities</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Professional Management</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <img src="images/1.jpg" alt="Subdivision Overview" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">Register as Resident</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="registerForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Personal Details</h6>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="first_name" placeholder="First Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="last_name" placeholder="Last Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                                </div>
                                <div class="mb-3">
                                    <input type="tel" class="form-control" name="phone" placeholder="Phone Number" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="address" placeholder="Complete Address" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Account Setup</h6>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password" id="reg_password" placeholder="Password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg_password">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="reg_confirm_password" placeholder="Confirm Password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg_confirm_password">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="d-block mb-2">Resident Type:</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="resident_type" value="homeowner" id="typeHomeowner" checked>
                                        <label class="form-check-label" for="typeHomeowner">Homeowner</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="resident_type" value="tenant" id="typeTenant">
                                        <label class="form-check-label" for="typeTenant">Tenant</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Verification</h6>
                                <div class="mb-3">
                                    <label class="form-label">Valid ID (Photo)</label>
                                    <input type="file" class="form-control" name="id_photo" accept="image/*" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Proof of Residency</label>
                                    <input type="file" class="form-control" name="proof_of_residency" accept="image/*" required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                        <label class="form-check-label" for="agreeTerms">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="submit" class="btn btn-primary">Register Account</button>
                        </div>
                        <div class="text-center mt-2">
                            Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Login</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add Welcome Message -->
                    <div class="text-center mb-4">
                        <i class="fas fa-home fa-3x mb-3" style="color: var(--primary-color);"></i>
                        <h4 class="mb-3">Welcome</h4>
                        <p class="text-muted">Please login to access your account</p>
                    </div>
                    
                    <form id="loginForm">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="username" placeholder="Username" required>
                        </div>
                        <div class="mb-3">
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="login_password" placeholder="Password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="login_password">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="login" value="1">
                        <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                        
                        <!-- Add Forgot Password Link -->
                        <div class="text-center">
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                <i class="fas fa-lock me-1"></i> Forgot Password?
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-key fa-3x mb-3" style="color: var(--primary-color);"></i>
                        <h5>Forgot Your Password?</h5>
                        <p class="text-muted">Enter your email address to reset your password</p>
                    </div>
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering, you agree to abide by the rules and regulations of South Pacific Subdivision.</p>
                    <h6>2. Resident Responsibilities</h6>
                    <p>You must provide accurate information and valid documentation. False information may lead to account rejection.</p>
                    <h6>3. Account Approval</h6>
                    <p>All resident accounts require admin approval. You will be notified via email once approved.</p>
                    <h6>4. Security & Conduct</h6>
                    <p>Residents must comply with community guidelines, including noise restrictions, parking rules, and visitor policies.</p>
                    <h6>5. Data Privacy</h6>
                    <p>Your personal data will be used solely for verification and community management purposes.</p>
                    <h6>6. Modifications</h6>
                    <p>The subdivision management reserves the right to update these terms at any time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="agreeTermsBtn">I Agree</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="messageText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Password Toggle (Reusable)
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    }
                });
            });

            // Register Form
            const registerForm = document.getElementById('registerForm');
            if (registerForm) {
                registerForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const password = document.getElementById('reg_password').value;
                    const confirmPassword = document.getElementById('reg_confirm_password').value;
                    if (password !== confirmPassword) {
                        alert('Passwords do not match!');
                        return;
                    }
                    const formData = new FormData(this);
                    const btn = this.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Registering...';

                    try {
                        const res = await fetch('index.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
                            document.getElementById('messageText').textContent = data.message;
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
                            registerForm.reset();
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (err) {
                        document.getElementById('messageText').textContent = err.message;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = 'Register Account';
                    }
                });
            }

            // Login Form
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const btn = this.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Logging in...';

                    try {
                        const res = await fetch('index.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (err) {
                        document.getElementById('messageText').textContent = err.message;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = 'Login';
                    }
                });
            }

            // Forgot Password Form Handler
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            if (forgotPasswordForm) {
                forgotPasswordForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = this.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

                    try {
                        // Add your password reset logic here
                        document.getElementById('messageText').textContent = 'Password reset link has been sent to your email.';
                        bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal')).hide();
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
                        this.reset();
                    } catch (err) {
                        document.getElementById('messageText').textContent = err.message;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = 'Send Reset Link';
                    }
                });
            }

            // Terms Modal Handler
            const agreeTermsBtn = document.getElementById('agreeTermsBtn');
            const agreeTermsCheckbox = document.getElementById('agreeTerms');
            const termsModal = document.getElementById('termsModal');
            const registerModal = document.getElementById('registerModal');

            if (agreeTermsBtn) {
                agreeTermsBtn.addEventListener('click', function() {
                    // Check the terms checkbox
                    agreeTermsCheckbox.checked = true;
                    
                    // Hide terms modal and show register modal
                    bootstrap.Modal.getInstance(termsModal).hide();
                    bootstrap.Modal.getInstance(registerModal).show();
                });
            }

            // Modify the terms link click handler
            const termsLink = document.querySelector('a[data-bs-target="#termsModal"]');
            if (termsLink) {
                termsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Hide register modal and show terms modal
                    bootstrap.Modal.getInstance(registerModal).hide();
                    bootstrap.Modal.getOrCreateInstance(termsModal).show();
                });
            }

            // Handle modal switching for forgot password
            document.querySelector('a[data-bs-target="#forgotPasswordModal"]').addEventListener('click', function(e) {
                e.preventDefault();
                bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('forgotPasswordModal')).show();
            });
        });
    </script>
</body>
</html>