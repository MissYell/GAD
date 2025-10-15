<?php
// Include database connection
require_once 'db_connection.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$title = $organization = $fileLink = $message = "";
$uploadOk = 1;

// Retrieve the logged-in user's ID
$userID = "";
if (isset($_SESSION['user_id'])) {
    $email = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT userID FROM enduser WHERE userID = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $userID = $row['userID'];
    }
    $stmt->close();
 }

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $userID !== "") {
        // Get form data
        $title = $_POST["title"];
        $organization = $_POST["organization"];
        $fileLink = $_POST["fileLink"];

        // Check if any required field is empty
        if (empty($title) || empty($organization) || empty($fileLink)) {
            $message = "<div class='alert alert-danger'>All fields are required</div>";
            $uploadOk = 0;
        }

        // Validate link format
        if (!empty($fileLink) && !filter_var($fileLink, FILTER_VALIDATE_URL)) {
            $message = "<div class='alert alert-danger'>Please enter a valid URL</div>";
            $uploadOk = 0;
        }

        // If all validations pass
           if ($uploadOk == 1) {
            // Generate PAPS ID
            $papsID = "PAPS" . rand(1000, 9999);

            // Set default status to 'Unassigned'
            $status = "Unassigned";

            $sql = "INSERT INTO paps (papsID, userID, title, organization, fileLink, status) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            // Use prepared statement for security
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssss", $papsID, $userID, $title, $organization, $fileLink, $status);

                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success'>The form has been submitted successfully.</div>";
                    // Clear form data after successful submission
                    $title = $organization = $fileLink = "";
                } else {
                    $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert alert-danger'>Error preparing statement: " . $mysqli->error . "</div>";
            }
    }
}
header("location: enduser.php");
exit;
?>

