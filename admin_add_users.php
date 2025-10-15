<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Force JSON response

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit;
}

// Sanitize and assign variables
$evaluatorID = trim($data->evaluatorID);
$fname = trim($data->fname);
$lname = trim($data->lname);
$dob = trim($data->dob);
$sex = strtoupper(trim($data->sex));
$contactNo = trim($data->contactNo);
$email = trim($data->email);
$address = trim($data->address);
$expertise = trim($data->expertise);
$department = trim($data->department);
$password = $data->password;

// Validations
if (!preg_match("/^[a-zA-Z\s'-]{1,25}$/", $fname)) {
    echo json_encode(["status" => "error", "message" => "Invalid first name"]);
    exit;
}

if (!preg_match("/^[a-zA-Z\s'-]{1,25}$/", $lname)) {
    echo json_encode(["status" => "error", "message" => "Invalid last name"]);
    exit;
}

if (!preg_match("/^\d{11}$/", $contactNo)) {
    echo json_encode(["status" => "error", "message" => "Contact number must be exactly 11 digits"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}



// Hash the password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// SQL insert
$sql = "INSERT INTO evaluator 
    (evaluatorID, fname, lname, dob, sex, contactNo, email, address, expertise, department, password) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssssss",
    $evaluatorID,
    $fname,
    $lname,
    $dob,
    $sex,
    $contactNo,
    $email,
    $address,
    $expertise,
    $department,
    $passwordHash
);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
