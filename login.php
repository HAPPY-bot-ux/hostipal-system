<?php
// login.php - Enhanced login page with role-based redirection & modern medical UI
session_start(); // MUST be at the very top

require_once 'config/database.php';
require_once 'includes/SessionManager.php';
require_once 'includes/Auth.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
$success = '';
$selectedRole = '';

// Check if user is already logged in, redirect to appropriate dashboard
if ($auth->isLoggedIn()) {
    $userRole = $auth->getCurrentRole();
    switch ($userRole) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'doctor':
            header("Location: doctor/dashboard.php");
            break;
        case 'patient':
            header("Location: patient/dashboard.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars(strip_tags(trim($_POST['username'])));
    $password = $_POST['password'];
    $selectedRole = $_POST['role'] ?? '';
    
    // Validate role selection
    $validRoles = ['admin', 'doctor', 'patient'];
    if (!in_array($selectedRole, $validRoles)) {
        $error = 'Please select a valid user type.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            // Verify the user's role matches the selected role
            $userRole = $auth->getCurrentRole();
            
            if ($userRole === $selectedRole) {
                // Get user data for welcome message
                $userData = $auth->getCurrentUser();
                $fullName = is_array($userData) && isset($userData['full_name']) ? $userData['full_name'] : $username;
                
                // Set a success flash message
                SessionManager::setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($fullName) . '!');
                
                // Redirect to role-specific dashboard
                switch ($userRole) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: doctor/dashboard.php");
                        break;
                    case 'patient':
                        header("Location: patient/dashboard.php");
                        break;
                    default:
                        header("Location: dashboard.php");
                }
                exit();
            } else {
                // Role mismatch - log out and show error
                $auth->logout();
                $error = "Invalid user type selection. You are registered as a " . ucfirst($userRole) . ". Please select the correct user type.";
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>MediFlow | Hospital Management System</title>
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

        /* Premium dark overlay for readability and depth */
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

        /* Main card container — realistic glassmorphism with subtle shadow */
        .login-container {
            position: relative;
            z-index: 2;
            max-width: 520px;
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255,255,255,0.2) inset;
            transition: transform 0.25s ease;
            overflow: hidden;
        }

        .login-container:hover {
            transform: translateY(-3px);
        }

        /* Header with medical branding */
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
            backdrop-filter: blur(4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .brand-icon i {
            font-size: 2.8rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
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

        /* Main form area */
        .form-wrapper {
            padding: 2rem 2rem 2rem;
        }

        /* Role selector improved */
        .section-label {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1f3b4c;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-label i {
            color: #2c7da0;
            font-size: 1rem;
        }

        .role-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.8rem;
            flex-wrap: wrap;
        }
        
        .role-option {
            flex: 1;
            cursor: pointer;
        }
        
        .role-option input {
            display: none;
        }
        
        .role-card {
            background: #ffffff;
            border: 1.5px solid #e2edf2;
            border-radius: 24px;
            padding: 1rem 0.5rem;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }
        
        .role-option input:checked + .role-card {
            border-color: #1f6e8c;
            background: linear-gradient(145deg, #F6FBFE, #EFF7FC);
            box-shadow: 0 8px 18px rgba(31, 110, 140, 0.12);
            transform: scale(1.01);
        }
        
        .role-card:hover {
            border-color: #9cc9dc;
            background: #fafeff;
            transform: translateY(-2px);
        }
        
        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .role-title {
            font-weight: 700;
            font-size: 1rem;
            color: #1f3b4c;
        }
        
        .role-desc {
            font-size: 0.7rem;
            color: #5f7f8c;
            margin-top: 4px;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.4rem;
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
            width: 18px;
            font-size: 0.9rem;
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
            color: #0a2a38;
        }

        .form-control:focus {
            outline: none;
            border-color: #2c7da0;
            background: white;
            box-shadow: 0 0 0 4px rgba(44, 125, 160, 0.1);
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper .form-control {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #7f9aa8;
            font-size: 1.1rem;
            transition: color 0.2s;
            padding: 0;
        }

        .password-toggle:hover {
            color: #1f6e8c;
        }

        /* Alert styles modern */
        .alert {
            padding: 0.9rem 1rem;
            border-radius: 28px;
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

        .alert-info {
            background: #dbeafe;
            border-left: 5px solid #2563eb;
            color: #2563eb;
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

        /* Button modern */
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
            margin-top: 0.5rem;
            box-shadow: 0 8px 18px rgba(15, 70, 90, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(105deg, #2585a8 0%, #136481 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(15, 70, 90, 0.25);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .footer-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.8rem;
            font-size: 0.85rem;
        }

        .footer-links a {
            text-decoration: none;
            color: #2c7da0;
            font-weight: 500;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #154e63;
            text-decoration: underline;
        }

        .login-tips {
            background: #f4fafd;
            border-radius: 24px;
            margin-top: 1.8rem;
            padding: 0.8rem 1rem;
            border: 1px solid #cbe5ed;
            transition: all 0.2s;
        }
        
        .login-tips summary {
            font-weight: 600;
            font-size: 0.8rem;
            color: #2c7da0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-tips summary:hover {
            color: #1f6e8c;
        }
        
        .login-tips p {
            margin: 0.5rem 0 0 0;
            font-size: 0.75rem;
            color: #4f7e8c;
        }
        
        .demo-badge {
            background: white;
            border-radius: 40px;
            padding: 4px 10px;
            display: inline-block;
            margin-top: 8px;
            margin-right: 8px;
            font-size: 0.7rem;
            border: 1px solid #cce3ea;
            color: #1f5e7a;
        }

        @media (max-width: 550px) {
            .form-wrapper {
                padding: 1.5rem;
            }
            .role-selector {
                gap: 0.6rem;
            }
            .role-card {
                padding: 0.7rem 0.2rem;
            }
            .role-icon {
                font-size: 1.6rem;
            }
            .role-title {
                font-size: 0.85rem;
            }
            .brand-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand-header">
            <div class="brand-icon">
                <i class="fas fa-hospital-user"></i>
            </div>
            <h1>MediFlow HMS</h1>
            <p>Secure access to your medical dashboard</p>
        </div>
        
        <div class="form-wrapper">
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
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <!-- Role Selection -->
                <div class="section-label">
                    <i class="fas fa-user-tag"></i> <span>I am a</span>
                </div>
                <div class="role-selector">
                    <label class="role-option">
                        <input type="radio" name="role" value="patient" <?php echo ($selectedRole == 'patient' || $selectedRole == '') ? 'checked' : ''; ?> required>
                        <div class="role-card">
                            <div class="role-icon"><i class="fas fa-user-injured"></i></div>
                            <div class="role-title">Patient</div>
                            <div class="role-desc">Appointments & records</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="doctor" <?php echo $selectedRole == 'doctor' ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon"><i class="fas fa-user-md"></i></div>
                            <div class="role-title">Doctor</div>
                            <div class="role-desc">Patient care & schedule</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="admin" <?php echo $selectedRole == 'admin' ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="role-title">Admin</div>
                            <div class="role-desc">System & analytics</div>
                        </div>
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        <span>Username or Email</span>
                    </label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username or email" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        <span>Password</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">
                    <i class="fas fa-arrow-right-to-bracket"></i> Sign In
                </button>
            </form>
            
            <div class="footer-links">
                <a href="register.php"><i class="fas fa-user-plus"></i> Create account</a>
                <a href="forgot_password.php"><i class="fas fa-question-circle"></i> Forgot password?</a>
            </div>
            
            <details class="login-tips">
                <summary><i class="fas fa-flask"></i> Demo Credentials (Development Only)</summary>
                <p><strong>Admin:</strong> admin / password</p>
                <p><strong>Doctor:</strong> KB / Kbndlovu1234@</p>                            
                <p><strong>Patient:</strong> john_doe / password</p>
                <div class="demo-badge"><i class="fas fa-info-circle"></i> Select matching role first</div>
            </details>
        </div>
    </div>
    
    <script>
        // Role-based placeholder text for username field
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const usernameInput = document.querySelector('input[name="username"]');
                const role = this.value;
                
                const placeholders = {
                    'patient': 'Enter your username or email (e.g., john_doe)',
                    'doctor': 'Enter your username or email (e.g., KB or dr.smith)',
                    'admin': 'Enter your username or email (e.g., admin)'
                };
                
                usernameInput.placeholder = placeholders[role] || 'Enter your username or email';
                usernameInput.focus();
            });
        });

        // Add loading state on form submit
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalHtml = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Logging in...';
                submitBtn.disabled = true;
                
                // Re-enable button if form submission fails (optional)
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }
                }, 10000);
            });
        }

        // Password visibility toggle function
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Double-click on logo to auto-fill demo credentials (helpful for testing)
        const logoIcon = document.querySelector('.brand-icon');
        if (logoIcon) {
            logoIcon.addEventListener('dblclick', function() {
                const selectedRole = document.querySelector('input[name="role"]:checked').value;
                const usernameInput = document.querySelector('input[name="username"]');
                const passwordInput = document.querySelector('input[name="password"]');
                
                const demos = {
                    'admin': { username: 'admin', password: 'password' },
                    'doctor': { username: 'KB', password: 'Kbndlovu1234@' },
                    'patient': { username: 'john_doe', password: 'password' }
                };
                
                if (demos[selectedRole]) {
                    usernameInput.value = demos[selectedRole].username;
                    passwordInput.value = demos[selectedRole].password;
                    
                    // Show temporary success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info';
                    alertDiv.innerHTML = '<span class="alert-icon"><i class="fas fa-key"></i></span><span>Demo credentials filled for ' + selectedRole + '!</span>';
                    const formWrapper = document.querySelector('.form-wrapper');
                    formWrapper.insertBefore(alertDiv, formWrapper.firstChild);
                    
                    setTimeout(() => alertDiv.remove(), 3000);
                }
            });
        }
    </script>
</body>
</html>