// Get users
        let users = [];
        document.addEventListener('DOMContentLoaded', function () {
            const entriesDropdown = document.getElementById('entriesDropdown');

            let currentPage = 1;
            const defaultRowsPerPage = 10;
            let rowsPerPage = defaultRowsPerPage;

            
            fetch('admin_getusers.php')
                .then(response => response.json())
                .then(data => {
                    users = data;
                    totalPages = Math.ceil(users.length / rowsPerPage);
                    populateUserTable();
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    populateUserTable(); 
            });

        
        // Pagination kemerut
        function updatePagination(filteredUsers = users) {
        const startEntry = ((currentPage - 1) * rowsPerPage) + 1;
        const endEntry = Math.min(currentPage * rowsPerPage, filteredUsers.length);
        const totalEntries = filteredUsers.length;
        
        if (filteredUsers.length === 0) {
            document.getElementById('startEntry').textContent = 0;
            document.getElementById('endEntry').textContent = 0;
            document.getElementById('totalEntries').textContent = 0;
        } else {
            document.getElementById('startEntry').textContent = startEntry;
            document.getElementById('endEntry').textContent = endEntry;
            document.getElementById('totalEntries').textContent = totalEntries;
        }
        
        document.getElementById('firstPageBtn').disabled = currentPage === 1;
        document.getElementById('prevPageBtn').disabled = currentPage === 1;
        document.getElementById('nextPageBtn').disabled = currentPage === totalPages;
        document.getElementById('lastPageBtn').disabled = currentPage === totalPages;
        }

        
        function populateUserTable(filteredUsers = users) {
        const tableBody = document.getElementById('userTableBody');
        const noResults = document.getElementById('noResults');
        
        tableBody.innerHTML = '';
        
        if (filteredUsers.length === 0) {
            tableBody.style.display = 'none';
            noResults.style.display = 'block';
            updatePagination(filteredUsers); 
            return;
        }
        
    tableBody.style.display = '';
    noResults.style.display = 'none';
    
    totalPages = Math.ceil(filteredUsers.length / rowsPerPage);
    
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, filteredUsers.length);
    
    const usersToShow = filteredUsers.slice(startIndex, endIndex);
    
    //view bttn
   usersToShow.forEach(user => {
    const row = document.createElement('tr');

    const statusBadge = `
        <span class="status-badge ${user.status === 'Active' ? 'active' : 'inactive'}">
            ${user.status}
        </span>
    `;

    row.innerHTML = `
        <td>${user.fullName}</td>
        <td>${user.role}</td>
        <td>${user.department || '-'}</td>
        <td>${user.dateJoined || '-'}</td>
        <td>${statusBadge}</td> 
        <td>
            <button class="view-button" data-id="${user.id}">
               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </td>
    `;

    tableBody.appendChild(row);
});




    document.querySelectorAll('.view-button').forEach(button => {
        button.addEventListener('click', function() {
        const userId = this.getAttribute('data-id'); 
        openUserDetailsModal(userId);
    });
    });
    
    updatePagination(filteredUsers); 
    }
      
    
    document.getElementById('firstPageBtn').addEventListener('click', function() {
        if (currentPage !== 1) {
            currentPage = 1;
            populateUserTable();
        }
    });
    
    document.getElementById('prevPageBtn').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            populateUserTable();
        }
    });
    
    document.getElementById('nextPageBtn').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            populateUserTable();
        }
    });
    
    document.getElementById('lastPageBtn').addEventListener('click', function() {
        if (currentPage !== totalPages) {
            currentPage = totalPages;
            populateUserTable();
        }
    });
    
    // Filter buttons
    document.querySelectorAll('.filter-button').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.filter-button').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            let filteredUsers;
            
            if (filter === 'all') {
                filteredUsers = users;
            } else {
                filteredUsers = users.filter(user => user.role === filter);
            }
            
            
            currentPage = 1;
            populateUserTable(filteredUsers);
        });
    });
    
    // User details modal functions
    function openUserDetailsModal(userId) {
        const user = users.find(user => user.id === userId);
        
        if (user) {
            document.getElementById('detailFullname').textContent = user.fullName;
            document.getElementById('detailEmail').textContent = user.email;
            document.getElementById('detailDepartment').textContent = user.department;
            document.getElementById('detailSpecialization').textContent = user.specialization;
            document.getElementById('detailUserGroup').textContent = user.userGroup;
            
            document.getElementById('userDetailsModal').style.display = 'block';
            
            document.getElementById('deleteUserBtn').onclick = function() {
                openDeleteConfirmationModal(userId);
            };
            
            const editBtn = document.getElementById('editUserBtn');
            if (user.role === 'Evaluator') {
                editBtn.style.display = 'inline-block'; // or 'block'
                editBtn.onclick = function() {
                    openEditUserModal(userId);
                };
            } else {
                editBtn.style.display = 'none';
            }
        }
    }
    
    window.closeUserDetailsModal = function() {
        document.getElementById('userDetailsModal').style.display = 'none';
    };
    
    // Add user modal functions
    document.getElementById('addUserBtn').addEventListener('click', function() {
        document.getElementById('addUserModal').style.display = 'block';
    });
    
    window.closeAddUserModal = function() {
        document.getElementById('addUserModal').style.display = 'none';
        
        document.getElementById('lastName').value = '';
        document.getElementById('firstName').value = '';
        document.getElementById('email').value = '';
        document.getElementById('department').selectedIndex = 0;
        document.getElementById('specialization').value = '';
        document.getElementById('userGroup').selectedIndex = 0;
    };
    
    document.getElementById('addUserSubmitBtn').addEventListener('click', function() {
        const lastName = document.getElementById('lastName').value.trim();
        const firstName = document.getElementById('firstName').value.trim();
        const email = document.getElementById('email').value.trim();
        const department = document.getElementById('department');
        const departmentValue = department.value;
        const departmentText = department.options[department.selectedIndex].text;
        const specialization = document.getElementById('specialization').value.trim();
        const userGroup = document.getElementById('userGroup').value;
        const sex = document.getElementById('sex').value;
        const contactNo = document.getElementById('contactNo').value.trim();

        if (!lastName || !firstName || !email || !departmentValue || !specialization || !userGroup || !sex || !contactNo) {
            alert('Please fill in all fields');
            return;
        }

        const evaluatorID = 'E' + Date.now();

        const newUser = {
            evaluatorID: evaluatorID,
            fname: firstName,
            lname: lastName,
            dob: '',
            sex: sex,
            contactNo: contactNo,
            email: email,
            address: '',
            expertise: specialization,
            department: departmentText,
            password: 'eval123'
        };

        fetch('admin_add_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newUser)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('User added successfully!');
                closeAddUserModal();
                fetch('admin_getusers.php')
                    .then(response => response.json())
                    .then(data => {
                        users = data;
                        populateUserTable();
                    });
            } else {
                alert('Failed to add user: ' + data.message);
            }
        })
        .catch(error => {
            alert('Fetch error: ' + error);
        });
    });
    
    // Edit user modal functions
    window.openEditUserModal = function(userId) {
        const user = users.find(user => user.id === userId);

    if (user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editLastName').value = user.lastName;
        document.getElementById('editFirstName').value = user.firstName;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editDepartment').value = user.departmentCode;
        document.getElementById('editSpecialization').value = user.specialization;
        document.getElementById('editUserGroup').value = user.userGroup;
        document.getElementById('editSex').value = user.sex || '';
        document.getElementById('editContactNo').value = user.contactNo || '';

        document.getElementById('editUserModal').style.display = 'block';
        document.getElementById('userDetailsModal').style.display = 'none';
    }
    };
    
    window.closeEditUserModal = function() {
        document.getElementById('editUserModal').style.display = 'none';
    };
    
    document.getElementById('saveEditUserBtn').addEventListener('click', function() {
        const userId = document.getElementById('editUserId').value;
        const lastName = document.getElementById('editLastName').value.trim();
        const firstName = document.getElementById('editFirstName').value.trim();
        const email = document.getElementById('editEmail').value.trim();
        const department = document.getElementById('editDepartment');
        const departmentValue = department.value;
        const departmentText = department.options[department.selectedIndex].text;
        const specialization = document.getElementById('editSpecialization').value.trim();
        const userGroup = document.getElementById('editUserGroup').value;
        const sex = document.getElementById('editSex').value;
        const contactNo = document.getElementById('editContactNo').value.trim();

        if (!lastName || !firstName || !email || !departmentValue || !specialization || !userGroup || !sex || !contactNo) {
            alert('Please fill in all fields');
            return;
        }
            fetch('admin_update_users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId,
                    lastName,
                    firstName,
                    email,
                    department: departmentText,
                    specialization,
                    userGroup,
                    sex,
                    contactNo
                }),
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                closeEditUserModal();
                location.reload(); 
            })
            .catch(error => {
                console.error('Error updating user:', error);
            });

        
    });
    
        // Delete user functions
        window.openDeleteConfirmationModal = function(userId) {
        document.getElementById('deleteConfirmationModal').style.display = 'block';
        
        document.getElementById('confirmDeleteBtn').onclick = function() {
            deleteUser(userId);
        };
        
        document.getElementById('userDetailsModal').style.display = 'none';
    };
    
    window.closeDeleteConfirmationModal = function() {
        document.getElementById('deleteConfirmationModal').style.display = 'none';
    };
    
    function deleteUser(userId) {
    const user = users.find(u => u.id === userId);
    console.log('Deleting', userId, 'with group', user.userGroup);
    if (!user) {
        alert('User not found');
        return;
    }

    fetch('admin_deleteuser.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId, userGroup: user.userGroup })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            users = users.filter(u => u.id !== userId);
            populateUserTable();
            closeDeleteConfirmationModal();
            alert('User deleted successfully!');
        } else {
            alert('Failed to delete user: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        alert('An error occurred while deleting the user.');
    });
}
});