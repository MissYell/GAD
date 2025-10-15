<?php
$host = 'localhost';
$db   = 'gad_dbmsold';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// First connection using $conn
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed (conn): " . $conn->connect_error);
} else {
    // Optional: echo "Connection via \$conn successful.";
}

// Second connection using $mysqli
$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed (mysqli): " . $mysqli->connect_error);
} else {
    // Optional: echo "Connection via \$mysqli successful.";
}
?>
