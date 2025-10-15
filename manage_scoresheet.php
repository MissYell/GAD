<?php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=gad_dbms', 'root', '');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=Unauthorized access");
    exit();
}

// Retrieve adminID (now stored as user_id in session)
$adminID = $_SESSION['user_id']; 

$_SESSION['adminID'] = $adminID; // Explicitly set adminID in session
// Load latest version items if not already loaded
if (!isset($_SESSION['scoresheet']) && !isset($_SESSION['cleared'])) {
    $stmt = $pdo->query("
        SELECT s.* FROM Scoresheet s
        JOIN (
            SELECT itemID FROM ScoresheetVersions 
            WHERE versionID = (
                SELECT versionID FROM ScoresheetVersions 
                ORDER BY dateAdministered DESC LIMIT 1
            )
        ) AS latest ON latest.itemID = s.itemID
    ");
    $_SESSION['scoresheet'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $_SESSION['deleted'] = [];
}

// Function to extract number from item text for sorting
function extractItemNumber($item) {
    if (preg_match('/(\d+)/', $item, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

// Sort scoresheet items by item number
if (isset($_SESSION['scoresheet']) && is_array($_SESSION['scoresheet'])) {
    usort($_SESSION['scoresheet'], function($a, $b) {
        $numA = extractItemNumber($a['item']);
        $numB = extractItemNumber($b['item']);
        return $numA - $numB;
    });
}

// Add item
if (isset($_POST['add'])) {
    $_SESSION['scoresheet'][] = [
        'itemID' => $_POST['itemID'],
        'item' => $_POST['item'],
        'subitem' => $_POST['subitem'],
        'yesValue' => $_POST['yesValue'],
        'noValue' => $_POST['noValue'],
        'partlyValue' => $_POST['partlyValue'],
        'adminID' => $adminID,
        'new' => true
    ];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Delete item
if (isset($_POST['delete'])) {
    $_SESSION['deleted'][] = $_POST['delete'];
    $_SESSION['scoresheet'] = array_filter($_SESSION['scoresheet'], function ($item) {
        return $item['itemID'] !== $_POST['delete'];
    });

    // If nothing remains, mark as cleared
    if (empty($_SESSION['scoresheet'])) {
        $_SESSION['cleared'] = true;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Edit item
if (isset($_POST['edit_apply'])) {
    foreach ($_SESSION['scoresheet'] as &$item) {
        if ($item['itemID'] === $_POST['itemID']) {
            $item['item'] = $_POST['item'];
            $item['subitem'] = $_POST['subitem'];
            $item['yesValue'] = $_POST['yesValue'];
            $item['noValue'] = $_POST['noValue'];
            $item['partlyValue'] = $_POST['partlyValue'];
            $item['edited'] = true;
            break;
        }
    }
    unset($item);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Save to DB
if (isset($_POST['save'])) {
    // Generate a single versionID for all items
    $versionID = 'V' . strtoupper(uniqid());
    $dateNow = date('Y-m-d H:i:s');

    foreach ($_SESSION['scoresheet'] as $item) {
        // Insert or update Scoresheet item
        $insert = $pdo->prepare("
            INSERT INTO Scoresheet (itemID, item, subitem, yesValue, noValue, partlyValue, adminID)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                item = VALUES(item),
                subitem = VALUES(subitem),
                yesValue = VALUES(yesValue),
                noValue = VALUES(noValue),
                partlyValue = VALUES(partlyValue),
                adminID = VALUES(adminID)
        ");
        $insert->execute([
            $item['itemID'], $item['item'], $item['subitem'],
            $item['yesValue'], $item['noValue'], $item['partlyValue'],
            $adminID
        ]);

        // Insert into ScoresheetVersions
        $insertVer = $pdo->prepare("
            INSERT INTO ScoresheetVersions (versionID, itemID, adminID, dateAdministered)
            VALUES (?, ?, ?, ?)
        ");
        $insertVer->execute([
            $versionID,
            $item['itemID'],
            $adminID,
            $dateNow
        ]);
    }

    echo "<script>alert('New version $versionID created successfully.');</script>";

    // Clear session
    unset($_SESSION['scoresheet']);
    unset($_SESSION['deleted']);
    unset($_SESSION['cleared']);
    // Redirect to admin scoresheet view
    header("Location: adminscoresheet.php");
    exit;
}

    if (isset($_SESSION['adminID'])) {
        $adminID = $_SESSION['adminID'];
        $adminTooltip = htmlspecialchars($adminID);
    } else {
        echo "Admin ID is not set in the session.";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="globalstyles.css">
    <link rel="stylesheet" href="admin-tables.css">
    <link rel="stylesheet" href="header-admin.css">
    <title>Manage Scoresheet</title>
    <style>
       .table-container{max-height:550px;overflow-y:auto;margin:0 20px 10px 20px;border:1px solid #ddd;flex:1}.table-body-container{max-height:calc(10 * 53px);overflow-y:auto}table{width:100%;table-layout:fixed;border-collapse:collapse}th,td{padding:10px;text-align:left;border:1px solid #ddd;word-wrap:break-word;word-break:break-word;white-space:normal}th{background-color:#c8b7d8;color:#000;position:sticky;top:0;z-index:10;text-align:center}.main-category{background-color:#f5f5f5;font-weight:700}.sub-item{background-color:#f9f9f9;border-left:4px solid #8458B3}.sub-item td:first-child{padding-left:20px;color:#666}.action-buttons{display:flex;gap:5px;flex-direction:column}.action-buttons form{margin:0}.action-buttons button{padding:5px 10px;cursor:pointer;border:1px solid #ddd;background-color:#f8f9fa;border-radius:3px;font-size:12px;width:100%}.action-buttons button:hover{background-color:#e9ecef}form{margin-bottom:0}.form-group{margin-bottom:10px}.form-group label{display:inline-block;width:150px;vertical-align:top;padding-top:5px}.form-group input,.form-group textarea{width:calc(100% - 160px);min-height:35px;padding:5px;box-sizing:border-box;resize:vertical;overflow-wrap:break-word;word-wrap:break-word;word-break:break-word;font-family:inherit;font-size:14px;border-radius:3px}.form-group input[type=number]{resize:none}.additem-modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.4)}.additem-modal-content{background-color:#fefefe;margin:5% auto;padding:20px;border:1px solid #888;max-width:700px;max-height:80vh;border-radius:5px;overflow-y:auto}.response-columns{text-align:center}.close{color:#aaa;float:right;font-size:28px;font-weight:700}.close:hover,.close:focus{color:#000;text-decoration:none;cursor:pointer}.sticky-header th{position:sticky;top:0;background-color:#c8b7d8;z-index:1}.footer{padding:10px 20px;display:flex;justify-content:flex-end;gap:10px;margin-top:-10px}.edit-input,.edit-input-textarea{width:100%;padding:5px;border:1px solid #ddd;border-radius:3px;white-space:pre-wrap;word-wrap:break-word;word-break:break-word;overflow-wrap:break-word;resize:vertical;min-height:35px;max-height:150px;overflow-y:auto;font-family:inherit;font-size:14px;line-height:1.4;box-sizing:border-box}.edit-input-number{width:100%;padding:5px;border:1px solid #ddd;border-radius:3px;min-height:35px;font-family:inherit;font-size:14px;box-sizing:border-box;text-align:center}.item-description{font-size:.9em;color:#666;font-style:italic;margin-top:3px}
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
<body style="overflow: hidden;">
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
                    <li><a href="admin-usrmgt.php" class="nav-item">Manage users</a></li>
                    <li><a href="admin-papseval.html" class="nav-item">Track PAPs</a></li>
                    <li><a href="adminscoresheet.php" class="nav-item active">Scorecard</a></li>
                </ul>
            </nav>
            
           <div class="header-actions">
                <div class="header-icon-container">
                    <button class="icon-button" style="position: relative;" data-tooltip="<?php echo $adminTooltip; ?>" 
                    onmouseover="this.querySelector('.tooltip').style.display='block'" 
                        onmouseout="this.querySelector('.tooltip').style.display='none'">
                     <div class="tooltip" style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                        <?php echo $adminTooltip; ?>
                    </div>
                    <span style="color: #8458B3; font-weight: 600; font-size: 14px;">Admin</span>
                </button>
                    <span class="header-icon-text"></span>
                </div>
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
                <div class="header-left">
                    <a href="adminscoresheet.php" class="back-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 11H7.83L13.42 5.41L12 4L4 12L12 20L13.41 18.59L7.83 13H20V11Z" fill="#333333"/>
                        </svg>
                    </a>
                    <div class="subheader-title">Manage Scoresheet</div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead class="sticky-header">
                    <tr>
                        <th style="width: 8%;">Item ID</th>
                        <th style="width: 35%;">Item</th>
                        <th style="width: 25%;">Subitem</th>
                        <th style="width: 6%;" class="response-columns">Yes</th>
                        <th style="width: 6%;" class="response-columns">No</th>
                        <th style="width: 7%;" class="response-columns">Partly</th>
                        <th style="width: 8%;">Actions</th>
                    </tr>
                </thead>
                <tbody class="table-body-container">
                    <?php if (isset($_SESSION['scoresheet']) && is_array($_SESSION['scoresheet'])): ?>
                        <?php foreach ($_SESSION['scoresheet'] as $item): ?>
                           <?php if (isset($_POST['edit']) && $_POST['edit'] === $item['itemID']): ?>
                <form method="post">
                    <tr>
                        <td>
                            <?= htmlspecialchars($item['itemID']) ?>
                            <input type="hidden" name="itemID" value="<?= htmlspecialchars($item['itemID']) ?>">
                        </td>
                        <td>
                            <textarea name="item" class="edit-input-textarea auto-resize" 
                                    placeholder="Enter item text..."><?= htmlspecialchars($item['item']) ?></textarea>
                        </td>
                        <td>
                            <textarea name="subitem" class="edit-input-textarea auto-resize" 
                                    placeholder="Enter subitem text..."><?= htmlspecialchars($item['subitem']) ?></textarea>
                        </td>
                        <td>
                            <input name="yesValue" class="edit-input-number" type="number" step="0.01" 
                                value="<?= htmlspecialchars($item['yesValue']) ?>">
                        </td>
                        <td>
                            <input name="noValue" class="edit-input-number" type="number" step="0.01" 
                                value="<?= htmlspecialchars($item['noValue']) ?>">
                        </td>
                        <td>
                            <input name="partlyValue" class="edit-input-number" type="number" step="0.01" 
                                value="<?= htmlspecialchars($item['partlyValue']) ?>">
                        </td>
                        <td class="action-buttons">
                            <button name="edit_apply" type="submit">✅ Save</button>
                            <button type="button" onclick="cancelEdit()">❌ Cancel</button>
                        </td>
                    </tr>
                </form>
            <?php else: ?>
                <tr class="<?= !empty($item['subitem']) ? 'sub-item' : 'main-category' ?>">
                    <td><?= htmlspecialchars($item['itemID']) ?></td>
                    <td><?= htmlspecialchars($item['item']) ?></td>
                    <td><?= htmlspecialchars($item['subitem']) ?></td>
                    <td class="response-columns"><?= htmlspecialchars($item['yesValue']) ?></td>
                    <td class="response-columns"><?= htmlspecialchars($item['noValue']) ?></td>
                    <td class="response-columns"><?= htmlspecialchars($item['partlyValue']) ?></td>
                    <td>
                        <div class="action-buttons">
                            <form method="post" style="display:inline">
                                <button name="edit" value="<?= htmlspecialchars($item['itemID']) ?>">✏️ Edit</button>
                            </form>
                            <form method="post" style="display:inline">
                                <button name="delete" value="<?= htmlspecialchars($item['itemID']) ?>" 
                                        onclick="return confirm('Delete this item?')">🗑️ Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 20px;">No items to display</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
           <button type="button" id="addItemBtn" style="background-color: #8458B3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;" onmouseover="this.style.backgroundColor='#6a4a8d'" onmouseout="this.style.backgroundColor='#8458B3'">Add New Item</button>
            <form method="post" style="display: inline;">
            <button type="submit" name="save" style="background-color: #8458B3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;" onmouseover="this.style.backgroundColor='#6a4a8d'" onmouseout="this.style.backgroundColor='#8458B3'" onclick="return confirm('Save changes and create new version?')">Save Changes</button>
            </form>
        </div>
        
        </div>
    </div>

   <!-- Add Item Modal -->
<div id="addModal" class="additem-modal">
    <div class="additem-modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="text-align: center;">Add New Item</h2>
        <form method="post">
            <div class="form-group">
                <label for="itemID">Item ID:</label>
                <textarea id="itemID" name="itemID" rows="1" required style="margin-left: 155px;"></textarea>
            </div>

            <div class="form-group">
                <label for="item">Item Text:</label>
                <textarea id="item" name="item" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label for="subitem">Subitem:</label>
                <textarea id="subitem" name="subitem" rows="1"></textarea>
            </div>

          <div class="form-row">
            <div class="form-group">
                <label for="yesValue">Yes Value:</label>
                <input type="number" id="yesValue" name="yesValue" value="" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="noValue">No Value:</label>
                <input type="number" id="noValue" name="noValue" value="" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="partlyValue">Partly Value:</label>
                <input type="number" id="partlyValue" name="partlyValue" value="" step="0.01" required>
            </div>
            
           
            <div style="text-align: right; margin-top: 20px;">
            <button type="button" onclick="closeAddModal()" style="margin-right: 10px; padding: 8px 15px; background-color: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;" onmouseover="this.style.backgroundColor='#5a6268'" onmouseout="this.style.backgroundColor='#6c757d'">Cancel</button>
            <button type="submit" name="add" style="padding: 8px 15px; background-color: #8458B3; color: white; border: none; border-radius: 3px; cursor: pointer;" onmouseover="this.style.backgroundColor='#6a4a8d'" onmouseout="this.style.backgroundColor='#8458B3'">Add Item</button>
            </div>
        </form>
    </div>
</div>
    <script src="baseUI.js"></script>
    <script src="adminnotifs.js"></script>

    <script>
    const currentUserID = "<?php echo $_SESSION['user_id']; ?>";
    const currentUserType = "<?php echo $_SESSION['role']; ?>";
    </script>

    <script>
            // Auto-resize textarea functionality
        function autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
        }

        // Initialize auto-resize for existing textareas
        function initializeAutoResize() {
            const textareas = document.querySelectorAll('.auto-resize');
            textareas.forEach(textarea => {
                autoResizeTextarea(textarea);
                
                textarea.addEventListener('input', function() {
                    autoResizeTextarea(this);
                });
                
                textarea.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'Enter') {
                        e.preventDefault();
                        const cursorPos = this.selectionStart;
                        const textBefore = this.value.substring(0, cursorPos);
                        const textAfter = this.value.substring(cursorPos);
                        this.value = textBefore + '\n' + textAfter;
                        this.setSelectionRange(cursorPos + 1, cursorPos + 1);
                        autoResizeTextarea(this);
                    }
                });
            });
        }

        function cancelEdit() {
            history.back();
        
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeAutoResize();
        });


        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    initializeAutoResize();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        document.getElementById('addItemBtn').addEventListener('click', function() {
            document.getElementById('addModal').style.display = 'block';
            setTimeout(() => {
                initializeAutoResize();
            }, 100);
        });

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            
            if (event.target == addModal) {
                closeAddModal();
            }
        }
    </script>
</body>
</html>