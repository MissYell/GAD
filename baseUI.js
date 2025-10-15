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
    
    
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            notificationList.innerHTML = ''; // Clear existing items
            if (data.success && data.notifications.length > 0) {
                emptyNotifications.style.display = 'none';

                data.notifications.forEach(notification => {
                    const item = document.createElement('div');
                    item.classList.add('notification-item');

                    const text = document.createElement('p');
                    text.classList.add('notification-text');
                    text.textContent = notification.message || 'New Notification';

                    const link = document.createElement('a');
                    link.classList.add('notification-link');
                    link.href = notification.link || '#';
                    link.textContent = 'View';

                    item.appendChild(text);
                    item.appendChild(link);
                    notificationList.appendChild(item);
                });
            } else {
                emptyNotifications.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });


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

    // Toggle evaluator modal when evaluator icon is clicked
    evaluatorButton.addEventListener('click', function(e) {
        e.stopPropagation();
        evaluatorModal.style.display = 'block';
        evaluatorModalOverlay.style.display = 'block';
    });

    // Close modal when clicking overlay
    evaluatorModalOverlay.addEventListener('click', function() {
        evaluatorModal.style.display = 'none';
        evaluatorModalOverlay.style.display = 'none';
    });

    // Navigate to profile page when Edit profile is clicked
    editProfileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = "evaluator-profile.html";
    });

    document.addEventListener('click', function(e) {
        if (!evaluatorModal.contains(e.target) && e.target !== evaluatorButton) {
            evaluatorModal.style.display = 'none';
            evaluatorModalOverlay.style.display = 'none';
        }
    });

    })