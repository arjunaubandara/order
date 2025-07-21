<?php
// File: export_set_master_break.php
// Database connection
include("database_connection.php");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set CSV filename with date
$filename = "BRDMASDS_" . date('Ymd') . ".csv";

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer for output
$output = fopen('php://output', 'w');

// Define header row for the CSV
$header = array(
    'BDPATT', 'BDITEM',
    'BDPATT01', 'BDITEM01', 'BDTUKI01',
    'BDPATT02', 'BDITEM02', 'BDTUKI02',
    'BDPATT03', 'BDITEM03', 'BDTUKI03',
    'BDPATT04', 'BDITEM04', 'BDTUKI04',
    'BDPATT05', 'BDITEM05', 'BDTUKI05',
    'BDPATT06', 'BDITEM06', 'BDTUKI06',
    'BDPATT07', 'BDITEM07', 'BDTUKI07',
    'BDPATT08', 'BDITEM08', 'BDTUKI08',
    'BDPATT09', 'BDITEM09', 'BDTUKI09',
    'BDPATT10', 'BDITEM10', 'BDTUKI10',
    'BDPATT11', 'BDITEM11', 'BDTUKI11',
    'BDPATT12', 'BDITEM12', 'BDTUKI12',
    'BDPATT13', 'BDITEM13', 'BDTUKI13',
    'BDPATT14', 'BDITEM14', 'BDTUKI14',
    'BDPATT15', 'BDITEM15', 'BDTUKI15',
    'BDPATT16', 'BDITEM16', 'BDTUKI16',
    'BDPATT17', 'BDITEM17', 'BDTUKI17',
    'BDPATT18', 'BDITEM18', 'BDTUKI18',
    'BDPATT19', 'BDITEM19', 'BDTUKI19',
    'BDPATT20', 'BDITEM20', 'BDTUKI20',
    'FILLER'
);

// Write the header row
fputcsv($output, $header);

// Get all unique Pattern and setItem combinations
$sql = "SELECT DISTINCT Pattern, setItem FROM set_master_break ORDER BY Pattern, setItem";
$result = $conn->query($sql);

// Process each Pattern and setItem combination
while ($row = $result->fetch_assoc()) {
    $pattern = $row['Pattern'];
    $setItem = $row['setItem'];
    
    // Get all detail records for this Pattern and setItem
    $detailSql = "SELECT patt, Item, Qty FROM set_master_break 
                 WHERE Pattern = ? AND setItem = ?
                 ORDER BY ID"; // Use ID to maintain the original order
    
    $stmt = $conn->prepare($detailSql);
    $stmt->bind_param("ss", $pattern, $setItem);
    $stmt->execute();
    $detailResult = $stmt->get_result();
    
    // Initialize the row data with the Pattern and setItem
    $rowData = array($pattern, $setItem);
    
    // Initialize counter for items
    $itemCount = 0;
    
    // Process each detail record
    while ($detailRow = $detailResult->fetch_assoc() and $itemCount < 20) {
        $itemCount++;
        // Add the detail record to the row data (patt, Item, Qty)
        $rowData[] = $detailRow['patt'];
        $rowData[] = $detailRow['Item'];
        $rowData[] = $detailRow['Qty'];
    }
    
    // Fill in empty values for remaining positions up to 20 items
    while ($itemCount < 20) {
        $itemCount++;
        $rowData[] = ''; // BDPATT empty
        $rowData[] = ''; // BDITEM empty
        $rowData[] = '0'; // BDTUKI is 0
    }
    
    // Add the FILLER column (empty)
    $rowData[] = '';
    
    // Write the row to the CSV
    fputcsv($output, $rowData);
}

// Close the database connection
$conn->close();
exit;
?>