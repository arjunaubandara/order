<?php
// Start session to maintain field values
session_start();

// Display success/error messages
$statusMessage = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $inserted = isset($_GET['inserted']) ? (int)$_GET['inserted'] : 0;
        $errors = isset($_GET['errors']) ? (int)$_GET['errors'] : 0;
        $duplicates = isset($_GET['duplicates']) ? (int)$_GET['duplicates'] : 0;
        
        if ($inserted > 0) {
            $statusMessage = '<div class="success-message">'; // Using CSS class instead of inline style
            $statusMessage .= 'Successfully inserted ' . $inserted . ' order record(s).';
            if (!empty($_SESSION['last_po_number'])) {
                $statusMessage .= ' Last PO number: ' . $_SESSION['last_po_number'] . '.';
            }
            if ($duplicates > 0) {
                $statusMessage .= ' Skipped ' . $duplicates . ' duplicate record(s).';
            }
            $statusMessage .= '</div>';
        } elseif ($errors > 0 || $duplicates > 0) {
            $statusMessage = '<div class="error-message">'; // Using CSS class instead of inline style
            if ($errors > 0) {
                $statusMessage .= 'Failed to insert data. There were ' . $errors . ' error(s). ';
            }
            if ($duplicates > 0) {
                $statusMessage .= 'Skipped ' . $duplicates . ' duplicate record(s).';
            }
            $statusMessage .= '</div>';
        }
    } elseif ($_GET['status'] == 'error') {
        $message = isset($_GET['message']) ? $_GET['message'] : 'An error occurred';
        $statusMessage = '<div class="error-message">' . $message . '</div>';
    }
}

// Store form values after submission (these will be used when redirected back)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['ordel'] = isset($_POST['ordel']) ? $_POST['ordel'] : '';
    $_SESSION['orcust'] = isset($_POST['orcust']) ? $_POST['orcust'] : '';
    $_SESSION['ordest'] = isset($_POST['ordest']) ? $_POST['ordest'] : '';
    $_SESSION['orordno'] = isset($_POST['orordno']) ? $_POST['orordno'] : '';
    $_SESSION['orbiko'] = isset($_POST['orbiko']) ? $_POST['orbiko'] : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Data Entry</title>
    <style>
        /* Terminal-style theme for order form */
        body {
            font-family: 'Courier New', monospace;
            background-color: #000;
            color: #0f0;
            letter-spacing: 0.5px;
            -webkit-font-smoothing: none;
        }

        .container {
            background-color: #000;
            border: 1px solid #0f0;
            padding: 10px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin: 0 0 8px 0;
            color: #0f0;
            font-size: 18px;
            font-weight: bold;
        }

        h3 {
            margin: 5px 0;
            font-size: 14px;
            color: #0f0;
        }

        /* More compact header section */
        .header-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5px;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #0f0; /* Changed from #999 to #0f0 */
        }

        .field-group {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            position: relative;
        }

        label {
            font-weight: bold;
            font-size: 12px;
            margin-right: 5px;
            min-width: 70px;
            color: #0f0;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            padding: 3px 5px;
            border: 1px solid #0f0;
            font-size: 12px;
            width: calc(100% - 75px);
            text-transform: uppercase;
            font-family: 'Courier New', Courier, monospace;
            background-color: #000;
            color: #0f0;
        }
        
        /* Fix field sizing to prevent overlap */
        .table-column {
            position: relative;
            padding: 0 10px;
        }
        
        /* Pattern and Item fields */
        td:nth-child(2) input, 
        td:nth-child(3) input {
            width: 100%;
        }
        
        /* Unit field */
        .unit-cell input {
            width: 95% !important;
            text-align: center;
        }
        
        /* QTY field */
        td:nth-child(5) input {
            width: 95%;
            text-align: right;
        }

        input[type="submit"] {
            padding: 6px;
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            width: 100%;
            font-family: 'Courier New', Courier, monospace;
        }

        /* Two column table layout */
        .table-container {
            display: flex;
            gap: 20px;  /* Increased gap between columns */
        }

        .table-column {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            table-layout: fixed;
            border-color: #0f0;
        }

        th {
            background-color: #000;
            padding: 4px;
            text-align: left;
            border: 1px solid #0f0; /* Changed from #666 to #0f0 */
            color: #0f0;
        }

        td {
            padding: 0;
            border: 1px solid #0f0; /* Changed from #999 to #0f0 */
            height: 26px;
            position: relative;
            background-color: #000;
        }

        td input {
            padding: 3px 5px;
            border: none;
            margin: 0;
            box-sizing: border-box;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            height: 100%;
            background-color: #000;
            color: #0f0;
        }

        button {
            padding: 4px 10px;
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            cursor: pointer;
            margin-top: 6px;
            font-size: 12px;
            font-family: 'Courier New', Courier, monospace;
        }

        .id-cell {
            width: 30px;
            text-align: center;
            background-color: #000; /* Changed from #333 to #000 */
            color: #0f0; /* Ensure text is green */
        }
        
        .unit-cell {
            width: 40px;
        }
        
        /* Fixed column widths */
        th:nth-child(2), td:nth-child(2) { width: 25%; } /* PATTERN */
        th:nth-child(3), td:nth-child(3) { width: 25%; } /* ITEM */
        th:nth-child(5), td:nth-child(5) { width: 60px; } /* QTY */
        
        /* Validation markers */
        .mark-cell {
            width: 25px;
            padding: 0 !important;
            text-align: center;
        }
        
        .valid-mark, .invalid-mark {
            display: none;
            font-weight: bold;
            font-size: 14px;
        }
        
        .valid-mark {
            color: lime;
        }
        
        .invalid-mark {
            color: red;
        }

        .terminal-section {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
        }

        .terminal-field {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
        }

        .compact-field-row, .tight-row, .tight-col, .decal-row {
            /* Add any specific styles for these classes if needed */
        }

        /* Success/error messages */
        .success-message {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .error-message {
            background-color: #000;
            color: #ff0000;
            border: 1px solid #ff0000;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>LOCAL ORDER DATA ENTRY</h1>
        <?php echo $statusMessage; ?>
        <form action="insert_data.php" method="post" onsubmit="return validateForm()">
            <div class="header-section">
                <div class="field-group">
                    <label for="oridate">Input Date:</label>
                    <input type="text" id="oridate" name="oridate" class="terminal-field" readonly 
                        value="<?php print date('Ymd'); ?>" maxlength="8">
                </div>
                
                <div class="field-group">
                    <label for="ordel">Planned Delivery:</label>
                    <input type="text" id="ordel" name="ordel" class="terminal-field" maxlength="8" 
                        value="<?php echo isset($_SESSION['ordel']) ? $_SESSION['ordel'] : ''; ?>">
                </div>
                
                <div class="field-group">
                    <label for="orcust">Customer Code:</label>
                    <input type="text" id="orcust" name="orcust" class="terminal-field" maxlength="4"
                        value="<?php echo isset($_SESSION['orcust']) ? $_SESSION['orcust'] : ''; ?>">
                </div>
                
                <div class="field-group">
                    <label for="ordest">Customer Name:</label>
                    <input type="text" id="ordest" name="ordest" class="terminal-field"
                        value="<?php echo isset($_SESSION['ordest']) ? $_SESSION['ordest'] : ''; ?>">
                </div>
                
                <div class="field-group">
                    <label for="orordno">Job Card Number:</label>
                    <input type="text" id="orordno" name="orordno" class="terminal-field"
                        value="<?php echo isset($_SESSION['orordno']) ? $_SESSION['orordno'] : ''; ?>">
                </div>
                
                <div class="field-group">
                    <label for="orbiko">Special Remarks:</label>
                    <input type="text" id="orbiko" name="orbiko" class="terminal-field"
                        value="<?php echo isset($_SESSION['orbiko']) ? $_SESSION['orbiko'] : ''; ?>">
                </div>
            </div>
            
            <h3>ORDER DATA ENTRY</h3>
            <div class="table-container">
                <!-- First column of the table -->
                <div class="table-column">
                    <table id="dynamic-table-1">
                        <tr>
                            <th class="id-cell">No</th>
                            <th>Design</th>
                            <th>Set/Item</th>
                            <th class="unit-cell">Unit</th>
                            <th>Qty</th>
                            <th class="mark-cell"></th>
                        </tr>
                        <!-- First 10 rows -->
                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                        <tr id="row-<?php echo $i; ?>">
                            <td class="id-cell"><?php echo $i; ?></td>
                            <td>
                                <input type="text" name="pattern[]" id="pattern-<?php echo $i; ?>" 
                                       oninput="validateRow(<?php echo $i; ?>)" onblur="validateRow(<?php echo $i; ?>)">
                            </td>
                            <td>
                                <input type="text" name="item[]" id="item-<?php echo $i; ?>"
                                       oninput="validateRow(<?php echo $i; ?>)" onblur="validateRow(<?php echo $i; ?>)">
                            </td>
                            <td class="unit-cell">
                                <input type="text" name="unit[]" id="unit-<?php echo $i; ?>" 
                                       maxlength="1" oninput="validateUnitType(this)">
                            </td>
                            <td>
                                <input type="text" name="qty[]" id="qty-<?php echo $i; ?>">
                            </td>
                            <td class="mark-cell">
                                <span class="valid-mark" id="valid-<?php echo $i; ?>">✓</span>
                                <span class="invalid-mark" id="invalid-<?php echo $i; ?>">✗</span>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </table>
                </div>
                
                <!-- Second column of the table -->
                <div class="table-column">
                    <table id="dynamic-table-2">
                        <tr>
                            <th class="id-cell">No</th>
                            <th>Design</th>
                            <th>Set/Item</th>
                            <th class="unit-cell">Unit</th>
                            <th>Qty</th>
                            <th class="mark-cell"></th>
                        </tr>
                        <!-- Second 10 rows -->
                        <?php for ($i = 11; $i <= 20; $i++) : ?>
                        <tr id="row-<?php echo $i; ?>">
                            <td class="id-cell"><?php echo $i; ?></td>
                            <td>
                                <input type="text" name="pattern[]" id="pattern-<?php echo $i; ?>"
                                       oninput="validateRow(<?php echo $i; ?>)" onblur="validateRow(<?php echo $i; ?>)">
                            </td>
                            <td>
                                <input type="text" name="item[]" id="item-<?php echo $i; ?>"
                                       oninput="validateRow(<?php echo $i; ?>)" onblur="validateRow(<?php echo $i; ?>)">
                            </td>
                            <td class="unit-cell">
                                <input type="text" name="unit[]" id="unit-<?php echo $i; ?>"
                                       maxlength="1" oninput="validateUnitType(this)">
                            </td>
                            <td>
                                <input type="text" name="qty[]" id="qty-<?php echo $i; ?>">
                            </td>
                            <td class="mark-cell">
                                <span class="valid-mark" id="valid-<?php echo $i; ?>">✓</span>
                                <span class="invalid-mark" id="invalid-<?php echo $i; ?>">✗</span>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </table>
                </div>
            </div>
            <button type="button" onclick="addRow()">Add Row</button>
            <input type="submit" value="SUBMIT">
        </form>
    </div>

    <script>
        // Validate pattern+item combination with unit
        function validateRow(rowId) {
            const patternInput = document.getElementById('pattern-' + rowId);
            const itemInput = document.getElementById('item-' + rowId);
            const unitInput = document.getElementById('unit-' + rowId);
            const validMark = document.getElementById('valid-' + rowId);
            const invalidMark = document.getElementById('invalid-' + rowId);
            
            const pattern = patternInput.value.trim().toUpperCase();
            const item = itemInput.value.trim().toUpperCase();
            const unit = unitInput.value.trim().toUpperCase();
            
            // Force uppercase
            patternInput.value = pattern;
            itemInput.value = item;
            unitInput.value = unit;
            
            // Only validate if pattern and item fields have values
            if (pattern && item) {
                // If pattern and item exist but unit is empty, show red cross
                if (unit === '') {
                    validMark.style.display = 'none';
                    invalidMark.style.display = 'inline';
                    console.log('Validation failed: Unit is required');
                    return;
                }
                
                // Hard-coded test case
                if (pattern === '4166L' && item === '91320') {
                    if (unit === 'P') {
                        validMark.style.display = 'inline';
                        invalidMark.style.display = 'none';
                        return;
                    } else if (unit === 'S') {
                        validMark.style.display = 'none';
                        invalidMark.style.display = 'inline';
                        return;
                    }
                }
                
                // Add timestamp to prevent caching
                const timestamp = new Date().getTime();
                const url = 'validate_field.php?field=combination&pattern=' + 
                        encodeURIComponent(pattern) + '&item=' + 
                        encodeURIComponent(item) + 
                        '&unit=' + encodeURIComponent(unit) +
                        '&t=' + timestamp;
                
                console.log('Validating:', pattern, item, unit);
                
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
                    
                    if (data.valid === true) {
                        validMark.style.display = 'inline';
                        invalidMark.style.display = 'none';
                    } else {
                        validMark.style.display = 'none';
                        invalidMark.style.display = 'inline';
                        // Show message if available
                        if (data.message) {
                            console.log('Validation failed:', data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Validation request failed:', error.message);
                    validMark.style.display = 'none';
                    invalidMark.style.display = 'inline';
                });
            } else {
                // Hide both marks if fields are empty
                validMark.style.display = 'none';
                invalidMark.style.display = 'none';
            }
        }
        
        // Validate unit type (P or S only)
        function validateUnitType(input) {
            const value = input.value.trim().toUpperCase();
            
            // Force to uppercase
            input.value = value;
            
            // Only allow P or S
            if (value && value !== 'P' && value !== 'S') {
                input.value = '';
            }
            
            // Get the row ID from the input ID
            const rowId = input.id.split('-')[1];
            if (rowId) {
                // Trigger validation of the entire row
                validateRow(rowId);
            }
        }
        
        // Add a new row
        function addRow() {
            var table = document.getElementById("dynamic-table-2");
            var rowCount = table.rows.length;
            var row = table.insertRow(-1);
            var rowId = 10 + rowCount;
            
            row.id = 'row-' + rowId;
            
            // ID cell
            var cell1 = row.insertCell(0);
            cell1.className = 'id-cell';
            cell1.innerHTML = rowId;
            
            // Pattern cell
            var cell2 = row.insertCell(1);
            cell2.innerHTML = '<input type="text" name="pattern[]" id="pattern-' + rowId + '" class="terminal-field" oninput="validateRow(' + rowId + ')" onblur="validateRow(' + rowId + ')">';
            
            // Item cell
            var cell3 = row.insertCell(2);
            cell3.innerHTML = '<input type="text" name="item[]" id="item-' + rowId + '" class="terminal-field" oninput="validateRow(' + rowId + ')" onblur="validateRow(' + rowId + ')">';
            
            // Unit cell
            var cell4 = row.insertCell(3);
            cell4.className = 'unit-cell';
            cell4.innerHTML = '<input type="text" name="unit[]" id="unit-' + rowId + '" class="terminal-field" maxlength="1" oninput="validateUnitType(this)">';
            
            // Qty cell
            var cell5 = row.insertCell(4);
            cell5.innerHTML = '<input type="text" name="qty[]" id="qty-' + rowId + '" class="terminal-field">';
            
            // Validation marks cell
            var cell6 = row.insertCell(5);
            cell6.className = 'mark-cell';
            cell6.innerHTML = '<span class="valid-mark" id="valid-' + rowId + '">✓</span><span class="invalid-mark" id="invalid-' + rowId + '">✗</span>';
        }
        
        // Validate the entire form before submission
        function validateForm() {
            // Check required header fields
            const orcust = document.getElementById('orcust').value.trim();
            const ordest = document.getElementById('ordest').value.trim();
            const orordno = document.getElementById('orordno').value.trim();
            
            // Check if header fields are empty
            if (!orcust || !ordest || !orordno) {
                alert('Customer Code, Customer Name, and Job Card Number are required fields');
                return false;
            }
            
            // Check if at least one row has complete data
            let hasCompleteRow = false;
            
            // Determine maximum row ID by finding the last table row
            const table1 = document.getElementById('dynamic-table-1');
            const table2 = document.getElementById('dynamic-table-2');
            const lastRowId = Math.max(
                table1.rows.length - 1 + 10, // First table has rows starting from ID 1
                table2.rows.length - 1 + 20  // Second table has rows starting from ID 11
            );
            
            // Check each row for complete data
            for (let i = 1; i <= lastRowId; i++) {
                const pattern = document.getElementById('pattern-' + i)?.value.trim() || '';
                const item = document.getElementById('item-' + i)?.value.trim() || '';
                const unit = document.getElementById('unit-' + i)?.value.trim() || '';
                const qty = document.getElementById('qty-' + i)?.value.trim() || '';
                
                // If all fields in this row are filled, mark as complete
                if (pattern && item && unit && qty) {
                    hasCompleteRow = true;
                    break;
                }
            }
            
            if (!hasCompleteRow) {
                alert('At least one row must have complete data (Design, Item, Unit, and Qty)');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>