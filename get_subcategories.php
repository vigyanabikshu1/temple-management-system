<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$temple_id = getCurrentTempleId();
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($category_id > 0) {
    $query = "SELECT id, subcategory_name 
              FROM expense_subcategories 
              WHERE temple_id = $temple_id AND category_id = $category_id AND is_active = 1 
              ORDER BY subcategory_name";
    
    $result = $conn->query($query);
    $subcategories = array();
    
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }
    
    echo json_encode($subcategories);
} else {
    echo json_encode([]);
}
?>
