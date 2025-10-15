<?php
include 'GADdbms.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    $userGroup = strtolower(trim($data['userGroup']));
    $id = trim($data['userId']);  // evaluatorID (varchar)
    $firstName = trim($data['firstName']);
    $lastName = trim($data['lastName']);
    $email = trim($data['email']);
    $department = trim($data['department']);
    $specialization = trim($data['specialization']);
    $sex = trim($data['sex']);
    $contactNo = trim($data['contactNo']);
    $dob = isset($data['dob']) ? trim($data['dob']) : null;
    $address = isset($data['address']) ? trim($data['address']) : null;

    // Basic input validation
    if (empty($id)) {
        echo "Missing or invalid evaluator ID.";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format.";
        exit;
    }

    if (!preg_match('/^\d{11}$/', $contactNo)) {
        echo "Contact number must be exactly 11 digits.";
        exit;
    }

    // Validate sex (assuming only M/F)
    if (!in_array(strtoupper($sex), ['M', 'F'])) {
        echo "Invalid value for sex. Must be 'M' or 'F'.";
        exit;
    }

    if ($userGroup === 'evaluator') {
        $sql = "UPDATE evaluator SET 
                    fname = ?, 
                    lname = ?, 
                    dob = ?, 
                    sex = ?, 
                    contactNo = ?, 
                    email = ?, 
                    address = ?, 
                    expertise = ?, 
                    department = ? 
                WHERE evaluatorID = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss",
            $firstName,
            $lastName,
            $dob,
            $sex,
            $contactNo,
            $email,
            $address,
            $specialization,
            $department,
            $id
        );

        if ($stmt->execute()) {
            echo ($stmt->affected_rows > 0) 
                ? "Evaluator updated successfully!" 
                : "No changes made or evaluator not found.";
        } else {
            echo "Error updating evaluator: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Unsupported user group.";
    }

    $conn->close();
} else {
    echo "No data received.";
}
?>
