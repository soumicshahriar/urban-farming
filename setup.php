<?php
/**
 * Urban Farming Management System - Setup Script
 * This script helps configure the system for first-time use
 */

// Check if setup is already completed
if (file_exists('config/setup_complete.txt')) {
    die('Setup has already been completed. Remove config/setup_complete.txt to run setup again.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch($step) {
        case 1: // Database configuration
            $host = $_POST['db_host'];
            $name = $_POST['db_name'];
            $username = $_POST['db_username'];
            $password = $_POST['db_password'];
            
            // Test database connection
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$name", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Update database config
                $config_content = "<?php
class Database {
    private \$host = \"$host\";
    private \$db_name = \"$name\";
    private \$username = \"$username\";
    private \$password = \"$password\";
    public \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO(\"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name, \$this->username, \$this->password);
            \$this->conn->exec(\"set names utf8\");
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }
        return \$this->conn;
    }
}
?>";
                
                file_put_contents('config/database.php', $config_content);
                $success = 'Database configuration saved successfully!';
                $step = 2;
                
            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 2: // Import database schema
            try {
                require_once 'config/database.php';
                $database = new Database();
                $db = $database->getConnection();
                
                $schema = file_get_contents('database/schema.sql');
                $db->exec($schema);
                
                $success = 'Database schema imported successfully!';
                $step = 3;
                
            } catch (Exception $e) {
                $error = 'Schema import failed: ' . $e->getMessage();
            }
            break;
            
        case 3: // Create admin account
            $admin_username = $_POST['admin_username'];
            $admin_email = $_POST['admin_email'];
            $admin_password = $_POST['admin_password'];
            
            if (strlen($admin_password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } else {
                try {
                    require_once 'config/database.php';
                    $database = new Database();
                    $db = $database->getConnection();
                    
                    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$admin_username, $admin_email, $password_hash]);
                    
                    $success = 'Admin account created successfully!';
                    $step = 4;
                    
                } catch (Exception $e) {
                    $error = 'Admin account creation failed: ' . $e->getMessage();
                }
            }
            break;
            
        case 4: // Finalize setup
            file_put_contents('config/setup_complete.txt', date('Y-m-d H:i:s'));
            $success = 'Setup completed successfully! You can now access the system.';
            $step = 5;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Farming MS - Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .setup-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #2E8B57;
            color: white;
        }
        .step.completed {
            background: #32CD32;
            color: white;
        }
        .step.pending {
            background: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <div class="setup-card">
                <div class="text-center mb-4">
                    <h2><i class="fas fa-seedling me-2"></i>Urban Farming MS Setup</h2>
                    <p class="text-muted">Complete the setup to get started</p>
                </div>
                
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step == 1 ? 'active' : 'completed') : 'pending'; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : 'pending'; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? ($step == 3 ? 'active' : 'completed') : 'pending'; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? ($step == 4 ? 'active' : 'completed') : 'pending'; ?>">4</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Step 1: Database Configuration -->
                    <h4>Step 1: Database Configuration</h4>
                    <p class="text-muted">Configure your MySQL database connection.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="db_host" class="form-label">Database Host</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" value="urban_farming" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_username" class="form-label">Database Username</label>
                            <input type="text" class="form-control" id="db_username" name="db_username" value="root" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_password" class="form-label">Database Password</label>
                            <input type="password" class="form-control" id="db_password" name="db_password">
                        </div>
                        
                        <button type="submit" class="btn btn-blue-color w-100">Test Connection & Continue</button>
                    </form>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Import Schema -->
                    <h4>Step 2: Database Schema</h4>
                    <p class="text-muted">Import the database schema to create all necessary tables.</p>
                    
                    <form method="POST">
                        <button type="submit" class="btn btn-blue-color w-100">Import Database Schema</button>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Admin Account -->
                    <h4>Step 3: Admin Account</h4>
                    <p class="text-muted">Create the administrator account for system management.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="admin_username" class="form-label">Admin Username</label>
                            <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Admin Email</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Admin Password</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <button type="submit" class="btn btn-blue-color w-100">Create Admin Account</button>
                    </form>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Step 4: Finalize -->
                    <h4>Step 4: Finalize Setup</h4>
                    <p class="text-muted">Complete the setup process.</p>
                    
                    <form method="POST">
                        <button type="submit" class="btn btn-success w-100">Complete Setup</button>
                    </form>
                    
                <?php elseif ($step == 5): ?>
                    <!-- Setup Complete -->
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h4>Setup Complete!</h4>
                        <p class="text-muted">Your Urban Farming Management System is ready to use.</p>
                        
                        <div class="alert alert-info">
                            <strong>Default Admin Account:</strong><br>
                            Username: <?php echo $_POST['admin_username'] ?? 'admin'; ?><br>
                            Password: [The password you set]
                        </div>
                        
                        <a href="index.php" class="btn btn-blue-color">Go to Homepage</a>
                        <a href="login.php" class="btn btn-outline-primary">Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
