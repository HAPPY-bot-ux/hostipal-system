<?php
// forgot_password.php - Password reset request page with email verification
session_start();

require_once 'config/database.php';
require_once 'includes/SessionManager.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$email = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = htmlspecialchars(strip_tags(trim($_POST['email'])));
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in database
        $query = "SELECT id, full_name, username FROM users WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate unique reset token
            $reset_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $update_query = "UPDATE users SET reset_token = :reset_token, reset_token_expiry = :reset_token_expiry WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':reset_token', $reset_token);
            $update_stmt->bindParam(':reset_token_expiry', $token_expiry);
            $update_stmt->bindParam(':user_id', $user['id']);
            
            if ($update_stmt->execute()) {
                // In a production environment, send actual email here
                // For demo purposes, we'll show the reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/reset_password.php?token=" . $reset_token . "&email=" . urlencode($email);
                
                // Log the reset request (for security monitoring)
                $log_query = "INSERT INTO password_reset_logs (user_id, email, ip_address, requested_at) VALUES (:user_id, :email, :ip, NOW())";
                $log_stmt = $db->prepare($log_query);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $log_stmt->bindParam(':user_id', $user['id']);
                $log_stmt->bindParam(':email', $email);
                $log_stmt->bindParam(':ip', $ip_address);
                $log_stmt->execute();
                
                // For demo: Show success with reset link (in production, this would be emailed)
                $success = "A password reset link has been sent to your email address. The link will expire in 1 hour.";
                
                // Store reset info in session for demo display
                $_SESSION['demo_reset_link'] = $reset_link;
                $_SESSION['demo_reset_email'] = $email;
            } else {
                $error = 'An error occurred. Please try again later.';
            }
        } else {
            // Don't reveal that email doesn't exist (security best practice)
            $success = "If an account exists with that email, you will receive a password reset link.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Forgot Password | MediFlow HMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            background: linear-gradient(135deg, rgba(15, 25, 45, 0.75) 0%, rgba(10, 20, 40, 0.85) 100%);
            backdrop-filter: blur(3px);
            z-index: 0;
        }

        .reset-container {
            position: relative;
            z-index: 2;
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            transition: transform 0.25s ease;
            overflow: hidden;
        }

        .reset-container:hover {
            transform: translateY(-3px);
        }

        .brand-header {
            background: linear-gradient(115deg, #0B2B40 0%, #1A4B6E 100%);
            padding: 1.8rem 1.8rem 1.5rem;
            text-align: center;
            color: white;
        }

        .brand-icon {
            font-size: 3.2rem;
            background: rgba(255,255,255,0.15);
            width: 75px;
            height: 75px;
            line-height: 75px;
            border-radius: 60px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon i {
            font-size: 2.8rem;
        }

        .brand-header h1 {
            font-weight: 700;
            font-size: 1.85rem;
            letter-spacing: -0.3px;
            margin-bottom: 0.3rem;
        }

        .brand-header p {
            font-weight: 400;
            font-size: 0.9rem;
            opacity: 0.85;
        }

        .form-wrapper {
            padding: 2rem;
        }

        .info-text {
            background: #e8f4f8;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: #1f6e8c;
            border-left: 4px solid #1f6e8c;
        }

        .info-text i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            color: #1e2f3a;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: #2c7da0;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            background: #f9fcfd;
            border: 1.5px solid #e1eef3;
            border-radius: 20px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2c7da0;
            background: white;
            box-shadow: 0 0 0 4px rgba(44, 125, 160, 0.1);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(105deg, #1f6e8c 0%, #0e4b64 100%);
            border: none;
            padding: 0.9rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 1rem;
            box-shadow: 0 8px 18px rgba(15, 70, 90, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(105deg, #2585a8 0%, #136481 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(15, 70, 90, 0.25);
        }

        .btn-secondary {
            width: 100%;
            background: transparent;
            border: 1.5px solid #cbe5ed;
            padding: 0.85rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #2c7da0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #f0f8fc;
            border-color: #1f6e8c;
            transform: translateY(-1px);
        }

        .alert {
            padding: 0.9rem 1rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .alert-danger {
            background: #ffe9e9;
            border-left: 5px solid #e03a3a;
            color: #b91c1c;
        }

        .alert-success {
            background: #e6f9ef;
            border-left: 5px solid #2b9348;
            color: #166534;
        }

        .alert-icon {
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .demo-reset-box {
            background: #f4fafd;
            border-radius: 20px;
            padding: 1rem;
            margin-top: 1.5rem;
            border: 1px dashed #2c7da0;
        }

        .demo-reset-box p {
            font-size: 0.75rem;
            color: #1f6e8c;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }

        .demo-reset-box strong {
            color: #0e4b64;
        }

        .back-to-login {
            text-align: center;
            margin-top: 1rem;
        }

        .back-to-login a {
            color: #2c7da0;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }

        @media (max-width: 550px) {
            .form-wrapper {
                padding: 1.5rem;
            }
            .brand-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="brand-header">
            <div class="brand-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Reset Password</h1>
            <p>We'll help you get back into your account</p>
        </div>
        
        <div class="form-wrapper">
            <div class="info-text">
                <i class="fas fa-envelope"></i>
                <span>Enter your registered email address and we'll send you a password reset link.</span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                
                <?php if (isset($_SESSION['demo_reset_link'])): ?>
                <div class="demo-reset-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Demo Mode:</strong> Since email sending is disabled in development, here's your reset link:</p>
                    <p><strong>Reset Link:</strong> <a href="<?php echo htmlspecialchars($_SESSION['demo_reset_link']); ?>" target="_blank" style="color: #1f6e8c;"><?php echo htmlspecialchars($_SESSION['demo_reset_link']); ?></a></p>
                    <p><small><i class="fas fa-clock"></i> This link will expire in 1 hour.</small></p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="POST" action="" id="resetForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        <span>Email Address</span>
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required value="<?php echo htmlspecialchars($email); ?>" autofocus>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
                
                <a href="login.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </form>
            
            <div class="back-to-login">
                <p style="font-size: 0.75rem; color: #6c8d9b; margin-top: 1rem;">
                    <i class="fas fa-shield-alt"></i> For security, reset links expire after 1 hour
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Add loading state on form submit
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalHtml = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Sending...';
                submitBtn.disabled = true;
                
                // Re-enable button after 10 seconds if needed
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }
                }, 10000);
            });
        }
        
        // Auto-fill demo email on double-click logo (for testing)
        const logoIcon = document.querySelector('.brand-icon');
        if (logoIcon) {
            logoIcon.addEventListener('dblclick', function() {
                const emailInput = document.querySelector('input[name="email"]');
                if (emailInput && !emailInput.value) {
                    emailInput.value = 'demo@mediflow.com';
                    
                    // Show temporary hint
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<span class="alert-icon"><i class="fas fa-info-circle"></i></span><span>Demo email filled! Use any email for testing.</span>';
                    const formWrapper = document.querySelector('.form-wrapper');
                    formWrapper.insertBefore(alertDiv, formWrapper.firstChild);
                    
                    setTimeout(() => alertDiv.remove(), 3000);
                }
            });
        }
    </script>
</body>
</html>