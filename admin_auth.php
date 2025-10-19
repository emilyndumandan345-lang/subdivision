<?php
// filepath: c:\xampp\htdocs\subdivision\admin\admin_auth.php
session_start();

// Single consistent check for admin authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit;
}
?>