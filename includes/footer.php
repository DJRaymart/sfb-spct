<!-- Footer -->
<footer class="footer mt-auto py-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5 class="text-white mb-3">
                    <i class="fas fa-school me-2"></i>School Facility Reservation System
                </h5>
                <p class="text-light opacity-75">
                    Streamline your facility reservation process with our easy-to-use reservation system. Book rooms, track schedules, and manage facilities all in one place.
                </p>
            </div>
            <div class="col-lg-2 mb-4 mb-lg-0">
                <h6 class="text-white mb-3">Quick Links</h6>
                <ul class="footer-links">
                    <li><a href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                    <li><a href="booking_history.php"><i class="fas fa-history me-2"></i>Booking History</a></li>
                    <li><a href="manage_facilities.php"><i class="fas fa-building me-2"></i>Manage Facilities</a></li>
                    <li><a href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a></li>
                </ul>
            </div>
            <div class="col-lg-3 mb-4 mb-lg-0">
                <h6 class="text-white mb-3">Facilities</h6>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-door-open me-2"></i>Classrooms</a></li>
                    <li><a href="#"><i class="fas fa-running me-2"></i>Sports Facilities</a></li>
                    <li><a href="#"><i class="fas fa-microphone me-2"></i>Auditorium</a></li>
                    <li><a href="#"><i class="fas fa-flask me-2"></i>Laboratories</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-white mb-3">Contact Us</h6>
                <ul class="footer-links">
                    <li><i class="fas fa-envelope me-2"></i>saintpetertoril@gmail.com</li>
                    <li><i class="fas fa-phone me-2"></i>291-2007</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>McArthur Highway Crossing Bayabas Toril Davao City, Philippines</li>
                </ul>
            </div>
        </div>
        <hr class="mt-4 mb-3 border-light opacity-25">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-light mb-0 opacity-75">
                    &copy; <?php echo date('Y'); ?> School Facility Reservation System. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <div class="social-links">
                    <a href="#" class="me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Floating Chatbot Button - Only for Students and Faculty -->
<?php if (isset($auth) && !$auth->isAdmin()): ?>
<div class="chatbot-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
    <button class="chatbot-toggle" onclick="openChatbot()" id="floatingChatbotToggle" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-size: 24px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;">
        <i class="fas fa-robot"></i>
    </button>
</div>
<?php endif; ?>

<script>
// Floating chatbot functionality
function openChatbot() {
    window.open('chatbot.php', '_blank', 'width=500,height=600,scrollbars=yes,resizable=yes');
}

// Hide floating button on chatbot page
if (window.location.pathname.includes('chatbot.php')) {
    document.getElementById('floatingChatbotToggle').style.display = 'none';
}
</script>

<style>
.footer {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    margin-top: 3rem;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
}

.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 0.75rem;
}

.footer-links a {
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
}

.footer-links a:hover {
    color: white;
    transform: translateX(5px);
}

.social-links a {
    color: white;
    text-decoration: none;
    opacity: 0.75;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.social-links a:hover {
    opacity: 1;
    transform: translateY(-3px);
}
</style> 