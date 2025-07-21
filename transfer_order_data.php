<?php
// filepath: h:\Current\Order\transfer_order_data.php
// Database connection parameters
include("database_connection.php");

// Set time zone
date_default_timezone_set('Asia/Colombo');

// Function to check if a record already exists in destination table
function recordExists($conn, $po_number, $orpatt, $oritem) {
    // If po_number is guaranteed to be unique, we can just check that
    $sql = "SELECT COUNT(*) as count FROM tbl_local_order_file 
            WHERE ORORDNO = '" . $conn->real_escape_string($po_number) . "'";
    
    $result = $conn->query($sql);
    if (!$result) {
        return false; // Error in query
    }
    
    $row = $result->fetch_assoc();
    return ($row['count'] > 0);
}

// Initialize counters and different types of messages
$inserted = 0;
$skipped = 0;
$errors = array();
$warnings = array(); // New array for non-error informational messages
$message = '';

// Handle form submission for data transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer'])) {
    // Current date in YYYYMMDD format
    $today = date('Ymd');
    
    // Fetch records from source table that haven't been transferred yet
    // You can modify this query to limit which records to transfer
    $sourceQuery = "SELECT * FROM tbl_local_order_data WHERE 1";
    
    // Add filters if provided
    if (!empty($_POST['filter_po'])) {
        $sourceQuery .= " AND ORORDNO = '" . $conn->real_escape_string($_POST['filter_po']) . "'";
    }
    if (!empty($_POST['filter_date'])) {
        $sourceQuery .= " AND DATE(created_at) <= '" . $conn->real_escape_string($_POST['filter_date']) . "'";
    }
    
    $sourceResult = $conn->query($sourceQuery);
    
    if ($sourceResult && $sourceResult->num_rows > 0) {
        // Loop through each source record
        while ($sourceRow = $sourceResult->fetch_assoc()) {
            // Check if this record already exists in the destination table
            if (recordExists($conn, $sourceRow['po_number'], $sourceRow['ORPATT'], $sourceRow['ORITEM'])) {
                $skipped++;
                continue;
            }
            
            // Get ORSETC, ORLINC, ORHINSHU, ORAT91 from tbl_product_master
            $prodQuery = "SELECT SMSETC, SMLINC, SMHINSHU, SMAT91 
                          FROM tbl_product_master 
                          WHERE SMPATT = ? AND SMITEM = ?";
            
            $prodStmt = $conn->prepare($prodQuery);
            $prodStmt->bind_param("ss", $sourceRow['ORPATT'], $sourceRow['ORITEM']);
            $prodStmt->execute();
            $prodResult = $prodStmt->get_result();
            $prodData = $prodResult->fetch_assoc();
            
            // Default values if product data not found
            $orsetc = ($prodData && $prodData['SMSETC']) ? $prodData['SMSETC'] : NULL;
            $orlinc = ($prodData && $prodData['SMLINC']) ? $prodData['SMLINC'] : NULL;
            $orhinshu = ($prodData && $prodData['SMHINSHU']) ? $prodData['SMHINSHU'] : NULL;
            $orat91 = ($prodData && $prodData['SMAT91']) ? $prodData['SMAT91'] : 0;
            
            // Prepare the insert statement with all mapped fields
            $insertQuery = "INSERT INTO tbl_local_order_file (
                ORMRKT, ORCUST, ORDEST, ORORDNO, ORCASENO,
                ORPATT, ORITEM, ORUNIT, ORIDATE, ORJDATE, 
                ORDEL, ORSDATE, ORUDATE, ORCORDNO, ORBIKO, 
                ORHINMEI, ORPNAME, ORSETC, ORLINC, ORHINSHU,
                ORJPC, ORZPC, ORSPC, ORCPC, ORSKPC, 
                ORKNPC, ORUPRICE, ORAMOUNT, ORUCOST, ORCOST, 
                ORURINE, ORGENKA, ORJ1GEN, ORJ2GEN, ORYOBIPR1,
                ORYOBIPR2, ORJOBC, ORPRUNIT, ORCTNNO, ORJYURYO, 
                ORCTNSIZ, ORAT91, ORYOBI1, ORYOBI2, ORSFIN, 
                ORKFIN, ORPRIC1, ORPRIC2, ORNYDATE, ORSALC, 
                ORMRKT2, ORDEST2, ORSHOH, ORKOBN, ORPKG, 
                ORSTYL, ORUTINO, ORIRI, ORKOZUN, ORNJYURYO,
                ORCSMK1, ORCSMK2, ORCSMK3, ORCSMK4, ORCSMK5, 
                ORCSMK6, ORHSIZE1, ORHSIZE2, ORHSIZE3, ORBARCD1,
                ORLBLPOS1, ORLBLPOS2, ORLBLPOS3, ORPONAME, ORKANRYO,
                ORSLIPC, ORCTNC, FILLER
            ) VALUES (
                '1', ?, ?, ?, NULL,
                ?, ?, ?, ?, ?,
                ?, '0', '0', ?, ?,
                ?, NULL, ?, ?, ?,
                ?, ?, 0, 0, '0',
                '0', '0', '0', '0', '0',
                '0', '0', '0', '0', '0',
                '0', NULL, '1', ?, '0',
                '0', ?, NULL, NULL, NULL,
                NULL, NULL, NULL, ?, 'G',
                NULL, NULL, NULL, NULL, 0,
                ?, ?, 0, '0', '0',
                NULL, NULL, NULL, NULL, NULL,
                NULL, '0', '0', '0', NULL,
                NULL, NULL, NULL, NULL, NULL,
                NULL, NULL, NULL
            )";
            
            $insertStmt = $conn->prepare($insertQuery);
            
            if ($insertStmt) {
                // Debug code to check binding parameters
                $placeholderCount = substr_count($insertQuery, '?');
                $typeStringLength = strlen("sssssssssssssssiisssss");
                $paramCount = count(array($sourceRow['ORCUST'], $sourceRow['ORDEST'], $sourceRow['po_number'], 
                    $sourceRow['ORPATT'], $sourceRow['ORITEM'], $sourceRow['ORUNIT'], $sourceRow['ORIDATE'],
                    $today, $sourceRow['ORDEL'], $sourceRow['ORORDNO'], $sourceRow['ORBIKO'],
                    $sourceRow['ORHINMEI'], $orsetc, $orlinc, $orhinshu, $sourceRow['ORJPC'],
                    $sourceRow['ORJPC'], $sourceRow['ORCTNNO'], $orat91, $sourceRow['ORDEL'],
                    $sourceRow['ORSTYL'], $sourceRow['ORUTINO']));
                
                // Verify all counts match
                if ($placeholderCount !== $typeStringLength || $typeStringLength !== $paramCount) {
                    $errors[] = "Binding parameter mismatch: SQL has $placeholderCount placeholders, type string has $typeStringLength chars, and $paramCount params are provided.";
                    continue; // Skip this record
                }
                
                // Bind parameters - 19 parameters to bind
                $insertStmt->bind_param(
                    "sssssssssssssssiisssss", // Corrected - exactly 22 characters (15s + 2i + 5s)
                    $sourceRow['ORCUST'],
                    $sourceRow['ORDEST'],
                    $sourceRow['po_number'], // Maps to ORORDNO
                    $sourceRow['ORPATT'],
                    $sourceRow['ORITEM'],
                    $sourceRow['ORUNIT'],
                    $sourceRow['ORIDATE'],
                    $today, // Current date as ORJDATE
                    $sourceRow['ORDEL'],
                    $sourceRow['ORORDNO'], // Maps to ORCORDNO
                    $sourceRow['ORBIKO'],
                    $sourceRow['ORHINMEI'],
                    $orsetc,
                    $orlinc,
                    $orhinshu,
                    $sourceRow['ORJPC'],
                    $sourceRow['ORJPC'], // Same as ORJPC for ORZPC
                    $sourceRow['ORCTNNO'],
                    $orat91,
                    $sourceRow['ORDEL'], // ORDEL as ORNYDATE
                    $sourceRow['ORSTYL'],
                    $sourceRow['ORUTINO']
                );
                
                // Execute the insert
                if ($insertStmt->execute()) {
                    $inserted++;
                } else {
                    $errors[] = "Error inserting record for PO#: " . $sourceRow['po_number'] . " - " . $insertStmt->error;
                }
                
                $insertStmt->close();
            } else {
                $errors[] = "Failed to prepare insert statement: " . $conn->error;
            }
        }
        
        // Prepare success/error message
        if ($inserted > 0) {
            $message = "<div class='alert alert-success'>Successfully transferred $inserted record(s). Skipped $skipped existing record(s).</div>";
        } else if ($skipped > 0) {
            $message = "<div class='alert alert-warning'>No new records to transfer. Skipped $skipped existing record(s).</div>";
        } else {
            $message = "<div class='alert alert-danger'>No records found to transfer.</div>";
        }
        
        // Display warnings in their own section with a different style
        if (!empty($warnings)) {
            $message .= "<div class='alert alert-info'><strong>Information:</strong><ul>";
            foreach ($warnings as $warning) {
                $message .= "<li>$warning</li>";
            }
            $message .= "</ul></div>";
        }
        
        // Display actual errors separately
        if (!empty($errors)) {
            $message .= "<div class='alert alert-danger'><strong>Errors:</strong><ul>";
            foreach ($errors as $error) {
                $message .= "<li>$error</li>";
            }
            $message .= "</ul></div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>No records found in source table or error querying source data.</div>";
    }
}

// Get source data for preview
$sourcePreviewQuery = "SELECT 
    id, ORCUST, ORDEST, ORORDNO, po_number, ORPATT, ORITEM, ORUNIT, ORIDATE, ORDEL, ORHINMEI, ORJPC, 
    ORCTNNO, ORSTYL, ORUTINO, ORBIKO, created_at
    FROM tbl_local_order_data 
    ORDER BY created_at DESC LIMIT 20";
$sourcePreview = $conn->query($sourcePreviewQuery);

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Local Order Data</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        h1 {
            color: #343a40;
            margin-bottom: 20px;
        }
        .alert {
            margin-top: 20px;
        }
        .filter-section {
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .data-preview {
            margin-top: 30px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        table {
            font-size: 0.9rem;
        }
        .field-mapping {
            font-size: 0.85rem;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .field-mapping h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        .field-mapping pre {
            margin: 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Transfer Local Order Data</h1>
        <p class="lead">Transfer data from <code>Local Order Input</code> to <code>Local Order File</code></p>
        
        <?php echo $message; ?>
        
        <form method="post" action="" class="filter-section">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="filter_po">Filter by Job Card #:</label>
                        <input type="text" id="filter_po" name="filter_po" class="form-control" placeholder="Enter PO Number">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="filter_date">Filter by Input Date:</label>
                        <input type="date" id="filter_date" name="filter_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group" style="margin-top: 32px;">
                        <button type="submit" name="transfer" class="btn btn-primary btn-block">Transfer Data</button>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="data-preview">
            <h2>Data Preview</h2>
            <p>Showing the most recent 20 records from <code>Local Order Input</code></p>
            
            <?php if ($sourcePreview && $sourcePreview->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Job Card #</th>
                                <th>Destination</th>
                                <th>Design</th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Delivery</th>
                                <th>Quantity</th>
                                <th>PO Number</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $sourcePreview->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['ORORDNO']; ?></td>
                                    <td><?php echo $row['ORCUST'] . ' - ' . $row['ORDEST']; ?></td>
                                    <td><?php echo $row['ORPATT']; ?></td>
                                    <td><?php echo $row['ORITEM']; ?></td>
                                    <td><?php echo $row['ORUNIT']; ?></td>
                                    <td><?php echo $row['ORDEL']; ?></td>
                                    <td><?php echo $row['ORJPC']; ?></td>
                                    <td><?php echo $row['po_number']; ?></td>
                                    <td><?php echo $row['created_at']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No data available for preview.</div>
            <?php endif; ?>
        </div>
        

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>