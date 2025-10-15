<?php
session_start();
include 'db_connection.php'; // your DB connection logic

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userID = $_SESSION['user_id'];
$papsID = $_GET['papsID'] ?? null;

if (!$papsID) {
    die("No PAP specified.");
}

// Check if user is certified for this PAP
$certStmt = $mysqli->prepare("SELECT * FROM certification WHERE userID = ? AND papsID = ? LIMIT 1");
$certStmt->bind_param("ss", $userID, $papsID);
$certStmt->execute();
$certResult = $certStmt->get_result();

if ($certResult && $certResult->num_rows === 1) {
    // Now fetch the project title from the paps table
    $papsStmt = $mysqli->prepare("SELECT title FROM paps WHERE papsID = ? LIMIT 1");
    $papsStmt->bind_param("s", $papsID);
    $papsStmt->execute();
    $papsResult = $papsStmt->get_result();

    if ($papsResult && $papsResult->num_rows === 1) {
        $papsTitle = $papsResult->fetch_assoc()['title'];
    } else {
        die("PAP not found.");
    }

    // Fetch user full name (assuming from EndUser table)
    $userStmt = $mysqli->prepare("SELECT fname, lname FROM EndUser WHERE userID = ? LIMIT 1");
    $userStmt->bind_param("s", $userID);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($userRes && $userRes->num_rows === 1) {
        $userData = $userRes->fetch_assoc();
        $fullName = $userData['fname'] . ' ' . $userData['lname'];
    } else {
        $fullName = "Unknown User";
    }

} else {
    die("This PAP is not certified for you.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="globalstyles.css">
    <link rel="stylesheet" href="certificate.css">
    <link rel="stylesheet" href="endUser.css">
    <title>GAD Management Information System - Certificate</title>
</head>

<body style="overflow:hidden; margin:0; padding:0; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f8fafc; color:#334155; line-height:1.5;">
    <div class="container" style="margin:0 auto; padding:20px; box-sizing:border-box;">
    
    <div style="position: absolute; top: 30px; right: 30px; z-index: 10;">
        <a href="download_certificate.php?papsID=<?php echo urlencode($papsID); ?>" class="btn">Download Certificate</a>
    </div>

    <div class="certificate-container">
        <div class="certificate" id="certificate">
            <div class="watermark">APPROVED</div>
            
            <div class="certificate-content">
                <div class="university-logo">
                    <img src="usep-logo.png" alt="University Logo" style="width: 80px; height: 80px; border-radius: 50%;">
                </div>
                
                <div class="university-name">University of Southeastern Philippines</div>
                <div class="university-details">
                    Republic of the Philippines<br>
                    Iñigo Street, Bo. Obrero, Davao City, Davao Del Sur, 8000 +6382 227 8192<br>
                    www.usep.edu.ph
                </div>

                <div class="certificate-title">CERTIFICATION</div>

                <div class="certificate-content" style="text-align: center;">
                    This is to certify that the proposed project entitled 
                    <span class="project-title">"<?php echo htmlspecialchars($papsTitle); ?>"</span> 
                    has been reviewed and evaluated using the Gender and Development (GAD) Scorecard and has satisfactorily met the criteria for gender-responsiveness.
                    <br><br>
                    Accordingly, the project is hereby certified as gender-sensitive and has passed the GAD test, demonstrating alignment with the principles of gender equality and inclusivity in its planning, implementation, and expected outcomes.
                </div>

                <div class="date-info" style="text-align: center;">
                    Date: <span id="currentDate"></span>
                </div>

                <div class="signature-section">
                    <div class="signature-box" style="text-align: center;">
                        <div class="signature-text">APPROVED BY: GAD</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">Authorized Officer, MC</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="endUbaseUI.js"></script>
    <script>
        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    </script>
</body>
</html>
