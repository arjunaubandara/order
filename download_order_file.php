<?php
// Database connection parameters
include("database_connection.php");

date_default_timezone_set('Asia/Colombo'); // Set the timezone

// Check if the download request is made
if (isset($_GET['download'])) {
    // Updated query to combine data from both tables
    $sql = "
        (SELECT 
            NULL AS ORMRKT,
            ORCUST,
            ORDEST,
            ORORDNO,
            ORCASENO,
            ORPATT,
            ORITEM,
            ORUNIT,
            ORIDATE,
            ORJDATE,
            ORDEL,
            NULL AS ORSDATE,         -- Column not in table
            ORUDATE,
            ORCORDNO,
            ORBIKO,
            ORHINMEI,
            ORPNAME,
            ORSETC,
            NULL AS ORLINC,          -- Column not in table
            ORHINSHU,
            ORJPC,
            ORZPC,
            ORSPC,
            ORCPC,
            NULL AS ORSKPC,          -- Column not in table
            NULL AS ORKNPC,          -- Column not in table
            ORUPRICE,
            ORAMOUNT,
            NULL AS ORUCOST,         -- Column not in table
            NULL AS ORCOST,          -- Column not in table
            NULL AS ORURINE,         -- Column not in table
            NULL AS ORGENKA,         -- Column not in table
            NULL AS ORJ1GEN,         -- Column not in table
            NULL AS ORJ2GEN,         -- Column not in table
            NULL AS ORYOBIPR1,       -- Column not in table
            NULL AS ORYOBIPR2,       -- Column not in table
            NULL AS ORJOBC,          -- Column not in table
            NULL AS ORPRUNIT,        -- Column not in table
            ORCTNNO,
            ORJYURYO,
            ORCTNSIZ,
            ORAT91,
            NULL AS ORYOBI1,         -- Column not in table
            NULL AS ORYOBI2,         -- Column not in table
            NULL AS ORSFIN,          -- Column not in table
            NULL AS ORKFIN,          -- Column not in table
            NULL AS ORPRIC1,         -- Column not in table
            NULL AS ORPRIC2,         -- Column not in table
            ORNYDATE,
            NULL AS ORSALC,          -- Column not in table
            ORMRKT AS ORMRKT2,       -- Column not in table
            ORDEST_CODE AS ORDEST2,  -- Column not in table
            ORSHOH,
            ORKOBN,
            ORPKG,
            ORSTYL,
            ORUTINO,
            ORIRI,
            ORKOZUN,
            ORNJYURYO,
            ORCSMK1,
            ORCSMK2,
            ORCSMK3,
            ORCSMK4,
            ORCSMK5,
            ORCSMK6,
            ORHSIZE1,
            ORHSIZE2,
            ORHSIZE3,
            ORBARCD1,
            ORLBLPOS1,
            ORLBLPOS2,
            ORLBLPOS3,
            ORPONAME,
            NULL AS ORKANRYO,        -- Column not in table
            NULL AS ORSLIPC,         -- Column not in table
            NULL AS ORCTNC,          -- Column not in table
            NULL AS FILLER          -- Column not in table

        FROM 
            tbl_nhq_as400_data_match WHERE ORCPC = 0)
        
        UNION ALL
        
        (SELECT 
            ORMRKT,
            ORCUST,
            ORDEST,
            ORORDNO,
            ORCASENO,
            ORPATT,
            ORITEM,
            ORUNIT,
            ORIDATE,
            ORJDATE,
            ORDEL,
            ORSDATE,
            ORUDATE,
            ORCORDNO,
            ORBIKO,
            ORHINMEI,
            ORPNAME,
            ORSETC,
            ORLINC,
            ORHINSHU,
            ORJPC,
            ORZPC,
            ORSPC,
            ORCPC,
            ORSKPC,
            ORKNPC,
            ORUPRICE,
            ORAMOUNT,
            ORUCOST,
            ORCOST,
            ORURINE,
            ORGENKA,
            ORJ1GEN,
            ORJ2GEN,
            ORYOBIPR1,
            ORYOBIPR2,
            ORJOBC,
            ORPRUNIT,
            ORCTNNO,
            ORJYURYO,
            ORCTNSIZ,
            ORAT91,
            ORYOBI1,
            ORYOBI2,
            ORSFIN,
            ORKFIN,
            ORPRIC1,
            ORPRIC2,
            ORNYDATE,
            ORSALC,
            ORMRKT2,
            ORDEST2,
            ORSHOH,
            ORKOBN,
            ORPKG,
            ORSTYL,
            ORUTINO,
            ORIRI,
            ORKOZUN,
            ORNJYURYO,
            ORCSMK1,
            ORCSMK2,
            ORCSMK3,
            ORCSMK4,
            ORCSMK5,
            ORCSMK6,
            ORHSIZE1,
            ORHSIZE2,
            ORHSIZE3,
            ORBARCD1,
            ORLBLPOS1,
            ORLBLPOS2,
            ORLBLPOS3,
            ORPONAME,
            ORKANRYO,
            ORSLIPC,
            ORCTNC,
            FILLER
        FROM 
            tbl_local_order_file)
    ";
    
    $result = $conn->query($sql);

    // Check if there are records
    if ($result && $result->num_rows > 0) {
        // Set the header to download the file as a CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ORDFLDDS_' . date('Ymd') . '.csv"');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Set CSV column headings based on the required order
        $columns = array(
            'ORMRKT', 'ORCUST', 'ORDEST', 'ORORDNO', 'ORCASENO', 'ORPATT', 'ORITEM', 
            'ORUNIT', 'ORIDATE', 'ORJDATE', 'ORDEL', 'ORSDATE', 'ORUDATE', 'ORCORDNO', 
            'ORBIKO', 'ORHINMEI', 'ORPNAME', 'ORSETC', 'ORLINC', 'ORHINSHU', 'ORJPC', 
            'ORZPC', 'ORSPC', 'ORCPC', 'ORSKPC', 'ORKNPC', 'ORUPRICE', 'ORAMOUNT', 
            'ORUCOST', 'ORCOST', 'ORURINE', 'ORGENKA', 'ORJ1GEN', 'ORJ2GEN', 'ORYOBIPR1', 
            'ORYOBIPR2', 'ORJOBC', 'ORPRUNIT', 'ORCTNNO', 'ORJYURYO', 'ORCTNSIZ', 'ORAT91', 
            'ORYOBI1', 'ORYOBI2', 'ORSFIN', 'ORKFIN', 'ORPRIC1', 'ORPRIC2', 'ORNYDATE', 
            'ORSALC', 'ORMRKT2', 'ORDEST2', 'ORSHOH', 'ORKOBN', 'ORPKG', 'ORSTYL', 'ORUTINO', 
            'ORIRI', 'ORKOZUN', 'ORNJYURYO', 'ORCSMK1', 'ORCSMK2', 'ORCSMK3', 'ORCSMK4', 
            'ORCSMK5', 'ORCSMK6', 'ORHSIZE1', 'ORHSIZE2', 'ORHSIZE3', 'ORBARCD1', 'ORLBLPOS1', 
            'ORLBLPOS2', 'ORLBLPOS3', 'ORPONAME', 'ORKANRYO', 'ORSLIPC', 'ORCTNC', 'FILLER'
             // Optional: include source table information
        );

        // Write the header row to the CSV
        fputcsv($output, $columns);

        // Fetch rows from the database and write them to the CSV
        while ($row = $result->fetch_assoc()) {
            // Map each row to the correct columns, including NULL values for missing columns
            $csvRow = array_map(function($col) use ($row) {
                return isset($row[$col]) ? $row[$col] : NULL;
            }, $columns);
            fputcsv($output, $csvRow); // Write row to CSV
        }

        // Close output stream
        fclose($output);
        exit; // Stop script execution after output
    } else {
        echo "No records found in the tables.";
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Combined Order File</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .btn-container {
            display: flex;
            margin-top: 20px;
        }
        .btn {
            display: block;
            padding: 15px;
            margin: 10px;
            text-align: center;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-download {
            background-color: #4CAF50;
            flex: 1;
        }
        .btn-view {
            background-color: #2196F3;
            flex: 1;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>NLPL Order Data Management</h1>
        
        <div class="info">
            <p>This tool allows you to download order data from into a single CSV file.</p>
            <p>The download includes records from:</p>
            <ul>
                <li>Export Order Data</li>
                <li>Local order data</li>
            </ul>
        </div>
        
        <div class="btn-container">
            <a href="?download=true" class="btn btn-download">Download Combined Order File</a>
            <a href="view_order_file.php" class="btn btn-view">View Order Data Online</a>
        </div>
    </div>
</body>
</html>
