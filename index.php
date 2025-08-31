<?php
session_start();
require_once 'config/database.php';

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch($_SESSION['role']) {
        case 'farmer':
            header('Location: farmer_dashboard.php');
            break;
        case 'planner':
            header('Location: planner_dashboard.php');
            break;
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Farming Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --secondary-color: #f59e0b;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        /* Modern Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            text-decoration: none;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(59, 130, 246, 0.9));
            color: white;
            padding: 120px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .hero-section .container {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            font-weight: 400;
            margin-bottom: 3rem;
            opacity: 0.9;
        }
        
        /* Feature Cards */
        .feature-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2.5rem;
            margin: 1rem 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }
        
        .feature-card:hover::before {
            left: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            font-size: 3.5rem;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .feature-card h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }
        
        .feature-card p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }
        
        /* Modern Buttons */
        .btn-modern {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            color: white;
            text-decoration: none;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(240, 249, 255, 0.9));
            backdrop-filter: blur(20px);
            padding: 80px 0;
            position: relative;
        }
        
        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%2310b981" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        }
        
        .stat-item {
            text-align: center;
            color: var(--gray-800);
            position: relative;
            z-index: 2;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stat-label {
            font-weight: 500;
            color: var(--gray-600);
            font-size: 1.1rem;
        }
        
        /* Role Cards */
        .role-cards {
            padding: 100px 0;
            background: var(--light-color);
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            color: var(--gray-800);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        .role-card {
            background: white;
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }
        
        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        
        .role-card:hover {
            transform: translateY(-20px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .role-icon {
            font-size: 4rem;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        
        .role-card:hover .role-icon {
            transform: scale(1.1);
        }
        
        .role-card h3 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }
        
        .role-card p {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .role-card ul {
            list-style: none;
            padding: 0;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .role-card ul li {
            padding: 0.5rem 0;
            color: var(--gray-600);
            position: relative;
            padding-left: 1.5rem;
        }
        
        .role-card ul li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: bold;
        }
        
        /* Footer */
        .footer {
            background: var(--gray-900);
            color: white;
            text-align: center;
            padding: 2rem 0;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .feature-card {
                padding: 2rem;
            }
            
            .role-card {
                padding: 2rem 1.5rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
        }
        
        /* Scroll Animations */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .scroll-reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-seedling me-2"></i>
                Urban Farming MS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                    <a class="nav-link" href="register.php">
                        <i class="fas fa-user-plus me-1"></i>Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title animate-fade-in-up">
                <i class="fas fa-seedling me-3"></i>
                Urban Farming Management System
            </h1>
            <p class="hero-subtitle animate-fade-in-up">Revolutionizing urban agriculture with cutting-edge IoT, AI, and drone technology</p>
            <div class="row justify-content-center g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card scroll-reveal">
                        <i class="fas fa-microchip feature-icon"></i>
                        <h5>IoT Monitoring</h5>
                        <p>Real-time sensor data for optimal crop management and environmental control</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card scroll-reveal">
                        <i class="fas fa-drone feature-icon"></i>
                        <h5>Drone Technology</h5>
                        <p>Automated pest control, crop monitoring, and precision agriculture</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card scroll-reveal">
                        <i class="fas fa-brain feature-icon"></i>
                        <h5>AI Recommendations</h5>
                        <p>Smart insights and predictive analytics for better farming decisions</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card scroll-reveal">
                        <i class="fas fa-star feature-icon"></i>
                        <h5>Green Points</h5>
                        <p>Earn rewards and recognition for sustainable eco-friendly practices</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item scroll-reveal">
                        <span class="stat-number">150+</span>
                        <div class="stat-label">Active Farms</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item scroll-reveal">
                        <span class="stat-number">25</span>
                        <div class="stat-label">Drones Available</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item scroll-reveal">
                        <span class="stat-number">500+</span>
                        <div class="stat-label">IoT Sensors</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item scroll-reveal">
                        <span class="stat-number">1000+</span>
                        <div class="stat-label">Green Points Earned</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Role Cards Section -->
    <section class="role-cards">
        <div class="container">
            <h2 class="section-title scroll-reveal">Choose Your Role</h2>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="role-card scroll-reveal">
                        <i class="fas fa-user-farmer role-icon"></i>
                        <h3>Farmer</h3>
                        <p>Manage your farms, request drone services, monitor IoT sensors, and trade in the marketplace.</p>
                        <ul>
                            <li>Create and manage farm requests</li>
                            <li>Request drone services</li>
                            <li>Monitor IoT sensors in real-time</li>
                            <li>Trade seeds in marketplace</li>
                            <li>Earn Green Points for eco-friendly practices</li>
                        </ul>
                        <a href="register.php?role=farmer" class="btn btn-modern">
                            <i class="fas fa-arrow-right me-2"></i>Join as Farmer
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="role-card scroll-reveal">
                        <i class="fas fa-clipboard-check role-icon"></i>
                        <h3>Planner</h3>
                        <p>Approve farm requests, manage drone assignments, and optimize resource allocation.</p>
                        <ul>
                            <li>Review and approve farm requests</li>
                            <li>Manage drone assignments</li>
                            <li>Monitor city-wide operations</li>
                            <li>Optimize resource allocation</li>
                            <li>Earn Green Points for efficient planning</li>
                        </ul>
                        <a href="register.php?role=planner" class="btn btn-modern">
                            <i class="fas fa-arrow-right me-2"></i>Join as Planner
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="role-card scroll-reveal">
                        <i class="fas fa-shield-alt role-icon"></i>
                        <h3>Admin</h3>
                        <p>Oversee the entire system, manage users, and ensure compliance and security.</p>
                        <ul>
                            <li>Global system monitoring</li>
                            <li>User management and security</li>
                            <li>System logs and analytics</li>
                            <li>Policy enforcement</li>
                            <li>Earn Green Points for oversight</li>
                        </ul>
                        <a href="register.php?role=admin" class="btn btn-modern">
                            <i class="fas fa-arrow-right me-2"></i>Join as Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 Urban Farming Management System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll reveal animation
        function revealOnScroll() {
            const elements = document.querySelectorAll('.scroll-reveal');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('revealed');
                }
            });
        }
        
        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('load', revealOnScroll);
        
        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.15) !important';
                navbar.style.backdropFilter = 'blur(25px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.1) !important';
                navbar.style.backdropFilter = 'blur(20px)';
            }
        });
    </script>
</body>
</html>
