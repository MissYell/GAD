<?php
require 'vendor/autoload.php'; // Make sure Dompdf is installed
use Dompdf\Dompdf;

session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_POST['month'])) {
    die("Month not selected.");
}

$month = $_POST['month']; // format: YYYY-MM

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) die("DB Connection failed: ".$mysqli->connect_error);

// =====================
// 1. PAPS Stats
// =====================
$statsQuery = "
SELECT 
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
FROM paps
WHERE DATE_FORMAT(dateSubmitted, '%Y-%m') = ?
";

$stmt = $mysqli->prepare($statsQuery);
$stmt->bind_param('s', $month);
$stmt->execute();
$statsResult = $stmt->get_result()->fetch_assoc();

// =====================
// 2. Total Users
// =====================
$enduserCount = $mysqli->query("SELECT COUNT(*) as total FROM enduser")->fetch_assoc()['total'];
$evaluatorCount = $mysqli->query("SELECT COUNT(*) as total FROM evaluator")->fetch_assoc()['total'];

// =====================
// 3. Inactive Evaluators
// Inactive = no assignments in this month
// =====================
$inactiveQuery = "
SELECT e.fname, e.lname 
FROM evaluator e
LEFT JOIN assignedeval a ON e.evaluatorID = a.evaluatorID
LEFT JOIN paps p ON a.papsID = p.papsID AND DATE_FORMAT(p.dateSubmitted, '%Y-%m') = ?
WHERE a.papsID IS NULL
ORDER BY e.lname, e.fname
";
$stmtInactive = $mysqli->prepare($inactiveQuery);
$stmtInactive->bind_param('s', $month);
$stmtInactive->execute();
$inactiveResult = $stmtInactive->get_result();

// =====================
// 4. Build HTML for PDF
// =====================
$html = '<h2 style="text-align:center;">Monthly Report for '.date('F Y', strtotime($month.'-01')).'</h2>';

// PAPS Stats
$html .= '<h3>PAPS Status Summary</h3>';
$html .= '<table border="1" cellpadding="8" cellspacing="0" style="width:50%; border-collapse: collapse;">';
$html .= '<tr style="background-color:#F3F0FD;"><th>Status</th><th>Count</th></tr>';
$html .= '<tr><td>Pending</td><td>'.$statsResult['pending'].'</td></tr>';
$html .= '<tr><td>Failed</td><td>'.$statsResult['failed'].'</td></tr>';
$html .= '<tr><td>Completed</td><td>'.$statsResult['completed'].'</td></tr>';
$html .= '</table><br>';

// Total Users
$html .= '<h3>Total Users</h3>';
$html .= '<table border="1" cellpadding="8" cellspacing="0" style="width:50%; border-collapse: collapse;">';
$html .= '<tr style="background-color:#F3F0FD;"><th>User Type</th><th>Total</th></tr>';
$html .= '<tr><td>End Users</td><td>'.$enduserCount.'</td></tr>';
$html .= '<tr><td>Evaluators</td><td>'.$evaluatorCount.'</td></tr>';
$html .= '</table><br>';

// Inactive Evaluators
$html .= '<h3>Inactive Evaluators (No assignments in this month)</h3>';
$html .= '<table border="1" cellpadding="8" cellspacing="0" style="width:50%; border-collapse: collapse;">';
$html .= '<tr style="background-color:#F3F0FD;"><th>Evaluator Name</th></tr>';
if ($inactiveResult->num_rows > 0) {
    while ($row = $inactiveResult->fetch_assoc()) {
        $html .= '<tr><td>'.htmlspecialchars($row['fname'].' '.$row['lname']).'</td></tr>';
    }
} else {
    $html .= '<tr><td colspan="1" style="text-align:center;">None</td></tr>';
}
$html .= '</table>';

// =====================
// Generate PDF
// =====================
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Monthly_Report_".$month.".pdf", ["Attachment" => true]);
exit;
?>
