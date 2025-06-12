<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // After successful password verification and fetching user data from the database
// For example, if $user is an associative array containing user details:
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username']; // You might use username or full_name for display
$_SESSION['name'] = $user['full_name']; // Set full_name here
$_SESSION['role'] = $user['role'];
// ... other session variables you want to set
            
            header('Location: dashboard.php');
            exit;
        }
    }
    
    $error = "Invalid username or password";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | RVStore </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 400px;
            padding: 40px;
            text-align: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 30px;
        }
        
        h1 {
            font-size: 24px;
            margin-bottom: 30px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            border-color: #4e73df;
            outline: none;
        }
        
        .btn {
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #3a5bc7;
        }
        
        .error {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .divider {
            margin: 20px 0;
            position: relative;
            text-align: center;
            color: #999;
        }
        
        .divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #ddd;
            z-index: -1;
        }
        
        .divider span {
            background-color: white;
            padding: 0 10px;
        }
        
        .google-btn {
            background-color: white;
            color: #555;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background-color 0.3s;
        }
        
        .google-btn:hover {
            background-color: #f8f9fa;
        }
        
        .switch-form {
            margin-top: 20px;
            color: #555;
        }
        
        .switch-form a {
            color: #4e73df;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">RVStore</div>
        <h1>Sign In</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn">Sign In</button>
        </form>
        
        <div class="divider"><span>OR</span></div>
        
        <button class="google-btn">
            <i class="fab fa-google"></i> Sign In with Google
        </button>
        
        <div class="switch-form">
            Don't have an account? <a href="register.php">Sign Up</a>
        </div>
    </div>
</body>
</html>