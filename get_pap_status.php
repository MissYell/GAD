<?php
session_start();
require 'db_connection.php';

// First check if papsID exists in GET parameters
if (!isset($_GET['papsID']) || empty($_GET['papsID'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing PAPS ID']));
}

// Now safely get the value
$papsID = $_GET['papsID'];

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get PAP status
    $papStmt = $conn->prepare("
        SELECT p.evaluationStatus, p.finalScore, p.title,
               COUNT(ae.evaluatorID) as evaluator_count,
               SUM(CASE WHEN s.score IS NOT NULL THEN 1 ELSE 0 END) as completed_count
        FROM PAPs p
        LEFT JOIN AssignedEval ae ON p.papsID = ae.papsID
        LEFT JOIN Score s ON ae.evaluatorID = s.evaluatorID AND ae.papsID = s.papsID
        WHERE p.papsID = ?
        GROUP BY p.papsID
    ");
    $papStmt->bind_param("s", $papsID);
    $papStmt->execute();
    $papData = $papStmt->get_result()->fetch_assoc();
    $papStmt->close();

    // Handle case where PAP isn't found
    if (!$papData) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'PAP not found']));
    }

    // Get evaluator details
    $evalStmt = $conn->prepare("
        SELECT e.evaluatorID, e.fname, e.lname, 
               COUNT(s.score) as items_completed
        FROM AssignedEval ae
        JOIN Evaluator e ON ae.evaluatorID = e.evaluatorID
        LEFT JOIN Score s ON ae.evaluatorID = s.evaluatorID AND ae.papsID = s.papsID
        WHERE ae.papsID = ?
        GROUP BY e.evaluatorID
    ");
    $evalStmt->bind_param("s", $papsID);
    $evalStmt->execute();
    $evaluators = $evalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $evalStmt->close();

    echo json_encode([
        'success' => true,
        'status' => $papData['evaluationStatus'],
        'finalScore' => $papData['finalScore'],
        'title' => $papData['title'],
        'progress' => [
            'completed' => (int)$papData['completed_count'],
            'total' => (int)$papData['evaluator_count']
        ],
        'evaluators' => $evaluators
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>