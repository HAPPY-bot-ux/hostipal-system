<?php
// reset_password.php - Complete password reset page
session_start();

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$valid_token = false;
$user_id = null;

// Verify token
if (!empty($token) && !empty($email)) {
    $email = urldecode($email);
    $query = "SELECT id, full_name, reset_token, reset_token_expiry FROM users WHERE email = :email AND reset_token = :token AND reset_token_expiry > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $valid_token = true;
        $user_id = $user['id'];
    } else {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
    }
}

// Process password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password (use password_hash in production)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':password', $hashed_password);
        $update_stmt->bindParam(':user_id', $user_id);
        
        if ($update_stmt->execute()) {
            $success = 'Your password has been successfully reset! You can now login with your new password.';
            // Clear token variables
            $valid_token = false;
        } else {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | MediFlow HMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=2080&auto=format&fit=crop') no-repeat center center/cover;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15,25,45,0.75), rgba(10,20,40,0.85));
            backdrop-filter: blur(3px);
            z-index: 0;
        }
        .reset-container {
            position: relative;
            z-index: 2;
            max-width: 500px;
            width: 100%;
            background: rgba(255,255,255,0.96);
            border-radius: 36px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
            overflow: hidden;
        }
        .brand-header {
            background: linear-gradient(115deg, #0B2B40, #1A4B6E);
            padding: 1.8rem;
            text-align: center;
            color: white;
        }
        .brand-icon {
            font-size: 3rem;
            background: rgba(255,255,255,0.15);
            width: 75px;
            height: 75px;
            border-radius: 60px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-header h1 { font-size: 1.85rem; margin-bottom: 0.3rem; }
        .form-wrapper { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            color: #1e2f3a;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            background: #f9fcfd;
            border: 1.5px solid #e1eef3;
            border-radius: 20px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #2c7da0;
            box-shadow: 0 0 0 4px rgba(44,125,160,0.1);
        }
        .btn-primary {
            width: 100%;
            background: linear-gradient(105deg, #1f6e8c, #0e4b64);
            border: none;
            padding: 0.9rem;
            border-radius: 40px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        .btn-secondary {
            width: 100%;
            background: transparent;
            border: 1.5px solid #cbe5ed;
            padding: 0.85rem;
            border-radius: 40px;
            font-weight: 600;
            color: #2c7da0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .alert {
            padding: 0.9rem 1rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-danger { background: #ffe9e9; border-left: 5px solid #e03a3a; color: #b91c1c; }
        .alert-success { background: #e6f9ef; border-left: 5px solid #2b9348; color: #166534; }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #7f9aa8;
        }
        @media (max-width: 550px) { .form-wrapper { padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="brand-header">
            <div class="brand-icon"><i class="fas fa-lock"></i></div>
            <h1>Create New Password</h1>
            <p>Enter your new password below</p>
        </div>
        <div class="form-wrapper">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                <a href="login.php" class="btn-secondary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            <?php elseif ($valid_token): ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')"><i class="far fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')"><i class="far fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Reset Password</button>
                    <a href="login.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </form>
            <?php elseif (empty($token)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> No reset token provided. Please request a password reset from the login page.</div>
                <a href="forgot_password.php" class="btn-secondary"><i class="fas fa-key"></i> Request New Reset Link</a>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.password-toggle i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>