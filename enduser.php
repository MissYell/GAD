<?php
session_start(); // Start the sessio
include 'db_connection.php';

// Ensure the user is logged in via session
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userID = ($_SESSION['user_id']);

// Retrieve user details from DB using email
$email = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT * FROM EndUser WHERE userID = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $userID = $user['userID'];
    $fname = $user['fname'] ?? '';
    $lname = $user['lname'] ?? '';
    $fullName = trim($fname . ' ' . $lname);
} else {
    // Session corrupted or user not found
    session_destroy();
    header("Location: index.php?error=Session expired");
    exit();
}

// Search   
$search = isset($_GET['search']) ? trim($_GET['search']) : '';


if ($search !== '') {
    $searchParam = '%' . $search . '%';
    $stmt = $mysqli->prepare("
        SELECT p.*, 
               EXISTS (
                   SELECT 1 FROM Certification c WHERE c.papsID = p.papsID
               ) AS hasCertificate
        FROM paps p
        WHERE p.userID = ? AND p.title LIKE ?
        ORDER BY p.dateSubmitted DESC
    ");
    $stmt->bind_param("ss", $email, $searchParam);
} else {
    $stmt = $mysqli->prepare("
        SELECT p.*, 
               EXISTS (
                   SELECT 1 FROM Certification c WHERE c.papsID = p.papsID
               ) AS hasCertificate
        FROM paps p
        WHERE p.userID = ?
        ORDER BY p.dateSubmitted DESC
    ");
    $stmt->bind_param("s", $email);
}

$stmt->execute();
$result = $stmt->get_result();

$paps = [];
while ($row = $result->fetch_assoc()) {
    // Make sure 'hasCertificate' is int (0 or 1)
    $row['hasCertificate'] = (int) $row['hasCertificate'];
    $paps[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="endUser.css">
    <link rel="stylesheet" href="globalstyles.css">
    <title>GAD End-User Dashboard</title>

</head>

<body
    style="margin:0; padding:0; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f8fafc; color:#334155; line-height:1.5;">
    <div class="container" style="margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/logo.svg" alt="GAD Logo" width="80" height="80">
                </div>
            </div>
            <div class="header-actions">
                <div class="header-icon-container">
                    <button class="icon-button" title="Profile">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
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

        <div class="evaluator-modal" id="evaluator-modal">
            <div class="evaluator-modal-content">
                <div class="evaluator-options">
                    <a href="user-profile.php" class="evaluator-option" id="edit-profile-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M18.5 2.50001C18.8978 2.10219 19.4374 1.87869 20 1.87869C20.5626 1.87869 21.1022 2.10219 21.5 2.50001C21.8978 2.89784 22.1213 3.4374 22.1213 4.00001C22.1213 4.56262 21.8978 5.10219 21.5 5.50001L12 15L8 16L9 12L18.5 2.50001Z"
                                stroke="#8458B3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Edit profile
                    </a>
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
                <svg width="40" height="40" viewBox="0 0 s40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M15 35H8.33333C7.44928 35 6.60143 34.6488 5.97631 34.0237C5.35119 33.3986 5 32.5507 5 31.6667V8.33333C5 7.44928 5.35119 6.60143 5.97631 5.97631C6.60143 5.35119 7.44928 5 8.33333 5H15"
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
                <button class="logout-confirm-btn" id="logout-confirm-btn">Log Out</button>
            </div>
        </div>
        <div class="overlay" id="overlay"></div>


        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($fname ?: 'User'); ?>!</h1>
            <p>Manage your submitted documents and track their progress</p>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="search-box">
                <form method="get" action="enduser.php">
                    <input type="search" class="search-input" name="search" placeholder="Search your documents..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
            <button class="upload-docbtn" onclick="showUploadForm()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" />
                    <polyline points="17,8 12,3 7,8" stroke="currentColor" stroke-width="2" />
                    <line x1="12" y1="3" x2="12" y2="15" stroke="currentColor" stroke-width="2" />
                </svg>
                Upload Document
            </button>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active">All</button>
            <button class="filter-tab">Completed</button>
            <button class="filter-tab">Pending</button>
        </div>

        <!-- Files Section -->
        <div class="files-section">
            <div class="files-header">
                <div class="files-title">My Documents</div>
            </div>

            <?php if (!empty($paps)): ?>
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>Document Title</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paps as $pap): ?>
                            <tr data-has-cert="<?= (int) $pap['hasCertificate'] ?>"
                                data-papsid="<?= htmlspecialchars($pap['papsID']) ?>">
                                <td>
                                    <a class="file-link" href="<?= htmlspecialchars($pap['fileLink']) ?>" target="_blank"
                                        rel="noopener noreferrer">
                                        <?= htmlspecialchars($pap['title']) ?>
                                    </a>
                                </td>
                                <td><?= date('M j, Y', strtotime($pap['dateSubmitted'])) ?></td>
                                <td>
                                    <?php
                                    $statusRaw = strtolower(trim($pap['status']));
                                    $statusText = ucfirst($statusRaw);
                                    $statusClass = match ($statusRaw) {
                                        'completed', 'approved' => 'status-completed',
                                        'pending' => 'status-pending',
                                        'unassigned' => 'status-unassigned',
                                        default => 'status-unassigned'
                                    };
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="view-btn view-button" data-title="<?= htmlspecialchars($pap['title']) ?>"
                                        data-link="<?= htmlspecialchars($pap['fileLink']) ?>"
                                        data-status="<?= htmlspecialchars($pap['status']) ?>"
                                        data-date="<?= htmlspecialchars($pap['dateSubmitted']) ?>"
                                        data-papsid="<?= htmlspecialchars($pap['papsID']) ?>"
                                        data-organization="<?= htmlspecialchars($pap['organization']) ?>"
                                        data-has-cert="<?= (int) $pap['hasCertificate'] ?>">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor"
                                                stroke-width="2" />
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                        style="margin-bottom: 16px; stroke: #cbd5e0;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor"
                            stroke-width="2" />
                        <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" />
                    </svg>
                    <p>No documents found.</p>
                    <button class="upload-btn" onclick="showUploadForm()" style="margin-top: 16px;">
                        Upload your first document
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- Document Details Modal -->
    <div id="documentDetailsModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header" style="text-align: center;">
                <h3 style="margin: 0 auto;">Document Details</h3>
                <button class="close-btn" onclick="closeDocumentDetailsModal()">×</button>
            </div>
            <div class="modal-body">
                <p><strong>Title:</strong> <span id="projTitle"></span></p>
                <p><strong>Document ID:</strong> <span id="papsID"></span></p>
                <p><strong>Department:</strong> <span id="department"></span></p>
                <p><strong>Date Submitted:</strong> <span id="dateNeeded"></span></p>
                <p><strong>Status:</strong> <span id="status"></span></p>
                <p><strong>Link:</strong> <a id="fileLinks" class="file-link" href="#" target="_blank">View document</a>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="viewCertificate()">View Certificate</button>
            </div>
        </div>
    </div>

    <!-- Upload Form Modal -->
    <div id="uploadModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>Upload Document</h3>
                <button class="close-btn" onclick="hideUploadForm()">×</button>
            </div>
            <div class="modal-description" style="font-size: 0.95em; color: #64748b; margin-bottom: 12px;">
                <p style="margin: 25px 25px 0px 25px;">
                    Please fill out the form below with the necessary project details. Make sure to provide accurate
                    information, including the project title, department and the link to the supporting document.
                </p>
            </div>
            <form id="uploadPapsForm" method="post" action="paps.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title" class="form-label">Project Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="organization">Department</label>
                        <select name="organization" id="organization" class="form-control" required>
                            <option value="">-- Select Department --</option>
                            <option value="CIC">CIC - College of Information and Computing</option>
                            <option value="CEd">CEd - College of Education</option>
                            <option value="CT">CT - College of Technology</option>
                            <option value="CAS">CAS - College of Arts and Sciences</option>
                            <option value="CBA">CBA - College of Business Administration</option>
                            <option value="CoE">CoE - College of Engineering</option>
                            <option value="CAEc">CAEc - College of Applied Economics</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dateNeeded" class="form-label">Date Needed</label>
                        <input type="date" class="form-control" id="dateNeeded" name="dateNeeded" required>
                    </div>


                    <div class="form-group">
                        <label for="fileLink" class="form-label">File Link</label>
                        <input type="url" class="form-control" id="fileLink" name="fileLink" required>
                        <div class="form-text">Please provide a valid URL (Google Drive, etc.)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-primary">Submit Document</button>
                </div>
            </form>
        </div>
    </div>

    <script src="endUbaseUI.js"></script>
    <script>
        function showDocumentDetails(paps, button) {
            document.getElementById('projTitle').innerText = paps.title;
            document.getElementById('papsID').innerText = paps.papsID;
            document.getElementById('department').innerText = paps.organization;
            document.getElementById('dateNeeded').innerText = paps.dateSubmitted;
            document.getElementById('status').innerText = paps.status;

            const hrefFile = document.getElementById('fileLinks');
            if (hrefFile) {
                hrefFile.href = paps.fileLink;
            }

            const modal = document.getElementById('documentDetailsModal');
            if (modal) {
                modal.style.display = 'flex';
            }

            const hasCert = button.getAttribute('data-has-cert') === '1';
            const viewBtn = document.querySelector("#documentDetailsModal .modal-footer button");
            if (viewBtn) {
                viewBtn.style.display = hasCert ? 'inline-block' : 'none';
            }
        }

        function closeDocumentDetailsModal() {
            const modal = document.getElementById('documentDetailsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showUploadForm() {
            const modal = document.getElementById('uploadModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function hideUploadForm() {
            const modal = document.getElementById('uploadModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function viewCertificate() {
            const status = document.getElementById('status').textContent.trim();
            const papsID = document.getElementById('papsID').textContent.trim();

            if (status.toLowerCase() === "completed") {
                window.open("certificate.php?papsID=" + encodeURIComponent(papsID), "_blank");
            } else {
                alert("Certificate is only available once it's completed.");
            }
        }

        // Event listeners
        document.querySelectorAll('.view-button').forEach(button => {
            button.addEventListener('click', function () {
                const paps = {
                    papsID: this.getAttribute('data-papsid'),
                    title: this.getAttribute('data-title'),
                    fileLink: this.getAttribute('data-link'),
                    status: this.getAttribute('data-status'),
                    dateSubmitted: this.getAttribute('data-date'),
                    organization: this.getAttribute('data-organization')
                };
                showDocumentDetails(paps, this);
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const status = button.textContent.trim();
                const rows = document.querySelectorAll('.files-table tbody tr');

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    const rowStatus = cells[2]?.textContent.trim();

                    if (status === 'All' || rowStatus.includes(status)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

    </script>
</body>

</html>