<?php
// File: update_nydate.php
// Database connection
include("database_connection.php");

// Initialize variables
$message = '';
$messageType = '';
$updateResults = array(
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'notFound' => 0
);

// Process file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["update_file"])) {
    $file = $_FILES["update_file"];
    
    // Check for upload errors
    if ($file["error"] > 0) {
        $message = "File upload error: " . getUploadErrorMessage($file["error"]);
        $messageType = "danger";
    } else {
        $fileName = $file["name"];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check file extension - CSV only
        if ($fileExt == "csv") {
            // Process the file
            try {
                // For CSV files
                $rows = array();
                if (($handle = fopen($file["tmp_name"], "r")) !== FALSE) {
                    // Check and remove header row if it exists
                    $header = fgetcsv($handle, 1000, ",");
                    if (strcasecmp(trim($header[0]), "ORORDNO") === 0 && 
                        strcasecmp(trim($header[1]), "ORNYDATE") === 0) {
                        // Header found, continue with data rows
                    } else {
                        // No header, treat first row as data
                        $rows[] = $header;
                    }
                    
                    // Get all data rows
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }
                
                // Start database transaction
                $conn->autocommit(FALSE);
                
                // Prepare SQL statement
                $sql = "UPDATE tbl_local_order_file SET ORNYDATE = ? WHERE ORORDNO = ?";
                $stmt = $conn->prepare($sql);
                
                // Process each row
                $updateResults['total'] = count($rows);
                foreach ($rows as $row) {
                    if (count($row) >= 2) {
                        $orcordno = trim($row[0]);
                        $ornydate = trim($row[1]);
                        
                        // Validate ORORDNO and ORNYDATE
                        if (!empty($orcordno) && !empty($ornydate)) {
                            // Check if record exists
                            $checkSql = "SELECT COUNT(*) as count FROM tbl_local_order_file WHERE ORORDNO = ?";
                            $checkStmt = $conn->prepare($checkSql);
                            $checkStmt->bind_param("s", $orcordno);
                            $checkStmt->execute();
                            $result = $checkStmt->get_result();
                            $countRow = $result->fetch_assoc();
                            $count = $countRow['count'];
                            
                            if ($count > 0) {
                                // Record exists, update it
                                $stmt->bind_param("ss", $ornydate, $orcordno);
                                if ($stmt->execute()) {
                                    $updateResults['success'] += $stmt->affected_rows;
                                } else {
                                    $updateResults['failed']++;
                                }
                            } else {
                                $updateResults['notFound']++;
                            }
                        } else {
                            $updateResults['failed']++;
                        }
                    } else {
                        $updateResults['failed']++;
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Set success message
                $message = "File processed: " . 
                           $updateResults['total'] . " total records, " . 
                           $updateResults['success'] . " successfully updated, " . 
                           $updateResults['notFound'] . " Order numbers not found, " . 
                           $updateResults['failed'] . " failed updates.";
                $messageType = ($updateResults['success'] > 0) ? "success" : "warning";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                $message = "Error processing file: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Invalid file type. Please upload a CSV file (.csv) only.";
            $messageType = "danger";
        }
    }
}

// Function to get upload error message
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Order - Planned Delivery Adjustment</title>
    <style>
        /* Terminal style theme */
        body {
            font-family: 'Courier New', monospace;
            background-color: #000;
            color: #0f0;
            letter-spacing: 0.5px;
            -webkit-font-smoothing: none;
            padding: 10px;
        }
        
        .container {
            background-color: #000;
            padding: 15px;
            border: 1px solid #0f0;
            margin-bottom: 20px;
            border-radius: 5px;
            max-width: 800px;
            margin: 20px auto;
        }
        
        h1, h2, h3 {
            color: #0f0;
            text-align: center;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
        }
        
        .message-success {
            background-color: #001100;
            border: 1px solid #0f0;
        }
        
        .message-danger {
            background-color: #110000;
            color: #f00;
            border: 1px solid #f00;
        }
        
        .message-warning {
            background-color: #111100;
            color: #ff0;
            border: 1px solid #ff0;
        }
        
        .center {
            text-align: center;
        }
        
        input[type="file"] {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px;
            width: 100%;
            margin-bottom: 15px;
        }
        
        button, input[type="submit"] {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 8px 15px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        
        button:hover, input[type="submit"]:hover {
            background-color: #0f0;
            color: #000;
        }
        
        .instructions {
            background-color: #001100;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            border: 1px solid #0f0;
        }
        
        pre {
            background-color: #000;
            border: 1px solid #0f0;
            padding: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Local Order - Adjust Planned Delivery</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <h3>Instructions</h3>
            <p>Upload a CSV file with ORORDNO and ORNYDATE values to update planned delivery dates.</p>
            <p>The file should have two columns:</p>
            <pre>ORORDNO,ORNYDATE
2025050001,20250530
2025050002,20250601
...</pre>
            <p>- The first column must be the order number (ORORDNO)</p>
            <p>- The second column must be the new delivery date (ORNYDATE) in YYYYMMDD format</p>
            <p>- A header row is optional</p>
            <p>- If using Excel, save your file as CSV (.csv) before uploading</p>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <div class="center">
                <label for="update_file">Select CSV file:</label>
                <input type="file" id="update_file" name="update_file" accept=".csv" required>
            </div>
            
            <div class="center">
                <button type="submit">Upload and Adjust Dates</button>
            </div>
        </form>
        
        <?php if (isset($updateResults) && $updateResults['total'] > 0): ?>
            <div class="container">
                <h3>Update Summary</h3>
                <table>
                    <tr>
                        <td>Total Records Processed:</td>
                        <td><?php echo $updateResults['total']; ?></td>
                    </tr>
                    <tr>
                        <td>Successfully Updated:</td>
                        <td><?php echo $updateResults['success']; ?></td>
                    </tr>
                    <tr>
                        <td>Order Numbers Not Found:</td>
                        <td><?php echo $updateResults['notFound']; ?></td>
                    </tr>
                    <tr>
                        <td>Failed Updates:</td>
                        <td><?php echo $updateResults['failed']; ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Optional: Add link to go back to main dashboard -->
    <div class="center">
        <p><a href="dashboard.php" style="color: #0f0; text-decoration: none;">Back to Dashboard</a></p>
    </div>
</body>
</html>