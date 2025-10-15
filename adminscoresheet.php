<?php
session_start();
$conn = new mysqli("localhost", "root", "", "gad_dbms");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the latest version
$versionQuery = "SELECT versionID FROM ScoresheetVersions ORDER BY dateAdministered DESC LIMIT 1";
$versionResult = $conn->query($versionQuery);
$latestVersion = $versionResult->fetch_assoc()['versionID'] ?? '';

if (!$latestVersion) {
    echo "No versions found.";
    exit;
}

// Get all itemIDs linked to this version
$sql = "
    SELECT s.*
    FROM ScoresheetVersions sv
    JOIN Scoresheet s ON sv.itemID = s.itemID
    WHERE sv.versionID = '$latestVersion'
    ORDER BY CAST(SUBSTRING_INDEX(s.item, '.', 1) AS UNSIGNED), s.itemID
";
$result = $conn->query($sql);

// Group by item → subitems
$scoresheet = [];
while ($row = $result->fetch_assoc()) {
    $item = $row['item'];
    $subitem = $row['subitem'];

    if (!isset($scoresheet[$item])) {
        $scoresheet[$item] = [
            'parent' => $row,
            'subitems' => [],
        ];
    }

    if (!empty($subitem)) {
        $scoresheet[$item]['subitems'][] = $row;
    }
}

    if (isset($_SESSION['adminID'])) {
        $adminID = $_SESSION['adminID'];
        $adminTooltip = htmlspecialchars($adminID);
    } else {
        echo "Admin ID is not set in the session.";
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scoresheet - Version <?= htmlspecialchars($latestVersion) ?></title>
    <link rel="stylesheet" href="globalstyles.css">
    <link rel="stylesheet" href="admin-tables.css">
    <link rel="stylesheet" href="header-admin.css">

    <style>
    
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



    </style>
   
</head>
<body>
        <div class="view" style="min-height: 100vh; margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header">
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
                    <li><a href="adminscoresheet.php" class="nav-item active">Scorecard</a></li>
                    <li><a href="admin-reports.php" class="nav-item">Reports</a></li>
                </ul>
            </nav>
            
           <div class="header-actions">
                  <button class="icon-button" style="position: relative;" data-tooltip="<?php echo $adminTooltip; ?>" 
                    onmouseover="this.querySelector('.tooltip').style.display='block'" 
                        onmouseout="this.querySelector('.tooltip').style.display='none'">
                     <div class="tooltip" style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                        <?php echo $adminTooltip; ?>
                    </div>
                    <span style="color: #8458B3; font-weight: 600; font-size: 14px;">Admin</span>
                </button>

                <div class="header-icon-container">
                    <button class="icon-button" id="notification-button" title="Notifications">
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
    
    
        <div class="subheader-container">
            <div class="subheader-header">
            </div>
        </div>

        <a href="manage_scoresheet.php" title="Edit Scoresheet"
        style="position: fixed;bottom: 20px;right: 20px;background-color: #c8b7d8;border-radius: 50%;width: 50px;height: 50px;display: flex;align-items: center;justify-content: center;box-shadow: 0 4px 10px rgba(0,0,0,0.2);z-index: 1000;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="black" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM21.41 6.34a1.25 1.25 0 0 0 0-1.77l-2-2a1.25 1.25 0 0 0-1.77 0L15.13 4.1l3.75 3.75 2.53-2.51z"/>
            </svg>
        </a>

        <div class="table-container">
        <table class="sticky-header">
        <thead>
            <tr>
            <th>Elements and Items/Questions</th>
            <th style="width: 7%;">Yes</th>
            <th style="width: 7%;">No</th>
            <th style="width: 7%;">Partly</th>
            <th style="width: 7%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($scoresheet as $item => $group): ?>
            <tr>
                <td><strong><?= htmlspecialchars($item) ?></strong></td>
        <?php if (count($group['subitems']) === 0): ?>
        <td><input type="hidden" name="subitem_<?= $group['parent']['itemID'] ?>" value="<?= $group['parent']['yesValue'] ?>"></td>
        <td><input type="hidden" name="subitem_<?= $group['parent']['itemID'] ?>" value="<?= $group['parent']['noValue'] ?>"></td>
        <td><input type="hidden" name="subitem_<?= $group['parent']['itemID'] ?>" value="<?= $group['parent']['partlyValue'] ?>"></td>
        <td></td>
        <?php else: ?>
        <td colspan="3" style="text-align: center;"></td>
        <td></td>
        <?php endif; ?>
                </td>
                <td></td>
            </tr>

        <?php 
        usort($group['subitems'], function($a, $b) {
            // Extract numeric values from subitems like "3.1"
            $aVal = floatval(preg_replace('/[^0-9.]/', '', $a['subitem']));
            $bVal = floatval(preg_replace('/[^0-9.]/', '', $b['subitem']));
            return $aVal <=> $bVal;
        });

            foreach ($group['subitems'] as $sub): ?>
                <tr>
                <td><?= htmlspecialchars($sub['subitem']) ?></td>
                <td><input type="hidden" name="subitem_<?= $sub['itemID'] ?>" value="<?= $sub['yesValue'] ?>"></td>
                <td><input type="hidden" name="subitem_<?= $sub['itemID'] ?>" value="<?= $sub['noValue'] ?>"></td>
                <td><input type="hidden" name="subitem_<?= $sub['itemID'] ?>" value="<?= $sub['partlyValue'] ?>"></td>
                <td></td>
                </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
        </table>
</div> 
   <script src="baseUI.js"></script>
    <script src="adminnotifs.js"></script>
  
</body>
</html>