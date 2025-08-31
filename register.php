<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$role = isset($_GET['role']) ? $_GET['role'] : 'farmer';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $selected_role = $_POST['role'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password and insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            
            if ($insert_stmt->execute([$username, $email, $password_hash, $selected_role])) {
                $success = "Registration successful! You can now login.";
                
                // Log the registration
                $user_id = $db->lastInsertId();
                $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$user_id, 'user_registration', "New user registered as $selected_role", $_SERVER['REMOTE_ADDR']]);
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Urban Farming MS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E8B57;
            --secondary-color: #90EE90;
            --accent-color: #32CD32;
            --dark-green: #006400;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(46, 139, 87, 0.25);
        }
        
        .btn-blue-color {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-blue-color:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
        }
        
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .role-option:hover {
            border-color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .role-option.selected {
            border-color: var(--primary-color);
            background-color: var(--light-green);
            color: var(--dark-green);
        }
        
        .role-option input[type="radio"] {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-card">
                <div class="text-center mb-4">
                    <h2><i class="fas fa-seedling me-2"></i>Register</h2>
                    <p class="text-muted">Join the Urban Farming Management System</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <br><a href="login.php" class="alert-link">Click here to login</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Select Role</label>
                        <div class="role-selector">
                            <div class="role-option <?php echo $role == 'farmer' ? 'selected' : ''; ?>">
                                <input type="radio" name="role" value="farmer" <?php echo $role == 'farmer' ? 'checked' : ''; ?>>
                                <i class="fas fa-user-farmer mb-2"></i><br>
                                <strong>Farmer</strong><br>
                                <small>Manage farms & requests</small>
                            </div>
                            <div class="role-option <?php echo $role == 'planner' ? 'selected' : ''; ?>">
                                <input type="radio" name="role" value="planner" <?php echo $role == 'planner' ? 'checked' : ''; ?>>
                                <i class="fas fa-clipboard-check mb-2"></i><br>
                                <strong>Planner</strong><br>
                                <small>Approve & manage</small>
                            </div>
                            <div class="role-option <?php echo $role == 'admin' ? 'selected' : ''; ?>">
                                <input type="radio" name="role" value="admin" <?php echo $role == 'admin' ? 'checked' : ''; ?>>
                                <i class="fas fa-shield-alt mb-2"></i><br>
                                <strong>Admin</strong><br>
                                <small>System oversight</small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-blue-color w-100 mb-3">Register</button>
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role selector functionality
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('selected'));
                // Add selected class to clicked option
                this.classList.add('selected');
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
