<?php
// Database connection
include("database_connection.php");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set CSV filename with date
$filename = "SEIMASDS_" . date('Ymd') . ".csv";

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer for output
$output = fopen('php://output', 'w');

// Get all column names except ID
$sql_columns = "SHOW COLUMNS FROM tbl_product_master WHERE Field != 'ID'";
$column_result = $conn->query($sql_columns);

// Prepare column headers for CSV
$headers = array();
while ($column = $column_result->fetch_assoc()) {
    $headers[] = $column['Field'];
}

// Write the column headers to CSV
fputcsv($output, $headers);

// Query to get all data
$sql = "SELECT 
    SMPATT, SMITEM, SMHINMEI, SMSETC, SMLINC, SMHINSHU, SMAT91, 
    SMYOBI1, SMYOBI2, SMURINE, SMGENKA, SMJ1GEN, SMJ2GEN, SMYOBIPR1, SMYOBIPR2,
    SMKITEM1, SMKPATT1, SMBWNO1, SMKITEM2, SMKPATT2, SMBWNO2,
    SMTPATT01, SMGOSU01, SMTUKI01, SMTPATT02, SMGOSU02, SMTUKI02,
    SMTPATT03, SMGOSU03, SMTUKI03, SMTPATT04, SMGOSU04, SMTUKI04,
    SMTPATT05, SMGOSU05, SMTUKI05, SMTPATT06, SMGOSU06, SMTUKI06,
    SMTPATT07, SMGOSU07, SMTUKI07, SMTPATT08, SMGOSU08, SMTUKI08,
    SMTPATT09, SMGOSU09, SMTUKI09, SMTPATT10, SMGOSU10, SMTUKI10,
    SMTPATT11, SMGOSU11, SMTUKI11, SMTPATT12, SMGOSU12, SMTUKI12,
    SMTPATT13, SMGOSU13, SMTUKI13, SMTPATT14, SMGOSU14, SMTUKI14,
    SMTPATT15, SMGOSU15, SMTUKI15, SMTEKIZAQ, SMHACLOTQ, SMRDTM,
    SMEGKAKU1, SMEGKAKU2, SMEGKAKU3, SMEGKAKU4, SMRATE,
    SMFMETHOD, SMDFIRING, SMDMETHOD, FILLER
FROM tbl_product_master";

$result = $conn->query($sql);

// Check if there are records
if ($result->num_rows > 0) {
    // Output each row of data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
} else {
    // If no data, output a message in the CSV
    fputcsv($output, array("No data found in the product master table."));
}

// Close the database connection
$conn->close();
exit;
?>