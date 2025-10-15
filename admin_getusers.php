<?php
include "db_connection.php";

// Set timezone
date_default_timezone_set('Asia/Manila');

$users = [];

// Function to calculate "time ago"
function timeAgo($datetime) {
    if (empty($datetime) || $datetime == "0000-00-00 00:00:00") {
        return "Inactive";
    }

    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 0) {
        return "Just now"; // Handles future timestamps / mismatched clocks
    }

    if ($diff < 60) {
        return ($diff == 0 ? "Active now" : $diff . " sec ago");
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " mins ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } else {
        return floor($diff / 86400) . " days ago";
    }
}

// Get end users
$endUserSql = "SELECT userID AS id, fname, lname, mname, email, orgname, sex, contactNo, dob, last_active, date_joined 
               FROM enduser 
               ORDER BY lname ASC";
$endUserResult = $conn->query($endUserSql);

if ($endUserResult && $endUserResult->num_rows > 0) {
    while ($row = $endUserResult->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'firstName' => $row['fname'],
            'lastName' => $row['lname'],
            'fullName' => $row['fname'] . ' ' . $row['lname'],
            'middleName' => $row['mname'],
            'email' => $row['email'],
            'role' => 'End User',
            'organization' => $row['orgname'],
            'sex' => $row['sex'],
            'contactNo' => $row['contactNo'],
            'dob' => $row['dob'],
            'specialization' => '', // not applicable to end users
            'userGroup' => 'End User',
            'dateJoined' => $row['date_joined'] ?? '',
            'status' => timeAgo($row['last_active'])
        ];
    }
}

// Get evaluators
$evalSql = "SELECT evaluatorID AS id, fname, lname, email, department, expertise, last_active, date_joined 
            FROM evaluator 
            ORDER BY lname ASC";
$evalResult = $conn->query($evalSql);

if ($evalResult && $evalResult->num_rows > 0) {
    while ($row = $evalResult->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'firstName' => $row['fname'],
            'lastName' => $row['lname'],
            'fullName' => $row['fname'] . ' ' . $row['lname'],
            'email' => $row['email'],
            'role' => 'Evaluator',
            'department' => $row['department'],
            'specialization' => $row['expertise'],
            'userGroup' => 'Evaluator',
            'dateJoined' => $row['date_joined'] ?? '',
            'status' => timeAgo($row['last_active'])
        ];
    }
}

echo json_encode($users);
$conn->close();
?>
