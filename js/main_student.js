document.addEventListener('DOMContentLoaded', function() {
    
    // --- Notification Logic ---
    const notifIcon = document.getElementById('notification-icon');
    const notifBadge = document.getElementById('notification-badge');
    const notifDropdown = document.getElementById('notification-dropdown');

    if (notifIcon) {
        fetchNotifications();

        notifIcon.addEventListener('click', (e) => {
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
            updateNotificationUI(data.notifications, data.unread_count);
        } catch (error) {
            console.error('Error fetching notifications:', error);
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
                
                let iconClass = 'fas fa-info-circle'; 
                if (notif.message.includes('Ready for Pickup')) {
                    iconClass = 'fas fa-check-circle'
                } else if (notif.message.includes('Approved')) {
                    iconClass = 'fas fa-thumbs-up';
                } else if (notif.message.includes('Cancelled') || notif.message.includes('Rejected')) {
                    iconClass = 'fas fa-times-circle';
                }

                item.innerHTML = `
                    <div class="notification-icon"><i class="${iconClass}"></i></div>
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
                });
            }
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }
    
    const starContainer = document.querySelector('.interactive-rating');
    const ratingInput = document.getElementById('rating-input');
    const btnNext = document.getElementById('btn-next-step');
    const btnBack = document.getElementById('btn-back-step'); 
    const ratingText = document.getElementById('rating-text');

    const ratingLabels = ["Poor", "Fair", "Good", "Very Good", "Excellent"];

    if (starContainer && ratingInput) {
        const stars = starContainer.querySelectorAll('i'); 

        const setRating = (value) => {
            ratingInput.value = value;
            stars.forEach(star => {
                const starValue = parseInt(star.dataset.value);
                if (starValue <= value) {
                    star.classList.remove('fa-regular', 'far'); 
                    star.classList.add('fa-solid', 'fas');      
                    star.style.color = "#f39c12";
                } else {
                    star.classList.remove('fa-solid', 'fas');   
                    star.classList.add('fa-regular', 'far');    
                    star.style.color = "#e0e0e0";
                }
            });
            
            if (value > 0) {
                if(btnNext) btnNext.disabled = false;
                if(ratingText) {
                    ratingText.textContent = ratingLabels[value - 1];
                    ratingText.style.color = "#f0c040";
                    ratingText.style.fontWeight = "bold";
                }
            }
        };

        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                const hoverValue = parseInt(star.dataset.value);
                stars.forEach(s => {
                    if (parseInt(s.dataset.value) <= hoverValue) {
                        s.classList.remove('fa-regular', 'far');
                        s.classList.add('fa-solid', 'fas');
                        s.style.color = "#f39c12"; 
                    } else {
                        s.classList.remove('fa-solid', 'fas');
                        s.classList.add('fa-regular', 'far');
                        s.style.color = "#e0e0e0";
                    }
                });
            });

            star.addEventListener('mouseout', () => {
                if (ratingInput.value > 0) {
                    setRating(ratingInput.value);
                } else {
                    stars.forEach(s => {
                        s.classList.remove('fa-solid', 'fas');
                        s.classList.add('fa-regular', 'far');
                        s.style.color = "#e0e0e0";
                    });
                }
            });

            star.addEventListener('click', () => {
                setRating(star.dataset.value);
            });
        });
    }

    // --- Modal Navigation Listeners ---
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            document.getElementById('review-step-1').style.display = 'none';
            document.getElementById('review-step-2').style.display = 'block';
        });
    }

    if (btnBack) {
        btnBack.addEventListener('click', function() {
            document.getElementById('review-step-2').style.display = 'none';
            document.getElementById('review-step-1').style.display = 'block';
        });
    }

    const reviewForm = document.getElementById('review-form');

    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault(); 

            if (ratingInput.value < 1) {
                alert('Please select a star rating.');
                return;
            }

            const formData = new FormData(reviewForm);
            const submitButton = reviewForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
            
            fetch('submit_review_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const writeModalEl = document.getElementById('writeReviewModal');
                    if(writeModalEl) {
                        const writeModal = bootstrap.Modal.getInstance(writeModalEl);
                        if(writeModal) writeModal.hide();
                    }

                    const successModalEl = document.getElementById('reviewSuccessModal');
                    if (successModalEl) {
                        const successModal = new bootstrap.Modal(successModalEl);
                        successModal.show();
                    } else {
                        alert("Review submitted successfully!");
                        location.reload();
                    }
                } else {
                    alert(data.message);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Submit Review';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected network error occurred.');
                submitButton.disabled = false;
                submitButton.textContent = 'Submit Review';
            });
        });
    }

    const successCartModalEl = document.getElementById('successCartModal');
    if (successCartModalEl) {
        var successModal = new bootstrap.Modal(successCartModalEl);
        successModal.show();
    }

    const pendingReviewModalEl = document.getElementById('pendingReviewsModal');
    if (pendingReviewModalEl) {
        var pendingModal = new bootstrap.Modal(pendingReviewModalEl);
        pendingModal.show();
    }
});