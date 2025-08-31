<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Farming Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E8B57;
            --secondary-color: #90EE90;
            --accent-color: #32CD32;
            --dark-green: #006400;
            --light-green: #98FB98;
            --blue-color: #00008B;
            --text-color:rgb(255, 255, 255);
            --gray-color:rgb(0, 0, 0);
        }
        
        body {
            /* background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(90deg, var(--blue-color), var(--dark-green)) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .btn-blue-color {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            transition: all 0.3s ease;
        }
        
        .btn-blue-color:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
        }
        
        .green-points {
            background: linear-gradient(45deg,rgb(7, 0, 139),rgb(21, 10, 238));
            color:white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .btn-blue-color {
                background: linear-gradient(45deg,rgb(7, 0, 139),rgb(21, 10, 238));
                color:white;
                padding: 5px 15px;
                border-radius: 20px;
                font-weight: bold;
            }
        .btn-blue-color:hover {
                color:white;
            }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .text-color{
            color: var(--text-color);
        }
        
        .status-pending { background-color: #FFA500; color: white; }
        .status-approved { background-color: #32CD32; color: white; }
        .status-rejected { background-color: #DC143C; color: white; }
        .status-active { background-color: #4169E1; color: white; }
        .status-completed { background-color: #20B2AA; color: white; }
        
        .dashboard-stats {
            background: linear-gradient(135deg, var(--light-green), var(--blue-color));
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .iot-card {
            background: linear-gradient(135deg, #E8F5E8, #F0FFF0);
            border-left: 5px solid var(--primary-color);
        }
        
        .drone-card {
            background: linear-gradient(135deg, #F0F8FF, #E6F3FF);
            border-left: 5px solid #4169E1;
        }
        
        .marketplace-card {
            background: linear-gradient(135deg, #FFF8DC, #FDF5E6);
            border-left: 5px solid #FFA500;
        }
        
        .real-time-indicator {
            width: 10px;
            height: 10px;
            background-color: #32CD32;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .main-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-seedling me-2"></i>
                Urban Farming MS
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'farmer'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="farmer_dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="farm_requests.php">
                                    <i class="fas fa-farm me-1"></i>My Farms
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="drone_requests.php">
                                    <i class="fas fa-drone me-1"></i>Drone Requests
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="iot_monitoring.php">
                                    <i class="fas fa-microchip me-1"></i>IoT Monitoring
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="marketplace.php">
                                    <i class="fas fa-store me-1"></i>Marketplace
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="notifications.php">
                                    <i class="fas fa-bell me-1"></i>Notifications
                                </a>
                            </li>
                        <?php elseif($_SESSION['role'] == 'planner'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="planner_dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="farm_approvals.php">
                                    <i class="fas fa-check-circle me-1"></i>Farm Approvals
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="drone_approvals.php">
                                    <i class="fas fa-drone me-1"></i>Drone Approvals
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="notifications.php">
                                    <i class="fas fa-bell me-1"></i>Notifications
                                </a>
                            </li>
                        <?php elseif($_SESSION['role'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="global_monitoring.php">
                                    <i class="fas fa-globe me-1"></i>Global Monitoring
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="user_management.php">
                                    <i class="fas fa-users me-1"></i>User Management
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="system_logs.php">
                                    <i class="fas fa-list me-1"></i>System Logs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="notifications.php">
                                    <i class="fas fa-bell me-1"></i>Notifications
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <span class="green-points me-3">
                                <i class="fas fa-star me-1"></i>
                                <?php echo $_SESSION['green_points'] ?? 0; ?> Green Points
                            </span>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link position-relative" href="notifications.php" id="notificationBell">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
                                    0
                                </span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <!-- <li><a class="dropdown-item" href="green_points.php">Green Points</a></li> -->
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>
