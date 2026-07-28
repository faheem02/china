<?php
/**
 * Login Page
 * Manufacturing ERP System
 */

// Include database connection
require_once 'includes/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        // Check user credentials (Plain Text Password as requested)
        $sql = "SELECT id, username, full_name, email, role, status 
                FROM users 
                WHERE username = ? AND password = ? AND status = 'active'";
        
        $user = getRow($sql, 'ss', [$username, $password]);
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to dashboard (relative path)
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Manufacturing ERP System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Font - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif }
        
        body {
            background: linear-gradient(135deg, #1a2332 0%, #2a3a4a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 560px;
        }
        
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 24px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1a2332 0%, #2d3d50 100%);
            padding: 36px 48px;
            text-align: center;
        }
        
        .login-header .icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        .login-header .icon i {
            font-size: 28px;
            color: #fff;
        }
        
        .login-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 4px;
        }
        
        .login-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.65);
            margin: 0;
        }
        
        .login-body {
            padding: 36px 48px 44px;
        }
        
        .login-body .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #9ca3af;
            pointer-events: none;
        }
        
        .input-icon .form-control {
            padding-left: 42px;
            height: 46px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .input-icon .form-control:focus {
            border-color: #1a2332;
            box-shadow: 0 0 0 3px rgba(26,35,50,0.1);
        }
        
        .btn-login {
            background: #1a2332;
            color: #fff;
            height: 46px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            width: 100%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }
        
        .btn-login:hover {
            background: #0d1624;
            color: #fff;
        }
        
        .alert {
            border-radius: 8px;
            font-size: 13px;
            padding: 10px 14px;
        }
        
        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #9ca3af;
        }
        
        @media (max-width: 480px) {
            .login-header, .login-body { padding-left: 28px; padding-right: 28px }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="icon">
                    <i class="fas fa-industry"></i>
                </div>
                <h3>ERP System</h3>
                <p>Manufacturing Enterprise Resource Planning</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-circle-exclamation me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter your username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn-login">
                            <i class="fas fa-right-to-bracket"></i> Sign In
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="footer-note">
            <i class="fas fa-shield-halved me-1"></i> Secure Login
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>