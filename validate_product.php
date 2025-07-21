<?php
// filepath: h:\Current\Order\validate_product.php
// Database connection parameters for product master
$host = '127.0.0.1';
$dbname = 'production_data'; 
$username = 'root';          
$password = '';              

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start with default response
$response = array(
    'valid' => false,
    'message' => ''
);

try {
    // Get parameters and convert to uppercase
    $pattern = isset($_GET['pattern']) ? strtoupper(trim($_GET['pattern'])) : '';
    $item = isset($_GET['item']) ? strtoupper(trim($_GET['item'])) : '';
    
    // Validate pattern/item exist in product master
    try {
        // Create database connection
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // First check if pattern exists
        if (!empty($pattern) && empty($item)) {
            $query = "SELECT COUNT(*) FROM tbl_product_master WHERE SMPATT = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute(array($pattern));
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $response['valid'] = true;
                $response['message'] = 'Valid pattern found';
            } else {
                $response['message'] = 'Pattern not found in product master';
            }
        }
        // Check if pattern+item combination exists
        else if (!empty($pattern) && !empty($item)) {
            $query = "SELECT COUNT(*) FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute(array($pattern, $item));
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $response['valid'] = true;
                $response['message'] = 'Valid pattern and item combination found';
            } else {
                $response['message'] = 'Pattern and item combination not found';
            }
        }
        // Check if just item exists
        else if (empty($pattern) && !empty($item)) {
            $query = "SELECT COUNT(*) FROM tbl_product_master WHERE SMITEM = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute(array($item));
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $response['valid'] = true;
                $response['message'] = 'Valid item found';
            } else {
                $response['message'] = 'Item not found in product master';
            }
        }
        else {
            $response['message'] = 'No validation parameters provided';
        }
    } catch (PDOException $e) {
        $response['error'] = "Database error: " . $e->getMessage();
        $response['message'] = 'Database connection failed';
    }
} catch (Exception $e) {
    $response['error'] = "General error: " . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>