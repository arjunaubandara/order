<?php
// Database connection test file
$host = '10.0.0.12';
$dbname = 'production_data'; 
$username = 'root';          
$password = '';  

echo "<h1>Database Connection Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

try {
    echo "<p>Attempting to connect to MySQL at $host...</p>";
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>Connection successful!</p>";
    
    // Test query execution
    echo "<p>Testing query execution:</p>";
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_product_master");
    $count = $stmt->fetchColumn();
    
    echo "<p>Found $count records in tbl_product_master table.</p>";
    
    // Test a specific record
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_product_master WHERE SMPATT = ? AND SMITEM = ?");
    $stmt->execute(array('4166L', '91320'));
    $testCount = $stmt->fetchColumn();
    
    if ($testCount > 0) {
        echo "<p style='color:green'>Test combination (4166L/91320) exists in database!</p>";
    } else {
        echo "<p style='color:red'>Test combination (4166L/91320) NOT found in database.</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color:red'>Connection failed: " . $e->getMessage() . "</p>";
}
?>