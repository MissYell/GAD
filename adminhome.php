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
        $statusCounts['pending'] = (int) $row['count'];
    }

    // For correction - since your schema doesn't seem to have 'for_correction' status,
    // we'll count PAPs that might need re-evaluation (finalScore <= 3.9 and completed)
    $correctionQuery = "SELECT COUNT(*) as count FROM paps WHERE finalScore IS NOT NULL AND finalScore <= 3.9";
    $result = $mysqli->query($correctionQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $statusCounts['for_correction'] = (int) $row['count'];
    }

    // Count completed items
    $completedQuery = "SELECT COUNT(*) as count FROM paps WHERE status = 'completed'";
    $result = $mysqli->query($completedQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $statusCounts['completed'] = (int) $row['count'];
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
        $userStats['evaluators'] = (int) $row['count'];
    }

    $endUserCountQuery = "SELECT COUNT(*) as count FROM enduser";
    $result = $mysqli->query($endUserCountQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $userStats['end_users'] = (int) $row['count'];
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
        $unassignedDocs = (int) $row['count'];
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
            if ((int) $row['count'] > 0) {
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


        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        /* Desktop Layout (Default) */
        .stats-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-dashboard {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 80px;
            width: 100%;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 30px;
            min-width: 350px;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #f0f0f0;
            background: #F3F0FD;
        }

        .stats-card-eval {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #f0f0f0;
            width: 155%;
        }

        .stats-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .user-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .user-stat-item:last-child {
            border-bottom: none;
        }

        .user-stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        .user-stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .recent-user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
            flex-wrap: wrap;
            gap: 8px;
        }

        .recent-user-item:last-child {
            border-bottom: none;
        }

        .user-info {
            flex: 1;
            min-width: 0;
            /* Allows text truncation */
        }

        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-designation {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            background: #d1fae5;
            color: #065f46;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .unassigned-card {
            background: #F3F0FD;
        }

        .unassigned-number {
            font-size: 32px;
            font-weight: 700;
            color: #92400e;
            margin: 8px 0;
        }

        .assign-link {
            color: #8458B3;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .assign-link:hover {
            text-decoration: underline;
        }

        .view-all-link {
            color: #8458B3;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* Chart responsiveness */
        .chart-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            overflow: hidden;
        }

        .chart-wrapper {
            position: relative;
            width: 350px;
            height: 350px;
            max-width: 100%;
            max-height: 100%;
        }

        #donutChart {
            width: 100%;
            height: 100%;
            max-width: 350px;
            max-height: 350px;
        }

        /* Status cards responsiveness */
        .status-cards-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            flex: 1;
        }

        .status-card-item {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 32px 40px;
            border-radius: 16px;
            background: #fafafa;
            min-height: 80px;
            transition: all 0.3s ease;
        }

        /* Main container responsiveness */
        .container {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        main {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Tablet Styles (1024px and below) */
        @media screen and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 0 15px;
            }

            .main-dashboard {
                flex-direction: column;
                gap: 40px;
                align-items: stretch;
            }

            .right-panel {
                min-width: unset;
                width: 100%;
                gap: 20px;
            }

            .stats-card-eval {
                width: 100%;
                max-width: none;
            }

            .chart-wrapper {
                width: 300px;
                height: 300px;
            }

            .unassigned-number {
                font-size: 28px;
            }

            /* Make evaluator cards responsive */
            .evaluator-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                width: 100%;
            }

            .stats-card-eval {
                width: 100%;
            }
        }

        /* Large Mobile Styles (768px and below) */
        @media screen and (max-width: 768px) {
            .stats-grid {
                gap: 20px;
                padding: 0 10px;
            }

            .main-dashboard {
                gap: 30px;
            }

            .right-panel {
                gap: 15px;
            }

            .stats-card,
            .stats-card-eval {
                padding: 20px;
                border-radius: 12px;
            }

            .stats-card h3 {
                font-size: 16px;
                margin: 0 0 15px 0;
            }

            .chart-wrapper {
                width: 250px;
                height: 250px;
            }

            .user-stat-label {
                font-size: 13px;
            }

            .user-stat-value {
                font-size: 16px;
            }

            .user-name {
                font-size: 13px;
            }

            .user-designation {
                font-size: 11px;
            }

            .unassigned-number {
                font-size: 24px;
            }

            .assign-link {
                font-size: 13px;
            }

            .view-all-link {
                font-size: 11px;
            }

            /* Stack status cards vertically on mobile */
            .status-card-item {
                padding: 20px 24px;
                gap: 16px;
                min-height: 60px;
            }

            .status-count {
                font-size: 28px;
            }

            .status-percentage {
                font-size: 16px;
            }

            /* Make recent user items more compact */
            .recent-user-item {
                padding: 10px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .user-status {
                align-self: flex-end;
                margin-top: -20px;
            }
        }

        /* Small Mobile Styles (480px and below) */
        @media screen and (max-width: 480px) {
            main {
                padding: 0 10px;
            }

            .stats-grid {
                gap: 15px;
                padding: 0 5px;
            }

            .stats-card,
            .stats-card-eval {
                padding: 16px;
                border-radius: 10px;
            }

            .stats-card h3 {
                font-size: 15px;
                margin: 0 0 12px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .chart-wrapper {
                width: 200px;
                height: 200px;
            }

            .total-count {
                font-size: 28px;
            }

            .chart-center-label {
                font-size: 11px;
            }

            .user-stat-item {
                padding: 6px 0;
            }

            .user-stat-label {
                font-size: 12px;
            }

            .user-stat-value {
                font-size: 14px;
            }

            .recent-user-item {
                padding: 8px 0;
            }

            .user-name {
                font-size: 12px;
            }

            .user-designation {
                font-size: 10px;
            }

            .user-status {
                font-size: 10px;
                padding: 2px 4px;
            }

            .unassigned-number {
                font-size: 20px;
            }

            .assign-link {
                font-size: 12px;
            }

            .view-all-link {
                font-size: 10px;
            }

            /* Ultra compact status cards for small screens */
            .status-card-item {
                padding: 16px 20px;
                gap: 12px;
                min-height: 50px;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .status-count {
                font-size: 24px;
            }

            .status-percentage {
                font-size: 14px;
            }

            .status-text {
                font-size: 16px;
            }
        }

        /* Extra Small Mobile Styles (360px and below) */
        @media screen and (max-width: 360px) {

            .stats-card,
            .stats-card-eval {
                padding: 12px;
                border-radius: 8px;
            }

            .chart-wrapper {
                width: 180px;
                height: 180px;
            }

            .total-count {
                font-size: 24px;
            }

            .unassigned-number {
                font-size: 18px;
            }

            .stats-card h3 {
                font-size: 14px;
            }

            .user-stat-value {
                font-size: 13px;
            }

            .status-count {
                font-size: 20px;
            }
        }

        /* Landscape orientation adjustments */
        @media screen and (max-height: 600px) and (orientation: landscape) {
            .chart-wrapper {
                width: 200px;
                height: 200px;
            }

            .stats-card,
            .stats-card-eval {
                padding: 16px;
            }

            .right-panel {
                gap: 15px;
            }
        }

        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2),
        (min-resolution: 192dpi) {

            .stats-card,
            .stats-card-eval {
                border-width: 0.5px;
            }
        }

        /* Print styles */
        @media print {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .main-dashboard {
                flex-direction: column;
                gap: 20px;
            }

            .chart-wrapper {
                width: 200px;
                height: 200px;
            }

            .stats-card,
            .stats-card-eval {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }

        @supports (container-type: inline-size) {
            .stats-card-eval {
                container-type: inline-size;
            }

            @container (max-width: 400px) {
                .stats-card-eval .recent-user-item {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .status-card-item {
                transition: none;
            }

            .stats-card,
            .stats-card-eval {
                transition: none;
            }
        }
    </style>
</head>

<body
    style="margin:0; padding:0; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f8fafc; color:#334155; line-height:1.5;">
    <div class="container" style="margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header"
            style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-radius: 12px;">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/logo.svg" alt="GAD Logo" width="80" height="80">
                </div>
            </div>

            <nav>
                <ul class="nav-menu">
                    <li><a href="#" class="nav-item active">Dashboard</a></li>
                    <li><a href="admin-usrmgt.php" class="nav-item">Manage users</a></li>
                    <li><a href="admin-papseval.html" class="nav-item">Track PAPs</a></li>
                    <li><a href="adminscoresheet.php" class="nav-item">Scorecard</a></li>
                    <li><a href="admin-reports.php" class="nav-item">Reports</a></li>
                </ul>
            </nav>
            <div class="header-actions" style="display: flex; gap: 8px;">
                <div class="header-icon-container" style="display: flex; align-items: center; gap: 6px;">
                    <button class="icon-button" style="position: relative;" data-tooltip="<?php echo $adminTooltip; ?>"
                        onmouseover="this.querySelector('.tooltip').style.display='block'"
                        onmouseout="this.querySelector('.tooltip').style.display='none'">
                        <div class="tooltip"
                            style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                            <?php echo $adminTooltip; ?>
                        </div>
                        <span style="color: #8458B3; font-weight: 600; font-size: 14px;">Admin</span>
                    </button>
                </div>
                <div class="header-icon-container">
                    <button class="icon-button" id="notification-button" title="Notifications"
                        style="position: relative;">
                        <?php if ($hasNotifications): ?>
                            <div class="notification-dot"></div>
                        <?php endif; ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <span class="header-icon-text"></span>
                </div>
                <div class="header-icon-container">
                    <button class="icon-button" id="menu-button" title="Log out">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M16 17L21 12L16 7" stroke="#8458B3" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M21 12H9" stroke="#8458B3" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
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
                        <path d="M20 6L9 17L4 12" stroke="#8458B3" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
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
                    <path
                        d="M15 35H8.33333C7.44928 35 6.60143 34.6488 5.97631 34.0237C5.35119 33.3986 5.32507 33 5 31.6667V8.33333C5 7.44928 5.35119 6.60143 5.97631 5.97631C6.60143 5.35119 7.44928 5 8.33333 5H15"
                        stroke="#8458B3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M26.6667 28.3333L35 20L26.6667 11.6667" stroke="#8458B3" stroke-width="3"
                        stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M35 20H15" stroke="#8458B3" stroke-width="3" stroke-linecap="round"
                        stroke-linejoin="round" />
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

        <main style="max-width: 1500px; width: 100%; margin: 0 auto;">
            <div style="background: white;padding: 40px; min-height: 650px;">
                <div style="text-align: left; margin: 20px 0; font-size: 20px; font-weight: 600; color: #374151;">
                    Welcome, <?php echo htmlspecialchars($adminID); ?>!
                </div>
                <div style="text-align: right; margin: 20px 0;">
                    <form method="POST" action="generate_report.php">
                        <label for="month" style="margin-right: 10px;">Select Month:</label>
                        <input type="month" name="month" id="month" required
                            style="padding: 6px 10px; border-radius: 6px; border: 1px solid #ccc;">
                        <button type="submit"
                            style="padding: 8px 16px; background-color: #8458B3; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Generate Report
                        </button>
                    </form>
                </div>

                <div class="stats-grid">
                    <div class="main-dashboard" style="margin-top: -10px;">
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <div style="display: flex; align-items: center; gap: 24px; padding: 32px 40px; border-radius: 16px; background: #fafafa; min-height: 80px; min-width: 300px;"
                                data-status="pending">
                                <div
                                    style="width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; background: #fbbf24;">
                                </div>
                                <div style="font-size: 20px; font-weight: 500; color: #374151; flex: 1;">Pending</div>
                                <div class="count"
                                    style="font-size: 36px; font-weight: 700; color: #111827; margin-right: 16px;">
                                    <?php echo $statusCounts['pending']; ?>
                                </div>
                                <div class="percentage" style="font-size: 20px; color: #6b7280;">
                                    <?php echo $percentages['pending']; ?>%
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 24px; padding: 32px 40px; border-radius: 16px; background: #fafafa; min-height: 80px; min-width: 300px;"
                                data-status="for_correction">
                                <div
                                    style="width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; background: #f87171;">
                                </div>
                                <div style="font-size: 20px; font-weight: 500; color: #374151; flex: 1;">Failed (≤3.9)
                                </div>
                                <div class="count"
                                    style="font-size: 36px; font-weight: 700; color: #111827; margin-right: 16px;">
                                    <?php echo $statusCounts['for_correction']; ?>
                                </div>
                                <div class="percentage" style="font-size: 20px; color: #6b7280;">
                                    <?php echo $percentages['for_correction']; ?>%
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 24px; padding: 32px 40px; border-radius: 16px; background: #fafafa; min-height: 80px; min-width: 300px;"
                                data-status="completed">
                                <div
                                    style="width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; background: #34d399;">
                                </div>
                                <div style="font-size: 20px; font-weight: 500; color: #374151; flex: 1;">Completed</div>
                                <div class="count"
                                    style="font-size: 36px; font-weight: 700; color: #111827; margin-right: 16px;">
                                    <?php echo $statusCounts['completed']; ?>
                                </div>
                                <div class="percentage" style="font-size: 20px; color: #6b7280;">
                                    <?php echo $percentages['completed']; ?>%
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: center; align-items: center;">
                            <div style="position: relative; width: 350px; height: 350px;">
                                <canvas id="donutChart" width="350" height="350"></canvas>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                    <div style="font-size: 16px; color: #6b7280; margin-bottom: 8px;">Total PAPs</div>
                                    <div class="total-count" style="font-size: 42px; font-weight: 700; color: #111827;">
                                        <?php echo $statusCounts['total']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="right-panel">
                        <div class="stats-card">
                            <h3>Total Users</h3>
                            <div style="text-align: center; margin-bottom: 20px;">
                                <div style="font-size: 48px; font-weight: 700; color: #111827;">
                                    <?php echo $userStats['total']; ?>
                                </div>
                            </div>
                            <div class="user-stat-item">
                                <span class="user-stat-label">Evaluators</span>
                                <span class="user-stat-value"><?php echo $userStats['evaluators']; ?></span>
                            </div>
                            <div class="user-stat-item">
                                <span class="user-stat-label">End Users</span>
                                <span class="user-stat-value"><?php echo $userStats['end_users']; ?></span>
                            </div>
                        </div>

                        <!-- Unassigned Documents Card -->
                        <?php if ($unassignedDocs > 0): ?>
                            <div class="stats-card unassigned-card">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z"
                                            stroke="#92400e" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M14 2V8H20" stroke="#92400e" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    Unassigned Documents
                                </h3>
                                <div class="unassigned-number"><?php echo $unassignedDocs; ?></div>
                                <a href="admin-papseval.html" class="assign-link">Assign now</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recently Active Evaluators -->
                    <div class="stats-card-eval" style="flex: 1; max-width: 900px;">
                        <h3>
                            Recently Added Evaluators (<?php echo count($recentEvaluators); ?>)
                            <a href="admin-usrmgt.php" class="view-all-link">Manage users</a>
                        </h3>

                        <?php if (empty($recentEvaluators)): ?>
                            <div style="text-align: center; color: #6b7280; padding: 20px;">
                                No evaluators found
                            </div>
                        <?php else: ?>
                            <div class="recent-users-container" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($recentEvaluators as $evaluator): ?>
                                    <div class="recent-user-item">
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($evaluator['full_name']); ?>
                                            </div>
                                            <div class="user-designation">
                                                <?php echo htmlspecialchars($evaluator['designation']); ?>
                                            </div>
                                        </div>
                                        <div class="user-status"><?php echo $evaluator['evaluated_paps']; ?> PAPs</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Inactive Evaluators -->
                    <div class="stats-card-eval" style="flex: 1; max-width: 450px;">
                        <h3>
                            Inactive Evaluators
                            <a href="admin-usrmgt.php" class="view-all-link">Manage users</a>
                        </h3>

                        <?php if (empty($inactiveEvaluators)): ?>
                            <div style="text-align: center; color: #6b7280; padding: 20px;">
                                No inactive evaluators
                            </div>
                        <?php else: ?>
                            <div class="recent-users-container" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($inactiveEvaluators as $evaluator): ?>
                                    <div class="recent-user-item">
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($evaluator['full_name']); ?>
                                            </div>
                                            <div class="user-designation">
                                                <?php echo htmlspecialchars($evaluator['designation']); ?>
                                            </div>
                                        </div>
                                        <div class="user-status"><?php echo $evaluator['evaluated_paps']; ?> PAPs</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
    </div>
    </div>
    </main>
    </div>

    <!-- CORRECTED: Pass PHP data to JavaScript -->
    <script>
        // Dashboard data from PHP - properly structured for your schema
        window.dashboardData = {
            pending: <?php echo $statusCounts['pending']; ?>,
            for_correction: <?php echo $statusCounts['for_correction']; ?>,
            completed: <?php echo $statusCounts['completed']; ?>,
            total: <?php echo $statusCounts['total']; ?>,
            percentages: {
                pending: <?php echo $percentages['pending']; ?>,
                for_correction: <?php echo $percentages['for_correction']; ?>,
                completed: <?php echo $percentages['completed']; ?>
            },
            userStats: {
                total: <?php echo $userStats['total']; ?>,
                evaluators: <?php echo $userStats['evaluators']; ?>,
                end_users: <?php echo $userStats['end_users']; ?>
            },
            unassignedDocs: <?php echo $unassignedDocs; ?>,
            hasNotifications: <?php echo $hasNotifications ? 'true' : 'false'; ?>,
            recentEvaluators: <?php echo json_encode($recentEvaluators); ?>
        };

        console.log('Dashboard Data loaded:', window.dashboardData);
    </script>

    <script src="baseUI.js"></script>
    <script src="adminnotifs.js"></script>
    <script src="dashboardUI.js"></script>
</body>

</html>