<?php
// Start session to store feedback messages
session_start();

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "production_data";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// First check if our table exists, if not, create it
$tableCheckSql = "SHOW TABLES LIKE 'tbl_local_order_data'";
$result = $conn->query($tableCheckSql);

if ($result->num_rows == 0) {
    // Table doesn't exist, create it
    $createTableSql = "CREATE TABLE tbl_local_order_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ORCUST VARCHAR(10) NOT NULL,
        ORDEST VARCHAR(50),
        ORORDNO VARCHAR(50),
        ORPATT VARCHAR(20) NOT NULL,
        ORITEM VARCHAR(20) NOT NULL,
        ORUNIT CHAR(1) NOT NULL,
        ORDEL VARCHAR(10),
        ORHINMEI VARCHAR(50),
        ORJPC INT,
        ORCTNNO VARCHAR(20),
        ORSTYL VARCHAR(20),
        ORUTINO VARCHAR(20),
        ORBIKO VARCHAR(50),
        ORIDATE VARCHAR(8),
        po_number VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createTableSql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Function to generate PO Number sequence - format: yymmxxxxxx
function generatePoNumber($conn) {
    $currentYearMonth = date('ym'); // Get current year and month (yymm)
    
    // Query to get the highest PO number with the current year-month prefix
    $sql = "SELECT po_number FROM tbl_local_order_data 
            WHERE po_number LIKE '{$currentYearMonth}%' 
            ORDER BY po_number DESC LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = $row['po_number'];
        
        // Extract the sequence part (last 6 digits)
        $sequence = (int)substr($lastNumber, 4);
        
        // Increment by 1 and format to 6 digits
        $newSequence = str_pad($sequence + 1, 6, '0', STR_PAD_LEFT);
    } else {
        // If no records found, start with 000001
        $newSequence = '000001';
    }
    
    // Combine current year-month with the new sequence
    return $currentYearMonth . $newSequence;
}

// Store form values back in session to preserve after redirection
$_SESSION['ordel'] = isset($_POST['ordel']) ? $_POST['ordel'] : '';
$_SESSION['orcust'] = isset($_POST['orcust']) ? $_POST['orcust'] : '';
$_SESSION['ordest'] = isset($_POST['ordest']) ? $_POST['ordest'] : '';
$_SESSION['orordno'] = isset($_POST['orordno']) ? $_POST['orordno'] : '';
$_SESSION['orbiko'] = isset($_POST['orbiko']) ? $_POST['orbiko'] : '';

// Get header form data
$orcust = isset($_POST['orcust']) ? strtoupper(trim($_POST['orcust'])) : '';
$ordest = isset($_POST['ordest']) ? strtoupper(trim($_POST['ordest'])) : '';
$orordno = isset($_POST['orordno']) ? strtoupper(trim($_POST['orordno'])) : '';
$ordel = isset($_POST['ordel']) ? strtoupper(trim($_POST['ordel'])) : '';
$orbiko = isset($_POST['orbiko']) ? strtoupper(trim($_POST['orbiko'])) : '';
$oridate = isset($_POST['oridate']) ? trim($_POST['oridate']) : date('Ymd'); // Using 8-digit format

// Get row data arrays
$patterns = isset($_POST['pattern']) ? $_POST['pattern'] : array();
$items = isset($_POST['item']) ? $_POST['item'] : array();
$units = isset($_POST['unit']) ? $_POST['unit'] : array();
$qtys = isset($_POST['qty']) ? $_POST['qty'] : array();

// Array to track unique combinations and prevent duplicates
$processedRows = array();
$duplicateCount = 0;

// Count how many records were processed
$insertCount = 0;
$errorCount = 0;
$lastPoNumber = '';

// Perform server-side validation
if (empty($orcust) || empty($ordest) || empty($orordno)) {
    header("Location: local_order_input.php?status=error&message=Required fields are missing");
    exit;
}

$hasCompleteRow = false;
for ($i = 0; $i < count($patterns); $i++) {
    if (!empty(trim($patterns[$i])) && !empty(trim($items[$i])) && 
        !empty(trim($units[$i])) && !empty(trim($qtys[$i]))) {
        $hasCompleteRow = true;
        break;
    }
}

if (!$hasCompleteRow) {
    header("Location: local_order_input.php?status=error&message=At least one complete row is required");
    exit;
}

// Process each row of data
for ($i = 0; $i < count($patterns); $i++) {
    // Skip empty rows
    if (empty($patterns[$i]) || empty($items[$i]) || empty($qtys[$i])) {
        continue;
    }

    // Ensure unit is either P or S, default to P
    $unit = isset($units[$i]) && ($units[$i] == 'S' || $units[$i] == 's') ? 'S' : 'P';
    
    // Clean and normalize data
    $pattern = strtoupper(trim($patterns[$i]));
    $item = strtoupper(trim($items[$i]));
    $qty = intval($qtys[$i]);
    
    // Only process rows with quantity > 0
    if ($qty <= 0) {
        continue;
    }
    
    // Create a unique key for this row to check for duplicates
    $rowKey = $pattern . '|' . $item . '|' . $unit . '|' . $qty;
    
    // Check if this exact combination has already been processed
    if (in_array($rowKey, $processedRows)) {
        $duplicateCount++;
        continue; // Skip this duplicate row
    }
    
    // Add to processed rows
    $processedRows[] = $rowKey;
    
    // Generate a unique PO number for each row
    $poNumber = generatePoNumber($conn);
    $lastPoNumber = $poNumber; // Store the last generated PO
    
    // Default values for optional fields
    $orhinmei = ""; // Product name/description
    $orctnno = ""; // Carton number
    $orstyl = "";  // Style
    $orutino = ""; // UTI number

    // Use direct SQL insertion instead of prepared statement to eliminate binding issues
    $sql = "INSERT INTO tbl_local_order_data 
            (ORCUST, ORDEST, ORORDNO, ORPATT, ORITEM, ORUNIT, ORDEL, ORHINMEI, 
             ORJPC, ORCTNNO, ORSTYL, ORUTINO, ORBIKO, ORIDATE, po_number) 
            VALUES (
                '" . $conn->real_escape_string($orcust) . "', 
                '" . $conn->real_escape_string($ordest) . "',
                '" . $conn->real_escape_string($orordno) . "',
                '" . $conn->real_escape_string($pattern) . "',
                '" . $conn->real_escape_string($item) . "',
                '" . $conn->real_escape_string($unit) . "',
                '" . $conn->real_escape_string($ordel) . "',
                '" . $conn->real_escape_string($orhinmei) . "',
                " . intval($qty) . ",
                '" . $conn->real_escape_string($orctnno) . "',
                '" . $conn->real_escape_string($orstyl) . "',
                '" . $conn->real_escape_string($orutino) . "',
                '" . $conn->real_escape_string($orbiko) . "',
                '" . $conn->real_escape_string($oridate) . "',
                '" . $conn->real_escape_string($poNumber) . "'
            )";
    
    if ($conn->query($sql)) {
        $insertCount++;
    } else {
        $errorCount++;
        error_log("SQL Error: " . $conn->error);
    }
}

// Store last PO number for display on success page
if (!empty($lastPoNumber)) {
    $_SESSION['last_po_number'] = $lastPoNumber;
}

// Store duplicate count for display
$_SESSION['duplicate_count'] = $duplicateCount;

// Close connection
$conn->close();

// Store results in session for feedback messages
$_SESSION['insert_count'] = $insertCount;
$_SESSION['error_count'] = $errorCount;

// Redirect back to the form with feedback
header("Location: index.php?status=success&inserted=" . $insertCount . "&errors=" . $errorCount . "&duplicates=" . $duplicateCount);
exit();
?>
