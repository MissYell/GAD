<?php
session_start(); // Start the session

// Check if the session variable is set
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Unauthorized access");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // or your password
$db = "gad_dbms";

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Count all users (admin + enduser + evaluator)
$sqlCount = "
    SELECT 
        (SELECT COUNT(*) FROM admin) +
        (SELECT COUNT(*) FROM enduser) +
        (SELECT COUNT(*) FROM evaluator) AS totalUsers
";

$resultCount = $mysqli->query($sqlCount);
$totalUsers = 0;

if ($resultCount && $row = $resultCount->fetch_assoc()) {
    $totalUsers = $row['totalUsers'];
}

// Retrieve adminID (stored in session)
$adminID = $_SESSION['user_id'];
$_SESSION['adminID'] = $adminID; // Explicitly set adminID in session

if (isset($_SESSION['adminID'])) {
    $adminID = $_SESSION['adminID'];
    $adminTooltip = htmlspecialchars($adminID);
} else {
    header("Location: index.php");
    exit();
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin-tables.css">
    <link rel="stylesheet" href="globalstyles.css">
    <link rel="stylesheet" href="header-admin.css">

    <title>GAD Management Information System</title>

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fafbfc;
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
            position: sticky;

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


        .title-and-filters {
            display: flex;
            align-items: center;
            gap: 48px;
            margin-top: 20px;
        }

        h2 {
            font-weight: 600;
            font-size: 28px;
            margin-left: 30px;
            margin-top: 15px;
            color: #111827;
            background: none;
            -webkit-background-clip: unset;
            -webkit-text-fill-color: unset;
            background-clip: unset;
        }

        .filter-options {
            display: flex;
            align-items: center;
            gap: 0;
        }

        /* Tab-style filter buttons */
        .filter-button {
            padding: 12px 0;
            margin-right: 32px;
            border: none;
            background: transparent;
            border-radius: 0;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            border-bottom: 2px solid transparent;
        }

        .filter-button::before {
            display: none;
        }

        .filter-button.active {
            background: transparent;
            color: #8458B3;
            box-shadow: none;
            transform: none;
            border-bottom: 2px solid #8458B3;
        }

        .filter-button:hover:not(.active) {
            background: transparent;
            color: #374151;
            transform: none;
        }

        /* Action buttons */
        .add-user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #8458B3;
            color: white;
            border: none;
            padding: 10px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .add-user-btn::before {
            display: none;
        }

        .add-user-btn:hover {
            background: #7043a0;
            transform: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .add-user-btn:hover::before {
            display: none;
        }

        .add-icon {
            width: 16px;
            height: 16px;
            border-radius: 0;
            background-color: transparent;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 16px;
            transition: none;
        }

        .add-user-btn:hover .add-icon {
            transform: none;
        }

        /* Controls section - horizontal layout beside user filters */
        .controls-section {
            background: transparent;
            padding: 0 24px 16px;
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            border: none;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 16px;
        }

        .left-controls {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .control-group {
            margin: 0;
        }

        /* Search input styling */
        .search-container {
            position: relative;
            display: flex;
            align-items: center;
            max-width: 100%;
        }

        .search-input {
            padding: 10px 12px 10px 36px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            width: 280px;
            transition: all 0.2s ease;
            font-weight: 400;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3e%3c/path%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: 12px center;
            background-size: 16px 16px;
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .search-input:focus {
            outline: none;
            border-color: #8458B3;
            box-shadow: 0 0 0 3px rgba(132, 88, 179, 0.1);
            transform: none;
        }

        .search-clear-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            font-size: 18px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            display: none;
        }

        .search-clear-btn:hover {
            color: #374151;
        }

        /* Date picker and filter styling */
        .control-select {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: all 0.2s ease;
            font-weight: 400;
            color: #374151;
            min-width: 140px;
        }

        .control-select:focus {
            outline: none;
            border-color: #8458B3;
            box-shadow: 0 0 0 3px rgba(132, 88, 179, 0.1);
            transform: none;
        }

        /* Table wrapper with scrollable functionality */
        .table-wrapper {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin: 0 24px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            position: relative;
        }

        /* Table container for horizontal and vertical scrolling */
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 63vh;
            position: relative;
        }

        /* Custom scrollbar styling */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
            border: 2px solid #f1f5f9;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .table-container::-webkit-scrollbar-corner {
            background: #f1f5f9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            min-width: 800px;
            /* Ensures table doesn't get too cramped on smaller screens */
        }

        /* Table headers - now sticky */
        table th {
            background: #f9fafb;
            padding: 12px 16px;
            text-align: left;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Table rows */
        table td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #111827;
            font-weight: 400;
            font-size: 14px;
            vertical-align: middle;
            transition: none;
        }

        table tr {
            transition: background-color 0.1s ease;
        }

        table tr:hover {
            background: #f9fafb;
            transform: none;
        }

        table tr:hover td {
            border-bottom-color: #f3f4f6;
        }

        /* User profile in table */
        .user-profile-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
            /* Prevents profile cell from being too narrow */
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 500;
            color: #111827;
            font-size: 14px;
        }

        .user-email {
            font-size: 12px;
            color: #6b7280;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            position: relative;
            overflow: visible;
            white-space: nowrap;
            /* Prevents badge text from wrapping */
        }

        .status-badge::before {
            display: none;
        }

        .status-badge:hover::before {
            display: none;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
            box-shadow: none;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
            box-shadow: none;
        }

        /* Service/Role badges */
        .service-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            margin-right: 4px;
            white-space: nowrap;
            /* Prevents badge text from wrapping */
        }

        .service-badge.salvage {
            background: #f3f4f6;
            color: #374151;
        }

        .service-badge.sr {
            background: #dbeafe;
            color: #1e40af;
        }

        .service-badge.hire {
            background: #dcfce7;
            color: #166534;
        }

        .service-badge.vd {
            background: #fef3c7;
            color: #92400e;
        }

        /* View/Action buttons */
        .view-btn {
            background: transparent;
            border: 1px solid #d1d5db;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            color: #374151;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            /* Prevents button text from wrapping */
        }

        .view-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
            transform: none;
            box-shadow: none;
        }

        /* Pagination footer */
        .pagination-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 24px;
            padding: 0;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            border: none;
        }

        .entries-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #6b7280;
            font-weight: 400;
        }

        .entries-info select {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-weight: 400;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .entries-info select:focus {
            outline: none;
            border-color: #8458B3;
            box-shadow: 0 0 0 3px rgba(132, 88, 179, 0.1);
        }

        .showing-info {
            font-size: 14px;
            color: #6b7280;
            font-weight: 400;
        }

        .pagination-nav {
            display: flex;
            gap: 2px;
        }

        .page-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #d1d5db;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 400;
            border-radius: 6px;
            color: #374151;
            transition: all 0.2s ease;
        }

        .page-btn:hover:not(:disabled) {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
            transform: none;
            box-shadow: none;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .page-btn.active {
            background: #8458B3;
            border-color: #8458B3;
            color: white;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 48px 20px;
            color: #6b7280;
            font-style: normal;
            font-size: 14px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin: 0 24px;
            border: 1px solid #e5e7eb;
        }

        /* Export button styling */
        .export-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-right: 12px;
        }

        .export-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Works on Chrome, Edge, Safari */
        body {
            overflow-y: scroll;
            /* keep scrolling */
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE/Edge Legacy */
        }

        body::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, Opera */
        }


        /* Responsive design */
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
                padding: 24px 16px 0;
            }

            .title-and-filters {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .controls-section {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 0 16px 16px;
            }

            .left-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .search-input {
                width: 100%;
            }

            .control-select {
                width: 100%;
            }

            .table-wrapper {
                margin: 0 16px;
            }

            .table-container {
                max-height: 50vh;
                /* Smaller max height on mobile */
            }

            .pagination-footer {
                margin: 16px;
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
        }
    </style>

</head>

<body>
    <div class="view" style="margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/logo.svg" alt="GAD Logo" width="80" height="80">
                </div>
            </div>
            <nav>
                <ul class="nav-menu">
                    <li><a href="adminhome.php" class="nav-item">Dashboard</a></li>
                    <li><a href="admin-usrmgt.php" class="nav-item active">Manage users</a></li>
                    <li><a href="admin-papseval.html" class="nav-item">Track PAPs</a></li>
                    <li><a href="adminscoresheet.php" class="nav-item">Scorecard</a></li>
                    <li><a href="admin-reports.php" class="nav-item">Reports</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <div class="header-icon-container">
                    <button class="icon-button" style="position: relative;" data-tooltip="<?php echo $adminTooltip; ?>"
                        onmouseover="this.querySelector('.tooltip').style.display='block'"
                        onmouseout="this.querySelector('.tooltip').style.display='none'">
                        <div class="tooltip"
                            style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                            <?php echo $adminTooltip; ?>
                        </div>
                        <span style="color: #8458B3; font-weight: 600; font-size: 14px;">Admin</span>
                    </button>

                    <span class="header-icon-text"></span>
                </div>
                <div class="header-icon-container">
                    <button class="icon-button" id="notification-button" title="Notifications">
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

        <div class="header-section">
            <div class="title-and-filters">
                <h2>All Users (<?php echo $totalUsers; ?>)</h2>
                <div class="filter-options">
                    <button class="filter-button active" data-filter="all">All users</button>
                    <button class="filter-button" data-filter="End User">End Users</button>
                    <button class="filter-button" data-filter="Evaluator">Evaluators</button>
                </div>
            </div>

        </div>

        <div> &nbsp;&nbsp;&nbsp;</div>

        <div class="controls-section">
            <div class="left-controls">
                <div class="control-group search-group">
                    <div class="search-container">
                        <input type="text" class="search-input" id="userSearchInput"
                            placeholder="Search users by name, email, or department...">
                        <button class="search-clear-btn" id="searchClearBtn" style="display: none;">×</button>
                    </div>
                </div>

                <div class="control-group">
                    <select class="control-select" id="sortSelect">
                        <option value="recently_joined" selected>Recently joined</option>
                        <option value="alphabetical">Alphabetical</option>
                    </select>
                </div>

                <div class="control-group">
                    <select class="control-select" id="departmentFilter">
                        <option value="">Filter by</option>
                        <option value="CAS">CIC</option>
                        <option value="CED">CED - College of Education</option>
                        <option value="CoE">CoE - College of Engineering</option>
                        <option value="CIC">CIC - College of Information and Computing</option>
                        <option value="CBA">CBA - College of Business Administration</option>
                        <option value="CAEc">CAEc - College of Applied Economics</option>
                        <option value="CoT">CT - College of Technology</option>
                    </select>
                </div>
            </div>

            <div>
                <button class="add-user-btn" id="addUserBtn">
                    <span class="add-icon">+</span>
                    Add Evaluator
                </button>
            </div>

            <div class="subheader-header">
                <div class="subheader-title"></div>

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

        <div class="table-section">
            <div class="table-wrapper">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Designation</th>
                                <th>Date Joined</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>

                        <tbody id="userTableBody">

                        </tbody>
                    </table>
                </div>
            </div>

            <div class="no-results" id="noResults" style="display: none;">
                No users match the selected filter
            </div>
        </div>

        <!-- New Pagination Footer -->
        <div class="pagination-footer">
            <div class="entries-info">
                <span>Show</span>
                <select id="entriesDropdownFooter"
                    style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="6" selected>6</option>
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
                <span>entries</span>
            </div>

            <div class="showing-info">
                showing <span id="startEntry">1</span> to <span id="endEntry">6</span> out of <span
                    id="totalEntries">6</span> entries
            </div>

            <div class="pagination-nav">
                <button class="page-btn" id="firstPageBtn" title="First page">«</button>
                <button class="page-btn" id="prevPageBtn" title="Previous page">‹</button>
                <button class="page-btn" id="nextPageBtn" title="Next page">›</button>
                <button class="page-btn" id="lastPageBtn" title="Last page">»</button>
            </div>
        </div>
    </div>

    <!-- Keeping all existing modals -->
    <div id="userDetailsModal" class="modal-usermgt">
        <div class="modal-content-usermgt" style="margin: 12% auto;">
            <div class="modal-header-usermgt">
                <h3>User Account Information</h3>
                <button class="close-btn" onclick="closeUserDetailsModal()">×</button>
            </div>
            <div class="modal-body-usermgt">
                <div class="user-profile">
                    <div class="user-avatar">
                        <img src="img/profile-icon.svg" alt="Profile Icon" />
                    </div>
                    <div style="flex: 1;">
                        <div class="user-info">
                            <span id="detailFullname"></span>
                        </div>
                        <div class="user-info">
                            <span id="detailEmail"></span>
                        </div>
                        <div class="user-info">
                            <span id="detailUserGroup"></span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="user-info">
                        <span class="user-info-label">Department:</span>
                        <span id="detailDepartment"></span>
                    </div>

                    <div class="user-info">
                        <span class="user-info-label">Specialization:</span>
                        <span id="detailSpecialization"></span>
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn" id="deleteUserBtn">Delete user</button>
                    <button class="btn" id="editUserBtn">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <div id="addUserModal" class="modal-usermgt">
        <div class="modal-content-usermgt">
            <div class="modal-header-usermgt">
                <h3>Add New User</h3>
                <button class="close-btn" onclick="closeAddUserModal()">×</button>
            </div>
            <div class="modal-body-usermgt">
                <div class="form-group-usermgt">
                    <label for="lastName">Last Name</label>
                    <input type="text" class="form-control-usermgt" id="lastName" placeholder="Enter last name"
                        required>
                </div>

                <div class="form-group-usermgt">
                    <label for="firstName">First Name</label>
                    <input type="text" class="form-control-usermgt" id="firstName" placeholder="Enter first name"
                        required>
                </div>

                <div class="form-group-usermgt">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control-usermgt" id="email" placeholder="Enter email" required>
                </div>

                <div class="form-group-usermgt">
                    <label for="department">Department</label>
                    <select class="form-select-usermgt" id="department">
                        <option selected disabled>Select Department</option>
                        <option value="CAS">CAS - College of Arts and Sciences</option>
                        <option value="CED">CED - College of Education</option>
                        <option value="CoE">CoE - College of Engineering</option>
                        <option value="CIC">CIC - College of Information and Computing</option>
                        <option value="CBA">CBA - College of Business Administration</option>
                        <option value="CAEc">CAEc - College of Applied Economics</option>
                        <option value="CoT">CT - College of Technology</option>
                    </select>
                </div>

                <div class="form-group-usermgt">
                    <label for="specialization">Specialization</label>
                    <input type="text" class="form-control-usermgt" id="specialization"
                        placeholder="Enter specialization" required>
                </div>

                <div class="form-group-usermgt">
                    <label for="userGroup">User Group</label>
                    <select class="form-select-usermgt" id="userGroup">
                        <option selected disabled>Select User Group</option>
                        <option value="Evaluator">Evaluator</option>
                    </select>
                </div>

                <div class="form-group-usermgt">
                    <label for="sex">Sex</label>
                    <select class="form-select-usermgt" id="sex" required>
                        <option selected disabled>Select Sex</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div class="form-group-usermgt">
                    <label for="contactNo">Contact No.</label>
                    <input type="text" class="form-control-usermgt" id="contactNo" placeholder="Enter contact number"
                        required>
                </div>

                <button class="btn" id="addUserSubmitBtn">Add</button>
            </div>
        </div>
    </div>

    <div id="editUserModal" class="modal-usermgt">
        <div class="modal-content-usermgt">
            <div class="modal-header-usermgt">
                <h3>Edit User</h3>
                <button class="close-btn" onclick="closeEditUserModal()">×</button>
            </div>
            <div class="modal-body-usermgt">
                <input type="hidden" id="editUserId">

                <div class="form-group-usermgt">
                    <label for="editLastName">Last Name</label>
                    <input type="text" class="form-control-usermgt" id="editLastName">
                </div>

                <div class="form-group-usermgt">
                    <label for="editFirstName">First Name</label>
                    <input type="text" class="form-control-usermgt" id="editFirstName">
                </div>

                <div class="form-group-usermgt">
                    <label for="editEmail">Email Address</label>
                    <input type="email" class="form-control-usermgt" id="editEmail">
                </div>

                <div class="form-group-usermgt">
                    <label for="editDepartment">Department</label>
                    <select class="form-select-usermgt" id="editDepartment">
                        <option selected disabled>Select Department</option>
                        <option value="CAS">CAS - College of Arts and Sciences</option>
                        <option value="CED">CED - College of Education</option>
                        <option value="CoE">CoE - College of Engineering</option>
                        <option value="CIC">CIC - College of Information and Computing</option>
                        <option value="CBA">CBA - College of Business Administration</option>
                        <option value="CAEc">CAEc - College of Applied Economics</option>
                        <option value="CoT">CT - College of Technology</option>
                    </select>
                </div>

                <div class="form-group-usermgt">
                    <label for="editSpecialization">Specialization</label>
                    <input type="text" class="form-control-usermgt" id="editSpecialization">
                </div>

                <div class="form-group-usermgt">
                    <label for="editUserGroup">User Group</label>
                    <select class="form-select-usermgt" id="editUserGroup">
                        <option selected disabled>Select User Group</option>
                        <option value="Evaluator">Evaluator</option>
                    </select>
                </div>
                <div class="form-group-usermgt">
                    <label for="editSex">Sex</label>
                    <select class="form-select-usermgt" id="editSex" required>
                        <option selected disabled>Select Sex</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div class="form-group-usermgt">
                    <label for="editContactNo">Contact No.</label>
                    <input type="text" class="form-control-usermgt" id="editContactNo"
                        placeholder="Enter contact number" required>
                </div>
                <button class="btn" id="saveEditUserBtn">Save</button>
            </div>
        </div>
    </div>

    <div id="deleteConfirmationModal" class="modal-usermgt"
        style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content-usermgt"
            style="text-align: center; padding: 20px; position: relative; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 30%; max-width: 90vw; margin: 12% auto;">
            <h2 style="margin-bottom: 10px;">Delete user</h2>
            <button class="close-btn" onclick="closeDeleteConfirmationModal()"
                style="position: absolute; top: 10px; right: 15px; font-size: 20px; background: none; border: none; cursor: pointer;">×</button>

            <div class="trash-icon" style="margin: 20px 0;">
                <img src="img/trash-icon.svg" alt="Delete Icon" style="width: 60px; height: 60px;">
            </div>
            <p style="margin-bottom: 20px;">Are you sure you want to delete this user?</p>
            <div class="confirmation-buttons">
                <button class="btn-confirm" id="confirmDeleteBtn">Yes</button>
                <button class="btn-cancel" onclick="closeDeleteConfirmationModal()">No</button>
            </div>
        </div>
    </div>

    <script src="baseUI.js"></script>
    <script src="admin-usermngmt.js"></script>
    <script>
        // Sort functionality
        const tbody = document.getElementById('userTableBody');

        function sortRows() {
            const sortType = document.getElementById('sortSelect').value;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                switch (sortType) {
                    case 'alphabetical':
                        const nameA = a.querySelector('td:first-child').textContent.trim().toLowerCase();
                        const nameB = b.querySelector('td:first-child').textContent.trim().toLowerCase();
                        return nameA.localeCompare(nameB);

                    case 'recently_joined':
                        const dateA = new Date(a.querySelector('td:nth-child(4)').textContent);
                        const dateB = new Date(b.querySelector('td:nth-child(4)').textContent);
                        return dateB - dateA; // newest first
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        document.getElementById('sortSelect').addEventListener('change', sortRows);

        // Search functionality
        const searchInput = document.getElementById('userSearchInput');
        const noResultsDiv = document.getElementById('noResults');

        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.trim().toLowerCase();
            let visibleCount = 0;

            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.forEach(row => {
                const cells = Array.from(row.querySelectorAll('td'));
                const rowText = cells.map(cell => cell.textContent.trim().toLowerCase()).join(' ');

                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show "no results" message if nothing matches
            noResultsDiv.style.display = visibleCount === 0 ? 'block' : 'none';
        });

        // Optional: clear button functionality
        const clearBtn = document.getElementById('searchClearBtn');
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            clearBtn.style.display = 'none';
        });

        searchInput.addEventListener('input', () => {
            clearBtn.style.display = searchInput.value ? 'flex' : 'none';
        });

    </script>

</body>

</html>