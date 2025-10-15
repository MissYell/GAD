<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include "db_connection.php";

$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['papsID'], $data['adminID'], $data['evaluatorIDs']) || !is_array($data['evaluatorIDs'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters.']);
    exit;
}

$papsID = $data['papsID'];
$adminID = $data['adminID'];
$evaluatorIDs = $data['evaluatorIDs'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT IGNORE INTO assignedeval (evaluatorID, papsID, adminID) VALUES (?, ?, ?)");

    foreach ($evaluatorIDs as $evaluatorID) {
        $stmt->bind_param("sss", $evaluatorID, $papsID, $adminID);
        $stmt->execute();
    }

    $update = $conn->prepare("UPDATE paps SET status = 'Pending' WHERE papsID = ?");
    $update->bind_param("s", $papsID);
    $update->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Evaluators assigned successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
if (isset($update)) $update->close();
$conn->close();
?>
