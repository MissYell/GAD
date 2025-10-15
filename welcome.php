<?php 

session_start();

if (isset($_SESSION['userid'])) {
    header("Location: welcome.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gad_dbms"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = isset($_SESSION['fname']) ? $_SESSION['fname'] : '';
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GAD Management Information System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            color: #333;
        }
        h2 {
            color: #444;
            margin-top: 20px;
        }
        h3 {
            color: #555;
        }
        ul {
            list-style-type: none;
            padding-left: 0;
        }
        ul li {
            margin-bottom: 5px;
        }
        ul li:before {
            content: "☐ ";
        }
        hr {
            margin: 20px 0;
            border: 0;
            border-top: 1px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>GAD Management Information System</h1>
    
    <h2>Welcome, <?php echo htmlspecialchars($name); ?>!</h2> 
    
    <h3>Submitted Files</h3>

    <!-- insert recently opened/uploaded files -->
    
    <hr>
    
    <h3>Search</h3>
    
    <div>
        <span><strong>My files</strong></span> | 
        <span>All</span> | 
        <span>Completed</span> | 
        <span>Pending</span>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Forms</th>
                <th>Date Uploaded</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Conduct of Gender-Related Research in Higher Education</td>
                <td>Jan 4, 2022</td>
                <td>Pending</td>
                <td></td>
            </tr>
            </tr>
        </tbody>
    </table>
</body>
</html>