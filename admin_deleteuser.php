<?php
include 'db_connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['id'] ?? null;
$userGroup = $data['userGroup'] ?? null;

if (!$userId || !$userGroup) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID or group']);
    exit;
}

// Normalize
$normalizedGroup = strtolower(str_replace(' ', '', $userGroup));

// Route deletion
if ($normalizedGroup === 'evaluator') {
    $stmt = $conn->prepare("DELETE FROM evaluator WHERE evaluatorID = ?");
} elseif ($normalizedGroup === 'enduser') {
    $stmt = $conn->prepare("DELETE FROM enduser WHERE userID = ?");
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user group']);
    exit;
}

$stmt->bind_param("s", $userId);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>