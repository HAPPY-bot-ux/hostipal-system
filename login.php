<?php
// login.php - Updated login page with role-based redirection
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .role-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
        }
        
        .role-option input:checked + .role-card {
            border-color: #2563eb;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .role-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        
        .role-desc {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .login-tips {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }
        
        .login-tips summary {
            cursor: pointer;
            font-weight: 500;
            color: #4b5563;
        }
        
        .login-tips summary:hover {
            color: #2563eb;
        }
        
        .login-tips p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
        }
        
        .demo-credentials {
            font-size: 0.75rem;
            font-family: monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-info {
            background: #dbeafe;
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }

        .alert-icon {
            font-size: 1.25rem;
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

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .glass-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            opacity: 0.6;
            padding: 0;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        @media (max-width: 640px) {
            .glass-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .role-selector {
                gap: 0.5rem;
            }
            
            .role-card {
                padding: 0.75rem;
            }
            
            .role-icon {
                font-size: 1.5rem;
            }
            
            .role-title {
                font-size: 0.875rem;
            }
            
            .role-desc {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="fade-in">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🏥</div>
                <h1 style="font-size: 2rem; background: linear-gradient(135deg, #2563eb, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0;">Hospital System</h1>
                <p style="color: #6b7280; margin-top: 0.5rem;">Login to access your dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✓</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <!-- Role Selection -->
                <label class="form-label" style="margin-bottom: 0.5rem; display: block;">Select User Type</label>
                <div class="role-selector">
                    <label class="role-option">
                        <input type="radio" name="role" value="patient" <?php echo ($selectedRole == 'patient' || $selectedRole == '') ? 'checked' : ''; ?> required>
                        <div class="role-card">
                            <div class="role-icon">👤</div>
                            <div class="role-title">Patient</div>
                            <div class="role-desc">Book appointments & view records</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="doctor" <?php echo $selectedRole == 'doctor' ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon">👨‍⚕️</div>
                            <div class="role-title">Doctor</div>
                            <div class="role-desc">Manage patients & schedules</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="admin" <?php echo $selectedRole == 'admin' ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon">🔧</div>
                            <div class="role-title">Admin</div>
                            <div class="role-desc">System management & reports</div>
                        </div>
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username or email" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="register.php" style="color: #2563eb; text-decoration: none; font-weight: 500;">Don't have an account? Register</a>
                <span style="margin: 0 0.5rem; color: #d1d5db;">|</span>
                <a href="forgot_password.php" style="color: #6b7280; text-decoration: none; font-size: 0.875rem;">Forgot Password?</a>
            </div>
            
            <details class="login-tips">
                <summary>🔐 Demo Credentials (Development Only)</summary>
                <p><strong>Admin:</strong> admin / password123</p>
                <p><strong>Doctor:</strong> dr.smith / password123</p>
                <p><strong>Doctor:</strong> dr.johnson / password123</p>
                <p><strong>Doctor:</strong> dr.williams / password123</p>
                <p><strong>Patient:</strong> john_doe / password123</p>
                <p><strong>Patient:</strong> jane_smith / password123</p>
                <p><strong>Patient:</strong> bob_wilson / password123</p>
                <p><strong>Patient:</strong> alice_brown / password123</p>
                <p><strong>Patient:</strong> charlie_davis / password123</p>
                <div class="demo-credentials">
                    ⚠️ Remove this section in production
                </div>
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
                    'doctor': 'Enter your username or email (e.g., dr.smith)',
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
                const originalText = submitBtn.textContent;
                
                submitBtn.textContent = '⏳ Logging in...';
                submitBtn.disabled = true;
                
                // Re-enable button if form submission fails (optional)
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }, 10000);
            });
        }

        // Password visibility toggle function
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Auto-fill demo credentials based on role selection (for development only)
        function autoFillDemoCredentials() {
            const selectedRole = document.querySelector('input[name="role"]:checked').value;
            const usernameInput = document.querySelector('input[name="username"]');
            
            const demos = {
                'admin': 'admin',
                'doctor': 'dr.smith',
                'patient': 'john_doe'
            };
            
            if (demos[selectedRole] && !usernameInput.value) {
                usernameInput.value = demos[selectedRole];
                document.querySelector('input[name="password"]').value = 'password123';
            }
        }
        
        // Optional: Add double-click on logo to auto-fill demo credentials
        const logo = document.querySelector('.glass-container h1');
        if (logo) {
            logo.addEventListener('dblclick', function() {
                const selectedRole = document.querySelector('input[name="role"]:checked').value;
                const usernameInput = document.querySelector('input[name="username"]');
                const passwordInput = document.querySelector('input[name="password"]');
                
                const demos = {
                    'admin': { username: 'admin', password: 'password123' },
                    'doctor': { username: 'dr.smith', password: 'password123' },
                    'patient': { username: 'john_doe', password: 'password123' }
                };
                
                if (demos[selectedRole]) {
                    usernameInput.value = demos[selectedRole].username;
                    passwordInput.value = demos[selectedRole].password;
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info';
                    alertDiv.innerHTML = '<span class="alert-icon">🔐</span><span>Demo credentials filled!</span>';
                    document.querySelector('.glass-container .fade-in').insertBefore(alertDiv, document.querySelector('form'));
                    
                    setTimeout(() => alertDiv.remove(), 3000);
                }
            });
        }
    </script>
</body>
</html>