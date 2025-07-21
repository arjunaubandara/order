<?php
// Database connection
include("database_connection.php");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$action = isset($_POST['action']) ? $_POST['action'] : 'INQ';
$message = '';
$messageType = '';

// Initialize form fields with default values
$patt = isset($_POST['patt']) ? $_POST['patt'] : '';
$item = isset($_POST['item']) ? $_POST['item'] : '';
$description = '';
$line = '';
$group = '';
$set = '';
$at91 = '';
$yield_rate = '0'; // Default to 0 instead of empty string

// Arrays for G W section
$gw_items = array_fill(0, 3, '');
$gw_patts = array_fill(0, 3, '');
$gw_bwnos = array_fill(0, 3, '');

// Arrays for DECAL section (3 sets of PATT, CH, STK)
$decal_patts = array_fill(0, 15, '');
$decal_chs = array_fill(0, 15, '');
$decal_stks = array_fill(0, 15, '0'); // Default to 0 instead of empty string

// Additional fields - set numeric fields to 0 by default
$normal_stock = '0';
$min_lot = '0';
$lead_time = '0';
// Replace grade with grade1-4
$grade1 = '';
$grade2 = '';
$grade3 = '';
$grade4 = '';
$forming_method = '';
$decoration_firing = '';
$decoration_method = '';

// Add these validation functions near the top of your file, after the database connection
// but before you start processing any data

/**
 * Validates GW items and patterns against bis_items table
 * @param mysqli $conn Database connection
 * @param string $item Item code
 * @param string $pattern Pattern code (can be empty)
 * @return bool True if valid, false otherwise
 */
function validateGWItem($conn, $item, $pattern = '') {
    // Skip validation if item is empty
    if (empty($item)) {
        return true;
    }
    
    // Construct item code based on whether pattern exists
    $itemCode = !empty($pattern) ? $item . '-' . $pattern : $item;
    
    // Prepare query to check if item code exists
    $query = "SELECT COUNT(*) AS count FROM db_fc.bis_items WHERE ITEM_CODE = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false; // If prepare fails, consider invalid
    }
    
    $stmt->bind_param("s", $itemCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['count'] > 0);
}

/**
 * Validates decal pattern and curve against tbl_decal_master
 * @param mysqli $conn Database connection
 * @param string $pattern Decal pattern
 * @param string $curve Decal curve/GOSU
 * @return bool True if valid, false otherwise
 */
function validateDecalPattern($conn, $pattern, $curve) {
    // Skip validation if either is empty
    if (empty($pattern) || empty($curve)) {
        return true;
    }
    
    // Prepare query to check if the combination exists
    $query = "SELECT COUNT(*) AS count FROM tbl_decal_master WHERE DMTPATT = ? AND DMGOSU = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false; // If prepare fails, consider invalid
    }
    
    $stmt->bind_param("ss", $pattern, $curve);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['count'] > 0);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patt = strtoupper(trim($_POST['patt']));
    $item = strtoupper(trim($_POST['item']));
    $group = isset($_POST['group']) ? strtoupper(trim($_POST['group'])) : '';
    
    // Updated validation for required fields
    $errors = array();
    
    // Validate required fields
    if (empty($patt)) {
        $errors[] = "Pattern (PATT) is required";
    } elseif (strlen($patt) > 8) {
        $errors[] = "Pattern must be 8 characters or less";
    }
    
    if (empty($item)) {
        $errors[] = "Item number (ITEM) is required";
    } elseif (strlen($item) > 7) {
        $errors[] = "Item must be 7 characters or less";
    }
    
    // New validation for group field
    if ($action === 'ADD' || $action === 'AMD') {
        if (empty($group)) {
            $errors[] = "Group (GROUP) is required";
        } elseif (strlen($group) != 2) {
            $errors[] = "Group must be exactly 2 characters";
        } elseif (!preg_match('/^[0-9A-Z]{2}$/', $group)) {
            $errors[] = "Group must contain only digits (0-9) or uppercase letters (A-Z)";
        }
    }
    
    // Display validation errors if any
    if (!empty($errors)) {
        $message = "Please correct the following errors:<br>• " . implode("<br>• ", $errors);
        $messageType = "danger";
    } else {
        // Handle different actions
        switch ($action) {
            case 'INQ': // Inquiry
                // Query to get product details
                $query = "SELECT * FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $patt, $item);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    
                    // Populate form fields with database values - updated field mapping
                    $description = isset($row['SMHINMEI']) ? $row['SMHINMEI'] : '';
                    $line = isset($row['SMLINC']) ? $row['SMLINC'] : '';
                    $group = isset($row['SMHINSHU']) ? $row['SMHINSHU'] : '';
                    $set = isset($row['SMSETC']) ? $row['SMSETC'] : '';
                    $at91 = isset($row['SMAT91']) ? $row['SMAT91'] : '';
                    $yield_rate = isset($row['SMRATE']) ? $row['SMRATE'] : '0';
                    
                    // G W section fields
                    $gw_items[0] = isset($row['SMKITEM1']) ? $row['SMKITEM1'] : '';
                    $gw_patts[0] = isset($row['SMKPATT1']) ? $row['SMKPATT1'] : '';
                    $gw_bwnos[0] = isset($row['SMBWNO1']) ? $row['SMBWNO1'] : '';
                    
                    $gw_items[1] = isset($row['SMKITEM2']) ? $row['SMKITEM2'] : '';
                    $gw_patts[1] = isset($row['SMKPATT2']) ? $row['SMKPATT2'] : '';
                    $gw_bwnos[1] = isset($row['SMBWNO2']) ? $row['SMBWNO2'] : '';
                    
                    // DECAL section fields - exact mapping as provided
                    $decal_patts[0] = isset($row['SMTPATT01']) ? $row['SMTPATT01'] : '';
                    $decal_chs[0] = isset($row['SMGOSU01']) ? $row['SMGOSU01'] : '';
                    $decal_stks[0] = isset($row['SMTUKI01']) ? $row['SMTUKI01'] : '0';
                    
                    $decal_patts[5] = isset($row['SMTPATT02']) ? $row['SMTPATT02'] : '';
                    $decal_chs[5] = isset($row['SMGOSU02']) ? $row['SMGOSU02'] : '';
                    $decal_stks[5] = isset($row['SMTUKI02']) ? $row['SMTUKI02'] : '0';
                    
                    $decal_patts[10] = isset($row['SMTPATT03']) ? $row['SMTPATT03'] : '';
                    $decal_chs[10] = isset($row['SMGOSU03']) ? $row['SMGOSU03'] : '';
                    $decal_stks[10] = isset($row['SMTUKI03']) ? $row['SMTUKI03'] : '0';
                    
                    $decal_patts[1] = isset($row['SMTPATT04']) ? $row['SMTPATT04'] : '';
                    $decal_chs[1] = isset($row['SMGOSU04']) ? $row['SMGOSU04'] : '';
                    $decal_stks[1] = isset($row['SMTUKI04']) ? $row['SMTUKI04'] : '0';
                    
                    $decal_patts[6] = isset($row['SMTPATT05']) ? $row['SMTPATT05'] : '';
                    $decal_chs[6] = isset($row['SMGOSU05']) ? $row['SMGOSU05'] : '';
                    $decal_stks[6] = isset($row['SMTUKI05']) ? $row['SMTUKI05'] : '0';
                    
                    $decal_patts[11] = isset($row['SMTPATT06']) ? $row['SMTPATT06'] : '';
                    $decal_chs[11] = isset($row['SMGOSU06']) ? $row['SMGOSU06'] : '';
                    $decal_stks[11] = isset($row['SMTUKI06']) ? $row['SMTUKI06'] : '0';
                    
                    $decal_patts[2] = isset($row['SMTPATT07']) ? $row['SMTPATT07'] : '';
                    $decal_chs[2] = isset($row['SMGOSU07']) ? $row['SMGOSU07'] : '';
                    $decal_stks[2] = isset($row['SMTUKI07']) ? $row['SMTUKI07'] : '0';
                    
                    $decal_patts[7] = isset($row['SMTPATT08']) ? $row['SMTPATT08'] : '';
                    $decal_chs[7] = isset($row['SMGOSU08']) ? $row['SMGOSU08'] : '';
                    $decal_stks[7] = isset($row['SMTUKI08']) ? $row['SMTUKI08'] : '0';
                    
                    $decal_patts[12] = isset($row['SMTPATT09']) ? $row['SMTPATT09'] : '';
                    $decal_chs[12] = isset($row['SMGOSU09']) ? $row['SMGOSU09'] : '';
                    $decal_stks[12] = isset($row['SMTUKI09']) ? $row['SMTUKI09'] : '0';
                    
                    $decal_patts[3] = isset($row['SMTPATT10']) ? $row['SMTPATT10'] : '';
                    $decal_chs[3] = isset($row['SMGOSU10']) ? $row['SMGOSU10'] : '';
                    $decal_stks[3] = isset($row['SMTUKI10']) ? $row['SMTUKI10'] : '0';
                    
                    $decal_patts[8] = isset($row['SMTPATT11']) ? $row['SMTPATT11'] : '';
                    $decal_chs[8] = isset($row['SMGOSU11']) ? $row['SMGOSU11'] : '';
                    $decal_stks[8] = isset($row['SMTUKI11']) ? $row['SMTUKI11'] : '0';
                    
                    $decal_patts[13] = isset($row['SMTPATT12']) ? $row['SMTPATT12'] : '';
                    $decal_chs[13] = isset($row['SMGOSU12']) ? $row['SMGOSU12'] : '';
                    $decal_stks[13] = isset($row['SMTUKI12']) ? $row['SMTUKI12'] : '0';
                    
                    $decal_patts[4] = isset($row['SMTPATT13']) ? $row['SMTPATT13'] : '';
                    $decal_chs[4] = isset($row['SMGOSU13']) ? $row['SMGOSU13'] : '';
                    $decal_stks[4] = isset($row['SMTUKI13']) ? $row['SMTUKI13'] : '0';
                    
                    $decal_patts[9] = isset($row['SMTPATT14']) ? $row['SMTPATT14'] : '';
                    $decal_chs[9] = isset($row['SMGOSU14']) ? $row['SMGOSU14'] : '';
                    $decal_stks[9] = isset($row['SMTUKI14']) ? $row['SMTUKI14'] : '0';
                    
                    $decal_patts[14] = isset($row['SMTPATT15']) ? $row['SMTPATT15'] : '';
                    $decal_chs[14] = isset($row['SMGOSU15']) ? $row['SMGOSU15'] : '';
                    $decal_stks[14] = isset($row['SMTUKI15']) ? $row['SMTUKI15'] : '0';
                    
                    // Additional fields
                    $normal_stock = isset($row['SMTEKIZAQ']) ? $row['SMTEKIZAQ'] : '0';
                    $min_lot = isset($row['SMHACLOTQ']) ? $row['SMHACLOTQ'] : '0';
                    $lead_time = isset($row['SMRDTM']) ? $row['SMRDTM'] : '0';
                    
                    // Parse grades
                    $grade1 = isset($row['SMEGKAKU1']) ? $row['SMEGKAKU1'] : '';
                    $grade2 = isset($row['SMEGKAKU2']) ? $row['SMEGKAKU2'] : '';
                    $grade3 = isset($row['SMEGKAKU3']) ? $row['SMEGKAKU3'] : '';
                    $grade4 = isset($row['SMEGKAKU4']) ? $row['SMEGKAKU4'] : '';
                    
                    $forming_method = isset($row['SMFMETHOD']) ? $row['SMFMETHOD'] : '';
                    $decoration_firing = isset($row['SMDFIRING']) ? $row['SMDFIRING'] : '';
                    $decoration_method = isset($row['SMDMETHOD']) ? $row['SMDMETHOD'] : '';
                    
                    $message = "Product information retrieved successfully";
                    $messageType = "success";
                } else {
                    $message = "No product found with Pattern: $patt and Item: $item";
                    $messageType = "warning";
                }
                break;
                
            case 'ADD': // Add new product
                // Check if product already exists
                $checkQuery = "SELECT COUNT(*) as count FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("ss", $patt, $item);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $row = $checkResult->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $message = "Product with Pattern: $patt and Item: $item already exists";
                    $messageType = "danger";
                } else {
                    // Extract form values
                    $description = isset($_POST['description']) ? $_POST['description'] : '';
                    $line = isset($_POST['line']) ? $_POST['line'] : '';
                    $group = isset($_POST['group']) ? $_POST['group'] : '';
                    $set = isset($_POST['set']) ? $_POST['set'] : '';
                    $at91 = isset($_POST['at91']) ? $_POST['at91'] : '';
                    $yield_rate = isset($_POST['yield_rate']) && $_POST['yield_rate'] !== '' ? $_POST['yield_rate'] : '0';
                    
                    // Process G W section
                    $gw_items = isset($_POST['gw_items']) ? $_POST['gw_items'] : array_fill(0, 3, '');
                    $gw_patts = isset($_POST['gw_patts']) ? $_POST['gw_patts'] : array_fill(0, 3, '');
                    $gw_bwnos = isset($_POST['gw_bwnos']) ? $_POST['gw_bwnos'] : array_fill(0, 3, '');
                    
                    // Process DECAL section
                    $decal_patts = isset($_POST['decal_patts']) ? $_POST['decal_patts'] : array_fill(0, 15, '');
                    $decal_chs = isset($_POST['decal_chs']) ? $_POST['decal_chs'] : array_fill(0, 15, '');
                    $decal_stks = isset($_POST['decal_stks']) ? $_POST['decal_stks'] : array_fill(0, 15, '0');
                    
                    // Ensure numeric fields have default values if empty
                    for ($i = 0; $i < 15; $i++) {
                        if (empty($decal_stks[$i])) $decal_stks[$i] = '0';
                    }
                    
                    $normal_stock = isset($_POST['normal_stock']) && $_POST['normal_stock'] !== '' ? $_POST['normal_stock'] : '0';
                    $min_lot = isset($_POST['min_lot']) && $_POST['min_lot'] !== '' ? $_POST['min_lot'] : '0';
                    $lead_time = isset($_POST['lead_time']) && $_POST['lead_time'] !== '' ? $_POST['lead_time'] : '0';
                    
                    // Process grades
                    $grade1 = isset($_POST['grade1']) ? $_POST['grade1'] : '';
                    $grade2 = isset($_POST['grade2']) ? $_POST['grade2'] : '';
                    $grade3 = isset($_POST['grade3']) ? $_POST['grade3'] : '';
                    $grade4 = isset($_POST['grade4']) ? $_POST['grade4'] : '';
                    
                    $forming_method = isset($_POST['forming_method']) ? $_POST['forming_method'] : '';
                    $decoration_firing = isset($_POST['decoration_firing']) ? $_POST['decoration_firing'] : '';
                    $decoration_method = isset($_POST['decoration_method']) ? $_POST['decoration_method'] : '';
                    
                    // For ADD case - add this right before your $insertQuery
                    $validationErrors = array();
                    
                    // Validate GW items
                    for ($i = 0; $i < 2; $i++) {
                        if (!empty($gw_items[$i])) {
                            if (!validateGWItem($conn, $gw_items[$i], $gw_patts[$i])) {
                                $validationErrors[] = "Invalid G/W item: " . $gw_items[$i] . 
                                    (!empty($gw_patts[$i]) ? "-" . $gw_patts[$i] : "") . 
                                    " does not exist in bis_items table";
                            }
                        }
                    }
                    
                    // Validate DECAL patterns
                    for ($i = 0; $i < 15; $i++) {
                        if (!empty($decal_patts[$i]) && !empty($decal_chs[$i])) {
                            if (!validateDecalPattern($conn, $decal_patts[$i], $decal_chs[$i])) {
                                $validationErrors[] = "Invalid DECAL pattern: " . $decal_patts[$i] . 
                                    " with curve " . $decal_chs[$i] . 
                                    " does not exist in decal_master table";
                            }
                        }
                    }
                    
                    // If validation errors exist, don't proceed with database operation
                    if (!empty($validationErrors)) {
                        $message = "Validation errors:<br>• " . implode("<br>• ", $validationErrors);
                        $messageType = "danger";
                    } else {
                        // Only continue with the database operations if validation passed
                        // Your existing INSERT or UPDATE code goes here
                        
                        // For ADD case, this is where your $insertQuery and execution would be
                        // For AMD case, this is where your $updateQuery and execution would be
                        
                        // Insert query with updated field mapping
                        $insertQuery = "INSERT INTO tbl_product_master SET 
                            SMPATT = ?, 
                            SMITEM = ?, 
                            SMHINMEI = ?, 
                            SMSETC = ?, 
                            SMLINC = ?, 
                            SMHINSHU = ?, 
                            SMAT91 = ?,
                            SMYOBI1 = NULL,
                            SMYOBI2 = NULL,
                            SMURINE = 0,
                            SMGENKA = 0,
                            SMJ1GEN = 0,
                            SMJ2GEN = 0,
                            SMYOBIPR1 = 0,
                            SMYOBIPR2 = 0,
                            SMKITEM1 = ?, 
                            SMKPATT1 = ?, 
                            SMBWNO1 = ?, 
                            SMKITEM2 = ?, 
                            SMKPATT2 = ?, 
                            SMBWNO2 = ?,
                            SMTPATT01 = ?, 
                            SMGOSU01 = ?, 
                            SMTUKI01 = ?,
                            SMTPATT02 = ?, 
                            SMGOSU02 = ?, 
                            SMTUKI02 = ?,
                            SMTPATT03 = ?, 
                            SMGOSU03 = ?, 
                            SMTUKI03 = ?,
                            SMTPATT04 = ?, 
                            SMGOSU04 = ?, 
                            SMTUKI04 = ?,
                            SMTPATT05 = ?, 
                            SMGOSU05 = ?, 
                            SMTUKI05 = ?,
                            SMTPATT06 = ?, 
                            SMGOSU06 = ?, 
                            SMTUKI06 = ?,
                            SMTPATT07 = ?, 
                            SMGOSU07 = ?, 
                            SMTUKI07 = ?,
                            SMTPATT08 = ?, 
                            SMGOSU08 = ?, 
                            SMTUKI08 = ?,
                            SMTPATT09 = ?, 
                            SMGOSU09 = ?, 
                            SMTUKI09 = ?,
                            SMTPATT10 = ?, 
                            SMGOSU10 = ?, 
                            SMTUKI10 = ?,
                            SMTPATT11 = ?, 
                            SMGOSU11 = ?, 
                            SMTUKI11 = ?,
                            SMTPATT12 = ?, 
                            SMGOSU12 = ?, 
                            SMTUKI12 = ?,
                            SMTPATT13 = ?, 
                            SMGOSU13 = ?, 
                            SMTUKI13 = ?,
                            SMTPATT14 = ?, 
                            SMGOSU14 = ?, 
                            SMTUKI14 = ?,
                            SMTPATT15 = ?, 
                            SMGOSU15 = ?, 
                            SMTUKI15 = ?,
                            SMTEKIZAQ = ?, 
                            SMHACLOTQ = ?, 
                            SMRDTM = ?,
                            SMEGKAKU1 = ?, 
                            SMEGKAKU2 = ?, 
                            SMEGKAKU3 = ?, 
                            SMEGKAKU4 = ?, 
                            SMRATE = ?,
                            SMFMETHOD = ?, 
                            SMDFIRING = ?, 
                            SMDMETHOD = ?,
                            FILLER = NULL";
                        
                        $insertStmt = $conn->prepare($insertQuery);
                        if (!$insertStmt) {
                            $message = "Prepare statement failed: " . $conn->error;
                            $messageType = "danger";
                        } else {
                            $insertStmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss", 
                                $patt, $item, $description, $set, $line, $group, $at91,
                                $gw_items[0], $gw_patts[0], $gw_bwnos[0], $gw_items[1], $gw_patts[1], $gw_bwnos[1],
                                $decal_patts[0], $decal_chs[0], $decal_stks[0],
                                $decal_patts[5], $decal_chs[5], $decal_stks[5],
                                $decal_patts[10], $decal_chs[10], $decal_stks[10],
                                $decal_patts[1], $decal_chs[1], $decal_stks[1],
                                $decal_patts[6], $decal_chs[6], $decal_stks[6],
                                $decal_patts[11], $decal_chs[11], $decal_stks[11],
                                $decal_patts[2], $decal_chs[2], $decal_stks[2],
                                $decal_patts[7], $decal_chs[7], $decal_stks[7],
                                $decal_patts[12], $decal_chs[12], $decal_stks[12],
                                $decal_patts[3], $decal_chs[3], $decal_stks[3],
                                $decal_patts[8], $decal_chs[8], $decal_stks[8],
                                $decal_patts[13], $decal_chs[13], $decal_stks[13],
                                $decal_patts[4], $decal_chs[4], $decal_stks[4],
                                $decal_patts[9], $decal_chs[9], $decal_stks[9],
                                $decal_patts[14], $decal_chs[14], $decal_stks[14],
                                $normal_stock, $min_lot, $lead_time,
                                $grade1, $grade2, $grade3, $grade4, $yield_rate,
                                $forming_method, $decoration_firing, $decoration_method
                            );
                            
                            if ($insertStmt->execute()) {
                                $message = "Product added successfully";
                                $messageType = "success";
                            } else {
                                $message = "Error adding product: " . $conn->error;
                                $messageType = "danger";
                            }
                        }
                    }
                }
                break;
                
            case 'AMD': // Amend/Update product
                // Extract form values
                $description = isset($_POST['description']) ? $_POST['description'] : '';
                $line = isset($_POST['line']) ? $_POST['line'] : '';
                $group = isset($_POST['group']) ? $_POST['group'] : '';
                $set = isset($_POST['set']) ? $_POST['set'] : '';
                $at91 = isset($_POST['at91']) ? $_POST['at91'] : '';
                $yield_rate = isset($_POST['yield_rate']) && $_POST['yield_rate'] !== '' ? $_POST['yield_rate'] : '0';
                
                // Process G W section
                $gw_items = isset($_POST['gw_items']) ? $_POST['gw_items'] : array_fill(0, 3, '');
                $gw_patts = isset($_POST['gw_patts']) ? $_POST['gw_patts'] : array_fill(0, 3, '');
                $gw_bwnos = isset($_POST['gw_bwnos']) ? $_POST['gw_bwnos'] : array_fill(0, 3, '');
                
                // Process DECAL section
                $decal_patts = isset($_POST['decal_patts']) ? $_POST['decal_patts'] : array_fill(0, 15, '');
                $decal_chs = isset($_POST['decal_chs']) ? $_POST['decal_chs'] : array_fill(0, 15, '');
                $decal_stks = isset($_POST['decal_stks']) ? $_POST['decal_stks'] : array_fill(0, 15, '0');
                
                // Ensure numeric fields have default values if empty
                for ($i = 0; $i < 15; $i++) {
                    if (empty($decal_stks[$i])) $decal_stks[$i] = '0';
                }
                
                $normal_stock = isset($_POST['normal_stock']) && $_POST['normal_stock'] !== '' ? $_POST['normal_stock'] : '0';
                $min_lot = isset($_POST['min_lot']) && $_POST['min_lot'] !== '' ? $_POST['min_lot'] : '0';
                $lead_time = isset($_POST['lead_time']) && $_POST['lead_time'] !== '' ? $_POST['lead_time'] : '0';
                
                // Process grades
                $grade1 = isset($_POST['grade1']) ? $_POST['grade1'] : '';
                $grade2 = isset($_POST['grade2']) ? $_POST['grade2'] : '';
                $grade3 = isset($_POST['grade3']) ? $_POST['grade3'] : '';
                $grade4 = isset($_POST['grade4']) ? $_POST['grade4'] : '';
                
                $forming_method = isset($_POST['forming_method']) ? $_POST['forming_method'] : '';
                $decoration_firing = isset($_POST['decoration_firing']) ? $_POST['decoration_firing'] : '';
                $decoration_method = isset($_POST['decoration_method']) ? $_POST['decoration_method'] : '';
                
                // Update query with updated field mapping
                $updateQuery = "UPDATE tbl_product_master SET 
                    SMHINMEI = ?, SMSETC = ?, SMLINC = ?, SMHINSHU = ?, SMAT91 = ?,
                    SMKITEM1 = ?, SMKPATT1 = ?, SMBWNO1 = ?, SMKITEM2 = ?, SMKPATT2 = ?, SMBWNO2 = ?,
                    SMTPATT01 = ?, SMGOSU01 = ?, SMTUKI01 = ?,
                    SMTPATT02 = ?, SMGOSU02 = ?, SMTUKI02 = ?,
                    SMTPATT03 = ?, SMGOSU03 = ?, SMTUKI03 = ?,
                    SMTPATT04 = ?, SMGOSU04 = ?, SMTUKI04 = ?,
                    SMTPATT05 = ?, SMGOSU05 = ?, SMTUKI05 = ?,
                    SMTPATT06 = ?, SMGOSU06 = ?, SMTUKI06 = ?,
                    SMTPATT07 = ?, SMGOSU07 = ?, SMTUKI07 = ?,
                    SMTPATT08 = ?, SMGOSU08 = ?, SMTUKI08 = ?,
                    SMTPATT09 = ?, SMGOSU09 = ?, SMTUKI09 = ?,
                    SMTPATT10 = ?, SMGOSU10 = ?, SMTUKI10 = ?,
                    SMTPATT11 = ?, SMGOSU11 = ?, SMTUKI11 = ?,
                    SMTPATT12 = ?, SMGOSU12 = ?, SMTUKI12 = ?,
                    SMTPATT13 = ?, SMGOSU13 = ?, SMTUKI13 = ?,
                    SMTPATT14 = ?, SMGOSU14 = ?, SMTUKI14 = ?,
                    SMTPATT15 = ?, SMGOSU15 = ?, SMTUKI15 = ?,
                    SMTEKIZAQ = ?, SMHACLOTQ = ?, SMRDTM = ?,
                    SMEGKAKU1 = ?, SMEGKAKU2 = ?, SMEGKAKU3 = ?, SMEGKAKU4 = ?, SMRATE = ?,
                    SMFMETHOD = ?, SMDFIRING = ?, SMDMETHOD = ?
                WHERE SMPATT = ? AND SMITEM = ?";
                
                $updateStmt = $conn->prepare($updateQuery);
                if (!$updateStmt) {
                    $message = "Prepare statement failed: " . $conn->error;
                    $messageType = "danger";
                } else {
                    $updateStmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss", 
                        $description, $set, $line, $group, $at91,
                        $gw_items[0], $gw_patts[0], $gw_bwnos[0], $gw_items[1], $gw_patts[1], $gw_bwnos[1],
                        $decal_patts[0], $decal_chs[0], $decal_stks[0],
                        $decal_patts[5], $decal_chs[5], $decal_stks[5],
                        $decal_patts[10], $decal_chs[10], $decal_stks[10],
                        $decal_patts[1], $decal_chs[1], $decal_stks[1],
                        $decal_patts[6], $decal_chs[6], $decal_stks[6],
                        $decal_patts[11], $decal_chs[11], $decal_stks[11],
                        $decal_patts[2], $decal_chs[2], $decal_stks[2],
                        $decal_patts[7], $decal_chs[7], $decal_stks[7],
                        $decal_patts[12], $decal_chs[12], $decal_stks[12],
                        $decal_patts[3], $decal_chs[3], $decal_stks[3],
                        $decal_patts[8], $decal_chs[8], $decal_stks[8],
                        $decal_patts[13], $decal_chs[13], $decal_stks[13],
                        $decal_patts[4], $decal_chs[4], $decal_stks[4],
                        $decal_patts[9], $decal_chs[9], $decal_stks[9],
                        $decal_patts[14], $decal_chs[14], $decal_stks[14],
                        $normal_stock, $min_lot, $lead_time,
                        $grade1, $grade2, $grade3, $grade4, $yield_rate,
                        $forming_method, $decoration_firing, $decoration_method,
                        $patt, $item
                    );
                    
                    if ($updateStmt->execute()) {
                        if ($updateStmt->affected_rows > 0) {
                            $message = "Product updated successfully";
                            $messageType = "success";
                        } else {
                            $message = "No changes made or product not found";
                            $messageType = "warning";
                        }
                    } else {
                        $message = "Error updating product: " . $conn->error;
                        $messageType = "danger";
                    }
                }
                break;
                
            case 'DEL': // Delete product
                // Check if product exists
                $checkQuery = "SELECT COUNT(*) as count FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("ss", $patt, $item);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $row = $checkResult->fetch_assoc();
                
                if ($row['count'] == 0) {
                    $message = "Product with Pattern: $patt and Item: $item not found";
                    $messageType = "danger";
                } else {
                    // Delete query
                    $deleteQuery = "DELETE FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?";
                    $deleteStmt = $conn->prepare($deleteQuery);
                    
                    if (!$deleteStmt) {
                        $message = "Prepare statement failed: " . $conn->error;
                        $messageType = "danger";
                    } else {
                        $deleteStmt->bind_param("ss", $patt, $item);
                        
                        if ($deleteStmt->execute()) {
                            if ($deleteStmt->affected_rows > 0) {
                                $message = "Product deleted successfully";
                                $messageType = "success";
                                
                                // Clear all field values after deletion
                                $description = '';
                                $line = '';
                                $group = '';
                                $set = '';
                                $at91 = '';
                                $yield_rate = '0';
                                $gw_items = array_fill(0, 3, '');
                                $gw_patts = array_fill(0, 3, '');
                                $gw_bwnos = array_fill(0, 3, '');
                                $decal_patts = array_fill(0, 15, '');
                                $decal_chs = array_fill(0, 15, '');
                                $decal_stks = array_fill(0, 15, '0');
                                $normal_stock = '0';
                                $min_lot = '0';
                                $lead_time = '0';
                                $grade1 = '';
                                $grade2 = '';
                                $grade3 = '';
                                $grade4 = '';
                                $forming_method = '';
                                $decoration_firing = '';
                                $decoration_method = '';
                            } else {
                                $message = "Error deleting product";
                                $messageType = "warning";
                            }
                        } else {
                            $message = "Error deleting product: " . $conn->error;
                            $messageType = "danger";
                        }
                    }
                }
                break;
        }
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Master Maintenance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 5px;
            background-color: #f5f5f5;
            font-family: 'Courier New', monospace;
        }
        .container {
            max-width: 1200px;
            padding: 15px; /* Increased from 10px */
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #000;
            color: #0f0;
            padding: 5px;
            margin-bottom: 10px;
            font-weight: bold;
            border-radius: 5px;
        }
        .form-section {
            margin-bottom: 8px;
        }
        .terminal-section {
            font-family: 'Courier New', monospace;
            background-color: #000;
            color: #0f0;
            padding: 15px; /* Increased from 10px */
            border-radius: 5px;
            margin-bottom: 15px; /* Increased from 10px */
        }
        .terminal-text {
            color: #0f0;
            font-family: 'Courier New', monospace;
            margin-bottom: 0px;
            font-size: 1rem; /* Increased from 0.85rem */
        }
        .terminal-field {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            height: 28px; /* Increased from 25px */
            padding: 2px 5px;
            font-size: 1rem; /* Increased from 0.85rem */
        }
        .form-control-sm {
            height: 25px; /* Increased from 22px */
            padding: 1px 3px;
            font-size: 0.95rem; /* Increased from 0.8rem */
        }
        label {
            margin-bottom: 1px;
            font-weight: bold;
            font-size: 0.95rem; /* Increased from 0.8rem */
        }
        .action-buttons {
            padding: 5px;
            background-color: #eee;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .sub-grid-header {
            font-weight: bold;
            text-align: center;
            font-size: 0.9rem; /* Increased from 0.75rem */
            margin-bottom: 3px;
        }
        .main-fields {
            border: 1px solid #0f0;
            background-color: #000;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .grade-field {
            width: 40px; /* Increased from 35px */
            display: inline-block;
            text-align: center;
            margin-right: 3px;
            height: 28px; /* Increased from 25px */
            padding: 0px;
            font-size: 0.95rem; /* Increased from 0.8rem */
        }
        .form-group {
            margin-bottom: 5px;
        }
        .compact-field-row {
            display: flex;
            align-items: center;
            margin-bottom: 5px; /* Increased from 3px */
        }
        .tight-row {
            margin-left: -5px;
            margin-right: -5px;
        }
        .tight-col {
            padding-left: 5px;
            padding-right: 5px;
        }
        .mb-1 {
            margin-bottom: 0.15rem !important;
        }
        .mb-2 {
            margin-bottom: 0.35rem !important;
        }
        
        /* Optional: Make the font rendering sharper */
        .terminal-text, .terminal-field {
            -webkit-font-smoothing: none;
            -moz-osx-font-smoothing: grayscale;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px; /* Slightly increased spacing for better readability */
        }

        /* Add these new CSS classes */
        .ultra-tight-col {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        .ultra-tight-row {
            margin-left: -2px !important;
            margin-right: -2px !important;
        }
        .no-spacing {
            margin: 0 !important;
            padding: 0 !important;
        }
        .decal-row {
            display: flex;
            flex-wrap: nowrap;
            margin-bottom: 4px; /* Increased from 2px */
        }
        
        /* Even tighter spacing for G W section */
        .ultra-ultra-tight-col {
            padding-left: 0px !important;
            padding-right: 0px !important;
            margin-right: -5px !important;
        }
        
        /* Compact headers for DECAL section */
        .mini-header {
            color: #0f0;
            font-size: 0.85rem; /* Increased from 0.7rem */
            margin: 0;
            padding: 0;
            text-align: center;
            line-height: 1;
        }
        
        /* Improved flex layout for both sections */
        .flex-container {
            display: flex;
            flex-wrap: nowrap;
            margin-bottom: 2px;
            align-items: flex-end;
        }
        
        .flex-col {
            margin-right: 0px;
        }
        
        /* Inline headers group */
        .decal-headers {
            display: flex;
            margin-bottom: 1px;
        }

        /* Add this new style for the header text */
        .header h3 {
            font-size: 1.25rem; /* Larger size for the main header */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="row">
                <div class="col-md-8">
                    <h3 class="mb-0">*** PRODUCT MASTER MAINTENANCE ***</h3>
                </div>
                <div class="col-md-4 text-right">
                    <?php echo date('d/m/y'); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> py-1 mb-2" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" autocomplete="off">
            <div class="action-buttons py-1">
                <strong>Operation Type =</strong>
                <div class="btn-group" role="group">
                    <button type="submit" name="action" value="INQ" class="btn btn-sm <?php echo ($action == 'INQ') ? 'btn-success' : 'btn-outline-success'; ?>">INQUIRY</button>
                    <button type="submit" name="action" value="ADD" class="btn btn-sm <?php echo ($action == 'ADD') ? 'btn-primary' : 'btn-outline-primary'; ?>">ADDITION</button>
                    <button type="submit" name="action" value="AMD" class="btn btn-sm <?php echo ($action == 'AMD') ? 'btn-warning' : 'btn-outline-warning'; ?>">AMEND</button>
                    <button type="submit" name="action" value="DEL" class="btn btn-sm <?php echo ($action == 'DEL') ? 'btn-danger' : 'btn-outline-danger'; ?>" onclick="return confirm('Are you sure you want to delete this product?')">DELETE</button>
                </div>
            </div>

            <!-- Main terminal section containing all fields -->
            <div class="terminal-section">
                <!-- Main identification fields -->
                <div class="row tight-row mb-2">
                    <div class="col-md-3 tight-col">
                        <div class="compact-field-row">
                            <label for="patt" class="terminal-text">PATT =</label>
                            <input type="text" class="form-control terminal-field" id="patt" name="patt" tabindex="1" value="<?php echo htmlspecialchars($patt); ?>" maxlength="8" required style="width: 100px;">
                        </div>
                    </div>
                    <div class="col-md-3 tight-col">
                        <div class="compact-field-row">
                            <label for="item" class="terminal-text">ITEM =</label>
                            <input type="text" class="form-control terminal-field" id="item" name="item" tabindex="2" value="<?php echo htmlspecialchars($item); ?>" maxlength="7" required style="width: 90px;">
                        </div>
                    </div>
                    <div class="col-md-6 tight-col">
                        <div class="compact-field-row">
                            <label for="description" class="terminal-text">DESCRIPTION =</label>
                            <input type="text" class="form-control terminal-field" id="description" name="description" tabindex="3" value="<?php echo htmlspecialchars($description); ?>" maxlength="50" style="width: 250px;">
                        </div>
                    </div>
                </div>

                <!-- Top section fields in a compact layout -->
                <div class="row tight-row mb-2">
                    <div class="col-md-2 tight-col">
                        <div class="compact-field-row">
                            <label for="set" class="terminal-text">SET =</label>
                            <input type="text" class="form-control terminal-field" id="set" name="set" tabindex="4" value="<?php echo htmlspecialchars($set); ?>" maxlength="1" style="width: 50px;">
                        </div>
                    </div>
                    <div class="col-md-2 tight-col">
                        <div class="compact-field-row">
                            <label for="line" class="terminal-text">LINE =</label>
                            <input type="text" class="form-control terminal-field" id="line" name="line" tabindex="5" value="<?php echo htmlspecialchars($line); ?>" maxlength="1" style="width: 50px;">
                        </div>
                    </div>
                    <div class="col-md-2 tight-col">
                        <div class="compact-field-row">
                            <label for="group" class="terminal-text">GROUP =</label>
                            <input type="text" class="form-control terminal-field" id="group" name="group" 
                                   tabindex="6" value="<?php echo htmlspecialchars($group); ?>" 
                                   maxlength="2" minlength="2" style="width: 55px;" 
                                   pattern="[0-9A-Z]{2}" title="2 characters (digits 0-9 or letters A-Z only)">
                        </div>
                    </div>
                    <div class="col-md-2 tight-col">
                        <div class="compact-field-row">
                            <label for="at91" class="terminal-text">AT91 =</label>
                            <input type="text" class="form-control terminal-field" id="at91" name="at91" tabindex="7" value="<?php echo htmlspecialchars($at91); ?>" maxlength="3" style="width: 60px;">
                        </div>
                    </div>
                    <div class="col-md-4 tight-col">
                        <div class="compact-field-row">
                            <label for="yield_rate" class="terminal-text">YIELD RATE =</label>
                            <input type="text" class="form-control terminal-field" id="yield_rate" name="yield_rate" tabindex="8" value="<?php echo htmlspecialchars($yield_rate); ?>" maxlength="10" style="width: 90px;">
                        </div>
                    </div>
                </div>

                <!-- G W Section -->
                <div class="mt-1 mb-1">
                    <div class="terminal-text">(WW/GW DATA)</div>
                    <!-- Use flex layout instead of Bootstrap grid for tighter control -->
                    <div class="flex-container">
                        <div class="flex-col" style="margin-right: 5px; width: 90px;">
                            <div class="sub-grid-header terminal-text no-spacing">ITEM</div>
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_items[]" tabindex="9" value="<?php echo htmlspecialchars($gw_items[0]); ?>" maxlength="7" style="width: 90px;">
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_items[]" tabindex="12" value="<?php echo htmlspecialchars($gw_items[1]); ?>" maxlength="7" style="width: 90px;">
                        </div>
                        <div class="flex-col" style="margin-right: 5px; width: 100px;">
                            <div class="sub-grid-header terminal-text no-spacing">GW PATT</div>
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_patts[]" tabindex="10" value="<?php echo htmlspecialchars($gw_patts[0]); ?>" maxlength="8" style="width: 100px;">
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_patts[]" tabindex="13" value="<?php echo htmlspecialchars($gw_patts[1]); ?>" maxlength="8" style="width: 100px;">
                        </div>
                        <div class="flex-col" style="width: 60px;">
                            <div class="sub-grid-header terminal-text no-spacing">BWNO</div>
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_bwnos[]" tabindex="11" value="<?php echo htmlspecialchars($gw_bwnos[0]); ?>" maxlength="3" style="width: 60px;">
                            <input type="text" class="form-control form-control-sm terminal-field mb-1" name="gw_bwnos[]" tabindex="14" value="<?php echo htmlspecialchars($gw_bwnos[1]); ?>" maxlength="3" style="width: 60px;">
                        </div>
                    </div>
                </div>

                <!-- DECAL Section with revised tab order (column by column instead of row by row) -->
                <div class="mt-1 mb-1">
                    <div class="terminal-text">(DECAL CURVE DATA)</div>
                    
                    <!-- Column headers for all groups -->
                    <div class="flex-container" style="margin-bottom: 0px;">
                        <!-- First group headers -->
                        <div class="flex-col" style="width: 95px;">
                            <p class="mini-header">PATT</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">CURVE</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">QTY</p>
                        </div>
                        
                        <!-- Second group headers -->
                        <div class="flex-col" style="width: 95px; margin-left: 5px;">
                            <p class="mini-header">PATT</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">CURVE</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">QTY</p>
                        </div>
                        
                        <!-- Third group headers -->
                        <div class="flex-col" style="width: 95px; margin-left: 5px;">
                            <p class="mini-header">PATT</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">CURVE</p>
                        </div>
                        <div class="flex-col" style="width: 45px;">
                            <p class="mini-header">QTY</p>
                        </div>
                    </div>
                    
                    <!-- Container for the three column groups -->
                    <div class="flex-container" style="align-items: flex-start;">
                        <!-- First column group (rows 0-4) -->
                        <div style="margin-right: 5px;">
                            <?php 
                            $tabIndex = 15;
                            for ($i = 0; $i < 5; $i++): ?>
                                <div class="decal-row">
                                    <div class="decal-cell" style="width: 95px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_patts[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_patts[$i]); ?>" 
                                               maxlength="8" style="width: 95px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_chs[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_chs[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_stks[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_stks[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Second column group (rows 5-9) -->
                        <div style="margin-right: 5px;">
                            <?php for ($i = 5; $i < 10; $i++): ?>
                                <div class="decal-row">
                                    <div class="decal-cell" style="width: 95px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_patts[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_patts[$i]); ?>" 
                                               maxlength="8" style="width: 95px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_chs[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_chs[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_stks[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_stks[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Third column group (rows 10-14) -->
                        <div>
                            <?php for ($i = 10; $i < 15; $i++): ?>
                                <div class="decal-row">
                                    <div class="decal-cell" style="width: 95px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_patts[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_patts[$i]); ?>" 
                                               maxlength="8" style="width: 95px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_chs[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_chs[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                    <div class="decal-cell" style="width: 45px;">
                                        <input type="text" class="form-control form-control-sm terminal-field" 
                                               name="decal_stks[]" 
                                               tabindex="<?php echo $tabIndex++; ?>" 
                                               value="<?php echo htmlspecialchars($decal_stks[$i]); ?>" 
                                               maxlength="3" style="width: 45px;">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Bottom Fields in a more compact layout -->
                <div class="mt-2">
                    <div class="row tight-row">
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="min_lot" class="terminal-text">MIN LOT =</label>
                                <input type="text" class="form-control terminal-field" id="min_lot" name="min_lot" tabindex="60" value="<?php echo htmlspecialchars($min_lot); ?>" maxlength="10" style="width: 80px;">
                            </div>
                        </div>
                        <div class="col-md-8 tight-col">
                            <div class="compact-field-row">
                                <label for="grade" class="terminal-text">GRADE =</label>
                                <input type="text" class="form-control terminal-field grade-field" id="grade1" name="grade1" tabindex="61" value="<?php echo htmlspecialchars($grade1); ?>" maxlength="1">
                                <input type="text" class="form-control terminal-field grade-field" id="grade2" name="grade2" tabindex="62" value="<?php echo htmlspecialchars($grade2); ?>" maxlength="1">
                                <input type="text" class="form-control terminal-field grade-field" id="grade3" name="grade3" tabindex="63" value="<?php echo htmlspecialchars($grade3); ?>" maxlength="1">
                                <input type="text" class="form-control terminal-field grade-field" id="grade4" name="grade4" tabindex="64" value="<?php echo htmlspecialchars($grade4); ?>" maxlength="1">
                            </div>
                        </div>
                    </div>

                    <div class="row tight-row">
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="normal_stock" class="terminal-text">NORMAL STOCK =</label>
                                <input type="text" class="form-control terminal-field" id="normal_stock" name="normal_stock" tabindex="65" value="<?php echo htmlspecialchars($normal_stock); ?>" maxlength="5" style="width: 70px;">
                            </div>
                        </div>
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="forming_method" class="terminal-text">FORMING METHOD =</label>
                                <input type="text" class="form-control terminal-field" id="forming_method" name="forming_method" tabindex="66" value="<?php echo htmlspecialchars($forming_method); ?>" maxlength="5" style="width: 70px;">
                            </div>
                        </div>
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="decoration_method" class="terminal-text">DECORATION METHOD =</label>
                                <input type="text" class="form-control terminal-field" id="decoration_method" name="decoration_method" tabindex="67" value="<?php echo htmlspecialchars($decoration_method); ?>" maxlength="5" style="width: 70px;">
                            </div>
                        </div>
                    </div>

                    <div class="row tight-row">
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="decoration_firing" class="terminal-text">DECORATION FIRING =</label>
                                <input type="text" class="form-control terminal-field" id="decoration_firing" name="decoration_firing" tabindex="68" value="<?php echo htmlspecialchars($decoration_firing); ?>" maxlength="5" style="width: 70px;">
                            </div>
                        </div>
                        <div class="col-md-4 tight-col">
                            <div class="compact-field-row">
                                <label for="lead_time" class="terminal-text">LEAD TIME =</label>
                                <input type="text" class="form-control terminal-field" id="lead_time" name="lead_time" tabindex="69" value="<?php echo htmlspecialchars($lead_time); ?>" maxlength="5" style="width: 70px;">
                            </div>
                        </div>
                        <div class="col-md-4 tight-col text-right">
                            <a href="index.php" class="btn btn-sm btn-secondary">Back to Menu</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto convert inputs to uppercase
        document.getElementById('patt').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('item').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Convert grade fields to uppercase
        document.getElementById('grade1').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('grade2').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('grade3').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('grade4').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Convert group field to uppercase
        document.getElementById('group').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>