<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-school"></i>
            <span>School Facility Reservation</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'booking_history.php' ? 'active' : ''; ?>" href="booking_history.php">
                        <i class="fas fa-history"></i>
                        <span>Booking History</span>
                    </a>
                </li>
                
                <?php if (isset($auth) && $auth->isAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['manage_facilities.php', 'manage_users.php', 'manage_materials.php', 'analytics.php']) ? 'active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs"></i>
                        <span>Administration</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'manage_facilities.php' ? 'active' : ''; ?>" href="manage_facilities.php">
                                <i class="fas fa-building"></i>
                                <span>Manage Facilities</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php">
                                <i class="fas fa-users"></i>
                                <span>Manage Users</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>Analytics</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'manage_support.php' ? 'active' : ''; ?>" href="manage_support.php">
                                <i class="fas fa-headset"></i>
                                <span>Support Management</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <?php if (isset($auth) && $auth->isFaculty()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'add_student.php' ? 'active' : ''; ?>" href="add_student.php">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($auth) && !$auth->isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'chatbot.php' ? 'active' : ''; ?>" href="chatbot.php">
                        <i class="fas fa-robot"></i>
                        <span>Help & Support</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link user-menu" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
                        <i class="fas fa-user-circle"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item logout-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Space for fixed navbar */
    body {
        padding-top: 70px;
    }
    
    /* Navbar styling */
    .navbar {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        padding: 0.7rem 0;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1030;
    }
    
    /* Brand styling */
    .navbar-brand {
        font-weight: 700;
        font-size: 1.2rem;
        color: white !important;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .navbar-brand i {
        font-size: 1.5rem;
        background-color: rgba(255,255,255,0.2);
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Navigation items */
    .navbar-nav {
        gap: 3px;
    }
    
    .nav-item {
        position: relative;
    }
    
    .nav-link {
        color: rgba(255,255,255,0.9) !important;
        font-weight: 500;
        padding: 0.6rem 1rem !important;
        border-radius: 8px;
        margin: 0 2px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .nav-link i {
        font-size: 1.1rem;
    }
    
    .nav-link:hover {
        color: white !important;
        background-color: rgba(255,255,255,0.15);
    }
    
    .nav-link.active {
        color: white !important;
        background-color: rgba(255,255,255,0.2);
        position: relative;
    }
    
    .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 100%;
        height: 3px;
        background-color: white;
        border-radius: 3px;
    }
    
    /* User menu */
    .user-menu {
        background-color: rgba(255,255,255,0.2);
        border-radius: 50px !important;
        padding: 0.4rem 1rem !important;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .user-menu .user-name {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .user-menu i {
        font-size: 1.4rem;
    }
    
    /* Dropdown styling */
    .dropdown-menu {
        border: none;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        padding: 0.6rem;
        margin-top: 0.7rem;
        background-color: white;
    }
    
    .dropdown-item {
        padding: 0.7rem 1rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .dropdown-item i {
        font-size: 1rem;
        color: #0d6efd;
        width: 20px;
        text-align: center;
    }
    
    .dropdown-item:hover {
        background-color: #f0f7ff;
        transform: translateX(3px);
    }
    
    .dropdown-item.active {
        background-color: #e7f1ff;
        color: #0d6efd;
    }
    
    .dropdown-divider {
        margin: 0.5rem 0;
        border-color: #f0f0f0;
    }
    
    .logout-item:hover {
        background-color: #fef2f2;
        color: #dc3545;
    }
    
    .logout-item:hover i {
        color: #dc3545;
    }
    
    /* Mobile styling */
    @media (max-width: 991.98px) {
        .navbar {
            padding: 0.6rem 1rem;
        }
        
        .navbar-brand {
            font-size: 1.1rem;
        }
        
        .navbar-brand i {
            width: 32px;
            height: 32px;
            font-size: 1.3rem;
        }
        
        .navbar-collapse {
            background: white;
            border-radius: 12px;
            margin-top: 0.7rem;
            padding: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .navbar-nav {
            margin: 0.5rem 0;
        }
        
        .nav-link {
            color: #333 !important;
            padding: 0.8rem 1rem !important;
            border-radius: 8px;
            margin: 0.3rem 0;
            gap: 12px;
        }
        
        .nav-link.active::after {
            display: none;
        }
        
        .nav-link i {
            font-size: 1.1rem;
            color: #0d6efd;
            width: 24px;
            text-align: center;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: #0d6efd !important;
            background-color: #f0f7ff;
        }
        
        .dropdown-menu {
            background-color: #f8f9fa;
            box-shadow: none;
            margin-left: 1rem;
            margin-top: 0.3rem;
            padding: 0.5rem;
        }
        
        .user-menu {
            background-color: transparent;
            border-radius: 8px !important;
            justify-content: space-between;
            color: #333 !important;
        }
        
        .user-menu i {
            color: #0d6efd;
        }
        
        /* Touch target improvements */
        .nav-link, .dropdown-item {
            min-height: 46px;
        }
        
        /* Navbar toggler */
        .navbar-toggler {
            border: none;
            padding: 0.4rem 0.6rem;
            background-color: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logout confirmation
        const logoutLink = document.querySelector('.logout-item');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Confirm Logout',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to logout page
                        window.location.href = 'logout.php';
                    }
                });
            });
        }

        // Set flag when manually logging out
        if (logoutLink) {
            logoutLink.addEventListener('click', function() {
                isLoggingOut = true;
            });
        }
    });
</script> 