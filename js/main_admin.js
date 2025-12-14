document.addEventListener('DOMContentLoaded', function() {
    
    const notifIcon = document.getElementById('notification-icon');
    const notifBadge = document.getElementById('notification-badge');
    const notifDropdown = document.getElementById('notification-dropdown');

    if (notifIcon && notifBadge && notifDropdown) {
        fetchNotifications();
        
        setInterval(fetchNotifications, 30000);

        notifIcon.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            if (notifDropdown.classList.contains('show')) {
                setTimeout(markNotificationsAsRead, 2000);
            }
        });
        
        document.addEventListener('click', (e) => {
            if (notifDropdown && !notifDropdown.contains(e.target) && notifDropdown.classList.contains('show')) {
                notifDropdown.classList.remove('show');
            }
        });
    }

    async function fetchNotifications() {
        try {
            const response = await fetch('notifications_ajax.php?action=get_notifications');
            if (!response.ok) return;

            const data = await response.json();
            if (data.notifications !== undefined) {
                updateNotificationUI(data.notifications, data.unread_count);
            }
        } catch (error) {
            console.error('Error fetching admin notifications:', error);
        }
    }

    function updateNotificationUI(notifications, unread_count) {
        if (notifBadge) {
            notifBadge.textContent = unread_count;
            if (unread_count > 0) {
                notifBadge.style.display = 'block';
            } else {
                notifBadge.style.display = 'none';
            }
        }

        const list = notifDropdown.querySelector('.notification-list');
        list.innerHTML = ''; 

        if (notifications.length === 0) {
            list.innerHTML = '<div class="notification-item">No new notifications.</div>';
        } else {
            notifications.forEach(notif => {
                const item = document.createElement('a');
                
                item.href = notif.link || '#';
                item.className = 'notification-item' + (notif.is_read == 0 ? ' unread' : '');
                
                let iconClass = 'bx bx-info-circle'; 
                if (notif.message.includes('New Order')) {
                    iconClass = 'bx bx-package';
                } else if (notif.message.includes('Cancelled')) {
                    iconClass = 'bx bx-error-circle'; 
                }

                item.innerHTML = `
                    <div class="notification-icon-holder"><i class="${iconClass}"></i></div>
                    <div class="notification-content">
                        <p>${notif.message}</p>
                        <span class="notification-time">${notif.time_ago}</span>
                    </div>
                `;
                
                list.appendChild(item);
            });
        }
    }

    async function markNotificationsAsRead() {
        try {
            const response = await fetch('notifications_ajax.php?action=mark_as_read', { method: 'POST' });
            if (!response.ok) return;

            const data = await response.json();
            if (data.success) {
                if (notifBadge) {
                    notifBadge.style.display = 'none';
                    notifBadge.textContent = '0';
                }
                notifDropdown.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                
                    const p = item.querySelector('.notification-content p');
                    if(p) {
                        p.style.color = '';
                        p.style.fontWeight = '';
                    }
                });
            }
        } catch (error) {
            console.error('Error marking admin notifications as read:', error);
        }
    }
});