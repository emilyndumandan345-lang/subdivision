<?php
// Include your database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Creation timestamp
$creation_time = date('Y-m-d H:i:s');

try {
    // Admin account
    $stmt = $db->prepare("INSERT INTO users (username, email, password, user_type, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@subdivision.com', 
                   '$2y$10$MmfVTu.TM4YkKR3wvp.qVeamaBCDIFIR7vEiIVU7dIZZwcLqQF6SS',
                   'admin', 'active', $creation_time]);
    
    // Security account 1
    $stmt->execute(['security1', 'security1@subdivision.com',
                   '$2y$10$vP4QZC2QNaCI1hQ.XuQDvemsmCjZ3HVjMhPgC/L99ZQbB0CpfC9CC',
                   'security', 'active', $creation_time]);
    
    // Security account 2
    $stmt->execute(['security2', 'security2@subdivision.com',
                   '$2y$10$vP4QZC2QNaCI1hQ.XuQDvemsmCjZ3HVjMhPgC/L99ZQbB0CpfC9CC',
                   'security', 'active', $creation_time]);
    
    echo "Default accounts created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>