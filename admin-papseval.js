document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-button');
    const searchInput = document.getElementById('search-input');
    const tableBody = document.getElementById('table-body');    
    const documentModal = document.getElementById('documentModal');
    const closeDocumentModal = document.getElementById('close-document-modal');
    const documentModalBody = document.getElementById('documentModalBody');
    const entriesDropdown = document.getElementById('entriesDropdown');
    const overlay = document.querySelector('.overlay');
    //const currentAdminID = "<?php echo $_SESSION['adminID']; ?>"; //gamiti nig mag session na
    const currentAdminID = "ADMIN_001"; // Replace with actual session value in production


    // Assign Evaluator Modal Elements
    const assignEvaluatorModal = document.getElementById('assignEvaluatorModal');
    const closeModalBtn = document.getElementById('closeModal');
    const assignBtn = document.getElementById('assignButton');
    const selectAllCheckbox = document.getElementById('selectAll');
    const evaluatorSearchInput = document.getElementById('searchInput');

    // getpaps
    let documents = [];

    async function loadDocuments() {
        try {
            const response = await fetch('admin_getpaps.php');
            documents = await response.json();
            populateDocumentTable(); 
        } catch (error) {
            console.error('Error loading documents:', error);
        }
    }

    loadDocuments();


    let currentPage = 1;
    const defaultRowsPerPage = 8;
    let rowsPerPage = defaultRowsPerPage;
    let totalPages = Math.ceil(documents.length / rowsPerPage);
    let currentFilter = 'Unassigned';
    let currentDocument = null; 
    
    // Entries dropdown functionality
    if (entriesDropdown) {
        entriesDropdown.addEventListener('change', function() {
            rowsPerPage = parseInt(this.value);
            currentPage = 1;
            totalPages = Math.ceil(filterDocuments().length / rowsPerPage);
            populateDocumentTable();
        });
    }

    // Pagination buttons
    const firstPageBtn = document.getElementById('firstPageBtn');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const lastPageBtn = document.getElementById('lastPageBtn');
    
    if (firstPageBtn) {
        firstPageBtn.addEventListener('click', function() {
            if (currentPage !== 1) {
                currentPage = 1;
                populateDocumentTable();
            }
        });
    }

    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                populateDocumentTable();
            }
        });
    }

    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                populateDocumentTable();
            }
        });
    }

    if (lastPageBtn) {
        lastPageBtn.addEventListener('click', function() {
            if (currentPage !== totalPages) {
                currentPage = totalPages;
                populateDocumentTable();
            }
        });
    }

    // Filter buttons
    if (filterButtons) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                currentFilter = button.getAttribute('data-filter');
                currentPage = 1; // Reset to first page when filtering
                populateDocumentTable();
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentPage = 1; // Reset to first page when searching
            populateDocumentTable();
        });
    }

    // Update pagination info
    function updatePagination(filteredDocs) {
        const startEntry = ((currentPage - 1) * rowsPerPage) + 1;
        const endEntry = Math.min(currentPage * rowsPerPage, filteredDocs.length);
        const totalEntries = filteredDocs.length;
        
        const startEntryEl = document.getElementById('startEntry');
        const endEntryEl = document.getElementById('endEntry');
        const totalEntriesEl = document.getElementById('totalEntries');
        
        if (filteredDocs.length === 0) {
            if (startEntryEl) startEntryEl.textContent = 0;
            if (endEntryEl) endEntryEl.textContent = 0;
            if (totalEntriesEl) totalEntriesEl.textContent = 0;
        } else {
            if (startEntryEl) startEntryEl.textContent = startEntry;
            if (endEntryEl) endEntryEl.textContent = endEntry;
            if (totalEntriesEl) totalEntriesEl.textContent = totalEntries;
        }
        
        if (firstPageBtn) firstPageBtn.disabled = currentPage === 1;
        if (prevPageBtn) prevPageBtn.disabled = currentPage === 1;
        if (nextPageBtn) nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
        if (lastPageBtn) lastPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    function filterDocuments() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        
        let filteredDocs = [...documents];
        
        // Apply status filter (if not 'All')
        if (currentFilter !== 'All') {
            filteredDocs = filteredDocs.filter(doc => doc.status === currentFilter);
        }
        
        // Apply search filter
        if (searchTerm) {
            filteredDocs = filteredDocs.filter(doc => 
                doc.title.toLowerCase().includes(searchTerm)
            );
        }
        
        return filteredDocs;
    }

    // Populate document table
    function populateDocumentTable() {
        const filteredDocs = filterDocuments();
        if (!tableBody) return;
        
        const noResults = document.getElementById('noResults');
        
        tableBody.innerHTML = '';
        
        if (filteredDocs.length === 0) {
            tableBody.style.display = 'none';
            if (noResults) noResults.style.display = 'block';
            updatePagination(filteredDocs);
            return;
        }
        
        tableBody.style.display = '';
        if (noResults) noResults.style.display = 'none';
        
        totalPages = Math.ceil(filteredDocs.length / rowsPerPage);
        
        // Ensure current page is valid
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = Math.min(startIndex + rowsPerPage, filteredDocs.length);
        
        const docsToShow = filteredDocs.slice(startIndex, endIndex);
        
        docsToShow.forEach(doc => {
            const row = document.createElement('tr');
            let statusClass = '';
            if (doc.status.toLowerCase() === 'unassigned') {
                statusClass = 'status-unassigned';
            } else if (doc.status.toLowerCase() === 'pending') {
                statusClass = 'status-pending';
            } else {
                statusClass = 'status-completed';
            }

            
            // Different button content based on status
            let buttonContent = '';
            let buttonTitle = '';
            
            if (doc.status === 'Completed') {
                buttonContent = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                `;
                buttonTitle = 'View Certificate';
            } else if (doc.status === 'Pending' || doc.status === 'Unassigned') {
                buttonContent = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="m19 8 2 2-2 2"/>
                        <path d="m21 10-7.5 7.5L10 14l4-4 7-7"/>
                    </svg>
                `;
                buttonTitle = 'Assign Action';
            }
            
            row.innerHTML = `
                <td>${doc.docNo}</td>
                <td>${doc.collegeUnit}</td>
                <td>${doc.title}</td>
                <td>${doc.dateSubmitted}</td>
                <td class="${statusClass}">${doc.status}</td>
                <td>
                    <button class="view-button" data-id="${doc.id}" title="${buttonTitle}">
                        ${buttonContent}
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Add event listeners to action buttons
        document.querySelectorAll('.view-button').forEach(button => {
            button.addEventListener('click', function() {
                const docId = this.getAttribute('data-id');
                const doc = documents.find(d => d.id === docId);

                if (doc && doc.status === 'Completed') {
                    viewCertificate(doc);
                } else if (doc && (doc.status === 'Pending' || doc.status === 'Unassigned')) {
                    // For both Pending and Unassigned, open assign evaluator modal
                    currentDocument = doc;
                    openAssignEvaluatorModal();
                } else {
                    openDocumentDetailsModal(docId);
                }
            });
        });
        
        updatePagination(filteredDocs);
    }

    // Open Document Details Modal - NEW FUNCTION
    function openDocumentDetailsModal(docId) {
        const doc = documents.find(d => d.id === docId);
        if (!doc || !documentModal || !documentModalBody) {
            console.error('Document or modal not found');
            return;
        }
        
        currentDocument = doc; // Store current document
        
        // Populate modal with document details
        documentModalBody.innerHTML = `
            <div class="document-details">
                <h3>Document Details</h3>
                <div class="detail-row">
                    <label>Document Number:</label>
                    <span>${doc.docNo}</span>
                </div>
                <div class="detail-row">
                    <label>College/Unit:</label>
                    <span>${doc.collegeUnit}</span>
                </div>
                <div class="detail-row">
                    <label>Title:</label>
                    <span>${doc.title}</span>
                </div>
                <div class="detail-row">
                    <label>Date Submitted:</label>
                    <span>${doc.dateSubmitted}</span>
                </div>
                <div class="detail-row">
                    <label>Status:</label>
                    <span class="${
                        doc.status.toLowerCase() === 'unassigned' ? 'status-unassigned' :
                        doc.status.toLowerCase() === 'pending' ? 'status-pending' :
                        'status-completed'
                    }">${doc.status}</span>
                </div>
                <div class="modal-actions">
                    <button id="assignEvaluatorBtn" class="assign-evaluator-btn">
                        Assign Evaluator
                    </button>
                </div>
            </div>
        `;
        
        // Show the modal
        documentModal.style.display = 'flex';
        if (overlay) {
            overlay.classList.add('active');
        }
        
        // Add event listener to the assign evaluator button
        const assignEvaluatorBtn = document.getElementById('assignEvaluatorBtn');
        if (assignEvaluatorBtn) {
            assignEvaluatorBtn.addEventListener('click', function() {
                openAssignEvaluatorModal();
            });
        }
    }
    async function loadEvaluators() {
    try {
        const response = await fetch('admin_assign_getevaluators.php');
        const evaluators = await response.json();
        
        console.log('Raw evaluator data:', evaluators);
        
        const tbody = document.getElementById('evaluatorsList');
        tbody.innerHTML = '';

        evaluators.forEach(e => {
            console.log('Processing evaluator:', e);

            const row = document.createElement('tr');
            row.dataset.id = e.evaluatorID;
            row.innerHTML = `
                <td class="checkbox-column" style="width: 40px; padding: 8px; text-align: center;">
                    <input type="checkbox" class="evaluator-checkbox">
                </td>
                <td>${e.fullName.trim()}</td>
                <td>${e.expertise.trim()}</td>
                <td>${e.department.trim()}</td>
                <td style="text-align: center;">${e.last_active ? new Date(e.last_active).toLocaleString() : '-'}</td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading evaluators:', error);
    }
}

    // Open Assign Evaluator Modal 
        function openAssignEvaluatorModal() {
        if (!assignEvaluatorModal) {
            console.error('Assign Evaluator modal not found');
            return;
        }
        const modalContent = assignEvaluatorModal ? assignEvaluatorModal.querySelector('.modal-content') : null;
        // Close document modal if it's open
        if (documentModal) {
            documentModal.style.display = 'none';
        }
        
        // Show assign evaluator modal
        assignEvaluatorModal.style.display = 'flex';
        if (overlay) {
            overlay.classList.add('active');
        }
        
        // Reset search and selections
        if (evaluatorSearchInput) {
            evaluatorSearchInput.value = '';
        }
        resetEvaluatorSelections();

         // ✅ CALL the new function to load evaluator data from PHP
        loadEvaluators();

        // Show all evaluator rows after reset
        const evaluatorRows = document.querySelectorAll('#evaluatorsList tr');
        evaluatorRows.forEach(row => {
            row.style.display = '';
        });
        
        if (modalContent) {
            modalContent.style.width = '800px';
            modalContent.style.height = '400px';
            modalContent.style.maxWidth = '800px';
            modalContent.style.maxHeight = '400px';
            modalContent.style.minWidth = '800px';
            modalContent.style.minHeight = '400px';
        }
        
        if (evaluatorTableContainer) {
            evaluatorTableContainer.style.height = '400px';
            evaluatorTableContainer.style.maxHeight = '400px';
            evaluatorTableContainer.style.minHeight = '400px';
            evaluatorTableContainer.style.overflowY = 'auto';
            evaluatorTableContainer.style.overflowX = 'hidden';
        }
        
        if (evaluatorTable) {
            // Ensure table maintains full width
            evaluatorTable.style.width = '100%';
            evaluatorTable.style.tableLayout = 'fixed';
        }
    }
    // Enhanced reset evaluator selections
    function resetEvaluatorSelections() {
        const evaluatorCheckboxes = document.querySelectorAll('.evaluator-checkbox');
        evaluatorCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
    }

    // Enhanced Select All Functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            const evaluatorCheckboxes = document.querySelectorAll('.evaluator-checkbox');
            evaluatorCheckboxes.forEach(checkbox => {
                // Only check visible checkboxes
                const row = checkbox.closest('tr');
                if (row && row.style.display !== 'none') {
                    checkbox.checked = selectAllCheckbox.checked;
                }
            });
        });
    }

    // Enhanced update "Select All" checkbox based on individual selections
    function updateSelectAllCheckbox() {
        const visibleCheckboxes = [];
        const checkedVisibleBoxes = [];
        
        const evaluatorCheckboxes = document.querySelectorAll('.evaluator-checkbox');
        evaluatorCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            if (row && row.style.display !== 'none') {
                visibleCheckboxes.push(checkbox);
                if (checkbox.checked) {
                    checkedVisibleBoxes.push(checkbox);
                }
            }
        });
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedVisibleBoxes.length === visibleCheckboxes.length && visibleCheckboxes.length > 0;
        }
    }

    // Enhanced Search Functionality for evaluators
    if (evaluatorSearchInput) {
        evaluatorSearchInput.addEventListener('input', () => {
            const searchTerm = evaluatorSearchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#evaluatorsList tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const name = cells[1].textContent.toLowerCase();
                    const specialization = cells[2].textContent.toLowerCase();
                    const department = cells[3].textContent.toLowerCase();
                    
                    const matches = name.includes(searchTerm) || 
                                   specialization.includes(searchTerm) || 
                                   department.includes(searchTerm);
                    
                    row.style.display = matches ? '' : 'none';
                }
            });
            
            // Update select all checkbox after filtering
            updateSelectAllCheckbox();
        });
    }

    // Enhanced Assign Button Functionality
    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const selectedEvaluatorIDs = [];

            // Collect evaluator IDs (you need to map names to IDs or update table to store IDs)
            const checkboxes = document.querySelectorAll('.evaluator-checkbox:checked');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row && row.dataset.id) {
                    selectedEvaluatorIDs.push(row.dataset.id);
                }
            });

            if (!currentDocument || selectedEvaluatorIDs.length === 0) {
                alert('Please select at least one evaluator.');
                return;
            }

            const payload = {
                papsID: currentDocument.id,
                adminID: currentAdminID, // replace with actual logged-in admin ID
                evaluatorIDs: selectedEvaluatorIDs
            };

            try {
                const response = await fetch('admin_assign_evaluators.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (result.success) {
                    alert('Evaluators assigned successfully.');
                    loadDocuments(); // refresh the document list
                    assignEvaluatorModal.style.display = 'none';
                    if (overlay) overlay.classList.remove('active');
                } else {
                    alert('Assignment failed: ' + result.message);
                }
            } catch (error) {
                console.error('Assignment error:', error);
                alert('Error assigning evaluators.');
            }
        });
    }


    // Modal close event listeners
    if (closeDocumentModal && documentModal) {
        closeDocumentModal.addEventListener('click', function() {
            documentModal.style.display = 'none';
            if (overlay) overlay.classList.remove('active');
        });
    }

    if (closeModalBtn && assignEvaluatorModal) {
        closeModalBtn.addEventListener('click', function() {
            assignEvaluatorModal.style.display = 'none';
            if (overlay) overlay.classList.remove('active');
        });
    }

    // Enhanced modal closing when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === documentModal) {
            documentModal.style.display = 'none';
            if (overlay) overlay.classList.remove('active');
        }
        if (event.target === assignEvaluatorModal) {
            assignEvaluatorModal.style.display = 'none';
            if (overlay) overlay.classList.remove('active');
        }
    });

    // Add event listeners to evaluator checkboxes using event delegation
    document.addEventListener('change', function(event) {
        if (event.target.classList.contains('evaluator-checkbox')) {
            updateSelectAllCheckbox();
        }
    });

    // Updated certificate viewing function
    function viewCertificate(doc) {
        // Create URL with document parameters for the certificate page
        const params = new URLSearchParams({
            docId: doc.id,
            docNo: doc.docNo,
            title: doc.title,
            collegeUnit: doc.collegeUnit,
            dateSubmitted: doc.dateSubmitted
        });
        
      
         window.location.href = `certificate.php?${params.toString()}`;
    }

    // Initialize the table
    populateDocumentTable();
});