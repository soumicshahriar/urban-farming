    </div>
    
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Urban Farming Management System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Real-time updates for IoT data
        function updateIoTData() {
            if (typeof updateIoTReadings === 'function') {
                updateIoTReadings();
            }
        }
        
        // Update every 30 seconds
        setInterval(updateIoTData, 30000);
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Chart.js configuration
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#333';
        
        // Green points animation
        function animateGreenPoints() {
            $('.green-points').addClass('animate__animated animate__pulse');
            setTimeout(function() {
                $('.green-points').removeClass('animate__animated animate__pulse');
            }, 1000);
        }
        
        // Initialize any page-specific scripts
        if (typeof initPageScripts === 'function') {
            initPageScripts();
        }
        
        // Notification system
        function updateNotificationBadge() {
            fetch('get_notifications.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const badge = document.getElementById('notificationBadge');
                    if(badge) {
                        if(data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'block';
                            
                            // Add animation
                            badge.classList.add('animate__animated', 'animate__pulse');
                            setTimeout(() => {
                                badge.classList.remove('animate__animated', 'animate__pulse');
                            }, 1000);
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error updating notification badge:', error));
        }
        
        // Update notification badge on page load
        if(document.getElementById('notificationBadge')) {
            updateNotificationBadge();
            
            // Update every 30 seconds
            setInterval(updateNotificationBadge, 30000);
        }
    </script>
</body>
</html>
