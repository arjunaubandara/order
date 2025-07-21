<?php
// Include database connection
include("database_connection.php");

// Check if request is valid
if (!isset($_POST['action'])) {
    echo json_encode(array('valid' => false, 'message' => 'Invalid request'));
    exit;
}

// Process based on action type
switch ($_POST['action']) {
    case 'validateGW':
        // Validate G W item and pattern
        if (!isset($_POST['item']) || !isset($_POST['patt'])) {
            echo json_encode(array('valid' => false, 'message' => 'Missing parameters'));
            exit;
        }
        
        $item = trim($_POST['item']);
        $patt = trim($_POST['patt']);
        
        // Skip validation if either field is empty
        if (empty($item) || empty($patt)) {
            echo json_encode(array('valid' => false, 'message' => 'Both Item and Pattern are required'));
            exit;
        }
        
        // Check database
        $itemCode = $item . "-" . $patt;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM db_fc.bis_items WHERE ITEM_CODE = ?");
        $stmt->bind_param("s", $itemCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode(array('valid' => true));
        } else {
            echo json_encode(array('valid' => false, 'message' => "Item-Pattern combination not found: $item-$patt"));
        }
        break;
        
    case 'validateDecal':
        // Validate DECAL pattern and curve
        if (!isset($_POST['patt']) || !isset($_POST['curve'])) {
            echo json_encode(array('valid' => false, 'message' => 'Missing parameters'));
            exit;
        }
        
        $patt = trim($_POST['patt']);
        $curve = trim($_POST['curve']);
        
        // Skip validation if either field is empty
        if (empty($patt) || empty($curve)) {
            echo json_encode(array('valid' => false, 'message' => 'Both Pattern and Curve are required'));
            exit;
        }
        
        // Check database
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_decal_master WHERE DMTPATT = ? AND DMGOSU = ?");
        $stmt->bind_param("ss", $patt, $curve);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode(array('valid' => true));
        } else {
            echo json_encode(array('valid' => false, 'message' => "Invalid Decal combination: PATT=$patt, CURVE=$curve"));
        }
        break;
        
    default:
        echo json_encode(array('valid' => false, 'message' => 'Unknown action'));
}

// Close connection
$conn->close();

function validateGWItems($conn, $items, $patts) {
    $errors = array();
    
    // Your validation logic here
    
    return $errors;
}

function validateDecalItems($conn, $patts, $curves) {
    $errors = array();
    
    // Your validation logic here
    
    return $errors;
}

// Example usage
$gwErrors = validateGWItems($conn, $items, $patts);
$decalErrors = validateDecalItems($conn, $patts, $curves);

// Merge errors
$gwErrors = is_array($gwErrors) ? $gwErrors : array();
$decalErrors = is_array($decalErrors) ? $decalErrors : array();
$allErrors = array_merge($gwErrors, $decalErrors);

if (!empty($allErrors)) {
    // Handle errors
}
?>