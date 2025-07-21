<?php
// Start session for messaging
session_start();

// Database connection for set_master_break table
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

// Create a second connection for the product master (for validation)
$productConn = new mysqli($servername, $username, $password, "production_data");
if ($productConn->connect_error) {
    die("Product DB Connection failed: " . $productConn->connect_error);
}

// Initialize variables
$pattern = isset($_POST['pattern']) ? strtoupper(trim($_POST['pattern'])) : '';
$setItem = isset($_POST['setItem']) ? strtoupper(trim($_POST['setItem'])) : '';
$mode = isset($_POST['mode']) ? $_POST['mode'] : '1'; // Default to "Load" mode
$message = '';
$components = array();

// Check if our table exists, if not, create it
$tableCheckSql = "SHOW TABLES LIKE 'set_master_break'";
$result = $conn->query($tableCheckSql);

if ($result->num_rows == 0) {
    // Table doesn't exist, create it
    $createTableSql = "CREATE TABLE `set_master_break` (
        `ID` INT(7) NOT NULL AUTO_INCREMENT,
        `Pattern` VARCHAR(15) NULL DEFAULT NULL,
        `setItem` VARCHAR(15) NULL DEFAULT NULL,
        `patt` VARCHAR(15) NOT NULL,
        `Item` VARCHAR(15) NULL DEFAULT NULL,
        `Qty` INT(4) NULL DEFAULT NULL,
        PRIMARY KEY (`ID`),
        INDEX `Item` (`Item`),
        INDEX `patt` (`patt`),
        INDEX `Pattern` (`Pattern`),
        INDEX `setItem` (`setItem`),
        INDEX `Qty` (`Qty`)
    )
    ENGINE=MyISAM
    AUTO_INCREMENT=1";
    
    if (!$conn->query($createTableSql)) {
        $message = "Error creating table: " . $conn->error;
    }
}

// Function to load components based on pattern and/or setItem
function loadComponents($conn, $pattern, $setItem) {
    $components = array();
    $sql = "SELECT * FROM set_master_break WHERE 1=1";
    
    if (!empty($pattern)) {
        $sql .= " AND Pattern = '" . $conn->real_escape_string($pattern) . "'";
    }
    
    if (!empty($setItem)) {
        $sql .= " AND setItem = '" . $conn->real_escape_string($setItem) . "'";
    }
    
    $sql .= " ORDER BY Pattern, setItem, patt, Item";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $components[] = $row;
        }
    }
    
    return $components;
}

// Helper function to validate if pattern/item exists in product master
function validateProductExists($conn, $pattern, $item = '') {
    $sql = "SELECT COUNT(*) as count FROM tbl_product_master WHERE SMPATT = ?";
    $params = array($pattern);
    
    if (!empty($item)) {
        $sql .= " AND SMITEM = ?";
        $params[] = $item;
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    // Fix: Use bind_param correctly for older PHP versions
    if (count($params) == 1) {
        $stmt->bind_param('s', $params[0]);
    } else if (count($params) == 2) {
        $stmt->bind_param('ss', $params[0], $params[1]);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['count'] > 0);
}

// Process form submission if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Always load components for any mode if pattern or setItem provided
    if (!empty($pattern) || !empty($setItem)) {
        $components = loadComponents($conn, $pattern, $setItem);
        
        if (count($components) > 0) {
            $message = "Found " . count($components) . " component(s).";
        } else {
            $message = "No records found for the specified criteria.";
        }
    }
    
    // Mode 2: Edit Data - Save changes
    if ($mode === '2' && isset($_POST['save']) && $_POST['save'] === 'yes') {
        // Get arrays from form
        $ids = isset($_POST['id']) ? $_POST['id'] : array();
        $patterns = isset($_POST['edit_pattern']) ? $_POST['edit_pattern'] : array();
        $setItems = isset($_POST['edit_setItem']) ? $_POST['edit_setItem'] : array();
        $patts = isset($_POST['patt']) ? $_POST['patt'] : array();
        $items = isset($_POST['item']) ? $_POST['item'] : array();
        $qtys = isset($_POST['qty']) ? $_POST['qty'] : array();
        
        $updateCount = 0;
        $errorCount = 0;
        
        for ($i = 0; $i < count($ids); $i++) {
            if (empty($patterns[$i]) || empty($setItems[$i]) || empty($patts[$i]) || empty($items[$i]) || empty($qtys[$i])) {
                $errorCount++;
                continue;
            }
            
            // Validate patterns and items exist in product master
            if (!validateProductExists($productConn, $patts[$i], $items[$i])) {
                $errorCount++;
                continue;
            }
            
            $id = (int)$ids[$i];
            $pattern = strtoupper(trim($patterns[$i]));
            $setItem = strtoupper(trim($setItems[$i]));
            $patt = strtoupper(trim($patts[$i]));
            $item = strtoupper(trim($items[$i]));
            $qty = (int)$qtys[$i];
            
            $updateSql = "UPDATE set_master_break SET
                        Pattern = '" . $conn->real_escape_string($pattern) . "',
                        setItem = '" . $conn->real_escape_string($setItem) . "',
                        patt = '" . $conn->real_escape_string($patt) . "',
                        Item = '" . $conn->real_escape_string($item) . "',
                        Qty = " . $qty . "
                        WHERE ID = " . $id;
                        
            if ($conn->query($updateSql)) {
                $updateCount++;
            } else {
                $errorCount++;
            }
        }
        
        if ($updateCount > 0) {
            $message = "Updated $updateCount record(s) successfully.";
            if ($errorCount > 0) {
                $message .= " $errorCount record(s) had errors and were not updated.";
            }
        } else if ($errorCount > 0) {
            $message = "Error: No records were updated. $errorCount record(s) had validation errors.";
        }
        
        // Reload the data to reflect the changes
        $components = loadComponents($conn, $pattern, $setItem);
    }
    
    // Mode 3: Add New
    else if ($mode === '3' && isset($_POST['save']) && $_POST['save'] === 'yes') {
        // Get arrays from form
        $newPatts = isset($_POST['new_patt']) ? $_POST['new_patt'] : array();
        $newItems = isset($_POST['new_item']) ? $_POST['new_item'] : array();
        $newQtys = isset($_POST['new_qty']) ? $_POST['new_qty'] : array();
        
        $insertCount = 0;
        $errorCount = 0;
        
        for ($i = 0; $i < count($newPatts); $i++) {
            if (empty($newPatts[$i]) || empty($newItems[$i]) || empty($newQtys[$i])) {
                $errorCount++;
                continue;
            }
            
            $newPatt = strtoupper(trim($newPatts[$i]));
            $newItem = strtoupper(trim($newItems[$i]));
            $newQty = (int)$newQtys[$i];
            
            // Validate pattern and item exist in product master
            if (!validateProductExists($productConn, $newPatt, $newItem)) {
                $errorCount++;
                continue;
            }
            
            $insertSql = "INSERT INTO set_master_break 
                        (Pattern, setItem, patt, Item, Qty) 
                        VALUES (
                            '" . $conn->real_escape_string($pattern) . "',
                            '" . $conn->real_escape_string($setItem) . "',
                            '" . $conn->real_escape_string($newPatt) . "',
                            '" . $conn->real_escape_string($newItem) . "',
                            " . $newQty . "
                        )";
                        
            if ($conn->query($insertSql)) {
                $insertCount++;
            } else {
                $errorCount++;
            }
        }
        
        if ($insertCount > 0) {
            $message = "Added $insertCount new component(s) successfully.";
            if ($errorCount > 0) {
                $message .= " $errorCount component(s) had errors and were not added.";
            }
        } else if ($errorCount > 0) {
            $message = "Error: No components were added. $errorCount component(s) had validation errors.";
        }
        
        // Reload the data to include new records
        $components = loadComponents($conn, $pattern, $setItem);
    }
    
    // Mode 4: Delete
    else if ($mode === '4' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        if (!empty($pattern) && !empty($setItem)) {
            $deleteSql = "DELETE FROM set_master_break 
                        WHERE Pattern = '" . $conn->real_escape_string($pattern) . "' 
                        AND setItem = '" . $conn->real_escape_string($setItem) . "'";
            
            if ($conn->query($deleteSql)) {
                $affectedRows = $conn->affected_rows;
                $message = "Deleted set breakdown successfully. Removed $affectedRows component(s).";
                // Clear components array since they've been deleted
                $components = array();
            } else {
                $message = "Error deleting records: " . $conn->error;
            }
        } else {
            $message = "Both Pattern and Set Item are required for deletion.";
        }
    }
}

// Close connection
$productConn->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Set Breakdown Master Maintenance</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
        }
        button.secondary {
            background-color: #2196F3;
        }
        button.danger {
            background-color: #f44336;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message.success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .message.error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .message.info {
            background-color: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .add-row-button {
            margin-top: 10px;
        }
        .hidden {
            display: none;
        }
        /* Validation markers */
        .validation-mark {
            display: none;
            margin-left: 5px;
            font-weight: bold;
        }
        .valid-mark {
            color: #4CAF50;
        }
        .invalid-mark {
            color: #f44336;
        }
        .input-with-validation {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Set Breakdown Master Maintenance</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="searchForm">
            <div class="search-form">
                <div class="form-group">
                    <label for="pattern">Design #:</label>
                    <input type="text" id="pattern" name="pattern" value="<?php echo htmlspecialchars($pattern); ?>" maxlength="15">
                </div>
                
                <div class="form-group">
                    <label for="setItem">Set #:</label>
                    <input type="text" id="setItem" name="setItem" value="<?php echo htmlspecialchars($setItem); ?>" maxlength="15">
                </div>
                
                <div class="form-group">
                    <label>Operation Mode:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="mode1" name="mode" value="1" <?php echo $mode === '1' ? 'checked' : ''; ?>>
                            <label for="mode1">Load Set</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="mode2" name="mode" value="2" <?php echo $mode === '2' ? 'checked' : ''; ?>>
                            <label for="mode2">Edit Set</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="mode3" name="mode" value="3" <?php echo $mode === '3' ? 'checked' : ''; ?>>
                            <label for="mode3">Add New Set</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="mode4" name="mode" value="4" <?php echo $mode === '4' ? 'checked' : ''; ?>>
                            <label for="mode4">Delete Set</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit">Submit</button>
                </div>
            </div>
            
            <?php if (count($components) > 0 && $mode === '2'): ?>
                <!-- Edit Mode -->
                <input type="hidden" name="save" value="yes">
                <table>
                    <thead>
                        <tr>
                            <th>Design #</th>
                            <th>Set #</th>
                            <th>Design</th>
                            <th>Item</th>
                            <th>Qty (Pcs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $index => $component): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="id[]" value="<?php echo $component['ID']; ?>">
                                    <input type="text" name="edit_pattern[]" value="<?php echo htmlspecialchars($component['Pattern']); ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="edit_setItem[]" value="<?php echo htmlspecialchars($component['setItem']); ?>" required>
                                </td>
                                <td>
                                    <div class="input-with-validation">
                                        <input type="text" id="patt-<?php echo $index; ?>" name="patt[]" 
                                               value="<?php echo htmlspecialchars($component['patt']); ?>" 
                                               onblur="validatePatternItem(this, '<?php echo $index; ?>', 'patt')" required>
                                        <span id="valid-patt-<?php echo $index; ?>" class="validation-mark valid-mark">✓</span>
                                        <span id="invalid-patt-<?php echo $index; ?>" class="validation-mark invalid-mark">✗</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-with-validation">
                                        <input type="text" id="item-<?php echo $index; ?>" name="item[]" 
                                               value="<?php echo htmlspecialchars($component['Item']); ?>" 
                                               onblur="validatePatternItem(this, '<?php echo $index; ?>', 'item')" required>
                                        <span id="valid-item-<?php echo $index; ?>" class="validation-mark valid-mark">✓</span>
                                        <span id="invalid-item-<?php echo $index; ?>" class="validation-mark invalid-mark">✗</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" value="<?php echo $component['Qty']; ?>" min="1" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="action-buttons">
                    <button type="submit" class="secondary">Save Changes</button>
                    <button type="button" onclick="resetForm()">Cancel</button>
                </div>
            
            <?php elseif (count($components) > 0 && $mode === '1'): ?>
                <!-- View Mode -->
                <table>
                    <thead>
                        <tr>
                            <th>Design #</th>
                            <th>Set #</th>
                            <th>Design</th>
                            <th>Item</th>
                            <th>Qty (Pcs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $component): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($component['Pattern']); ?></td>
                                <td><?php echo htmlspecialchars($component['setItem']); ?></td>
                                <td><?php echo htmlspecialchars($component['patt']); ?></td>
                                <td><?php echo htmlspecialchars($component['Item']); ?></td>
                                <td><?php echo $component['Qty']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
            <?php elseif ($mode === '3' && !empty($pattern) && !empty($setItem)): ?>
                <!-- Add New Mode -->
                <input type="hidden" name="save" value="yes">
                
                <h3>Current Components</h3>
                <?php if (count($components) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Design #</th>
                                <th>Set #</th>
                                <th>Design</th>
                                <th>Item</th>
                                <th>Qty (Pcs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($components as $component): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($component['Pattern']); ?></td>
                                    <td><?php echo htmlspecialchars($component['setItem']); ?></td>
                                    <td><?php echo htmlspecialchars($component['patt']); ?></td>
                                    <td><?php echo htmlspecialchars($component['Item']); ?></td>
                                    <td><?php echo $component['Qty']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No existing Set found for this set.</p>
                <?php endif; ?>
                
                <h3>Add New Set</h3>
                <table id="newComponentsTable">
                    <thead>
                        <tr>
                            <th>Design</th>
                            <th>Item</th>
                            <th>Qty (Pcs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="input-with-validation">
                                    <input type="text" id="new-patt-0" name="new_patt[]" 
                                           value="<?php echo htmlspecialchars($pattern); ?>" 
                                           onblur="validatePatternItem(this, 'new-0', 'patt')" required>
                                    <span id="valid-patt-new-0" class="validation-mark valid-mark">✓</span>
                                    <span id="invalid-patt-new-0" class="validation-mark invalid-mark">✗</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-with-validation">
                                    <input type="text" id="new-item-0" name="new_item[]" 
                                           onblur="validatePatternItem(this, 'new-0', 'item')" required>
                                    <span id="valid-item-new-0" class="validation-mark valid-mark">✓</span>
                                    <span id="invalid-item-new-0" class="validation-mark invalid-mark">✗</span>
                                </div>
                            </td>
                            <td><input type="number" name="new_qty[]" value="1" min="1" required></td>
                        </tr>
                    </tbody>
                </table>
                
                <button type="button" class="add-row-button" onclick="addNewComponentRow()">Add Another Component</button>
                
                <div class="action-buttons">
                    <button type="submit" class="secondary">Save New Components</button>
                    <button type="button" onclick="resetForm()">Cancel</button>
                </div>
            
            <?php elseif ($mode === '4' && !empty($pattern) && !empty($setItem)): ?>
                <!-- Delete Mode -->
                <div class="message info">
                    <p>Are you sure you want to delete the breakdown for Design #<strong><?php echo htmlspecialchars($pattern); ?></strong> 
                    and Set # <strong><?php echo htmlspecialchars($setItem); ?></strong>?</p>
                    <p>This will delete <strong>ALL</strong> breakdown for this set.</p>
                </div>
                
                <?php if (count($components) > 0): ?>
                    <h3>Set to be deleted:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Design #</th>
                                <th>Set #</th>
                                <th>Design</th>
                                <th>Item</th>
                                <th>Qty (Pcs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($components as $component): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($component['Pattern']); ?></td>
                                    <td><?php echo htmlspecialchars($component['setItem']); ?></td>
                                    <td><?php echo htmlspecialchars($component['patt']); ?></td>
                                    <td><?php echo htmlspecialchars($component['Item']); ?></td>
                                    <td><?php echo $component['Qty']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <input type="hidden" name="confirm_delete" value="yes">
                
                <div class="action-buttons">
                    <button type="submit" class="danger">Confirm Deletion</button>
                    <button type="button" onclick="resetForm()">Cancel</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        // Utility functions for form handling
        function resetForm() {
            document.getElementById('searchForm').reset();
            document.getElementById('mode1').checked = true;
            document.getElementById('searchForm').submit();
        }
        
        // Counter for new component rows
        let newRowCounter = 1;
        
        function addNewComponentRow() {
            const table = document.getElementById('newComponentsTable').getElementsByTagName('tbody')[0];
            const pattern = document.getElementById('pattern').value;
            const newRow = table.insertRow();
            
            const rowId = 'new-' + newRowCounter;
            
            newRow.innerHTML = `
                <td>
                    <div class="input-with-validation">
                        <input type="text" id="new-patt-${newRowCounter}" name="new_patt[]" 
                               value="${pattern}" onblur="validatePatternItem(this, '${rowId}', 'patt')" required>
                        <span id="valid-patt-${rowId}" class="validation-mark valid-mark">✓</span>
                        <span id="invalid-patt-${rowId}" class="validation-mark invalid-mark">✗</span>
                    </div>
                </td>
                <td>
                    <div class="input-with-validation">
                        <input type="text" id="new-item-${newRowCounter}" name="new_item[]" 
                               onblur="validatePatternItem(this, '${rowId}', 'item')" required>
                        <span id="valid-item-${rowId}" class="validation-mark valid-mark">✓</span>
                        <span id="invalid-item-${rowId}" class="validation-mark invalid-mark">✗</span>
                    </div>
                </td>
                <td><input type="number" name="new_qty[]" value="1" min="1" required></td>
            `;
            
            newRowCounter++;
        }
        
        // Combined validation function for both pattern and item
        function validatePatternItem(input, rowId, fieldType) {
            const value = input.value.trim().toUpperCase();
            input.value = value;
            
            if (!value) {
                hideValidationMarks(rowId, fieldType);
                return;
            }
            
            let pattern = '';
            let item = '';
            
            if (fieldType === 'patt') {
                pattern = value;
                // For pattern validation, we don't need item
            } else if (fieldType === 'item') {
                item = value;
                // Get the pattern for this row
                let patternInput;
                if (rowId.startsWith('new-')) {
                    patternInput = document.getElementById('new-patt-' + rowId.split('-')[1]);
                } else {
                    patternInput = document.getElementById('patt-' + rowId);
                }
                pattern = patternInput ? patternInput.value.trim().toUpperCase() : '';
            }
            
            // Call directly to validate_product.php for checking in the production_data database
            validateWithDatabase(pattern, item, rowId, fieldType);
        }
        
        function validateWithDatabase(pattern, item, rowId, fieldType) {
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            let url = 'validate_product.php?';
            
            if (pattern) url += 'pattern=' + encodeURIComponent(pattern);
            if (item) url += '&item=' + encodeURIComponent(item);
            
            url += '&t=' + timestamp;
            
            // AJAX call with better error handling
            fetch(url, {
                method: 'GET',
                headers: { 'Cache-Control': 'no-cache' }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Validation data received:', data);
                showValidationResult(rowId, fieldType, data.valid);
            })
            .catch(error => {
                console.error('Validation request failed:', error.message);
                showValidationResult(rowId, fieldType, false);
            });
        }
        
        function showValidationResult(rowId, fieldType, isValid) {
            const validMark = document.getElementById('valid-' + fieldType + '-' + rowId);
            const invalidMark = document.getElementById('invalid-' + fieldType + '-' + rowId);
            
            if (isValid) {
                validMark.style.display = 'inline';
                invalidMark.style.display = 'none';
            } else {
                validMark.style.display = 'none';
                invalidMark.style.display = 'inline';
            }
        }
        
        function hideValidationMarks(rowId, fieldType) {
            const validMark = document.getElementById('valid-' + fieldType + '-' + rowId);
            const invalidMark = document.getElementById('invalid-' + fieldType + '-' + rowId);
            
            validMark.style.display = 'none';
            invalidMark.style.display = 'none';
        }
        
        // Initialize validation for existing fields
        document.addEventListener('DOMContentLoaded', function() {
            // Validate any pre-populated fields
            const pattFields = document.querySelectorAll('input[id^="patt-"]');
            pattFields.forEach(field => {
                if (field.value) {
                    validatePatternItem(field, field.id.split('-')[1], 'patt');
                }
            });
            
            const itemFields = document.querySelectorAll('input[id^="item-"]');
            itemFields.forEach(field => {
                if (field.value) {
                    validatePatternItem(field, field.id.split('-')[1], 'item');
                }
            });
            
            // Also check new fields if any
            const newPattFields = document.querySelectorAll('input[id^="new-patt-"]');
            newPattFields.forEach(field => {
                if (field.value) {
                    const rowId = 'new-' + field.id.split('-')[2];
                    validatePatternItem(field, rowId, 'patt');
                }
            });
        });
    </script>
</body>
</html>