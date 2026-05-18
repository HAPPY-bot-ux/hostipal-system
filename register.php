<?php
// register.php - Allow registration for all user types
session_start();

require_once 'config/database.php';
require_once 'includes/SessionManager.php';
require_once 'includes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
$success = '';
$selectedRole = 'patient'; // Default role

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selectedRole = $_POST['role'] ?? 'patient';
    
    $data = [
        'username' => htmlspecialchars(strip_tags(trim($_POST['username']))),
        'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
        'password' => $_POST['password'],
        'full_name' => htmlspecialchars(strip_tags(trim($_POST['full_name']))),
        'phone' => htmlspecialchars(strip_tags(trim($_POST['phone'] ?? ''))),
        'address' => htmlspecialchars(strip_tags(trim($_POST['address'] ?? ''))),
        'role' => $selectedRole
    ];
    
    // Doctor-specific fields
    $doctorData = [];
    if ($selectedRole === 'doctor') {
        $doctorData = [
            'specialization' => htmlspecialchars(strip_tags(trim($_POST['specialization'] ?? ''))),
            'qualification' => htmlspecialchars(strip_tags(trim($_POST['qualification'] ?? ''))),
            'experience_years' => intval($_POST['experience_years'] ?? 0),
            'consultation_fee' => floatval($_POST['consultation_fee'] ?? 0),
            'available_days' => htmlspecialchars(strip_tags(trim($_POST['available_days'] ?? ''))),
            'available_time_start' => $_POST['available_time_start'] ?? null,
            'available_time_end' => $_POST['available_time_end'] ?? null
        ];
    }
    
    // Validation
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($data['password']) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $data['password']) || !preg_match('/[a-z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match!";
    } elseif ($selectedRole === 'doctor' && empty($doctorData['specialization'])) {
        $error = "Please enter doctor's specialization.";
    } else {
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':username', $data['username']);
        $checkStmt->bindParam(':email', $data['email']);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $error = "Username or email already exists. Please choose a different one.";
        } else {
            // Register the user
            $result = $auth->register($data);
            
            if ($result['success']) {
                // If user is a doctor, create doctor profile
                if ($selectedRole === 'doctor' && isset($result['user_id'])) {
                    $doctorQuery = "INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee, available_days, available_time_start, available_time_end) 
                                   VALUES (:user_id, :specialization, :qualification, :experience_years, :consultation_fee, :available_days, :available_time_start, :available_time_end)";
                    $doctorStmt = $db->prepare($doctorQuery);
                    $doctorStmt->bindParam(':user_id', $result['user_id']);
                    $doctorStmt->bindParam(':specialization', $doctorData['specialization']);
                    $doctorStmt->bindParam(':qualification', $doctorData['qualification']);
                    $doctorStmt->bindParam(':experience_years', $doctorData['experience_years']);
                    $doctorStmt->bindParam(':consultation_fee', $doctorData['consultation_fee']);
                    $doctorStmt->bindParam(':available_days', $doctorData['available_days']);
                    $doctorStmt->bindParam(':available_time_start', $doctorData['available_time_start']);
                    $doctorStmt->bindParam(':available_time_end', $doctorData['available_time_end']);
                    $doctorStmt->execute();
                }
                
                $success = $result['message'] . " You can now login.";
                
                // Log the registration
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (NULL, 'User Registration', :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $details = "New {$selectedRole} registered: {$data['username']} ({$data['email']})";
                $logStmt->bindParam(':details', $details);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                // Redirect after 3 seconds
                header("refresh:3;url=login.php");
            } else {
                $error = $result['message'];
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
    <title>Register - Hospital Management System</title>
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
            padding: 2rem 1rem;
        }

        .glass-container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }

        .role-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
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

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-label.required::after {
            content: " *";
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease-out;
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

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .strength-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .doctor-fields {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .doctor-fields.active {
            display: block;
        }

        small {
            font-size: 0.75rem;
            color: #6b7280;
        }

        @media (max-width: 640px) {
            .glass-container {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
        }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="fade-in">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🏥</div>
                <h1 style="font-size: 2rem; background: linear-gradient(135deg, #2563eb, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent;">Create Account</h1>
                <p style="color: #6b7280; margin-top: 0.5rem;">Register as a new user</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?> Redirecting to login...</div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <!-- Role Selection -->
                <label class="form-label" style="margin-bottom: 0.5rem; display: block;">Register As *</label>
                <div class="role-selector">
                    <label class="role-option">
                        <input type="radio" name="role" value="patient" <?php echo ($selectedRole == 'patient') ? 'checked' : ''; ?> required>
                        <div class="role-card">
                            <div class="role-icon">👤</div>
                            <div class="role-title">Patient</div>
                            <div class="role-desc">Book appointments & view records</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="doctor" <?php echo ($selectedRole == 'doctor') ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon">👨‍⚕️</div>
                            <div class="role-title">Doctor</div>
                            <div class="role-desc">Manage patients & schedules</div>
                        </div>
                    </label>
                    
                    <label class="role-option">
                        <input type="radio" name="role" value="admin" <?php echo ($selectedRole == 'admin') ? 'checked' : ''; ?>>
                        <div class="role-card">
                            <div class="role-icon">🔧</div>
                            <div class="role-title">Admin</div>
                            <div class="role-desc">System management & reports</div>
                        </div>
                    </label>
                </div>
                
                <!-- Common Fields for All Users -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <small>Username must be unique</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="+1 234 567 8900">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Enter your full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <!-- Doctor Specific Fields -->
                <div id="doctorFields" class="doctor-fields <?php echo ($selectedRole == 'doctor') ? 'active' : ''; ?>">
                    <div style="border-top: 2px solid #e5e7eb; margin: 1.5rem 0 1rem 0;"></div>
                    <h3 style="margin-bottom: 1rem; color: #374151;">Professional Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Specialization</label>
                            <select name="specialization" class="form-control">
                                <option value="">Select Specialization</option>
                                <option value="Cardiology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="Pediatrics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                <option value="Neurology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                                <option value="Orthopedics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                <option value="Dermatology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                <option value="Gynecology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Gynecology') ? 'selected' : ''; ?>>Gynecology</option>
                                <option value="Ophthalmology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Ophthalmology') ? 'selected' : ''; ?>>Ophthalmology</option>
                                <option value="Psychiatry" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Qualification</label>
                            <input type="text" name="qualification" class="form-control" value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : ''; ?>" placeholder="e.g., MD, PhD - Harvard Medical School">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control" value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : ''; ?>" min="0" max="50">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Consultation Fee ($)</label>
                            <input type="number" name="consultation_fee" class="form-control" value="<?php echo isset($_POST['consultation_fee']) ? htmlspecialchars($_POST['consultation_fee']) : ''; ?>" step="10" min="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Available Days</label>
                            <select name="available_days" class="form-control">
                                <option value="">Select Schedule</option>
                                <option value="Monday,Tuesday,Wednesday,Thursday,Friday" <?php echo (isset($_POST['available_days']) && $_POST['available_days'] == 'Monday,Tuesday,Wednesday,Thursday,Friday') ? 'selected' : ''; ?>>Monday - Friday</option>
                                <option value="Monday,Tuesday,Wednesday,Thursday,Friday,Saturday" <?php echo (isset($_POST['available_days']) && $_POST['available_days'] == 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday') ? 'selected' : ''; ?>>Monday - Saturday</option>
                                <option value="Tuesday,Wednesday,Thursday,Friday,Saturday" <?php echo (isset($_POST['available_days']) && $_POST['available_days'] == 'Tuesday,Wednesday,Thursday,Friday,Saturday') ? 'selected' : ''; ?>>Tuesday - Saturday</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Available Time From</label>
                            <input type="time" name="available_time_start" class="form-control" value="<?php echo isset($_POST['available_time_start']) ? htmlspecialchars($_POST['available_time_start']) : '09:00'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Available Time To</label>
                            <input type="time" name="available_time_end" class="form-control" value="<?php echo isset($_POST['available_time_end']) ? htmlspecialchars($_POST['available_time_end']) : '17:00'; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Password Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-bar-fill" id="strengthBar"></div>
                            </div>
                            <div id="strengthText"></div>
                        </div>
                        <small>Minimum 8 characters with uppercase, lowercase, and number</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="login.php" style="color: #2563eb; text-decoration: none;">Already have an account? Login here</a>
            </div>
        </div>
    </div>
    
    <script>
        // Role selection toggle for doctor fields
        const roleRadios = document.querySelectorAll('input[name="role"]');
        const doctorFields = document.getElementById('doctorFields');
        
        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'doctor') {
                    doctorFields.classList.add('active');
                    // Make doctor fields required
                    document.querySelectorAll('#doctorFields input, #doctorFields select').forEach(field => {
                        if (field.name !== 'available_days' && field.name !== 'available_time_start' && field.name !== 'available_time_end') {
                            field.required = true;
                        }
                    });
                } else {
                    doctorFields.classList.remove('active');
                    // Remove required from doctor fields
                    document.querySelectorAll('#doctorFields input, #doctorFields select').forEach(field => {
                        field.required = false;
                    });
                }
            });
        });
        
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            const percentages = {1: 20, 2: 40, 3: 60, 4: 80, 5: 100};
            const colors = {1: '#dc2626', 2: '#f59e0b', 3: '#fbbf24', 4: '#10b981', 5: '#059669'};
            const texts = {1: 'Very Weak', 2: 'Weak', 3: 'Fair', 4: 'Good', 5: 'Strong'};
            
            strengthBar.style.width = percentages[strength] + '%';
            strengthBar.style.backgroundColor = colors[strength];
            strengthText.textContent = texts[strength] || '';
            strengthText.style.color = colors[strength];
        });
        
        // Password match checker
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmPassword.value;
            
            if (confirm === '') {
                passwordMatch.innerHTML = '';
            } else if (password === confirm) {
                passwordMatch.innerHTML = '✓ Passwords match';
                passwordMatch.style.color = '#10b981';
            } else {
                passwordMatch.innerHTML = '✗ Passwords do not match';
                passwordMatch.style.color = '#dc2626';
            }
        }
        
        passwordInput.addEventListener('keyup', checkPasswordMatch);
        confirmPassword.addEventListener('keyup', checkPasswordMatch);
        
        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmPassword.value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>