<?php
session_start();
// Check if the session variable is set
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Unauthorized access");
    exit();
}

// Database connection
include 'db_connection.php';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Retrieve adminID (now stored as user_id in session)
$adminID = $_SESSION['user_id']; 
$_SESSION['adminID'] = $adminID;

if (isset($_SESSION['adminID'])) {
    $adminID = $_SESSION['adminID'];
    $adminTooltip = htmlspecialchars($adminID);
} else {
    header("Location: index.php");
    exit();
}

// Fetch status counts from database - CORRECTED for your schema
$statusCounts = [
    'pending' => 0,
    'for_correction' => 0,
    'completed' => 0,
    'total' => 0
];

// CORRECTED: User statistics based on your actual tables
$userStats = [
    'total' => 0,
    'evaluators' => 0,
    'end_users' => 0
];

$recentEvaluators = [];
$unassignedDocs = 0;
$hasNotifications = false;
$inactiveEvaluators = [];

try {
    
    // Count pending items - CORRECTED: your status field uses 'pending', 'completed', 'unassigned'
    $pendingQuery = "SELECT COUNT(*) as count FROM paps WHERE status = 'pending' OR status = 'unassigned'";
    $result = $mysqli->query($pendingQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $statusCounts['pending'] = (int)$row['count'];
    }

    // For correction - since your schema doesn't seem to have 'for_correction' status,
    // we'll count PAPs that might need re-evaluation (finalScore <= 3.9 and completed)
    $correctionQuery = "SELECT COUNT(*) as count FROM paps WHERE finalScore IS NOT NULL AND finalScore <= 3.9";
    $result = $mysqli->query($correctionQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $statusCounts['for_correction'] = (int)$row['count'];
    }

    // Count completed items
    $completedQuery = "SELECT COUNT(*) as count FROM paps WHERE status = 'completed'";
    $result = $mysqli->query($completedQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $statusCounts['completed'] = (int)$row['count'];
    }

    // Calculate total
    $statusCounts['total'] = $statusCounts['pending'] + $statusCounts['for_correction'] + $statusCounts['completed'];

    // Calculate percentages
    $percentages = [
        'pending' => $statusCounts['total'] > 0 ? round(($statusCounts['pending'] / $statusCounts['total']) * 100, 1) : 0,
        'for_correction' => $statusCounts['total'] > 0 ? round(($statusCounts['for_correction'] / $statusCounts['total']) * 100, 1) : 0,
        'completed' => $statusCounts['total'] > 0 ? round(($statusCounts['completed'] / $statusCounts['total']) * 100, 1) : 0
    ];

    // CORRECTED: Fetch user statistics from separate tables
    $evaluatorCountQuery = "SELECT COUNT(*) as count FROM evaluator";
    $result = $mysqli->query($evaluatorCountQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $userStats['evaluators'] = (int)$row['count'];
    }

    $endUserCountQuery = "SELECT COUNT(*) as count FROM enduser";
    $result = $mysqli->query($endUserCountQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $userStats['end_users'] = (int)$row['count'];
    }

    $userStats['total'] = $userStats['evaluators'] + $userStats['end_users'];

    $recentEvaluatorsQuery = "
    SELECT evaluatorID, fname, lname, email, department, date_joined
    FROM evaluator
    ORDER BY date_joined DESC
    LIMIT 10
";

    $result = $mysqli->query($recentEvaluatorsQuery);
    $recentEvaluators = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentEvaluators[] = [
                'user_id' => $row['evaluatorID'],
                'username' => $row['evaluatorID'],
                'full_name' => $row['fname'] . ' ' . $row['lname'],
                'email' => $row['email'],
                'designation' => $row['department'] ?? 'Evaluator',
                'created_at' => $row['date_joined'],
                'evaluated_paps' => 0 // optional, can keep 0 or fetch separately
            ];
        }
    }

    $inactiveQuery = "
       SELECT evaluatorID, fname, lname, email, department, date_joined
        FROM evaluator
        WHERE last_active IS NULL 
        OR last_active = '0000-00-00 00:00:00'
        OR last_active < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY last_active ASC


    ";
    $result = $mysqli->query($inactiveQuery);
        $inactiveEvaluators = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $inactiveEvaluators[] = [
                    'user_id' => $row['evaluatorID'],
                    'username' => $row['evaluatorID'],
                    'full_name' => $row['fname'] . ' ' . $row['lname'],
                    'email' => $row['email'],
                    'designation' => $row['department'] ?? 'Evaluator',
                    'created_at' => $row['date_joined'],
                    'evaluated_paps' => 0
                ];
            }
        }

    // CORRECTED: Count unassigned documents - PAPs with no assigned evaluators
    $unassignedQuery = "SELECT COUNT(*) as count FROM paps p 
                       LEFT JOIN assignedeval ae ON p.papsID = ae.papsID 
                       WHERE ae.papsID IS NULL";
    $result = $mysqli->query($unassignedQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $unassignedDocs = (int)$row['count'];
    }

    // CORRECTED: Check for notifications using your actual notifications table
    $notificationChecks = [
        // Check for unread notifications for admin
        "SELECT COUNT(*) as count FROM notifications WHERE recipientType = 'Admin' AND isRead = 0",
        // Check for recent PAP submissions (last 24 hours)  
        "SELECT COUNT(*) as count FROM paps WHERE dateSubmitted >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
    ];
    
    foreach ($notificationChecks as $query) {
        $result = $mysqli->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            if ((int)$row['count'] > 0) {
                $hasNotifications = true;
                break;
            }
        }
    }

} catch (Exception $e) {
    // If there's an error, use default values
    error_log("Dashboard query error: " . $e->getMessage());
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="header-admin.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="globalstyles.css">
    <title>GAD Management Information System</title>
    <style>

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .view {
    background-color: #fafbfc;
    min-height: 100vh;
    padding: 0;
}


nav .nav-menu {
    display: flex;
    align-items: center;
    gap: 32px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-item {
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    padding: 8px 0;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

.nav-item:hover {
    color: #374151;
}

.nav-item.active {
    border-bottom: 2px solid #8458B3;
}

/* Header Actions */
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-icon-container {
    position: relative;
}

.icon-button {
    width: 40px;
    height: 40px;
    border: none;
    background: transparent;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6b7280;
}

.icon-button:hover {
    background: #f9fafb;
    color: #374151;
}

/* Subheader section */
.subheader-container {
    background: transparent;
    padding: 32px 24px 16px;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    border: none;
}

.subheader-header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 24px;
}

      
       
    </style>
</head>
<body style="margin:0; padding:0; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f8fafc; color:#334155; line-height:1.5;">
    <div class="container" style="margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-radius: 12px;">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/logo.svg" alt="GAD Logo" width="80" height="80">
                </div>
            </div> 
             
            <nav>
                <ul class="nav-menu">
                    <li><a href="adminhome.php" class="nav-item">Dashboard</a></li>
                    <li><a href="admin-usrmgt.php" class="nav-item">Manage users</a></li>
                    <li><a href="admin-papseval.html" class="nav-item">Track PAPs</a></li>
                    <li><a href="adminscoresheet.php" class="nav-item">Scorecard</a></li>
                    <li><a href="admin-reports.php" class="nav-item active">Reports</a></li>
                </ul>
            </nav>
            <div class="header-actions" style="display: flex; gap: 8px;">
                <div class="header-icon-container" style="display: flex; align-items: center; gap: 6px;">
                    <button class="icon-button" style="position: relative;" data-tooltip="<?php echo $adminTooltip; ?>" 
                        onmouseover="this.querySelector('.tooltip').style.display='block'" 
                        onmouseout="this.querySelector('.tooltip').style.display='none'">
                        <div class="tooltip" style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                            <?php echo $adminTooltip; ?>
                        </div>
                        <span style="color: #8458B3; font-weight: 600; font-size: 14px;">Admin</span>
                    </button>
                </div>
                <div class="header-icon-container">
                    <button class="icon-button" id="notification-button" title="Notifications" style="position: relative;">
                        <?php if ($hasNotifications): ?>
                            <div class="notification-dot"></div>
                        <?php endif; ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <span class="header-icon-text"></span>
                </div>
                <div class="header-icon-container">
                    <button class="icon-button" id="menu-button" title="Log out">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 17L21 12L16 7" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12H9" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <span class="header-icon-text"></span>
                </div>
            </div>
        </div>
    
        <div class="menu-popup" id="menu-popup">
            <div class="menu-item" id="logout-btn">Log out</div>
        </div>
        
        <div class="notification-popup" id="notification-popup">
            <div class="notification-header">
                <div class="notification-title">Notifications</div>
                <button class="mark-read" id="mark-read-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 6L9 17L4 12" stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Mark all as read
                </button>
            </div>
            <div class="notification-list" id="notification-list">
                <div class="notification-item">
                    <p class="notification-text"></p>
                    <a class="notification-link"></a>
                </div>
                <div class="notification-item">
                    <p class="notification-text"></p>
                    <a class="notification-link"></a>
                </div>
                <div class="notification-item">
                    <p class="notification-text"></p>
                    <a class="notification-link"></a>
                </div>
            </div>
            <div class="empty-notifications" id="empty-notifications">
                No new notifications
            </div>
        </div>

        <div class="logout-modal" id="logout-modal">
            <div class="logout-modal-icon">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 35H8.33333C7.44928 35 6.60143 34.6488 5.97631 34.0237C5.35119 33.3986 5.32507 33 5 31.6667V8.33333C5 7.44928 5.35119 6.60143 5.97631 5.97631C6.60143 5.35119 7.44928 5 8.33333 5H15" stroke="#8458B3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M26.6667 28.3333L35 20L26.6667 11.6667" stroke="#8458B3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M35 20H15" stroke="#8458B3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="logout-modal-title">Confirm Logout</h3>
            <p class="logout-modal-text">Are you sure you want to log out?</p>
            <div class="logout-modal-buttons">
                <button class="logout-cancel-btn" id="logout-cancel-btn">Cancel</button>
                <a href="logout.php" class="logout-confirm-btn" id="logout-confirm-btn">Log Out</a>
            </div>
        </div>
                
        <div class="overlay" id="overlay"></div>

        
    </div>


    <script src="baseUI.js"></script>
    <script src="adminnotifs.js"></script>
    <script src="dashboardUI.js"></script>
</body>
</html>



