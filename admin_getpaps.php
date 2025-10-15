<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include "db_connection.php";

$paps = [];

$sql = "
SELECT 
    p.papsID, 
    p.title, 
    p.organization, 
    p.dateSubmitted, 
    p.fileLink, 
    p.status,
    u.fname, 
    u.lname, 
    u.email
FROM 
    paps p
JOIN 
    enduser u ON p.userID = u.userID
ORDER BY 
    p.dateSubmitted DESC
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $paps[] = [
            'id' => $row['papsID'],
            'docNo' => $row['papsID'],
            'title' => $row['title'],
            'collegeUnit' => $row['organization'],
            'dateSubmitted' => $row['dateSubmitted'],
            'fileLink' => $row['fileLink'],
            'status' => ucfirst($row['status']),
            'fullName' => $row['fname'] . ' ' . $row['lname'],
            'email' => $row['email']
        ];
    }
}

echo json_encode($paps);
$conn->close();
?>
