<?php

include("database_connection.php");

 error_reporting(0);

// Directory where CSV files will be stored
$uploadDir = __DIR__ . "/csv/";
$uploadFile = $uploadDir . "Production_Order_Detail.csv";

// Check if the directory exists, if not, create it
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        // If the file already exists, rename it with the current date and time
        if (file_exists($uploadFile)) {
            $currentDateTime = date('Y-m-d_H-i-s');
            $backupFile = $uploadDir . "Production_Order_Detail_" . $currentDateTime . ".csv";
            rename($uploadFile, $backupFile);
        }

        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadFile)) {
            echo "File has been uploaded successfully.\n";

            try {
                // Create a new PDO connection
                $pdo = new PDO("mysql:host=localhost;dbname=production_data", "root", "");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Get the latest invoice number
                $stmt = $pdo->query("SELECT MAX(CAST(Invoice_No AS UNSIGNED)) AS last_invoice_no FROM tbl_nhq_production_order_detail");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $last_invoice_no = $result['last_invoice_no'] ? $result['last_invoice_no'] : 0;

                // Calculate the new invoice number
                $new_invoice_no = $last_invoice_no + 1;

                // Open the CSV file for reading
                if (($handle = fopen($uploadFile, "r")) !== FALSE) {
                    // Skip the first row (headers)
                    fgetcsv($handle, 1000, ",");

                    // Prepare the insert statement
                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_nhq_production_order_detail (
                            Invoice_No, Market, Customer_No, Dest, Order_No, Case_No, Patt, Item, Unit, Goods_Code, 
                            Order_Small_No, Order_Date, Requested_Date, Quantity, Ctn_Quantity, Packing_Style_Code, 
                            Ctn_Code, Ibox_Code_, Ctn_Unit_Qty, Ibox_Unit_Qty, Gross_Weght, Net_Weght, Ctn_Width, 
                            Ctn_Depth, Ctn_Height, Direct_Unit_Cost, Gtin, Upc_Posision1, Upc_Posision2, Upc_Posision3, 
                            Cust_Order_No, Patt_Name, Item_Name, Goods_Code01, Patt01, Item01, Upc01, Goods_Code02, 
                            Patt02, Item02, Upc02, Goods_Code03, Patt03, Item03, Upc03, Goods_Code04, Patt04, Item04, 
                            Upc04, Goods_Code05, Patt05, Item05, Upc05, Goods_Code06, Patt06, Item06, Upc06, Goods_Code07, 
                            Patt07, Item07, Upc07, Goods_Code08, Patt08, Item08, Upc08, Goods_Code09, Patt09, Item09, 
                            Upc09, Goods_Code10, Patt10, Item10, Upc10, Goods_Code11, Patt11, Item11, Upc11, Goods_Code12, 
                            Patt12, Item12, Upc12, Goods_Code13, Patt13, Item13, Upc13, Goods_Code14, Patt14, Item14, 
                            Upc14, Goods_Code15, Patt15, Item15, Upc15, Goods_Code16, Patt16, Item16, Upc16, Goods_Code17, 
                            Patt17, Item17, Upc17, Goods_Code18, Patt18, Item18, Upc18, Goods_Code19, Patt19, Item19, 
                            Upc19, Goods_Code20, Patt20, Item20, Upc20
                        ) VALUES (
                            :Invoice_No, :Market, :Customer_No, :Dest, :Order_No, :Case_No, :Patt, :Item, :Unit, :Goods_Code, 
                            :Order_Small_No, :Order_Date, :Requested_Date, :Quantity, :Ctn_Quantity, :Packing_Style_Code, 
                            :Ctn_Code, :Ibox_Code_, :Ctn_Unit_Qty, :Ibox_Unit_Qty, :Gross_Weght, :Net_Weght, :Ctn_Width, 
                            :Ctn_Depth, :Ctn_Height, :Direct_Unit_Cost, :Gtin, :Upc_Posision1, :Upc_Posision2, :Upc_Posision3, 
                            :Cust_Order_No, :Patt_Name, :Item_Name, :Goods_Code01, :Patt01, :Item01, :Upc01, :Goods_Code02, 
                            :Patt02, :Item02, :Upc02, :Goods_Code03, :Patt03, :Item03, :Upc03, :Goods_Code04, :Patt04, :Item04, 
                            :Upc04, :Goods_Code05, :Patt05, :Item05, :Upc05, :Goods_Code06, :Patt06, :Item06, :Upc06, :Goods_Code07, 
                            :Patt07, :Item07, :Upc07, :Goods_Code08, :Patt08, :Item08, :Upc08, :Goods_Code09, :Patt09, :Item09, 
                            :Upc09, :Goods_Code10, :Patt10, :Item10, :Upc10, :Goods_Code11, :Patt11, :Item11, :Upc11, :Goods_Code12, 
                            :Patt12, :Item12, :Upc12, :Goods_Code13, :Patt13, :Item13, :Upc13, :Goods_Code14, :Patt14, :Item14, 
                            :Upc14, :Goods_Code15, :Patt15, :Item15, :Upc15, :Goods_Code16, :Patt16, :Item16, :Upc16, :Goods_Code17, 
                            :Patt17, :Item17, :Upc17, :Goods_Code18, :Patt18, :Item18, :Upc18, :Goods_Code19, :Patt19, :Item19, 
                            :Upc19, :Goods_Code20, :Patt20, :Item20, :Upc20
                        )
                    ");

                    // Loop through the CSV file and insert each row into the database
                    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $stmt->execute(array(
                            ':Invoice_No' => $new_invoice_no,
                            ':Market' => $row[0],
                            ':Customer_No' => $row[1],
                            ':Dest' => $row[2],
                            ':Order_No' => $row[3],
                            ':Case_No' => $row[4],
                            ':Patt' => $row[5],
                            ':Item' => $row[6],
                            ':Unit' => $row[7],
                            ':Goods_Code' => $row[8],
                            ':Order_Small_No' => $row[9],
                            ':Order_Date' => $row[10],
                            ':Requested_Date' => $row[11],
                            ':Quantity' => $row[12],
                            ':Ctn_Quantity' => $row[13],
                            ':Packing_Style_Code' => $row[14],
                            ':Ctn_Code' => $row[15],
                            ':Ibox_Code_' => $row[16],
                            ':Ctn_Unit_Qty' => $row[17],
                            ':Ibox_Unit_Qty' => $row[18],
                            ':Gross_Weght' => $row[19],
                            ':Net_Weght' => $row[20],
                            ':Ctn_Width' => $row[21],
                            ':Ctn_Depth' => $row[22],
                            ':Ctn_Height' => $row[23],
                            ':Direct_Unit_Cost' => $row[24],
                            ':Gtin' => $row[25],
                            ':Upc_Posision1' => $row[26],
                            ':Upc_Posision2' => $row[27],
                            ':Upc_Posision3' => $row[28],
                            ':Cust_Order_No' => $row[29],
                            ':Patt_Name' => $row[30],
                            ':Item_Name' => $row[31],
                            ':Goods_Code01' => $row[32],
                            ':Patt01' => $row[33],
                            ':Item01' => $row[34],
                            ':Upc01' => $row[35],
                            ':Goods_Code02' => $row[36],
                            ':Patt02' => $row[37],
                            ':Item02' => $row[38],
                            ':Upc02' => $row[39],
                            ':Goods_Code03' => $row[40],
                            ':Patt03' => $row[41],
                            ':Item03' => $row[42],
                            ':Upc03' => $row[43],
                            ':Goods_Code04' => $row[44],
                            ':Patt04' => $row[45],
                            ':Item04' => $row[46],
                            ':Upc04' => $row[47],
                            ':Goods_Code05' => $row[48],
                            ':Patt05' => $row[49],
                            ':Item05' => $row[50],
                            ':Upc05' => $row[51],
                            ':Goods_Code06' => $row[52],
                            ':Patt06' => $row[53],
                            ':Item06' => $row[54],
                            ':Upc06' => $row[55],
                            ':Goods_Code07' => $row[56],
                            ':Patt07' => $row[57],
                            ':Item07' => $row[58],
                            ':Upc07' => $row[59],
                            ':Goods_Code08' => $row[60],
                            ':Patt08' => $row[61],
                            ':Item08' => $row[62],
                            ':Upc08' => $row[63],
                            ':Goods_Code09' => $row[64],
                            ':Patt09' => $row[65],
                            ':Item09' => $row[66],
                            ':Upc09' => $row[67],
                            ':Goods_Code10' => $row[68],
                            ':Patt10' => $row[69],
                            ':Item10' => $row[70],
                            ':Upc10' => $row[71],
                            ':Goods_Code11' => $row[72],
                            ':Patt11' => $row[73],
                            ':Item11' => $row[74],
                            ':Upc11' => $row[75],
                            ':Goods_Code12' => $row[76],
                            ':Patt12' => $row[77],
                            ':Item12' => $row[78],
                            ':Upc12' => $row[79],
                            ':Goods_Code13' => $row[80],
                            ':Patt13' => $row[81],
                            ':Item13' => $row[82],
                            ':Upc13' => $row[83],
                            ':Goods_Code14' => $row[84],
                            ':Patt14' => $row[85],
                            ':Item14' => $row[86],
                            ':Upc14' => $row[87],
                            ':Goods_Code15' => $row[88],
                            ':Patt15' => $row[89],
                            ':Item15' => $row[90],
                            ':Upc15' => $row[91],
                            ':Goods_Code16' => $row[92],
                            ':Patt16' => $row[93],
                            ':Item16' => $row[94],
                            ':Upc16' => $row[95],
                            ':Goods_Code17' => $row[96],
                            ':Patt17' => $row[97],
                            ':Item17' => $row[98],
                            ':Upc17' => $row[99],
                            ':Goods_Code18' => $row[100],
                            ':Patt18' => $row[101],
                            ':Item18' => $row[102],
                            ':Upc18' => $row[103],
                            ':Goods_Code19' => $row[104],
                            ':Patt19' => $row[105],
                            ':Item19' => $row[106],
                            ':Upc19' => $row[107],
                            ':Goods_Code20' => $row[108],
                            ':Patt20' => $row[109],
                            ':Item20' => $row[110],
                            ':Upc20' => $row[111]
                        ));
                    }
                    fclose($handle);
                    echo "Data has been imported successfully.\n";
                } else {
                    echo "Failed to open the uploaded CSV file.\n";
                }
            } catch (PDOException $e) {
                echo "Database error: " . $e->getMessage();
            }
        } else {
            echo "Failed to move uploaded file.\n";
        }
    } else {
        echo "No file uploaded or there was an error during the upload.\n";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload CSV</title>
</head>
<body>
    <form enctype="multipart/form-data" action="" method="POST">
        <input type="file" name="csv_file" accept=".csv">
        <button type="submit">Upload and Import CSV</button>
    </form>
</body>
</html>
