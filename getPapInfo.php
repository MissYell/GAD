<?php
session_start();
require 'db_connection.php';


// Ensure evaluator is logged in
if (!isset($_SESSION['evaluatorID']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: index.php?error=Unauthorized access");
    exit();
}

// Set response type to JSON
header('Content-Type: application/json');

// Get papsID from query string
$papsID = $_GET['papsID'] ?? null;

if (!$papsID) {
    echo json_encode(['error' => 'No papsID provided']);
    exit;
}

// Get PAP information
$stmt = $conn->prepare("SELECT p.papsID, p.title, p.organization, p.fileLink, p.status FROM paps p WHERE p.papsID = ?");
$stmt->bind_param("s", $papsID);
$stmt->execute();
$papResult = $stmt->get_result();
$papInfo = $papResult->fetch_assoc();

// If no data is found
if (!$papInfo) {
    echo json_encode(['error' => 'PAP not found']);
    exit;
}

// Get assigned evaluators
$stmt2 = $conn->prepare("
    SELECT CONCAT(e.fname, ' ', e.lname) AS name 
    FROM assignedeval ae 
    JOIN evaluator e ON ae.evaluatorID = e.evaluatorID 
    WHERE ae.papsID = ?
");
$stmt2->bind_param("s", $papsID);
$stmt2->execute();
$evalResult = $stmt2->get_result();

$evaluators = [];
while ($row = $evalResult->fetch_assoc()) {
    $evaluators[] = $row['name'];
}

// Combine and return all data
$response = [
    'papsID' => $papInfo['papsID'],
    'title' => $papInfo['title'],
    'organization' => $papInfo['organization'],
    'fileLink' => $papInfo['fileLink'],
    'status' => $papInfo['status'],
    'assignedEvaluators' => $evaluators
];

echo json_encode($response);
?>