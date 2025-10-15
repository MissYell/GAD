<?php
session_start();
include 'db_connection.php';
// $servername = "localhost";
// $username = "root";
// $password = ""; // or your password
// $dbname = "gad_dbms";


// Check connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: index.php?error=Unauthorized access");
    exit();
}


$evaluatorID = ($_SESSION['user_id']);

$sql = "CALL GetAssignedPAPs(?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $evaluatorID);
$stmt->execute();

$result = $stmt->get_result();

$assignedPAPs = [];

while ($row = $result->fetch_assoc()) {
    $assignedPAPs[] = [
        "docNo" => $row['docNo'],
        "college" => $row['college'],
        "title" => $row['title'],
        "dateSubmitted" => date("m/d/Y", strtotime($row['dateSubmitted'])),
        "dateNeeded" => "", // Add if needed
        "status" => strtoupper($row['status']) === 'COMPLETED' ? 'COMPLETED' : 'FOR EVALUATION'
    ];
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="globalstyles.css">
    <link rel="stylesheet" href="evaluator.css">
    <title>GAD Management Information System</title>

    <style>
        .table-container {
            padding: 0 32px 32px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        /* Modal */
        .modal {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-btn:hover {
            background: #f1f5f9;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-body p {
            margin-bottom: 12px;
        }
        
        .modal-body strong {
            color: #374151;
            margin-right: 8px;
        }
        
        .modal-footer {
            padding: 24px;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }
        
        .btn-primary {
            background: #8458B3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
        }
        
    </style>

</head>

<body style>
    <div class="view" style="min-height: 90vh; margin:0 auto; padding:20px; box-sizing:border-box;">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/logo.svg" alt="GAD Logo" width="80" height="80">
                </div>
            </div>

            <div class="header-actions">
                <div class="header-icon-container">
                    <button class="icon-button">
                        <div class="tooltip"
                            style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: rgba(0, 0, 0, 0.8); color: white; padding: 6px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 1000; margin-top: 5px; pointer-events: none;">
                            <?php echo $adminTooltip; ?>
                        </div>
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
                    <a href="evaluator-profile.html" class="evaluator-option" id="edit-profile-btn">
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
                    <p><strong>Link:</strong> <a id="fileLinks" class="file-link" href="#" target="_blank">View document</a></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-primary" onclick="viewCertificate()">View Certificate</button>
                </div>
            </div>
        </div>


        <div class="card">
            <div class="container">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <p>WELCOME,</p>
                        <h1>Evaluator!</h1>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-box">
                        <p>Documents Evaluated:</p>
                        <div class="stat-number"></div>
                    </div>
                    <div class="stat-box">
                        <p>Pending Evaluations:</p>
                        <div class="stat-number"></div>
                    </div>
                </div>
            </div>

            <div class="filters-container">
                <div class="tabs">
                    <div class="tab active" data-tab="for-evaluation">For evaluation</div>
                    <div class="tab" data-tab="completed">Completed</div>
                    <div class="tab" data-tab="all">All</div>
                </div>

                <div class="search-sort">
                    <input type="text" class="search-box" placeholder="Search title..." id="searchInput"
                        style="width:800px;">
                    <div class="sort-icon" id="sortToggle">
                        <span id="sortLabel">Newest First</span>
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path d="M7,3 L7,21 M7,3 L11,7 M7,3 L3,7 M17,21 L17,3 M17,21 L21,17 M17,21 L13,17"
                                stroke="#663399" stroke-width="2" fill="none" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="table-container" id="tableContainer">
                <table class="documents-table">
                    <thead>
                        <tr>
                            <th>Doc. No</th>
                            <th>College/Unit</th>
                            <th class="title-column"
                                style="width: 50%; font-weight: 600; max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                Title</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="documentsTableBody">
                        <!-- table data here ,,,,, -->
                    </tbody>
                </table>
                <div id="emptyState" class="empty-state">
                    No documents found matching your criteria
                </div>
            </div>
        </div>

        <div class="pagination-container">
            <div class="entries-container">
                Show
                <select id="entriesDropdown">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                entries
            </div>
            <div class="showing-entries">
                showing <span id="startEntry">1</span> to <span id="endEntry">5</span> out of <span
                    id="totalEntries">6</span> entries
            </div>
            <div class="pagination-controls">
                <button class="pagination-button" id="firstPageBtn" title="First page">
                    «
                </button>
                <button class="pagination-button" id="prevPageBtn" title="Previous page">
                    ‹
                </button>
                <button class="pagination-button" id="nextPageBtn" title="Next page">
                    ›
                </button>
                <button class="pagination-button" id="lastPageBtn" title="Last page">
                    »
                </button>
            </div>
        </div>


        <script src="baseUI.js"></script>
        <script>
            const allDocuments = <?php echo json_encode($assignedPAPs); ?>;


            // Current state
            let currentDocuments = [...allDocuments];
            let currentTab = 'for-evaluation';
            let currentSortOrder = 'newest'; // Default sort: newest first
            let currentPage = 1;
            let entriesPerPage = 7; // Default to 5 entries per page

            // Parse date function (MM/DD/YYYY format)
            function parseDate(dateStr) {
                const [month, day, year] = dateStr.split('/').map(Number);
                return new Date(year, month - 1, day);
            }

            // Sort function
            function sortDocuments(docs, order) {
                return [...docs].sort((a, b) => {
                    const dateA = parseDate(a.dateSubmitted);
                    const dateB = parseDate(b.dateSubmitted);

                    if (order === 'newest') {
                        return dateB - dateA; // Newest first
                    } else {
                        return dateA - dateB; // Oldest first
                    }
                });
            }

            // Filter function
            function filterDocumentsByTab(docs, tab) {
                if (tab === 'all') {
                    return docs;
                } else if (tab === 'for-evaluation') {
                    return docs.filter(doc => doc.status === 'FOR EVALUATION');
                } else if (tab === 'completed') {
                    return docs.filter(doc => doc.status === 'COMPLETED');
                }
                return docs;
            }

            // Function to filter by search term
            function filterDocumentsBySearch(docs, searchTerm) {
                if (!searchTerm) return docs;

                searchTerm = searchTerm.toLowerCase();
                return docs.filter(doc =>
                    doc.docNo.toLowerCase().includes(searchTerm) ||
                    doc.college.toLowerCase().includes(searchTerm) ||
                    doc.title.toLowerCase().includes(searchTerm) ||
                    doc.dateSubmitted.toLowerCase().includes(searchTerm) ||
                    doc.status.toLowerCase().includes(searchTerm)
                );
            }

            // Function to render the table
            function renderTable(docs) {
                const tableBody = document.getElementById('documentsTableBody');
                const emptyState = document.getElementById('emptyState');
                const tableContainer = document.getElementById('tableContainer');

                tableBody.innerHTML = '';

                if (docs.length === 0) {
                    emptyState.style.display = 'block';
                } else {
                    emptyState.style.display = 'none';

                    // Display current page of data
                    const start = (currentPage - 1) * entriesPerPage;
                    const end = Math.min(start + entriesPerPage, docs.length);
                    const pageData = docs.slice(start, end);

                    pageData.forEach(doc => {
                        const row = document.createElement('tr');

                        // Create action button with appropriate styling and event handler
                        const actionButton = document.createElement('button');
                        actionButton.className = 'action-btn';
                        actionButton.textContent = doc.status;

                        // Add status-specific classes and behavior
                        if (doc.status === 'FOR EVALUATION') {
                            actionButton.classList.add('status-badge', 'for-evaluation');
                            actionButton.addEventListener('click', () => {
                                navigateToEvaluationForm(doc);
                            });
                        } else if (doc.status === 'COMPLETED') {
                            actionButton.classList.add('status-badge', 'completed');
                            actionButton.addEventListener('click', () => {
                                showDocumentDetails(doc);
                            });
                        }

                        // Create the table row content
                        row.innerHTML = `
                        <td>${doc.docNo}</td>
                        <td>${doc.college}</td>
                        <td>${doc.title}</td>
                        <td>${doc.dateSubmitted}</td>
                        <td>${doc.dateNeeded}</td>  
                        <td></td>
                    `;

                        // Append the button to the last cell
                        row.cells[4].appendChild(actionButton);
                        tableBody.appendChild(row);
                    });
                }

                // Set table container scrollable based on entries per page
                if (entriesPerPage > 5) {
                    tableContainer.style.overflowY = 'auto';
                } else {
                    tableContainer.style.overflowY = 'hidden';
                }

                // Update pagination info
                updatePaginationInfo(docs);
            }



            function navigateToEvaluationForm(document) {
                // Only pass the papsID (docNo)
                const queryParams = new URLSearchParams({
                    papsID: document.docNo
                }).toString();

                window.location.href = `evaluation-form.php?${queryParams}`;
            }

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



            // Function to update pagination information display
            function updatePaginationInfo(docs) {
                const startEntry = document.getElementById('startEntry');
                const endEntry = document.getElementById('endEntry');
                const totalEntries = document.getElementById('totalEntries');

                if (docs.length === 0) {
                    startEntry.textContent = '0';
                    endEntry.textContent = '0';
                    totalEntries.textContent = '0';
                } else {
                    const start = (currentPage - 1) * entriesPerPage + 1;
                    const end = Math.min(currentPage * entriesPerPage, docs.length);

                    startEntry.textContent = start;
                    endEntry.textContent = end;
                    totalEntries.textContent = docs.length;
                }
            }

            // Function to update the display
            function updateDisplay() {
                // First filter by tab
                let filteredDocs = filterDocumentsByTab(allDocuments, currentTab);

                // Then filter by search term
                const searchTerm = document.getElementById('searchInput').value;
                filteredDocs = filterDocumentsBySearch(filteredDocs, searchTerm);

                // Then sort
                const sortedDocs = sortDocuments(filteredDocs, currentSortOrder);

                // Update the display
                renderTable(sortedDocs);

                // Update stats
                document.querySelectorAll('.stat-box .stat-number')[0].textContent =
                    allDocuments.filter(doc => doc.status === 'COMPLETED').length;

                document.querySelectorAll('.stat-box .stat-number')[1].textContent =
                    allDocuments.filter(doc => doc.status === 'FOR EVALUATION').length;

                // Update current documents for pagination
                currentDocuments = sortedDocs;

                // Update pagination controls state
                updatePaginationControls();
            }

            // Function to update pagination controls
            function updatePaginationControls() {
                const totalPages = Math.ceil(currentDocuments.length / entriesPerPage);

                document.getElementById('firstPageBtn').disabled = currentPage === 1;
                document.getElementById('prevPageBtn').disabled = currentPage === 1;
                document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
                document.getElementById('lastPageBtn').disabled = currentPage >= totalPages;

                if (currentPage > totalPages && totalPages > 0) {
                    currentPage = totalPages;
                    updateDisplay();
                }
            }

            // Initial render with default settings
            document.addEventListener('DOMContentLoaded', function () {
                // Set default entries per page to 5
                document.getElementById('entriesDropdown').value = '5';
                entriesPerPage = 5;

                updateDisplay();

                // Tab switching
                const tabs = document.querySelectorAll('.tab');
                tabs.forEach(tab => {
                    tab.addEventListener('click', function () {
                        tabs.forEach(t => t.classList.remove('active'));
                        this.classList.add('active');

                        // Update current tab
                        currentTab = this.dataset.tab;
                        currentPage = 1; // Reset to first page on tab change
                        updateDisplay();
                    });
                });

                // Search functionality
                const searchInput = document.getElementById('searchInput');
                searchInput.addEventListener('input', function () {
                    currentPage = 1; // Reset to first page on search
                    updateDisplay();
                });

                // Sort toggle functionality
                const sortToggle = document.getElementById('sortToggle');
                const sortLabel = document.getElementById('sortLabel');

                sortToggle.addEventListener('click', function () {
                    // Toggle sort order
                    currentSortOrder = currentSortOrder === 'newest' ? 'oldest' : 'newest';

                    // Update label
                    sortLabel.textContent = currentSortOrder === 'newest' ? 'Newest First' : 'Oldest First';

                    // Update display
                    updateDisplay();
                });

                // Entries per page selector
                const entriesDropdown = document.getElementById('entriesDropdown');
                entriesDropdown.addEventListener('change', function () {
                    entriesPerPage = parseInt(this.value);
                    currentPage = 1; // Reset to first page

                    // Toggle scrollable behavior based on entries per page
                    const tableContainer = document.getElementById('tableContainer');
                    if (entriesPerPage > 5) {
                        tableContainer.style.overflowY = 'auto';
                    } else {
                        tableContainer.style.overflowY = 'hidden';
                    }

                    updateDisplay();
                });

                // Pagination controls
                document.getElementById('firstPageBtn').addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage = 1;
                        updateDisplay();
                    }
                });

                document.getElementById('prevPageBtn').addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        updateDisplay();
                    }
                });

                document.getElementById('nextPageBtn').addEventListener('click', () => {
                    const totalPages = Math.ceil(currentDocuments.length / entriesPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        updateDisplay();
                    }
                });

                document.getElementById('lastPageBtn').addEventListener('click', () => {
                    const totalPages = Math.ceil(currentDocuments.length / entriesPerPage);
                    if (currentPage < totalPages) {
                        currentPage = totalPages;
                        updateDisplay();
                    }
                });

                function navigateToEvaluationForm(document) {
                    // Encode the document data as URL parameters
                    const queryParams = new URLSearchParams({
                        docNo: document.docNo,
                        college: document.college,
                        title: document.title,
                        dateSubmitted: document.dateSubmitted,
                        dateNeeded: document.dateNeeded
                    }).toString();

                    // Navigate to evaluation form with the document data
                    window.location.href = `evaluation-form.php${queryParams}`;
                }

                // Initial render
                document.addEventListener('DOMContentLoaded', () => {
                    renderTable();
                });


            });
        </script>
</body>

</html>