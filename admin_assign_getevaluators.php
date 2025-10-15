<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "db_connection.php"; // your DB connection

$evaluators = [];

$sql = "SELECT evaluatorID, fname, lname, department, expertise, last_active FROM evaluator ORDER BY lname ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $evaluators[] = [
            'evaluatorID' => $row['evaluatorID'],
            'fullName'    => $row['fname'] . ' ' . $row['lname'],
            'department'  => $row['department'],
            'expertise'   => $row['expertise'],
            'last_active' => $row['last_active'] // include this
        ];
    }
}

echo json_encode($evaluators);
$conn->close();
?>
