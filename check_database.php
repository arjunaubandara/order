<?php
header('Content-Type: application/json');

 include("database_connection.php");

$orpatt = isset($_POST['orpatt']) ? $_POST['orpatt'] : '';
$oritem = isset($_POST['oritem']) ? $_POST['oritem'] : '';
 

$sql = "SELECT Pattern, setItem, Item, Qty FROM set_master_break WHERE Pattern = ? AND setItem = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $orpatt, $oritem);
$stmt->execute();
$result = $stmt->get_result();

$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
$conn->close();
?>