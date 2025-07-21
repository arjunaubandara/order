<?php
// filepath: h:\Current\Order\decal_master.php
// Database connection parameters
include("database_connection.php");

// Initialize variables
$id = '';
$dmtpatt = '';
$dmgosu = '';
$mode = isset($_POST['mode']) ? $_POST['mode'] : 'add';
$message = '';
$messageType = '';

// Pagination settings
$recordsPerPage = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Get form data
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $dmtpatt = isset($_POST['dmtpatt']) ? strtoupper(trim($_POST['dmtpatt'])) : '';
    $dmgosu = isset($_POST['dmgosu']) ? strtoupper(trim($_POST['dmgosu'])) : '';
    
    // Validate input
    $errors = array();
    
    if (empty($dmtpatt)) {
        $errors[] = "Design is required";
    } elseif (strlen($dmtpatt) > 8) {
        $errors[] = "Design must be 8 characters or less";
    }
    
    if (empty($dmgosu)) {
        $errors[] = "Curve number is required";
    } elseif (strlen($dmgosu) > 3) {
        $errors[] = "Curve number must be 3 characters or less";
    }
    
    // Process the data if no validation errors
    if (empty($errors)) {
        if ($action === 'add') {
            // Check if record already exists
            $checkQuery = "SELECT COUNT(*) as count FROM tbl_decal_master WHERE DMTPATT = ? AND DMGOSU = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ss", $dmtpatt, $dmgosu);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = "Record already exists with Pattern: $dmtpatt and Group: $dmgosu";
                $messageType = "warning";
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO tbl_decal_master (DMTPATT, DMGOSU) VALUES (?, ?)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ss", $dmtpatt, $dmgosu);
                
                if ($insertStmt->execute()) {
                    $message = "New record added successfully";
                    $messageType = "success";
                    // Retain the design value, only clear curve number
                    // $dmtpatt remains unchanged 
                    $dmgosu = '';
                } else {
                    $message = "Error adding record: " . $conn->error;
                    $messageType = "danger";
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        } elseif ($action === 'delete' && !empty($id)) {
            // Delete record - simplified to just delete without update
            $deleteQuery = "DELETE FROM tbl_decal_master WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                $message = "Record deleted successfully";
                $messageType = "success";
                // Clear form fields after deletion
                $id = '';
                $dmtpatt = '';
                $dmgosu = '';
                $mode = 'add';
            } else {
                $message = "Error deleting record: " . $conn->error;
                $messageType = "danger";
            }
            $deleteStmt->close();
        }
    } else {
        // Display validation errors
        $message = "Please fix the following errors:<ul>";
        foreach ($errors as $error) {
            $message .= "<li>$error</li>";
        }
        $message .= "</ul>";
        $messageType = "danger";
    }
}

// Handle edit request (when clicking "Edit" link)
if (isset($_GET['edit']) && !empty($_GET['id'])) {
    $id = $_GET['id'];
    $editQuery = "SELECT * FROM tbl_decal_master WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->bind_param("i", $id);
    $editStmt->execute();
    $result = $editStmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $dmtpatt = $row['DMTPATT'];
        $dmgosu = $row['DMGOSU'];
        $mode = 'edit';
    }
    $editStmt->close();
}

// Search parameters
$searchPattern = isset($_GET['search_pattern']) ? $_GET['search_pattern'] : '';
$searchGroup = isset($_GET['search_group']) ? $_GET['search_group'] : '';

// Build WHERE clause for search
$whereClause = "1";
$searchParams = array();

if (!empty($searchPattern)) {
    $whereClause .= " AND DMTPATT LIKE ?";
    $searchParams[] = "%{$searchPattern}%";
}

if (!empty($searchGroup)) {
    $whereClause .= " AND DMGOSU LIKE ?";
    $searchParams[] = "%{$searchGroup}%";
}

// Count total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM tbl_decal_master WHERE {$whereClause}";
$countStmt = $conn->prepare($countQuery);

// Bind search parameters if any
if (!empty($searchParams)) {
    // Create parameter types string - all strings for LIKE conditions
    $paramTypes = str_repeat('s', count($searchParams));
    
    // Create a reference-based parameter array for mysqli binding
    $bindParams = array($paramTypes); // First element is the types string
    
    // Create a separate array of references
    $refParams = array();
    foreach ($searchParams as $key => $value) {
        $refParams[$key] = $value;
        $bindParams[] = &$refParams[$key]; // Add reference to the array
    }
    
    // Now call bind_param with the reference array
    call_user_func_array(array($countStmt, 'bind_param'), $bindParams);
}


// Execute the query and check for errors
if (!$countStmt->execute()) {
    $error = $conn->error;
    echo "Error executing count query: " . $error;
    // Set safe default values
    $totalRecords = 0;
    $totalPages = 0;
} else {
    $countResult = $countStmt->get_result();
    if ($countResult) {
        $totalRow = $countResult->fetch_assoc();
        $totalRecords = $totalRow['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);
    } else {
        // Handle case where get_result() failed
        $totalRecords = 0;
        $totalPages = 0;
    }
}

// Get records with pagination
$recordsQuery = "SELECT * FROM tbl_decal_master WHERE {$whereClause} ORDER BY DMTPATT, DMGOSU LIMIT ? OFFSET ?";
$recordsStmt = $conn->prepare($recordsQuery);

// Bind search parameters plus pagination parameters
if (!empty($searchParams)) {
    // Create parameter types string - all strings for search + two integers for pagination
    $paramTypes = str_repeat('s', count($searchParams)) . 'ii';
    
    // Create a reference-based parameter array
    $bindParams = array($paramTypes);
    
    // Add search parameters by reference
    $refParams = array();
    foreach ($searchParams as $key => $value) {
        $refParams[$key] = $value;
        $bindParams[] = &$refParams[$key];
    }
    
    // Add pagination parameters by reference
    $refRecordsPerPage = $recordsPerPage;
    $refOffset = $offset;
    $bindParams[] = &$refRecordsPerPage;
    $bindParams[] = &$refOffset;
    
    call_user_func_array(array($recordsStmt, 'bind_param'), $bindParams);
} else {
    $recordsStmt->bind_param("ii", $recordsPerPage, $offset);
}

if (!$recordsStmt->execute()) {
    $error = $conn->error;
    echo "Error executing records query: " . $error;
    $recordsResult = null;
} else {
    $recordsResult = $recordsStmt->get_result();
}

// Close database connection after fetching results
$countStmt->close();
$recordsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decal Master Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Courier New', monospace;
            background-color: #000;
            color: #0f0;
            padding: 20px;
        }
        .container {
            background-color: #000;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,255,0,0.3);
            margin-top: 20px;
            border: 1px solid #0f0;
        }
        h1, h3 {
            color: #0f0;
            margin-bottom: 20px;
        }
        .form-section {
            background-color: #001100;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #0f0;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table {
            color: #0f0;
            background-color: #000;
        }
        .table th {
            background-color: #001100;
            color: #0f0;
            border-color: #0f0;
        }
        .table td {
            border-color: #0f0;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .search-section {
            margin-bottom: 20px;
            background-color: #001100;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #0f0;
        }
        .form-control {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
        }
        .form-control:focus {
            background-color: #000;
            color: #0f0;
            border-color: #0f0;
            box-shadow: 0 0 0 0.2rem rgba(0, 255, 0, 0.25);
        }
        .btn-primary, .btn-success, .btn-danger, .btn-secondary {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
        }
        .btn-primary:hover, .btn-success:hover, .btn-danger:hover, .btn-secondary:hover {
            background-color: #0f0;
            color: #000;
        }
        .alert {
            background-color: #001100;
            color: #0f0;
            border-color: #0f0;
        }
        .alert-danger {
            background-color: #110000;
            color: #f00;
            border-color: #f00;
        }
        .alert-warning {
            background-color: #111100;
            color: #ff0;
            border-color: #ff0;
        }
        .pagination {
            margin-top: 20px;
        }
        .page-link {
            background-color: #000;
            color: #0f0;
            border-color: #0f0;
        }
        .page-link:hover {
            background-color: #0f0;
            color: #000;
        }
        .page-item.active .page-link {
            background-color: #0f0;
            color: #000;
            border-color: #0f0;
        }
        label {
            color: #0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">Decal Master Management</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h3><?php echo ($mode === 'edit') ? 'Delete Record' : 'Add New Record'; ?></h3>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="dmtpatt">Design:</label>
                        <input type="text" class="form-control" id="dmtpatt" name="dmtpatt" 
                               maxlength="8" value="<?php echo htmlspecialchars($dmtpatt); ?>" 
                               placeholder="Enter pattern (max 8 chars)" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="dmgosu">Curve #:</label>
                        <input type="text" class="form-control" id="dmgosu" name="dmgosu" 
                               maxlength="3" value="<?php echo htmlspecialchars($dmgosu); ?>" 
                               placeholder="Enter Curve number (max 3 chars)" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <?php if ($mode === 'edit'): ?>
                        <button type="submit" name="action" value="delete" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to delete this record?');">Delete Record</button>
                        <a href="decal_master.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="action" value="add" class="btn btn-success">Add Record</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="search-section">
            <h3>Search Records</h3>
            <form method="get" action="">
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <input type="text" class="form-control" name="search_pattern" 
                               value="<?php echo htmlspecialchars($searchPattern); ?>" 
                               placeholder="Search by pattern">
                    </div>
                    <div class="form-group col-md-5">
                        <input type="text" class="form-control" name="search_group" 
                               value="<?php echo htmlspecialchars($searchGroup); ?>" 
                               placeholder="Search by group number">
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Search</button>
                    </div>
                </div>
                <?php if (!empty($searchPattern) || !empty($searchGroup)): ?>
                    <div class="form-group">
                        <a href="decal_master.php" class="btn btn-outline-secondary">Clear Search</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive">
            <h3>Records (<?php echo $totalRecords; ?> total)</h3>
            <?php if ($recordsResult && $recordsResult->num_rows > 0): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Design</th>
                            <th>Curve #</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recordsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['DMTPATT']); ?></td>
                                <td><?php echo htmlspecialchars($row['DMGOSU']); ?></td>
                                <td class="action-buttons">
                                    <a href="?edit=true&id=<?php echo $row['id']; ?>&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-primary">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination controls -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Calculate range of page numbers to display
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Always show at least 5 pages if available
                            if ($endPage - $startPage + 1 < 5) {
                                if ($startPage == 1) {
                                    $endPage = min($totalPages, $startPage + 4);
                                } else {
                                    $startPage = max(1, $endPage - 4);
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&search_pattern=<?php echo urlencode($searchPattern); ?>&search_group=<?php echo urlencode($searchGroup); ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">No records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-convert input to uppercase
        document.getElementById('dmtpatt').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('dmgosu').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>