<?php
session_start();
header('Content-Type: application/json');
require 'db_connection.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$evaluatorID = $_SESSION['user_id'] ?? null;
if (!$evaluatorID) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$check = $conn->prepare("SELECT COUNT(*) as count FROM evaluator WHERE evaluatorID = ?");
$check->bind_param("s", $evaluatorID);
$check->execute();
$checkResult = $check->get_result()->fetch_assoc();

if ($checkResult['count'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Evaluator not found in evaluator table']);
    exit;
}
$check->close();

$papsID = $_POST['papsID'];
$scores = $_POST['score'];

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$versionID = $_POST['versionID'] ?? null;

// Validate
if (!$versionID) {
    echo json_encode(['success' => false, 'message' => 'Missing version ID']);
    exit;
}

$conn->begin_transaction();

try {
    // Prepare insert/update statement for scores
$stmt = $conn->prepare("
    INSERT INTO Score (itemID, papsID, evaluatorID, score, versionID)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE score = VALUES(score)
");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    foreach ($scores as $itemID => $scoreValue) {
        if (!is_numeric($scoreValue)) {
            throw new Exception("Invalid score for item $itemID");
        }
        $scoreValue = floatval($scoreValue);
        $stmt->bind_param("sssds", $itemID, $papsID, $evaluatorID, $scoreValue, $versionID);
        $stmt->execute();
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
