 // Get modal elements
        const editModal = document.getElementById('editModal');
        const editProfileBtn = document.getElementById('editProfileBtn');
        const closeModal = document.getElementById('closeModal');
        const saveBtn = document.getElementById('saveBtn');
        
        // Photo upload elements
        const photoModal = document.getElementById('photoModal');
        const profileAvatar = document.getElementById('profileAvatar');
        const avatarInput = document.getElementById('avatarInput');
        const avatarImage = document.getElementById('avatarImage');
        const defaultAvatar = document.getElementById('defaultAvatar');
        const closePhotoModal = document.getElementById('closePhotoModal');
        const selectPhotoBtn = document.getElementById('selectPhotoBtn');
        const removePhotoBtn = document.getElementById('removePhotoBtn');
        const savePhotoBtn = document.getElementById('savePhotoBtn');
        const photoInput = document.getElementById('photoInput');
        const photoPreview = document.getElementById('photoPreview');
        
        let currentPhotoFile = null;
        let hasPhoto = false;
        
        // Open edit profile modal
        editProfileBtn.addEventListener('click', function() {
            editModal.style.display = 'flex';
        });
        
        // Close edit profile modal
        closeModal.addEventListener('click', function() {
            editModal.style.display = 'none';
        });
        
        // Open photo modal when clicking avatar
        profileAvatar.addEventListener('click', function() {
            photoModal.style.display = 'flex';
            updatePhotoPreview();
        });
        
        // Close photo modal
        closePhotoModal.addEventListener('click', function() {
            photoModal.style.display = 'none';
        });
        
        // Select photo button
        selectPhotoBtn.addEventListener('click', function() {
            photoInput.click();
        });
        
        // Photo input change
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                currentPhotoFile = file;
                displayPhotoPreview(file);
            }
        });
        
        // Avatar input change (for direct click on avatar)
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                currentPhotoFile = file;
                photoModal.style.display = 'flex';
                displayPhotoPreview(file);
            }
        });
        
        // Remove photo button
        removePhotoBtn.addEventListener('click', function() {
            currentPhotoFile = null;
            hasPhoto = false;
            updatePhotoPreview();
            updateMainAvatar();
        });
        
        // Save photo button
        savePhotoBtn.addEventListener('click', function() {
            if (currentPhotoFile) {
                hasPhoto = true;
                updateMainAvatar();
            }
            photoModal.style.display = 'none';
        });
        
        // Function to display photo preview in modal
        function displayPhotoPreview(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
        
        // Function to update photo preview in modal
        function updatePhotoPreview() {
            if (hasPhoto && avatarImage.src) {
                photoPreview.innerHTML = `<img src="${avatarImage.src}" alt="Current Photo">`;
            } else {
                photoPreview.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                `;
            }
        }
        
        // Function to update main avatar
        function updateMainAvatar() {
            if (hasPhoto && currentPhotoFile) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarImage.src = e.target.result;
                    avatarImage.style.display = 'block';
                    defaultAvatar.style.display = 'none';
                };
                reader.readAsDataURL(currentPhotoFile);
            } else {
                avatarImage.style.display = 'none';
                defaultAvatar.style.display = 'block';
            }
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
            if (event.target === photoModal) {
                photoModal.style.display = 'none';
            }
        });
        
        // Save profile button functionality
        saveBtn.addEventListener('click', function() {
            alert('Profile updated successfully!');
            editModal.style.display = 'none';
            
            // Update the profile information on the page
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const middleInitial = document.getElementById('middleInitial').value;
            const email = document.getElementById('email').value;
            const department = document.getElementById('department').value;
            
            // Update displayed information
            document.querySelector('.profile-name').textContent = firstName + ' ' + lastName;
            document.querySelector('.profile-email').textContent = email;
            document.querySelector('.detail-value').textContent = lastName + ', ' + firstName + ' ' + middleInitial + '.';
            document.querySelectorAll('.detail-value')[1].textContent = department;
            document.querySelector('.email-text div').textContent = email;
        });