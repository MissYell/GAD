<?php 
session_start();
include 'db_connection.php';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
} 

if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header("Location: adminhome.php");
        exit();
    } elseif ($_SESSION['role'] === 'evaluator') {
        header("Location: evalhome.php");
        exit();
    } else {
        header("Location: enduser.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. Check Admin
    $stmt = $mysqli->prepare("SELECT adminID, fname, email, password FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $adminResult = $stmt->get_result();

    if ($adminResult && $adminResult->num_rows === 1) {
        $admin = $adminResult->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['adminID'];
            $_SESSION['fname'] = $admin['fname'];
            $_SESSION['role'] = 'admin';

            $update = $mysqli->prepare("UPDATE admin SET last_active = NOW() WHERE adminID = ?");
            $update->bind_param("i", $admin['adminID']);
            $update->execute();

            session_write_close(); 
            header("Location: adminhome.php");
            exit();
        } else {
            header("Location: index.php?error=Incorrect password");
            exit();
        }
    }

    // 2. Check EndUser
    $stmt = $mysqli->prepare("SELECT userID, fname, email, password FROM EndUser WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $userResult = $stmt->get_result();

    if ($userResult && $userResult->num_rows === 1) {
        $user = $userResult->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['userID'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['role'] = 'user';

            $update = $mysqli->prepare("UPDATE EndUser SET last_active = NOW() WHERE userID = ?");
            $update->bind_param("s", $user['userID']);
            $update->execute();

            session_write_close(); 
            header("Location: enduser.php");
            exit();
        } else {
            header("Location: index.php?error=Incorrect password");
            exit();
        }
    }

    // 3. Check Evaluator
    $stmt = $mysqli->prepare("SELECT evaluatorID, fname, email, password FROM evaluator WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $evalResult = $stmt->get_result();

    if ($evalResult && $evalResult->num_rows === 1) {
        $eval = $evalResult->fetch_assoc();
        if (password_verify($password, $eval['password'])) {
            $_SESSION['user_id'] = $eval['evaluatorID'];
            $_SESSION['fname'] = $eval['fname'];
            $_SESSION['role'] = 'evaluator';

            $update = $mysqli->prepare("UPDATE evaluator SET last_active = NOW() WHERE evaluatorID = ?");
            $update->bind_param("s", $eval['evaluatorID']);
            $update->execute();

            session_write_close(); 
            header("Location: evalhome.php");
            exit();
        } else {
            header("Location: index.php?error=Incorrect password");
            exit();
        }
    }

    // ✅ Final fallback: email not found in any table
    header("Location: index.php?error=Account does not exist");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  
  <link rel="stylesheet" href="style.css" />
  <title>Login | GAD Score Card Management System</title>
</head>
<style>
    .popup-error {
        font-size: 13px;
        color: red;
        margin: 0 auto 20px auto;
        width: fit-content;
        text-align: center;
        font-weight: lighter;
        animation: fadeIn 0.5s ease;
      }

      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
      }
</style>
<body>
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
          <h1 class="title">Login</h1>
          <div class="divider"></div> <br>
          <p class="subtitle">Monitor and evaluate the gender-responsiveness of your Programs, Activities, and Projects (PAPs) with ease</p>
              <?php if (isset($_GET['error'])): ?>
            <div class="popup-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
          <form class="form" method="POST" action="index.php">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login" class="btn">Sign In</button>

            <div class="form-footer">
              <div class="checkbox-group">
                <input type="checkbox" id="remember">
                <label for="remember">Remember me</label>
              </div>
              <a href="forgot-password.html" class="link">Forgot password?</a>
            </div>

            <div class="center-link">
              Don't have an account? <a href="SignUp.php" class="link">Sign Up</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>


</body>
</html>