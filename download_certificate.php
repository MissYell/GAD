<?php
require 'vendor/autoload.php'; 
use Dompdf\Dompdf;

session_start();
include 'db_connection.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userID = $_SESSION['user_id'];
$papsID = $_GET['papsID'] ?? null;

if (!$papsID) {
    die("No PAP specified.");
}

// ✅ Check if certified
$certStmt = $mysqli->prepare("SELECT * FROM certification WHERE userID = ? AND papsID = ? LIMIT 1");
$certStmt->bind_param("ss", $userID, $papsID);
$certStmt->execute();
$certResult = $certStmt->get_result();

if (!$certResult || $certResult->num_rows !== 1) {
    die("This PAP is not certified for you.");
}

// ✅ Fetch project title
$papsStmt = $mysqli->prepare("SELECT title FROM paps WHERE papsID = ? LIMIT 1");
$papsStmt->bind_param("s", $papsID);
$papsStmt->execute();
$papsTitle = $papsStmt->get_result()->fetch_assoc()['title'] ?? "Unknown Project";

// ✅ Fetch user info
$userStmt = $mysqli->prepare("SELECT fname, lname FROM EndUser WHERE userID = ? LIMIT 1");
$userStmt->bind_param("s", $userID);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$fullName = $userData ? $userData['fname'] . ' ' . $userData['lname'] : "Unknown User";

// ✅ Build certificate HTML (inline CSS, centered layout)
$html = '
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 50px;
            line-height: 1.6;
        }
        .certificate {
            padding: 60px;
            text-align: center;
        }
        .certificate-title {
            font-size: 28px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .project-title {
            font-size: 20px;
            font-style: italic;
            margin: 25px 0;
        }
        .logo {
            margin-bottom: 20px;
        }
        .signature-section {
            margin-top: 80px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin: 0 auto 5px auto;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="logo">
            <img src="'.__DIR__.'/usep-logo.png" alt="Logo" style="width:100px;">
        </div>
        <div class="certificate-title">CERTIFICATION</div>
        <p>This is to certify that <b>'.$fullName.'</b></p>
        <p>has successfully proposed the project entitled:</p>
        <div class="project-title">"'.htmlspecialchars($papsTitle).'"</div>
        <p>This project has been reviewed using the GAD Scorecard and has met the criteria for gender-responsiveness.</p>
        <p>Date: '.date("F j, Y").'</p>

        <div class="signature-section">
            <div class="signature-line"></div>
            <p>Authorized Officer, MC</p>
        </div>
    </div>
</body>
</html>
';

// ✅ Generate PDF
$dompdf = new Dompdf();
$options = $dompdf->getOptions();
$options->set('isRemoteEnabled', true);
$dompdf->setOptions($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("certificate.pdf", ["Attachment" => true]);
exit;
