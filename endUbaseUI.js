document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.getElementById('menu-button');
    const menuPopup = document.getElementById('menu-popup');
    const notificationButton = document.getElementById('notification-button');
    const notificationPopup = document.getElementById('notification-popup');
    const overlay = document.getElementById('overlay');
    const markReadButton = document.getElementById('mark-read-btn');
    const notificationList = document.getElementById('notification-list');
    const emptyNotifications = document.getElementById('empty-notifications');
    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    const logoutCancelBtn = document.getElementById('logout-cancel-btn');
    const logoutConfirmBtn = document.getElementById('logout-confirm-btn');

    menuButton.addEventListener('click', () => {
        menuPopup.classList.toggle('active');
        notificationPopup.classList.remove('active');
        overlay.classList.toggle('active');
    });
    
    notificationButton.addEventListener('click', () => {
        notificationPopup.classList.toggle('active');
        menuPopup.classList.remove('active');
        overlay.classList.toggle('active');
    });
    
    overlay.addEventListener('click', () => {
        menuPopup.classList.remove('active');
        notificationPopup.classList.remove('active');
        logoutModal.classList.remove('active');
        overlay.classList.remove('active');
    });
    
    markReadButton.addEventListener('click', () => {
        notificationList.style.display = 'none';
        emptyNotifications.classList.add('active');
    });
    
    function showLogoutModal() {
        logoutModal.classList.add('active');
        overlay.classList.add('active');
        menuPopup.classList.remove('active');
    }
    
    function hideLogoutModal() {
        logoutModal.classList.remove('active');
        overlay.classList.remove('active');
    }
    
    function showLogoutModal() {
        logoutModal.classList.add('active');
        overlay.classList.add('active');
        menuPopup.classList.remove('active');
    }
    
    function hideLogoutModal() {
        logoutModal.classList.remove('active');
        overlay.classList.remove('active');
    }
    
    logoutBtn.addEventListener('click', showLogoutModal);
    
    menuButton.addEventListener('click', function(e) {
        if (e.target === menuButton || menuButton.contains(e.target)) {
            showLogoutModal();
            e.stopPropagation(); 
        }
    });
    
    logoutCancelBtn.addEventListener('click', hideLogoutModal);
    
    logoutConfirmBtn.addEventListener('click', () => {
        window.location.href = "logout.php";
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const evaluatorModalOverlay = document.createElement('div');
    evaluatorModalOverlay.className = 'evaluator-modal-overlay';
    evaluatorModalOverlay.id = 'evaluator-modal-overlay';
    document.body.appendChild(evaluatorModalOverlay);

    const evaluatorButton = document.querySelector('.header-icon-container:first-child .icon-button');
    const evaluatorModal = document.getElementById('evaluator-modal');
    const editProfileBtn = document.getElementById('edit-profile-btn');

    // Function to show evaluator modal
    function showEvaluatorModal() {
        evaluatorModal.style.display = 'block';
        evaluatorModalOverlay.style.display = 'block';
        evaluatorModal.classList.add('show');
    }

    // Function to hide evaluator modal
    function hideEvaluatorModal() {
        evaluatorModal.style.display = 'none';
        evaluatorModalOverlay.style.display = 'none';
        evaluatorModal.classList.remove('show');
    }

    // Toggle evaluator modal when evaluator icon is clicked
    if (evaluatorButton) {
        evaluatorButton.addEventListener('click', function(e) {
            e.stopPropagation();
            showEvaluatorModal();
        });
    }

    // Close modal when clicking overlay
    evaluatorModalOverlay.addEventListener('click', function() {
        hideEvaluatorModal();
    });

    // Navigate to profile page when Edit profile is clicked
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = "user-profile.php";
        });
    }

    // Close modal when clicking outside the modal content
    if (evaluatorModal) {
        evaluatorModal.addEventListener('click', function(e) {
            if (e.target === evaluatorModal) {
                hideEvaluatorModal();
            }
        });
    }

    // Global click handler to close evaluator modal when clicking outside
    document.addEventListener('click', function(e) {
        // Check if the evaluator modal is currently visible
        const modalVisible = evaluatorModal && 
            (evaluatorModal.style.display === 'block' || evaluatorModal.classList.contains('show'));
        
        if (modalVisible) {
            // If click is outside both the modal and the evaluator button, close the modal
            if (!evaluatorModal.contains(e.target) && 
                (!evaluatorButton || !evaluatorButton.contains(e.target))) {
                hideEvaluatorModal();
            }
        }
    });
});