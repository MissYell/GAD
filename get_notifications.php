<?php
session_start();

// Check if user is logged in (for either admin or regular user)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: index.php?error=Unauthorized access");
    exit();
}

// Include the database connection
require 'db_connection.php';  // Your database connection file

header('Content-Type: application/json');

// Get user info from session
$recipientID = $_SESSION['user_id'];  // This works for both admin and regular users
$role = $_SESSION['role'];

// Sanity check
if (empty($recipientID)) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

try {
    // Prepare the stored procedure call
    $stmt = $mysqli->prepare("CALL GetUserNotifications(?)");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    // Bind parameters: recipientID and role
    $stmt->bind_param("s", $recipientID);

    if (!$stmt->execute()) {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    // Fetch results
    $result = $stmt->get_result();
    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();

    // Free additional results if any (required for stored procedures)
    while ($mysqli->more_results() && $mysqli->next_result()) {
        $mysqli->use_result();
    }

    // Return the notifications as JSON
    echo json_encode([
        "success" => true,
        "notifications" => $notifications
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
error_log("Calling GetUserNotifications with recipientID=$recipientID, role=$role");

?>