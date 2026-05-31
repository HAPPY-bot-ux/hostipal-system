
<?php
// login.php - Re-imagined Next-Gen Interface for Hospital Appointment & Patient Management
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
$selectedRole = isset($_POST['role']) ? $_POST['role'] : 'patient';

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
    $selectedRole = $_POST['role'] ?? 'patient';
    
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
                $error = "Access denied. Your profile is assigned to the " . ucfirst($userRole) . " portal.";
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
    <title>MediFlow Platform Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Modern System Design Variables — Handles dynamic interface colors smoothly */
        :root {
            --bg-main: #090B11;
            --surface-card: rgba(18, 22, 33, 0.65);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            
            /* Dynamic Theme Profiles (Mutates smoothly via JavaScript) */
            --primary: #6366F1;     /* Interactive Violet */
            --primary-glow: rgba(99, 102, 241, 0.15);
            --accent: #10B981;      /* Emerald Detail */
            --gradient-angle: 135deg;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Fluid Background Elements */
        .ambient-glow-1 {
            position: absolute;
            width: 500px;
            height: 500px;
            top: -150px;
            left: -100px;
            background: radial-gradient(circle, var(--primary-glow) 0%, rgba(0,0,0,0) 70%);
            z-index: 1;
            transition: background 0.5s ease;
        }

        .ambient-glow-2 {
            position: absolute;
            width: 600px;
            height: 600px;
            bottom: -200px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, rgba(0,0,0,0) 70%);
            z-index: 1;
        }

        /* Split-Screen Framework Container */
        .gateway-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1180px;
            min-height: 720px;
            margin: 2rem;
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }

        /* Right Side: Interactive Branding Visual Space */
        .showcase-pane {
            position: relative;
            background: linear-gradient(var(--gradient-angle), #111422 0%, #0B0D17 100%);
            border-left: 1px solid var(--border-color);
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .showcase-pane::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 70% 30%, var(--primary-glow) 0%, transparent 60%);
            opacity: 0.8;
            transition: background 0.5s ease;
            z-index: 1;
        }

        .brand-identity {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, #3B82F6 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFF;
            font-size: 1.4rem;
            box-shadow: 0 8px 20px var(--primary-glow);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .brand-icon:hover {
            transform: scale(1.08) rotate(5deg);
        }

        .brand-name {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(120deg, #FFF 40%, var(--text-muted) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .showcase-content {
            position: relative;
            z-index: 2;
            margin-bottom: 2rem;
        }

        .showcase-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
        }

        .showcase-content h2 {
            font-size: 2.6rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 1rem;
        }

        .showcase-content p {
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.6;
            max-width: 420px;
        }

        /* Left Side: Form Input Ecosystem */
        .form-pane {
            padding: 4.5rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-header h3 {
            font-size: 1.85rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Custom Segmented Control Router for Roles */
        .segmented-control {
            display: flex;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            padding: 6px;
            border-radius: 16px;
            margin-bottom: 2.2rem;
            position: relative;
        }

        .segmented-control .role-label {
            flex: 1;
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 12px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .segmented-control input[type="radio"] {
            display: none;
        }

        /* Interactive highlighters dynamically shifted via JS classes */
        .segmented-control input[type="radio"]:checked + .role-label {
            color: #FFF;
        }

        .control-slider {
            position: absolute;
            top: 6px;
            left: 6px;
            bottom: 6px;
            width: calc(33.333% - 8px);
            background: linear-gradient(135deg, var(--primary) 0%, cubic-bezier(0.1, 0.9, 0.2, 1));
            border-radius: 11px;
            z-index: 1;
            transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), background 0.4s ease;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* Input Component Layout Architecture */
        .input-group {
            position: relative;
            margin-bottom: 1.8rem;
        }

        .input-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            color: #FFF;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.25);
        }

        /* Structural focus behaviors */
        .form-input:focus {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .form-input:focus ~ .input-icon {
            color: var(--primary);
        }

        .password-toggle-btn {
            position: absolute;
            right: 16px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.05rem;
            padding: 4px;
        }

        /* Interactive Feedback Panels */
        .alert-panel {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.88rem;
            line-height: 1.4;
            animation: paneEntrance 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #A7F3D0;
        }

        @keyframes paneEntrance {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Action Handlers */
        .action-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            font-size: 0.88rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-muted);
        }

        .remember-me input {
            accent-color: var(--primary);
        }

        .forgot-pass-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .forgot-pass-link:hover {
            opacity: 0.85;
        }

        .submit-trigger {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, #3B82F6 100%);
            border: none;
            border-radius: 14px;
            color: #FFF;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 24px var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submit-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(99, 102, 241, 0.3);
            filter: brightness(1.05);
        }

        .submit-trigger:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .pane-footer {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .pane-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }

        /* Collapsible Utilities Tray */
        .utilities-tray {
            margin-top: 2rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
        }

        .utilities-tray summary {
            padding: 14px 18px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }

        .utilities-tray summary::-webkit-details-marker {
            display: none;
        }

        .utilities-tray summary::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            transition: transform 0.3s;
        }

        .utilities-tray[open] summary::after {
            transform: rotate(180deg);
        }

        .tray-content {
            padding: 0 18px 16px;
            font-size: 0.8rem;
            border-top: 1px solid rgba(255, 255, 255, 0.03);
            background: rgba(0, 0, 0, 0.1);
        }

        .demo-credential-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: var(--text-muted);
            border-bottom: 1px dashed rgba(255, 255, 255, 0.04);
        }

        .demo-credential-row:last-child {
            border-bottom: none;
        }

        .demo-label {
            font-weight: 600;
            color: #FFF;
        }

        /* Responsive Breakpoint Adaptations */
        @media (max-width: 992px) {
            .gateway-wrapper {
                grid-template-columns: 1fr;
                max-width: 580px;
            }
            .showcase-pane {
                display: none;
            }
            .form-pane {
                padding: 3.5rem 2.5rem;
            }
        }

        @media (max-width: 480px) {
            .form-pane {
                padding: 2.5rem 1.5rem;
            }
            .segmented-control .role-label {
                font-size: 0.8rem;
                padding: 10px 0;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1" id="ambient1"></div>
    <div class="ambient-glow-2"></div>

    <div class="gateway-wrapper">
        
        <div class="form-pane">
            <div class="form-header">
                <h3>Account Gateway</h3>
                <p>Welcome back, please log into your terminal environment.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-panel alert-error">
                    <i class="fas fa-circle-exclamation" style="margin-top: 2px;"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-panel alert-success">
                    <i class="fas fa-circle-check" style="margin-top: 2px;"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                
                <div class="segmented-control">
                    <div class="control-slider" id="roleSlider"></div>
                    
                    <input type="radio" name="role" id="role-patient" value="patient" <?php echo ($selectedRole == 'patient') ? 'checked' : ''; ?> required>
                    <label class="role-label" for="role-patient" onclick="updateInterfaceTheme('patient', 0)">
                        <i class="fas fa-user-injured"></i> Patient
                    </label>

                    <input type="radio" name="role" id="role-doctor" value="doctor" <?php echo ($selectedRole == 'doctor') ? 'checked' : ''; ?>>
                    <label class="role-label" for="role-doctor" onclick="updateInterfaceTheme('doctor', 1)">
                        <i class="fas fa-user-md"></i> Doctor
                    </label>

                    <input type="radio" name="role" id="role-admin" value="admin" <?php echo ($selectedRole == 'admin') ? 'checked' : ''; ?>>
                    <label class="role-label" for="role-admin" onclick="updateInterfaceTheme('admin', 2)">
                        <i class="fas fa-user-gear"></i> Admin
                    </label>
                </div>

                <div class="input-group">
                    <div class="input-field-wrapper">
                        <input type="text" name="username" class="form-input" id="usernameField" placeholder="Username or email identity" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-fingerprint input-icon"></i>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-field-wrapper">
                        <input type="password" name="password" id="passwordField" class="form-input" placeholder="Security key parameter" required>
                        <i class="fas fa-shield-halved input-icon"></i>
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()">
                            <i class="far fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="action-container">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Keep me authenticated
                    </label>
                    <a href="forgot_password.php" class="forgot-pass-link">Forgot credentials?</a>
                </div>

                <button type="submit" class="submit-trigger" id="submitBtn">
                    <span>Initialize Session</span> <i class="fas fa-arrow-right-long"></i>
                </button>
            </form>

            <div class="pane-footer">
                Don't possess a terminal account?<a href="register.php">Create Profile</a>
            </div>

         <details class="utilities-tray">
    <summary>System Diagnostics & Mock Profiles</summary>
    <div class="tray-content">
        <div class="demo-credential-row">
            <span>👤 Patient Portal:</span>
            <span class="demo-label">emma.thompson / password</span>
        </div>
        <div class="demo-credential-row">
            <span>👩‍⚕️ Doctor Portal:</span>
            <span class="demo-label">dr.patel / password</span>
        </div>
        <div class="demo-credential-row">
            <span>🔐 Admin Portal:</span>
            <span class="demo-label">superadmin / password</span>
        </div>
        <div class="demo-credential-row" style="margin-top: 8px; color: rgba(255,255,255,0.3); font-size: 0.7rem;">
            <span>📋 All passwords are: <strong style="color:var(--primary)">password</strong></span>
        </div>
    </div>
</details>
        </div>

        <div class="showcase-pane">
            <div class="brand-identity">
                <div class="brand-icon" id="interactiveLogo">
                    <i class="fas fa-heart-pulse"></i>
                </div>
                <div class="brand-name">Hospital System</div>
            </div>

            <div class="showcase-content">
                <div class="showcase-tag" id="dynamicTag"><i class="fas fa-sparkles"></i> Core Portal</div>
                <h2 id="dynamicTitle">Unified Medical Infrastructure</h2>
                <p id="dynamicDesc">Access clinical systems, schedule operations, and process client telemetry with military-grade systemic isolation.</p>
            </div>

            <div class="pane-footer" style="text-align: left; margin-top: 0; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;">
                <span style="font-size: 0.78rem; opacity: 0.6;">Systemic Integrity Verified &bull; TLS 1.3 Encryption Active</span>
            </div>
        </div>

    </div>

  <script>
    // System Config Maps for Role Themes
    const themeConfigMatrix = {
        'patient': {
            primary: '#6366F1',
            primaryGlow: 'rgba(99, 102, 241, 0.15)',
            accent: '#10B981',
            placeholder: 'Enter your medical identity (e.g., emma.thompson)',
            tag: '<i class="fas fa-shield-heart"></i> Patient Link',
            title: 'Empowering Patient Digital Care',
            desc: 'Review medical records, track outpatient workflows, and configure clinical visit timelines intuitively.'
        },
        'doctor': {
            primary: '#0ea5e9',
            primaryGlow: 'rgba(14, 165, 233, 0.15)',
            accent: '#38bdf8',
            placeholder: 'Enter practitioner identifier (e.g., dr.patel)',
            tag: '<i class="fas fa-stethoscope"></i> Practitioner Node',
            title: 'Clinical Telemetry Terminal',
            desc: 'Review analytical reports, authorize patient transfers, and execute updates to pharmaceutical regimes.'
        },
        'admin': {
            primary: '#ec4899',
            primaryGlow: 'rgba(236, 72, 153, 0.15)',
            accent: '#f43f5e',
            placeholder: 'Enter system master username (e.g., superadmin)',
            tag: '<i class="fas fa-terminal"></i> Core Kernel Security',
            title: 'System Management Hub',
            desc: 'Audit transaction frameworks, isolate platform nodes, and configure infrastructure permissions globally.'
        }
    };

    // UI Theme Mutation State Engine
    function updateInterfaceTheme(roleKey, slideIndex) {
        const root = document.documentElement;
        const config = themeConfigMatrix[roleKey];
        
        // Re-render Dynamic Element Variables
        root.style.setProperty('--primary', config.primary);
        root.style.setProperty('--primary-glow', config.primaryGlow);
        root.style.setProperty('--accent', config.accent);
        
        // Animate Segmented Control Sliders
        const slider = document.getElementById('roleSlider');
        if (slider) {
            slider.style.transform = `translateX(${slideIndex * 100}%)`;
        }
        
        // Mutate Input Layout Placeholders Dynamically
        const usernameField = document.getElementById('usernameField');
        if (usernameField) {
            usernameField.placeholder = config.placeholder;
        }
        
        // Execute Smooth Morphing Text Micro-animations on Showcase Pane
        const tag = document.getElementById('dynamicTag');
        const title = document.getElementById('dynamicTitle');
        const desc = document.getElementById('dynamicDesc');

        if (tag && title && desc) {
            [tag, title, desc].forEach(el => el.style.opacity = '0');
            
            setTimeout(() => {
                tag.innerHTML = config.tag;
                title.innerText = config.title;
                desc.innerText = config.desc;
                [tag, title, desc].forEach(el => el.style.opacity = '1');
            }, 200);
        }
    }

    // Mask/Unmask Password Elements
    function togglePasswordVisibility() {
        const field = document.getElementById('passwordField');
        const icon = document.getElementById('passwordToggleIcon');
        
        if (field && icon) {
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'far fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'far fa-eye';
            }
        }
    }

    // Form Submit Indicator Micro-interaction
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner-third fa-spin"></i> Establishing Link...';
                btn.disabled = true;
            }
        });
    }

    // Developer Mode Shortcut Matrix: Double Click Logo to Auto-Fill 
    const interactiveLogo = document.getElementById('interactiveLogo');
    if (interactiveLogo) {
        interactiveLogo.addEventListener('dblclick', function() {
            const activeRoleRadio = document.querySelector('input[name="role"]:checked');
            if (!activeRoleRadio) return;
            
            const activeRole = activeRoleRadio.value;
            const usernameInput = document.getElementById('usernameField');
            const passwordInput = document.getElementById('passwordField');
            
            // Updated datasets with new users
            const datasets = {
                'admin': { u: 'superadmin', p: 'SecurePass123!' },
                'doctor': { u: 'dr.patel', p: 'SecurePass123!' },
                'patient': { u: 'emma.thompson', p: 'SecurePass123!' }
            };
            
            if (datasets[activeRole] && usernameInput && passwordInput) {
                usernameInput.value = datasets[activeRole].u;
                passwordInput.value = datasets[activeRole].p;
                
                // Optional: Add a small visual feedback
                const originalBg = usernameInput.style.backgroundColor;
                usernameInput.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                passwordInput.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                setTimeout(() => {
                    usernameInput.style.backgroundColor = originalBg;
                    passwordInput.style.backgroundColor = originalBg;
                }, 500);
            }
        });
    }

    // Initialize Default Visual Configuration on Page Mount
    window.addEventListener('DOMContentLoaded', () => {
        const preSelected = document.querySelector('input[name="role"]:checked');
        if (preSelected) {
            const realIdx = preSelected.value === 'patient' ? 0 : preSelected.value === 'doctor' ? 1 : 2;
            updateInterfaceTheme(preSelected.value, realIdx);
        }
    });
</script>
</body>
</html>

```