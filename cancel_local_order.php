<?php
// File: cancel_local_order.php
// Database connection
include("database_connection.php");

// Initialize variables
$message = '';
$messageType = '';
$filters = array();
$whereClause = '1=1'; // Default where clause that's always true
$params = array();
$paramTypes = '';
$showData = false; // Flag to control whether to show data initially

// Process form submission for cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $cancel_ids = isset($_POST['cancel']) ? $_POST['cancel'] : array();
    $cancel_qtys = isset($_POST['cancel_qty']) ? $_POST['cancel_qty'] : array();
    
    if (empty($cancel_ids)) {
        $message = "No orders selected for cancellation.";
        $messageType = "warning";
    } else {
        // Start transaction
        $conn->begin_transaction();
        $success = true;
        $cancelled = 0;
        
        foreach ($cancel_ids as $id) {
            if (isset($cancel_qtys[$id]) && is_numeric($cancel_qtys[$id]) && $cancel_qtys[$id] > 0) {
                $cancel_qty = (int)$cancel_qtys[$id];
                
                // Get current ORZPC and ORCPC values
                $checkSql = "SELECT ORZPC, ORCPC FROM tbl_local_order_file WHERE ID = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $current_zpc = (int)$row['ORZPC'];
                    $current_cpc = (int)$row['ORCPC'];
                    
                    // Validate cancel quantity
                    if ($cancel_qty <= $current_zpc) {
                        // Update the quantities
                        $new_zpc = $current_zpc - $cancel_qty;
                        $new_cpc = $current_cpc + $cancel_qty;
                        
                        $updateSql = "UPDATE tbl_local_order_file SET ORZPC = ?, ORCPC = ? WHERE ID = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("iii", $new_zpc, $new_cpc, $id);
                        
                        if (!$updateStmt->execute()) {
                            $success = false;
                            $message = "Error updating order ID $id: " . $conn->error;
                            break;
                        }
                        $cancelled++;
                    } else {
                        $success = false;
                        $message = "Cancel quantity for order ID $id exceeds available quantity ($current_zpc).";
                        break;
                    }
                } else {
                    $success = false;
                    $message = "Could not find order ID $id.";
                    break;
                }
            }
        }
        
        // Commit or rollback transaction
        if ($success) {
            $conn->commit();
            $message = "Successfully cancelled $cancelled order(s).";
            $messageType = "success";
        } else {
            $conn->rollback();
            $messageType = "danger";
        }
    }
}

// Process filter form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    // Set flag to show data since filters were applied
    $showData = true;
    
    // Get filter values
    $ordest = isset($_POST['ordest']) ? trim($_POST['ordest']) : '';
    $orordno = isset($_POST['orordno']) ? trim($_POST['orordno']) : '';
    $orcordno = isset($_POST['orcordno']) ? trim($_POST['orcordno']) : '';
    $orpatt = isset($_POST['orpatt']) ? trim($_POST['orpatt']) : '';
    $oritem = isset($_POST['oritem']) ? trim($_POST['oritem']) : '';
    
    // Build where clause
    $whereParts = array();
    
    if (!empty($ordest)) {
        $whereParts[] = "ORDEST LIKE ?";
        $params[] = "%$ordest%";
        $paramTypes .= "s";
        $filters['ordest'] = $ordest;
    }
    
    if (!empty($orordno)) {
        $whereParts[] = "ORORDNO LIKE ?";
        $params[] = "%$orordno%";
        $paramTypes .= "s";
        $filters['orordno'] = $orordno;
    }
    
    if (!empty($orcordno)) {
        $whereParts[] = "ORCORDNO LIKE ?";
        $params[] = "%$orcordno%";
        $paramTypes .= "s";
        $filters['orcordno'] = $orcordno;
    }
    
    if (!empty($orpatt)) {
        $whereParts[] = "ORPATT LIKE ?";
        $params[] = "%$orpatt%";
        $paramTypes .= "s";
        $filters['orpatt'] = $orpatt;
    }
    
    if (!empty($oritem)) {
        $whereParts[] = "ORITEM LIKE ?";
        $params[] = "%$oritem%";
        $paramTypes .= "s";
        $filters['oritem'] = $oritem;
    }
    
    // Add condition to only show records with available quantities
    $whereParts[] = "ORZPC > 0";
    
    if (!empty($whereParts)) {
        $whereClause = implode(" AND ", $whereParts);
    }
}

// Add pagination settings
$recordsPerPage = 100; // Show 100 records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Get the filtered records
$result = false;
$totalRecords = 0;
$totalPages = 0;

if ($showData) {
    // First get the count for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_local_order_file WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    
    if (!empty($params)) {
        // Create a reference-based parameter array for mysqli binding
        $bindParams = array($paramTypes); // First element is the types string
        foreach ($params as $key => $value) {
            $params[$key] = $value;
            $bindParams[] = &$params[$key];
        }
        call_user_func_array(array($countStmt, 'bind_param'), $bindParams);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $row = $countResult->fetch_assoc();
    $totalRecords = $row['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Now get the paginated data
    $sql = "SELECT ID, ORDEST, ORCORDNO, ORPATT, ORITEM, ORUNIT, ORIDATE, ORDEL, 
            ORJPC, ORZPC, ORSPC, ORCPC, ORNYDATE 
            FROM tbl_local_order_file 
            WHERE $whereClause 
            ORDER BY ORIDATE, ORDEST, ORCORDNO
            LIMIT $offset, $recordsPerPage";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        // Reset binding arrays for the main query
        $bindParams = array($paramTypes);
        foreach ($params as $key => $value) {
            $params[$key] = $value;
            $bindParams[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Local Orders</title>
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
        }
        
        h1, h2, h3 {
            color: #0f0;
            text-align: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        th, td {
            border: 1px solid #0f0;
            padding: 5px;
            text-align: left;
        }
        
        th {
            background-color: #001100;
        }
        
        input[type="text"], 
        input[type="number"], 
        select, 
        button {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px;
            font-family: 'Courier New', monospace;
        }
        
        input[type="checkbox"] {
            accent-color: #0f0;
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
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-form label {
            display: block;
            margin-bottom: 5px;
        }
        
        .filter-form input {
            width: 100%;
        }
        
        .submit-row {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 10px;
        }
        
        .cancel-form {
            margin-top: 20px;
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
        
        .cancel-qty {
            width: 80px;
        }
        
        .checkbox-cell {
            text-align: center;
        }
        
        .pagination {
            margin: 15px 0;
            text-align: center;
        }
        
        .page-link {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px 10px;
            margin: 0 3px;
            text-decoration: none;
            display: inline-block;
        }
        
        .page-link:hover {
            background-color: #0f0;
            color: #000;
        }
        
        .current-page {
            background-color: #0f0;
            color: #000;
            border: 1px solid #0f0;
            padding: 5px 10px;
            margin: 0 3px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Local Order Cancellation</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Filter Form -->
        <div class="container">
            <h3>Filter Orders</h3>
            <form method="post" class="filter-form">
                <div>
                    <label for="ordest">Destination:</label>
                    <input type="text" id="ordest" name="ordest" value="<?php echo isset($filters['ordest']) ? htmlspecialchars($filters['ordest']) : ''; ?>">
                </div>
                
                <div>
                    <label for="orordno">PO Number:</label>
                    <input type="text" id="orordno" name="orordno" value="<?php echo isset($filters['orordno']) ? htmlspecialchars($filters['orordno']) : ''; ?>">
                </div>
                
                <div>
                    <label for="orcordno">Job Card No# :</label>
                    <input type="text" id="orcordno" name="orcordno" value="<?php echo isset($filters['orcordno']) ? htmlspecialchars($filters['orcordno']) : ''; ?>">
                </div>
                
                <div>
                    <label for="orpatt">Design:</label>
                    <input type="text" id="orpatt" name="orpatt" value="<?php echo isset($filters['orpatt']) ? htmlspecialchars($filters['orpatt']) : ''; ?>">
                </div>
                
                <div>
                    <label for="oritem">Item / Set:</label>
                    <input type="text" id="oritem" name="oritem" value="<?php echo isset($filters['oritem']) ? htmlspecialchars($filters['oritem']) : ''; ?>">
                </div>
                
                <div class="submit-row">
                    <button type="submit" name="apply_filters">Apply Filters</button>
                    <button type="button" onclick="window.location.href='cancel_local_order.php'">Clear Filters</button>
                </div>
            </form>
        </div>
        
        <!-- Results and Cancellation Form -->
        <form method="post" class="cancel-form" onsubmit="return validateCancelForm()">
            <?php if (!$showData): ?>
                <div class="container">
                    <p class="center">Please use the filters above to search for orders to cancel.</p>
                    <p class="center">For better performance, provide at least one search criterion.</p>
                </div>
            <?php elseif ($result && $result->num_rows > 0): ?>
                <div class="container">
                    <h3>Order Data (<?php echo $totalRecords; ?> records found, showing page <?php echo $page; ?> of <?php echo $totalPages; ?>)</h3>
                    
                    <!-- Add pagination controls at the top -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($totalPages > 5): ?>
                            <span>...</span>
                            <a href="?page=<?php echo $totalPages; ?>&<?php echo http_build_query($filters); ?>" class="page-link"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Dest.</th>
                                <th>Job Card #</th>
                                <th>Design</th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Input Date</th>
                                <th>Delivery</th>
                                <th>JPCs</th>
                                <th>ZPCs</th>
                                <th>SPCs</th>
                                <th>CPCs</th>
                                <th>Cancel Qty</th>
                                <th>Select</th> <!-- Moved from first to after Cancel Qty -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['ORDEST']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORCORDNO']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORPATT']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORITEM']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORUNIT']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORIDATE']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORDEL']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORJPC']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORZPC']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORSPC']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORCPC']); ?></td>
                                    <td>
                                        <input type="number" name="cancel_qty[<?php echo $row['ID']; ?>]" id="cancel-qty-<?php echo $row['ID']; ?>" min="1" max="<?php echo $row['ORZPC']; ?>" class="cancel-qty" disabled>
                                    </td>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="cancel[<?php echo $row['ID']; ?>]" id="cancel-<?php echo $row['ID']; ?>" value="<?php echo $row['ID']; ?>" onchange="updateCancelQty(this, '<?php echo $row['ORZPC']; ?>')">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <!-- Add pagination controls at the bottom as well -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&<?php echo http_build_query($filters); ?>" class="page-link">Previous</a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&<?php echo http_build_query($filters); ?>" class="page-link">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="center">
                        <button type="submit" name="cancel_order">Process Cancellations</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="container">
                    <p class="center">No orders found matching the criteria or with available quantities to cancel.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        // Set the cancel quantity when checkbox is checked
        function updateCancelQty(checkbox, maxQty) {
            var id = checkbox.value;
            var qtyInput = document.getElementById('cancel-qty-' + id);
            
            if (checkbox.checked) {
                qtyInput.disabled = false;
                qtyInput.value = maxQty;
            } else {
                qtyInput.disabled = true;
                qtyInput.value = '';
            }
        }
        
        // Validate the form before submission
        function validateCancelForm() {
            var checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one order to cancel.');
                return false;
            }
            
            // Check that all selected rows have valid quantities
            var valid = true;
            checkboxes.forEach(function(checkbox) {
                var id = checkbox.value;
                var qtyInput = document.getElementById('cancel-qty-' + id);
                
                if (!qtyInput.value || isNaN(qtyInput.value) || Number(qtyInput.value) <= 0) {
                    valid = false;
                    alert('Please enter a valid quantity for all selected orders.');
                    return;
                }
            });
            
            if (!valid) return false;
            
            return confirm('Are you sure you want to cancel the selected orders?');
        }
    </script>
</body>
</html>