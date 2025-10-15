<?php 
session_start();
include 'db_connection.php';

// $host = '192.168.151.102';
// $db   = 'gad_dbms';
// $user = 'trisha';
// $pass = 'mackip';
// $charset = 'utf8mb4';

// $host = 'localhost';
// $db   = 'gad_dbms';
// $user = 'root';
// $pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
} 

function generateUserID() {
    return 'UID_' . bin2hex(random_bytes(4));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $UserID    = generateuserID();
    $fname     = $_POST['fname'];
    $lname     = $_POST['lname'];
    $mname     = $_POST['mname'];
    $email     = $_POST['email'];
    $password  = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $mysqli->prepare("INSERT INTO EndUser (userID, fname, lname,mname, email, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $UserID, $fname, $lname, $mname, $email, $hashedPassword);

    if ($stmt->execute()) {
        echo "<script>alert('User registered successfully!'); window.location.href = 'index.php';</script>";
        exit();
    } else {
        echo "<script>alert('Registration failed: " . addslashes($stmt->error) . "');</script>";
    }

    $stmt->close();
}
$mysqli->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="style.css" />
  <title>Sign Up | GAD Score Card Management System</title>
</head>
<body style="overflow: hidden;">
  <div id="app">
    <div class="container" id="pageContainer">
      <div class="content">
        <header class="header">
          <div class="logo">
            <img src="img/logo.svg" class="gad-logo" alt="GAD Logo">
          </div>
          <div class="logo-text">GAD Management Information System</div>
        </header>

        <div class="main">
          <h5 class="title">Sign Up</h5>
          <div class="divider"></div>
          <p class="subtitle">Create your account to continue.</p>

          <form class="form" method="POST" action="SignUp.php">
            <div class="form-row">
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lname" class="form-control" placeholder="Enter last name" required>
              </div>
              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="fname" class="form-control" placeholder="Enter first name" required>
              </div>
              <div class="form-group" style="flex: 0.5;">
                <label>M.I</label>
                <input type="text" name="mname" class="form-control" placeholder="M.I">
              </div>
            </div>

            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
              <label>Department</label>
              <div class="select-wrapper">
                <select name="department" class="form-control" required>
                  <option value="" disabled selected>Select Department</option>
                  <option value="CAS">CAS - College of Arts and Sciences</option>
                  <option value="CED">CED - College of Education</option>
                  <option value="CoE">CoE - College of Engineering</option>
                  <option value="CIC">CIC - College of Information and Computing</option>
                  <option value="CBA">CBA - College of Business Administration</option>
                  <option value="CAEc">CAEc - College of Applied Economics</option>
                  <option value="CoT">CT - College of Technology</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn">Create Account</button>

            <div class="form-footer">
              <div class="checkbox-group">
                <input type="checkbox" id="terms" required>
                <label for="terms">I agree to the Terms</label>
              </div>
              <a href="#" class="link">Terms & Conditions</a>
            </div>

            <div class="center-link">
              Already have an account? <a href="index.php" class="link">Sign In</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
