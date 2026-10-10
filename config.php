<?php
// config.php - Multi-Temple Configuration

session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_db_password_here'); // set your MySQL password
define('DB_NAME', 'temple_management1');

// System Configuration
define('SYSTEM_NAME', 'Temple Management System');
define('SYSTEM_VERSION', '2.0');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['temple_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin';
}

function isTempleAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'TempleAdmin' || $_SESSION['role'] === 'SuperAdmin');
}

function getCurrentTempleId() {
    return isset($_SESSION['temple_id']) ? intval($_SESSION['temple_id']) : 0;
}

function getCurrentTempleInfo() {
    global $conn;
    $temple_id = getCurrentTempleId();
    
    if ($temple_id > 0) {
        $query = "SELECT * FROM temples WHERE id = $temple_id AND is_active = 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

function getTempleConfig($key, $default = '') {
    global $conn;
    $temple_id = getCurrentTempleId();
    
    $query = "SELECT config_value FROM temple_config WHERE temple_id = $temple_id AND config_key = '$key'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['config_value'];
    }
    
    return $default;
}

function setTempleConfig($key, $value) {
    global $conn;
    $temple_id = getCurrentTempleId();
    $key = sanitizeInput($key);
    $value = sanitizeInput($value);
    
    $query = "INSERT INTO temple_config (temple_id, config_key, config_value) 
              VALUES ($temple_id, '$key', '$value')
              ON DUPLICATE KEY UPDATE config_value = '$value'";
    
    return $conn->query($query);
}

function generateReceiptNo() {
    global $conn;
    $temple_id = getCurrentTempleId();
    $templeInfo = getCurrentTempleInfo();
    
    $prefix = $templeInfo['temple_code'] . date('Ymd');
    
    $query = "SELECT receipt_no FROM vazhipadu_bookings 
              WHERE temple_id = $temple_id AND receipt_no LIKE '$prefix%' 
              ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNo = intval(substr($row['receipt_no'], -4));
        $newNo = $lastNo + 1;
    } else {
        $newNo = 1;
    }
    
    return $prefix . str_pad($newNo, 4, '0', STR_PAD_LEFT);
}

function sanitizeInput($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function logAudit($action, $table_name = null, $record_id = null, $old_value = null, $new_value = null) {
    global $conn;
    $temple_id = getCurrentTempleId();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $action = sanitizeInput($action);
    $table_name = $table_name ? sanitizeInput($table_name) : 'NULL';
    $record_id = $record_id ? intval($record_id) : 'NULL';
    $old_value = $old_value ? "'" . sanitizeInput(json_encode($old_value)) . "'" : 'NULL';
    $new_value = $new_value ? "'" . sanitizeInput(json_encode($new_value)) . "'" : 'NULL';
    $user_id = $user_id ? $user_id : 'NULL';
    
    $query = "INSERT INTO audit_log (temple_id, user_id, action, table_name, record_id, old_value, new_value, ip_address) 
              VALUES ($temple_id, $user_id, '$action', '$table_name', $record_id, $old_value, $new_value, '$ip_address')";
    
    $conn->query($query);
}

function checkSubscription() {
    $templeInfo = getCurrentTempleInfo();
    
    if ($templeInfo && $templeInfo['subscription_expiry']) {
        $expiry = strtotime($templeInfo['subscription_expiry']);
        $today = strtotime(date('Y-m-d'));
        
        if ($today > $expiry) {
            return false;
        }
    }
    
    return true;
}

function getAllTemples($active_only = true) {
    global $conn;
    
    $where = $active_only ? "WHERE is_active = 1" : "";
    $query = "SELECT * FROM temples $where ORDER BY temple_name";
    
    return $conn->query($query);
}

// Store temple info in session if logged in
if (isLoggedIn()) {
    if (!isset($_SESSION['temple_info'])) {
        $_SESSION['temple_info'] = getCurrentTempleInfo();
    }
}
?>
